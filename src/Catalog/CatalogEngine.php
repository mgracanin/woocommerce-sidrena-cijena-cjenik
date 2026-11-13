<?php

namespace SidrenaCijenaCjenik\Catalog;

if (!defined('ABSPATH')) {
    exit;
}

final class CatalogEngine
{
    const PAGE_SIZE = 100;

    private $lock;
    private $dirty;

    public function __construct()
    {
        $this->lock = new GenerationLock(CJENIK_GENERATION_LOCK, 30 * MINUTE_IN_SECONDS);
        $this->dirty = new DirtyState(CJENIK_DIRTY_OPTION);
    }

    public static function columns()
    {
        return array(
            'naziv',
            'sifra_proizvoda',
            'brend',
            'jedinica_mjere',
            'cijena_po_jedinici',
            'maloprodajna_cijena',
            'poseban_oblik_prodaje',
            'naziv_posebnog_oblika_prodaje',
            'sidrena_cijena',
            'barkod',
            'dostupnost',
        );
    }

    public static function textColumns()
    {
        return array(
            'naziv',
            'sifra_proizvoda',
            'brend',
            'jedinica_mjere',
            'poseban_oblik_prodaje',
            'naziv_posebnog_oblika_prodaje',
            'barkod',
            'dostupnost',
        );
    }

    public function markDirty()
    {
        return $this->dirty->mark();
    }

    public function rows()
    {
        $this->assertWooCommerceAvailable();
        $page = 1;

        do {
            $ids = wc_get_products(array(
                'status' => 'publish',
                'limit' => self::PAGE_SIZE,
                'page' => $page,
                'orderby' => 'ID',
                'order' => 'ASC',
                'return' => 'ids',
            ));

            if (is_wp_error($ids)) {
                throw new \RuntimeException($ids->get_error_message());
            }
            if (!is_array($ids)) {
                throw new \RuntimeException('WooCommerce je vratio neočekivan rezultat pri čitanju proizvoda.');
            }

            foreach ($ids as $productId) {
                $product = wc_get_product($productId);
                if (!$product || !cjenik_product_included($product)) {
                    continue;
                }

                // Grouped parents are containers; their children are queried as
                // individual products and must not be exported a second time.
                if ($product->is_type('grouped')) {
                    continue;
                }

                if ($product->is_type('variable')) {
                    foreach ($product->get_children() as $variationId) {
                        $variation = wc_get_product($variationId);
                        if ($variation && $variation->get_status() === 'publish') {
                            yield cjenik_build_row($variation, $product);
                        }
                    }
                    continue;
                }

                yield cjenik_build_row($product);
            }

            $page++;
        } while (count($ids) === self::PAGE_SIZE);
    }

