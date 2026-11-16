<?php
/**
 * Cjenik prema Pravilniku o načinu objave cjenika proizvoda (NN 132/2026),
 * primjena od 17.11.2026. Portions originated in Aplitap digital GPL code;
 * see NOTICE.md for provenance.
 */

use SidrenaCijenaCjenik\Config;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/compliance.php';

define('CJENIK_UNIT_META_KEY', '_cjenik_unit');
define('CJENIK_BARCODE_META_KEY', '_cjenik_barcode');
define('CJENIK_VISIBILITY_META_KEY', '_cjenik_visibility_override');
define('CJENIK_ENABLED_OPTION', 'sidrena_cijena_cjenik_enabled');
define('CJENIK_LOCATION_LABEL_OPTION', 'sidrena_cijena_cjenik_location_label');
define('CJENIK_OBJECT_TYPE_OPTION', 'sidrena_cijena_cjenik_object_type');
define('CJENIK_STORAGE_NUMBER_OPTION', 'sidrena_cijena_cjenik_storage_number');
define('CJENIK_LAST_GENERATED_OPTION', 'sidrena_cijena_cjenik_last_generated');
define('CJENIK_LAST_FILES_OPTION', 'sidrena_cijena_cjenik_last_files');
define('CJENIK_LAST_ERROR_OPTION', 'sidrena_cijena_cjenik_last_error');
define('CJENIK_DB_VERSION_OPTION', 'sidrena_cijena_cjenik_db_version');
define('CJENIK_DIRTY_OPTION', 'sidrena_cijena_cjenik_dirty_since');
define('CJENIK_GENERATION_LOCK', 'sidrena_cijena_cjenik_generation_lock');
define('CJENIK_CRON_HOOK', 'sidrena_cijena_cjenik_cron_generate');
define('CJENIK_REFRESH_HOOK', 'sidrena_cijena_cjenik_refresh');
define('CJENIK_DEFAULT_STORAGE_NUMBER', '01');
define('CJENIK_DEFAULT_OBJECT_TYPE', 'Internet trgovina');
define('CJENIK_MIN_RETENTION_DAYS', 15);

define('CJENIK_CRON_TIME_OPTION', 'sidrena_cijena_cjenik_cron_time');
define('CJENIK_DEFAULT_CRON_TIME', '07:30');
define('CJENIK_DELIMITER_OPTION', 'sidrena_cijena_cjenik_delimiter');
define('CJENIK_DEFAULT_DELIMITER', ';');
define('CJENIK_FORMAT_CSV_OPTION', 'sidrena_cijena_cjenik_format_csv');
define('CJENIK_FORMAT_XML_OPTION', 'sidrena_cijena_cjenik_format_xml');
define('CJENIK_RETENTION_DAYS_OPTION', 'sidrena_cijena_cjenik_retention_days');
define('CJENIK_NOTIFY_ENABLED_OPTION', 'sidrena_cijena_cjenik_notify_enabled');
define('CJENIK_NOTIFY_EMAIL_OPTION', 'sidrena_cijena_cjenik_notify_email');
define('CJENIK_SALE_LABEL_OPTION', 'sidrena_cijena_cjenik_sale_label');
define('CJENIK_DEFAULT_SALE_LABEL', 'Akcijska prodaja');

require_once dirname(__DIR__) . '/src/Catalog/State.php';
require_once dirname(__DIR__) . '/src/Catalog/StreamWriters.php';
require_once dirname(__DIR__) . '/src/Catalog/CatalogEngine.php';

function cjenik_engine() {
    static $engine = null;

    if ($engine === null) {
        $engine = new \SidrenaCijenaCjenik\Catalog\CatalogEngine();
    }

    return $engine;
}

function cjenik_cron_time_choices() {
    $choices = array();
    for ($h = 5; $h <= 7; $h++) {
        foreach (array('00', '15', '30', '45') as $m) {
            if ($h === 7 && $m === '45') {
                continue;
            }
            $time = sprintf('%02d:%s', $h, $m);
            $choices[$time] = $time;
        }
    }
    return $choices;
}

function cjenik_delimiter_choices() { return array(';' => __('točka-zarez (;) — NN 132/2026', 'sidrena-cijena')); }

function cjenik_unit_choices() {
    return array(
        'kom' => __('kom (komad)', 'sidrena-cijena'),
        'kg'  => __('kg', 'sidrena-cijena'),
        'g'   => __('g', 'sidrena-cijena'),
        'L'   => __('L', 'sidrena-cijena'),
        'ml'  => __('ml', 'sidrena-cijena'),
        'm'   => __('m', 'sidrena-cijena'),
        'm3'  => __('m3', 'sidrena-cijena'),
        'm2'  => __('m2', 'sidrena-cijena'),
    );
}

function cjenik_visibility_choices() {
    return array(
        'auto'    => __('Automatski, prema vidljivosti u trgovini', 'sidrena-cijena'),
        'include' => __('Uvijek uključi u cjenik', 'sidrena-cijena'),
        'exclude' => __('Uvijek izostavi iz cjenika', 'sidrena-cijena'),
    );
}

function cjenik_product_included($product, $ignore_exemption = false) {
    if (!$ignore_exemption && sidrena_cijena_base_exempt($product)) { return false; }
    $override = $product->get_meta(CJENIK_VISIBILITY_META_KEY, true);

    if ($override === 'include') {
        return true;
    }

    if ($override === 'exclude') {
        return false;
    }

    return $product->get_catalog_visibility() !== 'hidden';
}

// 1. Polja na proizvodu (tab Inventory, odmah ispod SKU-a): barkod, jedinica mjere, uključenost u cjenik
add_action('woocommerce_product_options_sku', 'cjenik_add_product_fields');
function cjenik_add_product_fields() {
    global $product_object;

    $unit = $product_object ? $product_object->get_meta(CJENIK_UNIT_META_KEY, true) : '';
    if ($unit === '') {
        $unit = 'kom';
    }

    $visibility_override = $product_object ? $product_object->get_meta(CJENIK_VISIBILITY_META_KEY, true) : '';
    if ($visibility_override === '') {
        $visibility_override = 'auto';
    }

    woocommerce_wp_select(array(
        'id'          => CJENIK_UNIT_META_KEY,
        'label'       => __('Jedinica mjere', 'sidrena-cijena'),
        'desc_tip'    => true,
        'description' => __('Jedinica mjere za potrebe cjenika.', 'sidrena-cijena'),
        'options'     => cjenik_unit_choices(),
        'value'       => $unit,
    ));

    woocommerce_wp_select(array(
        'id'          => CJENIK_VISIBILITY_META_KEY,
        'label'       => __('Prikaz u cjeniku', 'sidrena-cijena'),
        'desc_tip'    => true,
        'description' => __('U automatskom načinu proizvod se uključuje u cjenik ako nije skriven u katalogu trgovine. Ovdje ga možete izričito uključiti ili izostaviti. Provjerite je li izostavljanje dopušteno za taj proizvod.', 'sidrena-cijena'),
        'options'     => cjenik_visibility_choices(),
        'value'       => $visibility_override,
    ));
}

