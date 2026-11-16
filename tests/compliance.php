<?php
/** Regulatory regressions use real row builders/writers with in-memory WP data. */
require __DIR__ . '/catalog-rows.php';

use SidrenaCijenaCjenik\Catalog\CatalogEngine;

function plugin_dir_path($file) { return dirname($file) . '/'; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_kses_post($value) { return $value; }
function sanitize_hex_color($value) { return preg_match('/^#[0-9a-f]{6}$/i', $value) ? $value : null; }
function wc_price($value) { return number_format((float) $value, 2, '.', '') . ' EUR'; }
require_once dirname(__DIR__) . '/sidrena-cijena.php';

check(function_exists('sidrena_cijena_base_date'), 'Base dates must be resolved per product.');
check(function_exists('sidrena_cijena_base_value'), 'Saved base values must have an explicit resolver.');
check(sidrena_cijena_valid_date('2024-02-29'), 'Leap days must be accepted in leap years.');
check(!sidrena_cijena_valid_date('2026-02-29') && !sidrena_cijena_valid_date('2026-13-01'), 'Invalid calendar dates must not become reference dates.');

$item = new CatalogTestProduct(301, 'simple', '8.00');
$parent = new CatalogTestProduct(302, 'variable', '');
$test_options['sidrena_cijena_base_date'] = '2026-09-10';
check(sidrena_cijena_base_date($item) === '2026-09-10', 'Default reference date must be stable, not today.');
$test_options['sidrena_cijena_base_date'] = '2025-05-02';
check(sidrena_cijena_base_date($item) === '2025-05-02', 'The earlier regulated product group needs its own default.');
check(sidrena_cijena_default_date_label() === '02.05.2025.', 'Admin reference-date captions must use the selected store default.');
$item->meta['_base_price_date'] = '2026-11-15';
check(sidrena_cijena_base_date($item) === '2026-11-15', 'A new product must retain its first-offer date.');
check(sidrena_cijena_label($item) === 'Bazna cijena na dan 15.11.2026.', 'The label must resolve the product date rather than show a global literal date.');
$test_options['sidrena_cijena_label_text'] = 'Referentna cijena ({datum})';
check(sidrena_cijena_label($item) === 'Referentna cijena (15.11.2026.)', 'Merchant-defined label wording must survive the upgrade.');
$test_options['sidrena_cijena_label_text'] = 'Sidrena cijena na dan 10.09.2026.';
check(sidrena_cijena_label($item) === 'Bazna cijena na dan 15.11.2026.', 'Only the former stock label must migrate to the new dated template.');
unset($test_options['sidrena_cijena_label_text']);
$parent->meta['_base_price_date'] = '2025-05-02';
check(sidrena_cijena_base_date($item, $parent) === '2026-11-15', 'A variation date must override its parent date.');
unset($item->meta['_base_price_date']);
check(sidrena_cijena_base_date($item, $parent) === '2025-05-02', 'A variation can inherit the parent reference date.');

check(sidrena_cijena_base_value($item) === '', 'Missing historical data must not become the current price.');
check(sidrena_cijena_append_to_price_html('8.00 EUR', $item) === '8.00 EUR', 'The storefront must not label the current price as a missing historical price.');
check(sidrena_cijena_initialize_product_anchor(301) === 0 && $item->get_meta('_anchor_price') === '', 'Automatic initialization must not invent historical prices.');
$parent->meta['_anchor_price'] = '99.00';
check(sidrena_cijena_base_value($item, $parent) === '', 'A separately priced variation must not inherit an unrelated parent price.');
$item->meta['_anchor_price'] = '0';
check(sidrena_cijena_base_value($item) !== '' && (float) sidrena_cijena_base_value($item) === 0.0, 'A saved zero base price is data, not a missing value.');
check(strpos(sidrena_cijena_append_to_price_html('8.00 EUR', $item), '0.00 EUR') !== false, 'A saved zero base price must be displayed.');
$item->meta['_anchor_price'] = '12.00';
$row = cjenik_build_row($item);
check($row['bazna_cijena'] === '12.00', 'The saved historical price must be exported.');
unset($item->meta['_anchor_price']);
check(cjenik_build_row($item)['bazna_cijena'] === '', 'Exports must leave an unknown historical price empty.');
check(function_exists('sidrena_cijena_base_exempt'), 'Applicable legal exemptions must have an explicit per-product setting.');
$exempt = new CatalogTestProduct(303, 'simple', '8.00', array(), 'include');
$exempt->meta['_anchor_price'] = '12.00';
$exempt->meta['_base_price_exempt'] = 'yes';
check(sidrena_cijena_base_exempt($exempt), 'A product marked legally exempt must resolve as exempt.');
check(!cjenik_product_included($exempt), 'Legal exemption must take precedence over an explicit include override.');
check(sidrena_cijena_append_to_price_html('8.00 EUR', $exempt) === '8.00 EUR', 'Exempt products must not show a base-price line.');
$child = new CatalogTestProduct(304, 'variation', '8.00');
$child->meta['_anchor_price'] = '11.00';
check(sidrena_cijena_base_exempt($child, $exempt), 'A blank variation exemption must inherit its parent.');
$exemptVariation = sidrena_cijena_variation_price_html(array('price_html' => '8.00 EUR'), $exempt, $child);
check($exemptVariation['price_html'] === '8.00 EUR', 'An inherited exempt variation must not show a base-price line.');
$child->meta['_base_price_exempt'] = 'no';
check(!sidrena_cijena_base_exempt($child, $exempt), 'A variation may explicitly opt out of inherited exemption.');
$previousProducts = $products;
$products = array(
    305 => new CatalogTestProduct(305, 'variable', '', array(306, 307, 308)),
    306 => new CatalogTestProduct(306, 'variation', '8.00'),
    307 => new CatalogTestProduct(307, 'variation', '9.00'),
    308 => new CatalogTestProduct(308, 'variation', '10.00', array(), 'exclude'),
);
$products[305]->meta['_base_price_exempt'] = 'yes';
$products[306]->meta['_base_price_exempt'] = 'no';
$products[308]->meta['_base_price_exempt'] = 'no';
$variationIds = array_map('intval', array_column((new CatalogEngine())->collect(), 'sifra_proizvoda'));
check($variationIds === array(306), 'Export must include an explicit nonexempt child, omit its exempt sibling, and preserve a child-specific catalog exclusion.');
$products[305]->meta[CJENIK_VISIBILITY_META_KEY] = 'exclude';
check((new CatalogEngine())->collect() === array(), 'A parent catalog exclusion must still exclude its complete variation family.');
$products = $previousProducts;
$exempt->meta['_base_price_exempt'] = 'yes';
check(sidrena_cijena_base_exempt($exempt), 'A product can be explicitly marked legally exempt.');
check(!cjenik_product_included($exempt), 'An exemption must take priority over manual catalog inclusion.');
check(sidrena_cijena_append_to_price_html('8.00 EUR', $exempt) === '8.00 EUR', 'An exempt product must not show a base-price label.');
$exemptVariation = new CatalogTestProduct(304, 'variation', '8.00');
$exemptVariation->meta['_anchor_price'] = '10.00';
check(sidrena_cijena_base_exempt($exemptVariation, $exempt), 'Variations can inherit a parent exemption.');
$variationData = sidrena_cijena_variation_price_html(array('price_html' => '8.00 EUR'), $exempt, $exemptVariation);
check($variationData['price_html'] === '8.00 EUR', 'An inherited exemption must suppress the selected variation base label.');
$exemptVariation->meta['_base_price_exempt'] = 'no';
check(!sidrena_cijena_base_exempt($exemptVariation, $exempt), 'A variation can explicitly opt out of its parent exemption.');

$test_taxes_enabled = true;
$item->meta['_anchor_price'] = '12.00';
$item->meta['_cjenik_unit'] = 'ml';
$item->meta['_cjenik_net_quantity'] = '500';
$item->meta['_cjenik_ean'] = '0123456789012';
$row = cjenik_build_row($item);
check($row['maloprodajna_cijena'] === '10.00', 'Export must use gross retail price, not the tax-exclusive database value.');
check($row['bazna_cijena'] === '15.00', 'The historical price uses the same tax input basis and must also be exported gross.');
check($row['cijena_po_jedinici'] === '20.00', '500 ml at 10 EUR must give 20 EUR per litre.');
check($row['jedinica_mjere'] === 'ml', 'The exported unit must match the entered net quantity.');
check((float) $row['neto_kolicina'] === 500.0, 'The entered net quantity must be preserved.');
check($row['ean'] === '0123456789012', 'EAN must retain its leading zero and remain independent of other barcodes.');
$item->meta['_cjenik_unit'] = 'g';
$item->meta['_cjenik_net_quantity'] = '250';
$row = cjenik_build_row($item);
check($row['cijena_po_jedinici'] === '40.00' && $row['jedinica_mjere'] === 'g', '250 g at 10 EUR must give 40 EUR per kg while retaining the entered quantity unit.');
$item->meta['_cjenik_net_quantity'] = '';
check(cjenik_build_row($item)['cijena_po_jedinici'] === '', 'Missing quantity must not silently substitute a package price as a unit price.');
$test_prices_include_tax = true;
check(cjenik_build_row($item)['maloprodajna_cijena'] === '8.00', 'Gross stored prices must not be taxed twice.');
$test_taxes_enabled = false;
$test_prices_include_tax = false;
check(cjenik_gross_price($item, '') === '', 'Missing prices must not be converted to zero.');

$expectedGoods = array('naziv', 'sifra_proizvoda', 'brend', 'neto_kolicina', 'jedinica_mjere', 'maloprodajna_cijena', 'poseban_oblik_prodaje', 'cijena_po_jedinici', 'barkod', 'ean', 'bazna_cijena');
$barcodeItem = new CatalogTestProduct(309);
$barcodeItem->meta['_barcode'] = 'ABC';
$barcodeRow = cjenik_build_row($barcodeItem);
check($barcodeRow['barkod'] === 'ABC' && $barcodeRow['ean'] === '', 'A generic legacy barcode must not masquerade as an EAN.');
$barcodeItem->meta['_ean'] = '0012345678901';
$barcodeRow = cjenik_build_row($barcodeItem);
check($barcodeRow['barkod'] === 'ABC' && $barcodeRow['ean'] === '0012345678901', 'Legacy generic and EAN barcodes must remain separate and preserve leading zeroes.');
check(CatalogEngine::columns() === $expectedGoods, 'Goods exports must follow the prescribed column order without legacy extra columns.');
$test_options['sidrena_cijena_cjenik_kind'] = 'usluge';
check(CatalogEngine::columns() === array('naziv', 'maloprodajna_cijena', 'poseban_oblik_prodaje', 'bazna_cijena'), 'Services need the prescribed separate schema.');
$test_options['sidrena_cijena_cjenik_kind'] = 'roba';

$meta = array(
    'trader_id' => '12345678901', 'object_type' => 'Internet trgovina',
    'address' => 'Testna 1 Zagreb', 'location_label' => 'Web',
    'storage_number' => '7', 'sequence' => 1, 'kind' => 'roba', 'all_locations' => false,
    'generated_at' => '2026-11-16T10:30:00+01:00',
);
cjenik_validate_meta($meta);
foreach (array('trader_id', 'object_type', 'address', 'location_label', 'storage_number') as $required) {
    $invalid = $meta;
    $invalid[$required] = '';
    $rejected = false;
    try { cjenik_validate_meta($invalid); } catch (RuntimeException $error) { $rejected = true; }
    check($rejected, 'Publication must reject missing merchant/location metadata: ' . $required);
}
$filename = cjenik_build_filename('csv', $meta, true);
check(strpos($filename, '12345678901') !== false && strpos($filename, 'roba') !== false, 'The download filename must identify merchant and goods/services.');
check(substr($filename, -17) === '20261116_1030.csv', 'The filename must end in the prescribed local publication timestamp.');
$nextPublication = $meta;
$nextPublication['sequence'] = 2;
check(cjenik_build_filename('csv', $nextPublication, true) !== $filename, 'Two publications in the same minute must have different storage numbers.');
check(pathinfo(cjenik_build_filename('xml', $meta, true), PATHINFO_FILENAME) === pathinfo($filename, PATHINFO_FILENAME), 'CSV and XML from the same publication must share identity and sequence.');
$allLocations = $meta;
$allLocations['all_locations'] = true;
$allLocations['object_type'] = $allLocations['address'] = $allLocations['location_label'] = '';
cjenik_validate_meta($allLocations);
check(strpos(cjenik_build_filename('xml', $allLocations, true), 'ZA_SVA_PRODAJNA_MJESTA') !== false, 'A shared price list must use the all-locations designation.');

// An old list can remain current for months: retention starts when replaced,
// rather than when the file was first generated.
$test_upload_dir = sys_get_temp_dir() . '/sidrena-retention-' . uniqid();
mkdir($test_upload_dir);
$directory = cjenik_get_dir()['path'];
$recentReplacement = $directory . '/cjenik_recent.csv';
$expiredReplacement = $directory . '/cjenik_expired.csv';
$currentArchive = $directory . '/cjenik_current.csv';
foreach (array($recentReplacement, $expiredReplacement, $currentArchive) as $file) {
    file_put_contents($file, 'fixture');
    touch($file, time() - 100 * DAY_IN_SECONDS);
}
$test_options['sidrena_cijena_cjenik_replaced_at'] = array(
    basename($recentReplacement) => time() - 14 * DAY_IN_SECONDS,
    basename($expiredReplacement) => time() - 40 * DAY_IN_SECONDS,
);
$test_options[CJENIK_RETENTION_DAYS_OPTION] = 15;
$test_options[CJENIK_LAST_FILES_OPTION] = array('csv_file' => basename($currentArchive), 'csv_download_name' => basename($currentArchive));
try {
    cjenik_cleanup_old_files();
    check(is_file($recentReplacement), 'A recently replaced old catalog must survive its full retention window.');
    check(!is_file($expiredReplacement), 'An expired replaced catalog may be removed.');
    check(is_file($currentArchive), 'The current catalog must never expire based on creation time.');
} finally {
    foreach (array($recentReplacement, $expiredReplacement, $currentArchive) as $file) {
        if (is_file($file)) { unlink($file); }
    }
    rmdir($directory);
    rmdir($test_upload_dir);
}

echo "OK: base dates, historical values, gross prices, quantities and export schema checks passed.\n";