    public function collect($limit = 0)
    {
        $rows = array();
        foreach ($this->rows() as $row) {
            $rows[] = $row;
            if ($limit > 0 && count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }

    public function readiness(array $meta, $previewLimit = 100)
    {
        $report = array(
            'total' => 0,
            'missing_price' => 0,
            'missing_anchor' => 0,
            'missing_sku' => 0,
            'missing_brand' => 0,
            'missing_barcode' => 0,
            'settings_ok' => !empty($meta['object_type']) && !empty($meta['location_label']) && !empty($meta['storage_number']) && !empty($meta['address']),
            'preview' => array(),
        );

        foreach ($this->rows() as $row) {
            $report['total']++;
            if ($row['maloprodajna_cijena'] === '') {
                $report['missing_price']++;
            }
            if (empty($row['_anchor_is_stored'])) {
                $report['missing_anchor']++;
            }
            if ($row['sifra_proizvoda'] === '') {
                $report['missing_sku']++;
            }
            if ($row['brend'] === '') {
                $report['missing_brand']++;
            }
            if ($row['barkod'] === '') {
                $report['missing_barcode']++;
            }
            if (count($report['preview']) < $previewLimit) {
                $report['preview'][] = $row;
            }
        }

        return $report;
    }

    public function generate()
    {
        if (!$this->lock->acquire()) {
            update_option(CJENIK_LAST_ERROR_OPTION, 'Generiranje cjenika već je u tijeku.', false);
            return false;
        }

        $dirtySnapshot = $this->dirty->snapshot();
        $temporaryFiles = array();

        try {
            $this->assertWooCommerceAvailable();
            $formats = $this->enabledFormats();
            $meta = cjenik_get_meta();
            $location = cjenik_get_dir();
            $writers = array();

            foreach ($formats as $format) {
                $temporary = $this->temporaryPath($location['path']);
                $temporaryFiles[] = $temporary;
                if ($format === 'csv') {
                    $delimiter = get_option(CJENIK_DELIMITER_OPTION, CJENIK_DEFAULT_DELIMITER);
                    if (!array_key_exists($delimiter, cjenik_delimiter_choices())) {
                        $delimiter = CJENIK_DEFAULT_DELIMITER;
                    }
                    $writers[$format] = new CsvStreamWriter($temporary, $delimiter, self::columns(), self::textColumns());
                } else {
                    $writers[$format] = new XmlStreamWriter($temporary, self::columns(), $meta);
                }
            }

            $rowCount = 0;
            $lastLockRefresh = time();
            foreach ($this->rows() as $row) {
                foreach ($writers as $writer) {
                    $writer->writeRow($row);
                }
                $rowCount++;
                if ($rowCount % self::PAGE_SIZE === 0 || time() - $lastLockRefresh >= 60) {
                    if (!$this->lock->refresh()) {
                        throw new \RuntimeException('Generiranje je izgubilo vlasništvo nad zaključavanjem.');
                    }
                    $lastLockRefresh = time();
                }
            }

            foreach ($writers as $writer) {
                $writer->close();
            }

            $operations = array();
            $files = array(
                'csv_url' => '', 'xml_url' => '', 'csv_file' => '', 'xml_file' => '',
                'csv_download_name' => '', 'xml_download_name' => '',
            );

            foreach (array_values($formats) as $index => $format) {
                $source = $temporaryFiles[$index];
                $archiveStage = $this->temporaryPath($location['path']);
                $temporaryFiles[] = $archiveStage;
                if (!copy($source, $archiveStage)) {
                    throw new \RuntimeException('Nije moguće pripremiti arhivsku datoteku cjenika.');
                }

                $archiveName = cjenik_build_filename($format, $meta, true);
                $currentName = cjenik_build_filename($format, $meta, false);
                $operations[] = array('stage' => $archiveStage, 'target' => trailingslashit($location['path']) . $archiveName);
                $operations[] = array('stage' => $source, 'target' => trailingslashit($location['path']) . $currentName);

                $files[$format . '_url'] = trailingslashit($location['url']) . $currentName;
                $files[$format . '_file'] = $currentName;
                $files[$format . '_download_name'] = $archiveName;
            }

            $this->publish($operations);
            $temporaryFiles = array();

            update_option(CJENIK_LAST_GENERATED_OPTION, time());
            update_option(CJENIK_LAST_FILES_OPTION, $files, false);
            delete_option(CJENIK_LAST_ERROR_OPTION);

            $this->dirty->clearIfUnchanged($dirtySnapshot);
            if ($this->dirty->isDirty()) {
                cjenik_schedule_refresh(120);
            }

            cjenik_cleanup_old_files();
            return $files;
        } catch (\Throwable $error) {
            foreach ($temporaryFiles as $temporary) {
                if (is_string($temporary) && is_file($temporary)) {
                    @unlink($temporary);
                }
            }
            update_option(CJENIK_LAST_ERROR_OPTION, $error->getMessage(), false);
            cjenik_notify_failure($error->getMessage());
            return false;
        } finally {
            $this->lock->release();
        }
    }

    private function enabledFormats()
    {
        $formats = array();
        if (get_option(CJENIK_FORMAT_CSV_OPTION, 'yes') === 'yes') {
            $formats[] = 'csv';
        }
        if (get_option(CJENIK_FORMAT_XML_OPTION, 'yes') === 'yes') {
            $formats[] = 'xml';
        }
        if (!$formats) {
            throw new \RuntimeException('Odaberite barem jedan format cjenika (CSV ili XML).');
        }
        return $formats;
    }

    private function assertWooCommerceAvailable()
    {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_products') || !function_exists('wc_get_product')) {
            throw new \RuntimeException('WooCommerce nije dostupan; zadnji ispravni cjenik ostaje objavljen.');
        }
    }

    private function temporaryPath($directory)
    {
        $path = tempnam($directory, '.sidrena-');
        if ($path === false) {
            throw new \RuntimeException('Nije moguće napraviti privremenu datoteku u direktoriju cjenika.');
        }
        return $path;
    }

    private function publish(array $operations)
    {
        $completed = array();

        try {
            foreach ($operations as $operation) {
                $target = $operation['target'];
                $backup = null;
                @chmod($operation['stage'], defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);

                if (is_file($target)) {
                    $backup = $this->temporaryPath(dirname($target));
                    if (!copy($target, $backup)) {
                        @unlink($backup);
                        throw new \RuntimeException('Nije moguće napraviti sigurnosnu kopiju postojećeg cjenika.');
                    }
                }

                if (!@rename($operation['stage'], $target)) {
                    if (DIRECTORY_SEPARATOR === '\\' && is_file($target)) {
                        @unlink($target);
                    }
                    if (!@rename($operation['stage'], $target)) {
                        if ($backup && is_file($backup)) {
                            if (!is_file($target)) {
                                @rename($backup, $target);
                            } else {
                                @unlink($backup);
                            }
                        }
                        throw new \RuntimeException('Atomska objava cjenika nije uspjela.');
                    }
                }

                $completed[] = array('target' => $target, 'backup' => $backup);
            }
        } catch (\Throwable $error) {
            foreach (array_reverse($completed) as $item) {
                if ($item['backup'] && is_file($item['backup'])) {
                    @unlink($item['target']);
                    @rename($item['backup'], $item['target']);
                } else {
                    @unlink($item['target']);
                }
            }
            throw $error;
        }

        foreach ($completed as $item) {
            if ($item['backup'] && is_file($item['backup'])) {
                @unlink($item['backup']);
            }
        }
    }
}