// 2. Spremanje
add_action('woocommerce_process_product_meta', 'cjenik_save_product_fields');
function cjenik_save_product_fields($post_id) {
    if (!current_user_can('edit_post', $post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    if (isset($_POST[CJENIK_BARCODE_META_KEY])) {
        update_post_meta($post_id, CJENIK_BARCODE_META_KEY, sanitize_text_field(wp_unslash($_POST[CJENIK_BARCODE_META_KEY])));
    }

    if (isset($_POST[CJENIK_VISIBILITY_META_KEY])) {
        $visibility_override = sanitize_text_field(wp_unslash($_POST[CJENIK_VISIBILITY_META_KEY]));
        if (!array_key_exists($visibility_override, cjenik_visibility_choices())) {
            $visibility_override = 'auto';
        }
        update_post_meta($post_id, CJENIK_VISIBILITY_META_KEY, $visibility_override);
    }

    if (isset($_POST['_cjenik_general_barcode'])) {
        update_post_meta($post_id, '_cjenik_general_barcode', sanitize_text_field(wp_unslash($_POST['_cjenik_general_barcode'])));
    }

    if (isset($_POST[CJENIK_UNIT_META_KEY])) {
        $unit = sanitize_text_field(wp_unslash($_POST[CJENIK_UNIT_META_KEY]));
        if (!array_key_exists($unit, cjenik_unit_choices())) {
            $unit = 'kom';
        }
        update_post_meta($post_id, CJENIK_UNIT_META_KEY, $unit);
    }
}

// 2b. Podrška za Quick Edit
add_action('woocommerce_product_quick_edit_end', 'cjenik_quick_edit_field');
function cjenik_quick_edit_field() {
    ?>
    <div class="inline-edit-group cjenik-quick-edit-row" style="clear:both;display:block;width:100%;float:none;">
        <label class="alignleft" style="width:100%;">
            <span class="title"><?php echo esc_html__('Prikaz u cjeniku', 'sidrena-cijena'); ?></span>
            <span class="input-text-wrap">
                <select class="cjenik_quick_edit_field" name="<?php echo esc_attr(CJENIK_VISIBILITY_META_KEY); ?>">
                    <?php foreach (cjenik_visibility_choices() as $value => $option_label) : ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($option_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </span>
        </label>
    </div>
    <?php
}

add_action('manage_product_posts_custom_column', 'cjenik_output_hidden_visibility_value', 20, 2);
function cjenik_output_hidden_visibility_value($column, $post_id) {
    if ($column === 'price') {
        $value = get_post_meta($post_id, CJENIK_VISIBILITY_META_KEY, true);
        if ($value === '') {
            $value = 'auto';
        }
        echo '<div class="cjenik_hidden_visibility_value" style="display:none;">' . esc_html($value) . '</div>';
    }
}

add_action('woocommerce_product_quick_edit_save', 'cjenik_quick_edit_save');
function cjenik_quick_edit_save($product) {
    if (!current_user_can('edit_post', $product->get_id())) {
        return;
    }

    if (isset($_POST[CJENIK_VISIBILITY_META_KEY])) {
        $visibility_override = sanitize_text_field(wp_unslash($_POST[CJENIK_VISIBILITY_META_KEY]));
        if (!array_key_exists($visibility_override, cjenik_visibility_choices())) {
            $visibility_override = 'auto';
        }
        update_post_meta($product->get_id(), CJENIK_VISIBILITY_META_KEY, $visibility_override);
    }
}

add_action('admin_enqueue_scripts', 'cjenik_quick_edit_script');
function cjenik_quick_edit_script($hook) {
    global $post_type;

    if ($hook !== 'edit.php' || $post_type !== 'product') {
        return;
    }

    $js = "
        (function($){
            if (typeof inlineEditPost === 'undefined') { return; }
            var cjenik_wc_inline_edit = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                cjenik_wc_inline_edit.apply(this, arguments);
                var postId = 0;
                if (typeof(id) === 'object') {
                    postId = parseInt(this.getId(id), 10);
                } else {
                    postId = parseInt(id, 10);
                }
                if (postId > 0) {
                    var \$row = $('#post-' + postId);
                    var value = \$row.find('.cjenik_hidden_visibility_value').first().text();
                    if (value) {
                        $('select.cjenik_quick_edit_field').val(value);
                    }
                }
            };
        })(jQuery);
    ";

    wp_add_inline_script('inline-edit-post', $js);
}

// 3. Prikupljanje podataka za sve proizvode (i varijacije varijabilnih proizvoda)
function cjenik_collect_rows() {
    try {
        return cjenik_engine()->collect();
    } catch (Throwable $error) {
        update_option(CJENIK_LAST_ERROR_OPTION, $error->getMessage(), false);
        return array();
    }
}

function cjenik_get_inherited_meta($product, $parent, $key, $default = '') {
    $value = $product->get_meta($key, true);
    if ($value === '' && $parent) {
        $value = $parent->get_meta($key, true);
    }

    return $value === '' ? $default : $value;
}

function cjenik_get_brand_names($product, $parent = null) {
    $product_id = $parent ? $parent->get_id() : $product->get_id();
    $taxonomies = apply_filters(
        'sidrena_cijena_cjenik_brand_taxonomies',
        array('product_brand', 'pwb-brand', 'yith_product_brand')
    );

    foreach ($taxonomies as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'names'));
        if (!is_wp_error($terms) && !empty($terms)) {
            return $terms;
        }
    }

    return array();
}

function cjenik_get_barcode($product, $parent = null) {
    $keys = apply_filters(
        'sidrena_cijena_cjenik_barcode_meta_keys',
        array(CJENIK_BARCODE_META_KEY, '_global_unique_id', '_ean', '_alg_ean')
    );

    foreach ($keys as $key) {
        $value = cjenik_get_inherited_meta($product, $parent, $key);
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function cjenik_build_row($product, $parent = null) {
    $name = $parent ? $parent->get_name() . ' - ' . wc_get_formatted_variation($product, true, false) : $product->get_name();
    $gross = cjenik_gross_price($product, $product->get_price());
    $base = sidrena_cijena_base_value($product, $parent);
    $unit = cjenik_get_inherited_meta($product, $parent, CJENIK_UNIT_META_KEY, 'kom');
    $quantity = cjenik_get_inherited_meta($product, $parent, '_cjenik_net_quantity');
    if ($quantity === '' && $unit === 'kom') { $quantity = '1'; }
    $ean = cjenik_get_inherited_meta($product, $parent, '_cjenik_ean');
    if ($ean === '') { $ean = cjenik_get_barcode($product, $parent); }
    $row = array(
        'naziv' => wp_strip_all_tags($name),
        'sifra_proizvoda' => $product->get_sku(),
        'brend' => implode(', ', cjenik_get_brand_names($product, $parent)),
        'neto_kolicina' => $quantity,
        'jedinica_mjere' => $unit,
        'maloprodajna_cijena' => $gross,
        'poseban_oblik_prodaje' => $product->is_on_sale() ? 'DA' : 'NE',
        'cijena_po_jedinici' => apply_filters('sidrena_cijena_cjenik_unit_price', cjenik_unit_price($gross, $quantity, $unit), $product, $parent, $unit),
        'barkod' => cjenik_get_inherited_meta($product, $parent, '_cjenik_general_barcode', cjenik_get_inherited_meta($product, $parent, '_barcode')),
        'ean' => $ean,
        'bazna_cijena' => cjenik_gross_price($product, $base),
        '_anchor_is_stored' => $base !== '',
        '_unit_required' => cjenik_get_inherited_meta($product, $parent, '_cjenik_unit_required') === 'yes',
    );
    return apply_filters('sidrena_cijena_cjenik_row', $row, $product, $parent);
}

// 4. Gradnja CSV-a
function cjenik_build_csv($rows) {
    $headers = \SidrenaCijenaCjenik\Catalog\CatalogEngine::columns();
    $text_columns = array_fill_keys(\SidrenaCijenaCjenik\Catalog\CatalogEngine::textColumns(), true);

    $delimiter = CJENIK_DEFAULT_DELIMITER;
    if (!array_key_exists($delimiter, cjenik_delimiter_choices())) {
        $delimiter = CJENIK_DEFAULT_DELIMITER;
    }

    $handle = fopen('php://temp', 'w+');
    fwrite($handle, "\xEF\xBB\xBF");
    fputcsv($handle, $headers, $delimiter, '"', '');

    foreach ($rows as $row) {
        $ordered_row = array();
        foreach ($headers as $header) {
            $value = isset($row[$header]) ? $row[$header] : '';
            if (isset($text_columns[$header])) {
                $value = \SidrenaCijenaCjenik\Catalog\CsvStreamWriter::spreadsheetSafeText($value);
            }
            $ordered_row[] = $value;
        }
        fputcsv($handle, $ordered_row, $delimiter, '"', '');
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

// 5. Gradnja XML-a
function cjenik_build_xml($rows, $meta) {
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><cjenik/>');
    $xml->addAttribute('datum_generiranja', $meta['generated_at']);
    $xml->addAttribute('vrsta_prodajnog_objekta', $meta['object_type']);
    $xml->addAttribute('prodajno_mjesto', $meta['location_label']);
    $xml->addAttribute('adresa', $meta['address']);
    $xml->addAttribute('oznaka_objekta', $meta['location_label']);
    $xml->addAttribute('broj_skladista', $meta['storage_number']);

    foreach ($rows as $row) {
        $item = $xml->addChild('proizvod');
        foreach (\SidrenaCijenaCjenik\Catalog\CatalogEngine::columns() as $key) {
            $value = isset($row[$key]) ? $row[$key] : '';
            // Assignment through a SimpleXML node safely escapes XML special characters.
            $child = $item->addChild($key);
            $child[0] = (string) $value;
        }
    }

    return $xml->asXML();
}

// 6. Naziv datoteke: vrsta objekta, adresa, oznaka objekta, broj pohrane i vremenska oznaka.
function cjenik_build_filename($extension, $meta, $timestamped = true) {
    $kind = isset($meta['kind']) ? $meta['kind'] : cjenik_catalog_kind();
    if (!$timestamped) { return 'cjenik_' . $kind . '_trenutni.' . $extension; }
    cjenik_validate_meta($meta);
    $parts = array('cjenik', $meta['trader_id']);
    if (!empty($meta['all_locations'])) {
        $parts[] = 'ZA_SVA_PRODAJNA_MJESTA';
    } else {
        foreach (array('object_type', 'address', 'location_label') as $key) { $parts[] = sanitize_title($meta[$key]); }
    }
    $parts[] = $kind;
    $parts[] = sanitize_title($meta['storage_number']) . '-' . (isset($meta['sequence']) ? (int) $meta['sequence'] : 1);
    $parts[] = (new DateTimeImmutable($meta['generated_at']))->format('Ymd_Hi');
    return implode('_', $parts) . '.' . $extension;
}

function cjenik_get_meta() {
    $location_label = get_option(CJENIK_LOCATION_LABEL_OPTION, '');
    $object_type = get_option(CJENIK_OBJECT_TYPE_OPTION, CJENIK_DEFAULT_OBJECT_TYPE);
    if ($object_type === '') {
        $object_type = CJENIK_DEFAULT_OBJECT_TYPE;
    }
    $storage_number = get_option(CJENIK_STORAGE_NUMBER_OPTION, CJENIK_DEFAULT_STORAGE_NUMBER);
    if ($storage_number === '') {
        $storage_number = CJENIK_DEFAULT_STORAGE_NUMBER;
    }

    $address_parts = array_filter(array(
        get_option('woocommerce_store_address'),
        get_option('woocommerce_store_address_2'),
        get_option('woocommerce_store_postcode'),
        get_option('woocommerce_store_city'),
    ));
    $address = implode(', ', $address_parts);

    return array(
        'object_type'     => $object_type,
        'location_label'  => $location_label !== '' ? $location_label : get_bloginfo('name'),
        'storage_number'  => $storage_number,
        'address'         => $address,
        'generated_at'    => wp_date('Y-m-d\TH:i:sP'),
        'trader_id' => get_option('sidrena_cijena_cjenik_trader_id', ''),
        'kind' => cjenik_catalog_kind(),
        'all_locations' => get_option('sidrena_cijena_cjenik_all_locations', 'no') === 'yes',
    );
}

function cjenik_get_dir() {
    $upload_dir = wp_upload_dir();
    $dir = trailingslashit($upload_dir['basedir']) . 'cjenik';

    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    return array(
        'path' => $dir,
        'url'  => trailingslashit($upload_dir['baseurl']) . 'cjenik',
    );
}

function cjenik_write_file($path, $contents) {
    $temporary = tempnam(dirname($path), '.sidrena-');
    if ($temporary === false) {
        return false;
    }

    if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
        @unlink($temporary);
        return false;
    }

    if (@rename($temporary, $path)) {
        return true;
    }

    if (DIRECTORY_SEPARATOR === '\\' && is_file($path)) {
        @unlink($path);
    }
    if (@rename($temporary, $path)) {
        return true;
    }

    @unlink($temporary);
    return false;
}

// 7. Generiranje i spremanje datoteka (arhivska + "trenutna" verzija)
function cjenik_generate() {
    return cjenik_engine()->generate();
}

function cjenik_notify_failure($message) {
    if (get_option(CJENIK_NOTIFY_ENABLED_OPTION, 'no') !== 'yes') {
        return;
    }

    $email = get_option(CJENIK_NOTIFY_EMAIL_OPTION, get_option('admin_email'));
    if (!is_email($email)) {
        return;
    }

    $notification_key = 'sidrena_cijena_cjenik_failure_notice_lock';
    if (get_transient($notification_key)) {
        return;
    }
    set_transient($notification_key, '1', 15 * MINUTE_IN_SECONDS);

    wp_mail(
        $email,
        sprintf('[%s] Greška pri generiranju cjenika', get_bloginfo('name')),
        "Generiranje cjenika nije uspjelo.\n\nGreška: " . $message . "\n\nVrijeme: " . wp_date('d.m.Y. H:i')
    );
}

// 8. Brisanje arhivskih datoteka starijih od zadanog broja dana
function cjenik_cleanup_old_files() {
    $location = cjenik_get_dir();
    $last = get_option(CJENIK_LAST_FILES_OPTION, array());
    $active = array_filter(array($last['csv_file'] ?? '', $last['xml_file'] ?? '', $last['csv_download_name'] ?? '', $last['xml_download_name'] ?? ''));
    $replaced = get_option('sidrena_cijena_cjenik_replaced_at', array());
    $cutoff = time() - max(15, (int) get_option(CJENIK_RETENTION_DAYS_OPTION, 30)) * DAY_IN_SECONDS;
    foreach ((array) glob(trailingslashit($location['path']) . 'cjenik_*') as $file) {
        $name = basename($file);
        if (in_array($name, $active, true)) { continue; }
        // Unknown legacy files are conservatively kept until a replacement timestamp is recorded.
        if (!isset($replaced[$name])) { $replaced[$name] = time(); }
        if ($replaced[$name] < $cutoff && is_file($file) && unlink($file)) { unset($replaced[$name]); }
    }
    update_option('sidrena_cijena_cjenik_replaced_at', $replaced, false);
}

// 9. Automatsko generiranje - dnevno i i odgođeno nakon promjene proizvoda.
add_action('init', 'cjenik_schedule_cron');
function cjenik_schedule_cron($force = false) {
    $time = get_option(CJENIK_CRON_TIME_OPTION, CJENIK_DEFAULT_CRON_TIME);
    if (!array_key_exists($time, cjenik_cron_time_choices())) {
        $time = CJENIK_DEFAULT_CRON_TIME;
    }

    $scheduled_time = get_option('sidrena_cijena_cjenik_scheduled_time', '');

    if (!$force && wp_next_scheduled(CJENIK_CRON_HOOK) && $scheduled_time === $time) {
        return;
    }

    wp_clear_scheduled_hook(CJENIK_CRON_HOOK);
    list($hour, $minute) = array_map('intval', explode(':', $time));
    $now = new DateTimeImmutable('now', wp_timezone());
    $next = $now->setTime($hour, $minute, 0);
    if ($next <= $now) {
        $next = $next->modify('+1 day');
    }

    wp_schedule_single_event($next->getTimestamp(), CJENIK_CRON_HOOK);
    update_option('sidrena_cijena_cjenik_scheduled_time', $time);
}

add_action(CJENIK_CRON_HOOK, 'cjenik_run_daily_generation');
add_action(CJENIK_REFRESH_HOOK, 'cjenik_maybe_generate');
function cjenik_run_daily_generation() {
    cjenik_maybe_generate();
    cjenik_schedule_cron(true);
}

function cjenik_maybe_generate() {
    if (get_option(CJENIK_ENABLED_OPTION, 'yes') === 'yes') {
        cjenik_generate();
    }
}

function cjenik_schedule_refresh($delay = 120) {
    if (!wp_next_scheduled(CJENIK_REFRESH_HOOK)) {
        wp_schedule_single_event(time() + max(1, (int) $delay), CJENIK_REFRESH_HOOK);
    }
}

function cjenik_queue_refresh() {
    if (get_option(CJENIK_ENABLED_OPTION, 'yes') !== 'yes') {
        return;
    }

    cjenik_engine()->markDirty();
    $GLOBALS['sidrena_cjenik_request_changed'] = true;
    cjenik_schedule_refresh(60);
}

add_action('woocommerce_update_product', 'cjenik_queue_refresh', 10, 0);
add_action('woocommerce_product_set_stock', 'cjenik_queue_refresh', 10, 0);
add_action('woocommerce_variation_set_stock', 'cjenik_queue_refresh', 10, 0);
add_action('woocommerce_update_product_variation', 'cjenik_queue_refresh', 30, 0);
add_action('woocommerce_new_product_variation', 'cjenik_queue_refresh', 30, 0);
add_action('before_delete_post', 'cjenik_refresh_on_product_change', 10, 1);
add_action('trashed_post', 'cjenik_refresh_on_product_change', 10, 1);
add_action('untrashed_post', 'cjenik_refresh_on_product_change', 10, 1);
function cjenik_refresh_on_product_change($id) {
    if (in_array(get_post_type($id), array('product', 'product_variation'), true)) { cjenik_queue_refresh(); }
}

add_action('init', 'cjenik_register_rewrite_rule');
function cjenik_register_rewrite_rule() {
    add_rewrite_rule('^cjenik-proizvoda\.csv$', 'index.php?sidrena_cjenik_download=csv', 'top');
    add_rewrite_rule('^cjenik-proizvoda\.xml$', 'index.php?sidrena_cjenik_download=xml', 'top');
}

add_filter('query_vars', 'cjenik_register_query_var');
function cjenik_register_query_var($vars) {
    $vars[] = 'sidrena_cjenik_download';
    return $vars;
}

function cjenik_get_public_url($format) {
    $format = strtolower((string) $format);
    if (!in_array($format, array('csv', 'xml'), true)) {
        return '';
    }

    if (get_option('permalink_structure')) {
        return home_url('/cjenik-proizvoda.' . $format);
    }

    return add_query_arg('sidrena_cjenik_download', $format, home_url('/'));
}

function cjenik_get_public_csv_url() {
    return cjenik_get_public_url('csv');
}

function cjenik_get_public_xml_url() {
    return cjenik_get_public_url('xml');
}

function cjenik_public_format_config($format) {
    $formats = array(
        'csv' => array(
            'option' => CJENIK_FORMAT_CSV_OPTION,
            'mime'   => 'text/csv; charset=utf-8',
            'label'  => 'CSV',
        ),
        'xml' => array(
            'option' => CJENIK_FORMAT_XML_OPTION,
            'mime'   => 'application/xml; charset=utf-8',
            'label'  => 'XML',
        ),
    );

    return isset($formats[$format]) ? $formats[$format] : null;
}

function cjenik_public_error($status, $message, $retry_after = 0) {
    status_header($status);
    nocache_headers();
    if ($retry_after > 0) {
        header('Retry-After: ' . (int) $retry_after);
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo esc_html($message);
    exit;
}

add_action('template_redirect', 'cjenik_handle_public_download');
function cjenik_handle_public_download() {
    $format = strtolower((string) get_query_var('sidrena_cjenik_download'));
    $config = cjenik_public_format_config($format);
    if (!$config) {
        return;
    }

    if (get_option($config['option'], 'yes') !== 'yes') {
        cjenik_public_error(404, sprintf(__('Preuzimanje cjenika u formatu %s nije uključeno.', 'sidrena-cijena'), $config['label']));
    }

    $is_dirty = get_option(CJENIK_DIRTY_OPTION, false) !== false;
    $is_generating = get_option(CJENIK_GENERATION_LOCK, false) !== false;
    if ($is_dirty) {
        cjenik_schedule_refresh(5);
    }

    $last_files = get_option(CJENIK_LAST_FILES_OPTION, array());
    $file_key = $format . '_file';
    $download_key = $format . '_download_name';
    $filename = isset($last_files[$file_key]) ? basename($last_files[$file_key]) : '';
    $download_name = isset($last_files[$download_key]) ? basename($last_files[$download_key]) : $filename;
    $location = cjenik_get_dir();
    $path = $filename !== '' ? trailingslashit($location['path']) . $filename : '';

    if ($path === ''
        || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== $format
        || strpos($filename, 'cjenik_') !== 0
        || !is_readable($path)) {
        if ($is_dirty || $is_generating) {
            cjenik_public_error(503, __('Cjenik se priprema. Pokušajte ponovno za minutu.', 'sidrena-cijena'), 60);
        }
        cjenik_public_error(404, sprintf(__('Cjenik u formatu %s još nije izrađen.', 'sidrena-cijena'), $config['label']));
    }

    $modified = filemtime($path);
    $etag = '"' . md5_file($path) . '"';
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
    header('Cache-Control: public, max-age=60, must-revalidate');

    $client_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH'])) : '';
    $client_modified = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime(wp_unslash($_SERVER['HTTP_IF_MODIFIED_SINCE'])) : false;
    if ($client_etag === $etag || ($client_etag === '' && $client_modified !== false && $client_modified >= $modified)) {
        status_header(304);
        exit;
    }

    header('Content-Type: ' . $config['mime']);
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($download_name) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Robots-Tag: noindex, nofollow', true);
    readfile($path);
    exit;
}

function cjenik_get_archive_files($format) {
    if (!in_array($format, array('csv', 'xml'), true)) {
        return array();
    }

    $location = cjenik_get_dir();
    $files = glob(trailingslashit($location['path']) . 'cjenik_*.' . $format);
    $archives = array();
    foreach ((array) $files as $file) {
        if (strpos(basename($file), '_trenutni.') === false && is_file($file)) {
            $archives[$file] = filemtime($file);
        }
    }
    arsort($archives);

    return $archives;
}

add_shortcode('sidrena_cjenik', 'cjenik_download_shortcode');
function cjenik_download_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'tekst' => '',
            'format' => 'csv',
            'arhiva' => 'ne',
            'azurirano' => 'da',
            'tekst_csv' => '',
            'tekst_xml' => '',
        ),
        $atts,
        'sidrena_cjenik'
    );

    $requested_format = strtolower(sanitize_key($atts['format']));
    $formats = in_array($requested_format, array('oba', 'both'), true) ? array('csv', 'xml') : array($requested_format);
    $last_generated = (int) get_option(CJENIK_LAST_GENERATED_OPTION, 0);
    $last_files = get_option(CJENIK_LAST_FILES_OPTION, array());
    if (!$last_generated) {
        return '<span class="sidrena-cjenik-nedostupan">' . esc_html__('Cjenik trenutačno nije dostupan.', 'sidrena-cijena') . '</span>';
    }

    $html = '<div class="sidrena-cjenik-download">';
    $shown_formats = array();
    foreach ($formats as $format) {
        $config = cjenik_public_format_config($format);
        if (!$config || get_option($config['option'], 'yes') !== 'yes' || empty($last_files[$format . '_file'])) {
            continue;
        }

        $button_text = $atts['tekst_' . $format] !== '' ? $atts['tekst_' . $format] : ($atts['tekst'] !== '' && count($formats) === 1
            ? $atts['tekst']
            : sprintf(__('Preuzmi važeći cjenik (%s)', 'sidrena-cijena'), $config['label']));
        $html .= '<a style="margin-right:8px;" class="button sidrena-cjenik-button" href="' . esc_url(cjenik_get_public_url($format)) . '">' . esc_html($button_text) . '</a>';
        $shown_formats[] = $format;
    }

    if (!$shown_formats) {
        return '<span class="sidrena-cjenik-nedostupan">' . esc_html__('Odabrani format cjenika trenutačno nije dostupan.', 'sidrena-cijena') . '</span>';
    }

    if (strtolower($atts['azurirano']) === 'da') { $html .= '<small style="display:block;margin-top:6px;">' . esc_html(sprintf(__('Ažurirano: %s', 'sidrena-cijena'), wp_date('d.m.Y. H:i', $last_generated))) . '</small>'; }

    if (strtolower($atts['arhiva']) === 'da') { $html .= cjenik_archive_html($shown_formats); }

    return $html . '</div>';
}

function cjenik_archive_html($formats, $expanded = false) {
    $location = cjenik_get_dir();
    $html = '';
    foreach ($formats as $format) {
        if (!in_array($format, array('csv', 'xml'), true)) { continue; }
        $archives = cjenik_get_archive_files($format);
        if (!$archives) { continue; }
        $html .= '<details class="sidrena-cjenik-archive sidrena-cjenik-archive-' . esc_attr($format) . '"' . ($expanded ? ' open' : '') . '><summary class="sidrena-cjenik-archive-title">' . esc_html(sprintf(__('Arhiva cjenika u formatu %s', 'sidrena-cijena'), strtoupper($format))) . '</summary><ul class="sidrena-cjenik-archive-list">';
        foreach ($archives as $file => $modified) {
            $url = trailingslashit($location['url']) . rawurlencode(basename($file));
            $html .= '<li class="sidrena-cjenik-archive-item"><a class="sidrena-cjenik-archive-link" href="' . esc_url($url) . '">' . esc_html(wp_date('d.m.Y. H:i', $modified)) . '</a></li>';
        }
        $html .= '</ul></details>';
    }
    return $html;
}

add_shortcode('sidrena_cjenik_arhiva', 'cjenik_archive_shortcode');
function cjenik_archive_shortcode($atts) {
    $atts = shortcode_atts(array('format' => 'oba', 'otvoreno' => 'ne'), $atts, 'sidrena_cjenik_arhiva');
    $format = strtolower(sanitize_key($atts['format']));
    $formats = in_array($format, array('oba', 'both'), true) ? array('csv', 'xml') : array($format);
    $html = cjenik_archive_html($formats, strtolower(trim($atts['otvoreno'])) === 'da');
    return $html !== '' ? '<div class="sidrena-cjenik-archives">' . $html . '</div>' : '<span class="sidrena-cjenik-nedostupan">' . esc_html__('Arhiva trenutačno nije dostupna.', 'sidrena-cijena') . '</span>';
}

add_shortcode('sidrena_cjenik_azurirano', 'cjenik_updated_shortcode');
function cjenik_updated_shortcode($atts) {
    $atts = shortcode_atts(array('tekst' => __('Ažurirano:', 'sidrena-cijena')), $atts, 'sidrena_cjenik_azurirano');
    $timestamp = (int) get_option(CJENIK_LAST_GENERATED_OPTION, 0);
    if (!$timestamp) { return ''; }
    return '<span class="sidrena-cjenik-updated">' . esc_html(trim($atts['tekst'] . ' ' . wp_date('d.m.Y. H:i', $timestamp))) . '</span>';
}

function cjenik_column_label($column) {
    $labels = array('naziv' => 'Naziv', 'sifra_proizvoda' => 'Šifra proizvoda', 'brend' => 'Robna marka', 'neto_kolicina' => 'Neto količina', 'jedinica_mjere' => 'Jedinica mjere', 'maloprodajna_cijena' => 'Maloprodajna cijena', 'poseban_oblik_prodaje' => 'Poseban oblik prodaje', 'cijena_po_jedinici' => 'Cijena za jedinicu mjere', 'barkod' => 'Barkod', 'ean' => 'EAN', 'bazna_cijena' => 'Bazna cijena');
    return isset($labels[$column]) ? __($labels[$column], 'sidrena-cijena') : $column;
}

register_activation_hook(dirname(__DIR__) . '/sidrena-cijena.php', 'cjenik_activate');
function cjenik_activate() {
    cjenik_register_rewrite_rule();
    flush_rewrite_rules(false);
    cjenik_schedule_cron();
    update_option(CJENIK_DB_VERSION_OPTION, Config::VERSION, false);
}

add_action('init', 'cjenik_maybe_upgrade', 20);
function cjenik_maybe_upgrade() {
    if (get_option(CJENIK_DB_VERSION_OPTION) === Config::VERSION) {
        return;
    }

    flush_rewrite_rules(false);
    update_option(CJENIK_DB_VERSION_OPTION, Config::VERSION, false);
    cjenik_queue_refresh();
}

register_deactivation_hook(dirname(__DIR__) . '/sidrena-cijena.php', 'cjenik_deactivate');
function cjenik_deactivate() {
    wp_clear_scheduled_hook(CJENIK_CRON_HOOK);
    wp_clear_scheduled_hook(CJENIK_REFRESH_HOOK);
    flush_rewrite_rules(false);
}

function cjenik_get_readiness($rows, $meta) {
    $report = array(
        'total'          => count($rows),
        'missing_price'  => 0,
        'missing_anchor' => 0,
        'missing_sku'    => 0,
        'missing_brand'  => 0,
        'missing_barcode'=> 0,
        'settings_ok' => !empty($meta['trader_id']) && !empty($meta['storage_number']) && (!empty($meta['all_locations']) || (!empty($meta['object_type']) && !empty($meta['location_label']) && !empty($meta['address']))),
    );

    foreach ($rows as $row) {
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
        if ($row['barkod'] === '' && $row['ean'] === '') {
            $report['missing_barcode']++;
        }
    }

    return $report;
}

// 10. Sadržaj taba "Cjenik" (poziva se iz glavne stranice postavki u sidrena-cijena.php)
function cjenik_render_tab_content() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $tab_url = admin_url('admin.php?page=sidrena-cijena&tab=cjenik');

    if (isset($_POST['cjenik_save']) && check_admin_referer('cjenik_settings_save', 'cjenik_nonce')) {
        $auto_enabled = isset($_POST[CJENIK_ENABLED_OPTION]) ? 'yes' : 'no';
        update_option(CJENIK_ENABLED_OPTION, $auto_enabled);
        if ($auto_enabled === 'no') {
            delete_option(CJENIK_DIRTY_OPTION);
            wp_clear_scheduled_hook(CJENIK_REFRESH_HOOK);
        }

        $trader_id = isset($_POST['sidrena_cijena_cjenik_trader_id']) ? sanitize_text_field(wp_unslash($_POST['sidrena_cijena_cjenik_trader_id'])) : '';
        if (cjenik_valid_trader_id($trader_id)) { update_option('sidrena_cijena_cjenik_trader_id', $trader_id); }
        else { echo '<div class="notice notice-error"><p>Unesite ispravan OIB ili matični broj (samo znamenke).</p></div>'; }
        update_option('sidrena_cijena_cjenik_kind', isset($_POST['sidrena_cijena_cjenik_kind']) && $_POST['sidrena_cijena_cjenik_kind'] === 'usluge' ? 'usluge' : 'roba');
        update_option('sidrena_cijena_cjenik_all_locations', isset($_POST['sidrena_cijena_cjenik_all_locations']) ? 'yes' : 'no');
        $location_label = isset($_POST[CJENIK_LOCATION_LABEL_OPTION]) ? sanitize_text_field(wp_unslash($_POST[CJENIK_LOCATION_LABEL_OPTION])) : '';
        update_option(CJENIK_LOCATION_LABEL_OPTION, $location_label);

        $object_type = isset($_POST[CJENIK_OBJECT_TYPE_OPTION]) ? sanitize_text_field(wp_unslash($_POST[CJENIK_OBJECT_TYPE_OPTION])) : CJENIK_DEFAULT_OBJECT_TYPE;
        update_option(CJENIK_OBJECT_TYPE_OPTION, $object_type !== '' ? $object_type : CJENIK_DEFAULT_OBJECT_TYPE);

        $storage_number = isset($_POST[CJENIK_STORAGE_NUMBER_OPTION]) ? sanitize_text_field(wp_unslash($_POST[CJENIK_STORAGE_NUMBER_OPTION])) : '';
        update_option(CJENIK_STORAGE_NUMBER_OPTION, $storage_number !== '' ? $storage_number : CJENIK_DEFAULT_STORAGE_NUMBER);

        $cron_time = isset($_POST[CJENIK_CRON_TIME_OPTION]) ? sanitize_text_field(wp_unslash($_POST[CJENIK_CRON_TIME_OPTION])) : CJENIK_DEFAULT_CRON_TIME;
        if (!array_key_exists($cron_time, cjenik_cron_time_choices())) {
            $cron_time = CJENIK_DEFAULT_CRON_TIME;
        }
        update_option(CJENIK_CRON_TIME_OPTION, $cron_time);

        $delimiter = isset($_POST[CJENIK_DELIMITER_OPTION]) ? wp_unslash($_POST[CJENIK_DELIMITER_OPTION]) : CJENIK_DEFAULT_DELIMITER;
        if (!array_key_exists($delimiter, cjenik_delimiter_choices())) {
            $delimiter = CJENIK_DEFAULT_DELIMITER;
        }
        update_option(CJENIK_DELIMITER_OPTION, ';');

        update_option(CJENIK_FORMAT_CSV_OPTION, isset($_POST[CJENIK_FORMAT_CSV_OPTION]) ? 'yes' : 'no');
        update_option(CJENIK_FORMAT_XML_OPTION, isset($_POST[CJENIK_FORMAT_XML_OPTION]) ? 'yes' : 'no');
        if (!isset($_POST[CJENIK_FORMAT_CSV_OPTION]) && !isset($_POST[CJENIK_FORMAT_XML_OPTION])) {
            update_option(CJENIK_FORMAT_CSV_OPTION, 'yes');
        }

        $retention_days = isset($_POST[CJENIK_RETENTION_DAYS_OPTION]) ? (int) $_POST[CJENIK_RETENTION_DAYS_OPTION] : CJENIK_MIN_RETENTION_DAYS;
        if ($retention_days < CJENIK_MIN_RETENTION_DAYS) {
            $retention_days = CJENIK_MIN_RETENTION_DAYS;
        }
        update_option(CJENIK_RETENTION_DAYS_OPTION, $retention_days);

        update_option(CJENIK_NOTIFY_ENABLED_OPTION, isset($_POST[CJENIK_NOTIFY_ENABLED_OPTION]) ? 'yes' : 'no');

        $notify_email = isset($_POST[CJENIK_NOTIFY_EMAIL_OPTION]) ? sanitize_email(wp_unslash($_POST[CJENIK_NOTIFY_EMAIL_OPTION])) : '';
        update_option(CJENIK_NOTIFY_EMAIL_OPTION, $notify_email !== '' ? $notify_email : get_option('admin_email'));

        cjenik_schedule_cron();
        cjenik_queue_refresh();

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Postavke su spremljene.', 'sidrena-cijena') . '</p></div>';
    }

    if (isset($_POST['cjenik_generate_now']) && check_admin_referer('cjenik_generate_now_action', 'cjenik_generate_nonce')) {
        $result = cjenik_generate();
        if ($result === false) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Cjenik nije izrađen. U odjeljku Posljednja pogreška provjerite uzrok i dopunite potrebne podatke.', 'sidrena-cijena') . '</p></div>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cjenik je izrađen i objavljen.', 'sidrena-cijena') . '</p></div>';
        }
    }

    $enabled = get_option(CJENIK_ENABLED_OPTION, 'yes');
    $location_label = get_option(CJENIK_LOCATION_LABEL_OPTION, '');
    $object_type = get_option(CJENIK_OBJECT_TYPE_OPTION, CJENIK_DEFAULT_OBJECT_TYPE);
    $storage_number = get_option(CJENIK_STORAGE_NUMBER_OPTION, CJENIK_DEFAULT_STORAGE_NUMBER);
    $cron_time = get_option(CJENIK_CRON_TIME_OPTION, CJENIK_DEFAULT_CRON_TIME);
    $delimiter = CJENIK_DEFAULT_DELIMITER;
    $format_csv = get_option(CJENIK_FORMAT_CSV_OPTION, 'yes');
    $format_xml = get_option(CJENIK_FORMAT_XML_OPTION, 'yes');
    $retention_days = get_option(CJENIK_RETENTION_DAYS_OPTION, CJENIK_MIN_RETENTION_DAYS);
    $notify_enabled = get_option(CJENIK_NOTIFY_ENABLED_OPTION, 'no');
    $notify_email = get_option(CJENIK_NOTIFY_EMAIL_OPTION, get_option('admin_email'));
    $sale_label = get_option(CJENIK_SALE_LABEL_OPTION, CJENIK_DEFAULT_SALE_LABEL);

    $meta = cjenik_get_meta();
    $dir = cjenik_get_dir();
    $last_files = get_option(CJENIK_LAST_FILES_OPTION, array());
    $csv_url = cjenik_get_public_csv_url();
    $xml_url = cjenik_get_public_xml_url();
    $last_generated = get_option(CJENIK_LAST_GENERATED_OPTION, '');
    $last_error = get_option(CJENIK_LAST_ERROR_OPTION, '');
    $next_scheduled = wp_next_scheduled(CJENIK_CRON_HOOK);
    try {
        $readiness = cjenik_engine()->readiness($meta, 100);
        $preview_rows = $readiness['preview'];
    } catch (Throwable $error) {
        $preview_rows = array();
        $readiness = cjenik_get_readiness($preview_rows, $meta);
        update_option(CJENIK_LAST_ERROR_OPTION, $error->getMessage(), false);
    }
    ?>
    <p>
        <?php
        printf(
            /* translators: %s: NN reference */
            esc_html__('Ovdje možete izraditi i objaviti strojno čitljiv cjenik u formatu CSV ili XML prema Pravilniku o načinu objave cjenika proizvoda (%s), koji se primjenjuje od 17. studenoga 2026.', 'sidrena-cijena'),
            'NN 132/2026'
        );
        ?>
    </p>
    <div class="notice notice-warning inline" style="margin:0 0 20px;padding:12px;">
        <p style="margin:0;"><?php echo esc_html__('Dodatak olakšava tehničku pripremu cjenika. Prije objave provjerite točnost podataka i obveze koje se odnose na vašu trgovinu. Za tumačenje propisa po potrebi se obratite računovođi ili pravniku.', 'sidrena-cijena'); ?></p>
    </div>

    <?php if (empty($meta['address'])) : ?>
        <div class="notice notice-error inline" style="margin:0 0 20px;padding:12px;">
            <p style="margin:0;">
                <?php
                printf(
                    /* translators: %s: link to WooCommerce store address settings */
                    wp_kses(
                        __('Adresa trgovine nije unesena. Dodajte je u <a href="%s">WooCommerce → Postavke → Općenito</a>. Adresa se koristi u nazivu datoteke cjenika.', 'sidrena-cijena'),
                        array('a' => array('href' => array()))
                    ),
                    esc_url(admin_url('admin.php?page=wc-settings&tab=general'))
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url($tab_url); ?>">
        <?php wp_nonce_field('cjenik_settings_save', 'cjenik_nonce'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="cjenik-trader-id">OIB pravne osobe / matični broj trgovca fizičke osobe</label></th>
                <td><input id="cjenik-trader-id" name="sidrena_cijena_cjenik_trader_id" type="text" inputmode="numeric" pattern="[0-9]{1,20}" required value="<?php echo esc_attr($meta['trader_id']); ?>" class="regular-text" />
                <p class="description">Ovaj se podatak navodi u nazivu datoteke. Za pravnu osobu unesite OIB, a za trgovca fizičku osobu matični broj.</p></td>
            </tr>
            <tr>
                <th scope="row"><label for="cjenik-kind">Vrsta cjenika</label></th>
                <td><select id="cjenik-kind" name="sidrena_cijena_cjenik_kind">
                    <option value="roba" <?php selected(cjenik_catalog_kind(), 'roba'); ?>>Roba</option>
                    <option value="usluge" <?php selected(cjenik_catalog_kind(), 'usluge'); ?>>Usluge</option>
                </select><p class="description">Dodatak objavljuje jedan cjenik, za robu ili za usluge. Ako nudite oboje, osigurajte zasebne cjenike s odgovarajućim podacima.</p></td>
            </tr>
            <tr>
                <th scope="row">Jednak cjenik za sva prodajna mjesta</th>
                <td><label><input type="checkbox" name="sidrena_cijena_cjenik_all_locations" value="1" <?php checked($meta['all_locations']); ?> /> ZA_SVA_PRODAJNA_MJESTA</label>
                <p class="description">Označite ako se isti cjenik primjenjuje na svim prodajnim mjestima. Ako se cijene razlikuju, za svako prodajno mjesto potreban je zaseban cjenik.</p></td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Generiranje cjenika', 'sidrena-cijena'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(CJENIK_ENABLED_OPTION); ?>" value="1" <?php checked($enabled, 'yes'); ?> />
                        <?php echo esc_html__('Automatski generiraj cjenik (dnevno i nakon promjene proizvoda ili zalihe)', 'sidrena-cijena'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cjenik-location-label"><?php echo esc_html__('Oznaka prodajnog mjesta / objekta', 'sidrena-cijena'); ?></label>
                </th>
                <td>
                    <input type="text" id="cjenik-location-label" name="<?php echo esc_attr(CJENIK_LOCATION_LABEL_OPTION); ?>" value="<?php echo esc_attr($location_label); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
                    <p class="description"><?php echo esc_html__('Unesite kratku oznaku internetske trgovine ili prodajnog mjesta. Navodi se u nazivu datoteke cjenika. Ako polje ostavite prazno, koristi se naziv mrežnog mjesta.', 'sidrena-cijena'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cjenik-object-type"><?php echo esc_html__('Vrsta prodajnog objekta', 'sidrena-cijena'); ?></label>
                </th>
                <td>
                    <input type="text" id="cjenik-object-type" name="<?php echo esc_attr(CJENIK_OBJECT_TYPE_OPTION); ?>" value="<?php echo esc_attr($object_type); ?>" class="regular-text" />
                    <p class="description"><?php echo esc_html__('Primjerice: internetska trgovina. Podatak se navodi u nazivu datoteke cjenika.', 'sidrena-cijena'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cjenik-storage-number"><?php echo esc_html__('Broj pohrane', 'sidrena-cijena'); ?></label>
                </th>
                <td>
                    <input type="text" id="cjenik-storage-number" name="<?php echo esc_attr(CJENIK_STORAGE_NUMBER_OPTION); ?>" value="<?php echo esc_attr($storage_number); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Adresa iz postavki WooCommercea', 'sidrena-cijena'); ?></th>
                <td><?php echo esc_html($meta['address'] ? $meta['address'] : __('nije postavljeno', 'sidrena-cijena')); ?></td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cjenik-cron-time"><?php echo esc_html__('Vrijeme dnevne izrade cjenika', 'sidrena-cijena'); ?></label>
                </th>
                <td>
                    <select id="cjenik-cron-time" name="<?php echo esc_attr(CJENIK_CRON_TIME_OPTION); ?>">
                        <?php foreach (cjenik_cron_time_choices() as $value => $option_label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($cron_time, $value); ?>><?php echo esc_html($option_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Izmijenjeni cjenik objavite do 11 sati na dan izmjene. Kasnije promjene objavite odmah. WP-Cron pokreće se pri posjetima mrežnom mjestu. Za pouzdanije izvršavanje postavite zakazani zadatak na poslužitelju koji redovito pokreće wp-cron.php.', 'sidrena-cijena'); ?></p>
                    <?php if ($next_scheduled) : ?>
                        <p class="description"><strong><?php echo esc_html__('Sljedeća zakazana izrada:', 'sidrena-cijena'); ?></strong> <?php echo esc_html(wp_date('d.m.Y. H:i', $next_scheduled)); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cjenik-delimiter"><?php echo esc_html__('Razdjelnik u CSV datoteci', 'sidrena-cijena'); ?></label>
                </th>
                <td>
                    <select id="cjenik-delimiter" name="<?php echo esc_attr(CJENIK_DELIMITER_OPTION); ?>">
                        <?php foreach (cjenik_delimiter_choices() as $value => $option_label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($delimiter, $value); ?>><?php echo esc_html($option_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Format datoteka', 'sidrena-cijena'); ?></th>
                <td>
                    <label style="display:block;margin-bottom:6px;">
                        <input type="checkbox" name="<?php echo esc_attr(CJENIK_FORMAT_CSV_OPTION); ?>" value="1" <?php checked($format_csv, 'yes'); ?> />
                        <?php echo esc_html__('Generiraj CSV', 'sidrena-cijena'); ?>
                    </label>
                    <label style="display:block;">
                        <input type="checkbox" name="<?php echo esc_attr(CJENIK_FORMAT_XML_OPTION); ?>" value="1" <?php checked($format_xml, 'yes'); ?> />
                        <?php echo esc_html__('Generiraj XML', 'sidrena-cijena'); ?>
                    </label>
                    <p class="description"><?php echo esc_html__('Cjenik možete objaviti u jednom ili u oba navedena formata.', 'sidrena-cijena'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cjenik-retention-days"><?php echo esc_html__('Čuvanje arhive (dana)', 'sidrena-cijena'); ?></label>
                </th>
                <td>
                    <input type="number" id="cjenik-retention-days" name="<?php echo esc_attr(CJENIK_RETENTION_DAYS_OPTION); ?>" value="<?php echo esc_attr($retention_days); ?>" min="<?php echo esc_attr(CJENIK_MIN_RETENTION_DAYS); ?>" max="365" class="small-text" />
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %d: minimum days */
                            esc_html__('Arhivski cjenik mora biti dostupan najmanje %d dana od zamjene. Možete odabrati i dulje razdoblje.', 'sidrena-cijena'),
                            (int) CJENIK_MIN_RETENTION_DAYS
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Obavijest o pogrešci e-poštom', 'sidrena-cijena'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(CJENIK_NOTIFY_ENABLED_OPTION); ?>" value="1" <?php checked($notify_enabled, 'yes'); ?> />
                        <?php echo esc_html__('Pošalji obavijest e-poštom ako izrada cjenika ne uspije', 'sidrena-cijena'); ?>
                    </label>
                    <p>
                        <input type="email" name="<?php echo esc_attr(CJENIK_NOTIFY_EMAIL_OPTION); ?>" value="<?php echo esc_attr($notify_email); ?>" class="regular-text" />
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Spremi promjene', 'sidrena-cijena'), 'primary', 'cjenik_save'); ?>
    </form>

    <hr />

    <h2><?php echo esc_html__('Provjera spremnosti', 'sidrena-cijena'); ?></h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0 16px;">
        <?php
        $readiness_cards = array(
            array(__('Stavki u cjeniku', 'sidrena-cijena'), $readiness['total'], false),
            array(__('Bez maloprodajne cijene', 'sidrena-cijena'), $readiness['missing_price'], true),
            array(__('Bez bazne cijene', 'sidrena-cijena'), $readiness['missing_anchor'], true),
            array(__('Bez šifre', 'sidrena-cijena'), $readiness['missing_sku'], true),
            array(__('Bez robne marke', 'sidrena-cijena'), $readiness['missing_brand'], true),
            array(__('Bez barkoda', 'sidrena-cijena'), $readiness['missing_barcode'], true),
        );
        foreach ($readiness_cards as $card) :
            $card_color = $card[2] && $card[1] > 0 ? '#b32d2e' : '#008a20';
            ?>
            <div style="background:#fff;border:1px solid #dcdcde;padding:12px 14px;min-width:150px;">
                <strong style="font-size:20px;display:block;color:<?php echo esc_attr($card_color); ?>;"><?php echo (int) $card[1]; ?></strong>
                <?php echo esc_html($card[0]); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (!$readiness['settings_ok']) : ?>
        <div class="notice notice-error inline" style="margin:0 0 16px;padding:10px 12px;"><p style="margin:0;"><?php echo esc_html__('Prije objave dopunite identifikacijski broj trgovca, podatke o prodajnom mjestu, broj pohrane i adresu.', 'sidrena-cijena'); ?></p></div>
    <?php elseif ($readiness['missing_price'] || $readiness['missing_anchor'] || $readiness['missing_sku']) : ?>
        <div class="notice notice-warning inline" style="margin:0 0 16px;padding:10px 12px;"><p style="margin:0;"><?php echo esc_html__('Cjenik se ne može objaviti dok nisu unesene maloprodajne i bazne cijene. Šifru, robnu marku i barkod unesite ako su primjenjivi.', 'sidrena-cijena'); ?></p></div>
    <?php else : ?>
        <div class="notice notice-success inline" style="margin:0 0 16px;padding:10px 12px;"><p style="margin:0;"><?php echo esc_html__('Osnovni obvezni podaci su uneseni. Prije objave provjerite njihovu točnost.', 'sidrena-cijena'); ?></p></div>
    <?php endif; ?>

    <hr />

    <h2><?php echo esc_html__('Važeći cjenik', 'sidrena-cijena'); ?></h2>
    <?php if ($last_error) : ?>
        <div class="notice notice-error inline" style="margin:0 0 12px;padding:10px 12px;"><p style="margin:0;"><strong><?php echo esc_html__('Posljednja pogreška:', 'sidrena-cijena'); ?></strong> <?php echo esc_html($last_error); ?></p></div>
    <?php endif; ?>
    <?php if ($last_generated) : ?>
        <p>
            <?php
            printf(
                /* translators: %s: date/time */
                esc_html__('Posljednja izrada: %s', 'sidrena-cijena'),
                esc_html(wp_date('d.m.Y. H:i', $last_generated))
            );
            ?>
        </p>
    <?php else : ?>
        <p><?php echo esc_html__('Cjenik još nije izrađen.', 'sidrena-cijena'); ?></p>
    <?php endif; ?>
    <?php if ($last_generated) : ?><p>
        <?php if ($format_csv === 'yes') : ?>
            <a class="button button-secondary" href="<?php echo esc_url($csv_url); ?>"><?php echo esc_html__('Preuzmi CSV cjenik', 'sidrena-cijena'); ?></a>
        <?php endif; ?>
        <?php if ($format_csv === 'yes' && $format_xml === 'yes') : ?>
            &nbsp;|&nbsp;
        <?php endif; ?>
        <?php if ($format_xml === 'yes') : ?>
            <a href="<?php echo esc_url($xml_url); ?>" target="_blank" rel="noopener"><?php echo esc_html__('XML cjenik', 'sidrena-cijena'); ?></a>
        <?php endif; ?>
    </p><?php endif; ?>
    <p class="description"><?php echo esc_html__('Poveznice moraju biti dostupne bez prijave. Provjerite otvaraju li se u privatnom prozoru preglednika te blokiraju li ih sigurnosni dodatak, vatrozid ili druga zaštita mrežnog mjesta.', 'sidrena-cijena'); ?></p>
    <?php if ($format_csv === 'yes') : ?>
        <p><strong><?php echo esc_html__('Stalna adresa CSV cjenika:', 'sidrena-cijena'); ?></strong> <code><?php echo esc_html($csv_url); ?></code></p>
    <?php endif; ?>
    <?php if ($format_xml === 'yes') : ?>
        <p><strong><?php echo esc_html__('Stalna adresa XML cjenika:', 'sidrena-cijena'); ?></strong> <code><?php echo esc_html($xml_url); ?></code></p>
    <?php endif; ?>
    <h3>Kako postaviti cjenik na mrežno mjesto</h3>
    <p>Kratke kodove možete dodati na stranicu, u objavu ili u widget koji podržava njihovo izvršavanje. Stalne adrese iznad vode izravno na važeće datoteke, bez prikaza arhive.</p>
    <table class="widefat striped">
        <thead><tr><th>Namjena</th><th>Kratki kod</th></tr></thead>
        <tbody>
            <tr><td>Poveznica za preuzimanje u podnožju, bez arhive i datuma</td><td><code>[sidrena_cjenik tekst="Preuzmi cjenik" azurirano="ne"]</code></td></tr>
            <tr><td>XML cjenik</td><td><code>[sidrena_cjenik format="xml" tekst="Preuzmi XML"]</code></td></tr>
            <tr><td>CSV i XML s vlastitim tekstom poveznica</td><td><code>[sidrena_cjenik format="oba" tekst_csv="Preuzmi CSV" tekst_xml="Preuzmi XML"]</code></td></tr>
            <tr><td>Samo arhiva, primjerice na zasebnoj stranici</td><td><code>[sidrena_cjenik_arhiva]</code></td></tr>
            <tr><td>Samo arhiva CSV cjenika</td><td><code>[sidrena_cjenik_arhiva format="csv"]</code></td></tr>
            <tr><td>Preuzimanje i arhiva na istome mjestu</td><td><code>[sidrena_cjenik format="oba" arhiva="da"]</code></td></tr>
            <tr><td>Samo datum i vrijeme posljednje izrade</td><td><code>[sidrena_cjenik_azurirano]</code></td></tr>
        </tbody>
    </table>
    <p>Za zasebnu arhivu izradite javnu stranicu, na nju dodajte kratki kod za arhivu, a poveznicu na tu stranicu postavite u izbornik ili podnožje. Kratki kod za preuzimanje prema zadanim postavkama prikazuje datum ažuriranja, ali ne i arhivu. Datum sakrijte parametrom <code>azurirano="ne"</code>. Arhiva treba ostati javno dostupna. Popis je zadano zatvoren; za početno otvoren prikaz dodajte <code>otvoreno="da"</code> kratkom kodu za arhivu.</p>

    <form method="post" action="<?php echo esc_url($tab_url); ?>">
        <?php wp_nonce_field('cjenik_generate_now_action', 'cjenik_generate_nonce'); ?>
        <?php submit_button(__('Izradi i objavi cjenik', 'sidrena-cijena'), 'secondary', 'cjenik_generate_now'); ?>
    </form>

    <hr />

    <?php
    $preview_total = $readiness['total'];
    $preview_limit = 100;
    $preview_shown = array_slice($preview_rows, 0, $preview_limit);
    ?>
    <h2><?php echo esc_html__('Pregled podataka', 'sidrena-cijena'); ?></h2>
    <p>
        <?php
        if ($preview_total > $preview_limit) {
            printf(
                /* translators: 1: shown count, 2: total count */
                esc_html__('Prikazuje se prvih %1$d od ukupno %2$d proizvoda i varijacija uključenih u cjenik.', 'sidrena-cijena'),
                (int) $preview_limit,
                (int) $preview_total
            );
        } else {
            printf(
                /* translators: %d: total count */
                esc_html__('Broj proizvoda i varijacija uključenih u cjenik: %d.', 'sidrena-cijena'),
                (int) $preview_total
            );
        }
        ?>
    </p>

    <?php if ($preview_total === 0) : ?>
        <p><?php echo esc_html__('Nema proizvoda uključenih u cjenik.', 'sidrena-cijena'); ?></p>
    <?php else : ?>
        <div style="overflow-x:auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <?php foreach (\SidrenaCijenaCjenik\Catalog\CatalogEngine::columns() as $column) : ?>
                        <th><?php echo esc_html(cjenik_column_label($column)); ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($preview_shown as $row) : ?><tr>
                        <?php foreach (\SidrenaCijenaCjenik\Catalog\CatalogEngine::columns() as $column) : ?>
                            <td><?php echo esc_html($row[$column] ?? ''); ?></td>
                        <?php endforeach; ?>
                    </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php
}
