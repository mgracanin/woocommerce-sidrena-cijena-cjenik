<?php
/** Exercise product form saves and end-of-request publication without a browser. */
require __DIR__ . '/publication.php';

use SidrenaCijenaCjenik\Config;

function current_user_can(...$args) { return $GLOBALS['test_can_edit'] ?? true; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return stripslashes($value); }
function update_post_meta($id, $key, $value) { $GLOBALS['products'][$id]->meta[$key] = $value; }
function delete_post_meta($id, $key) { unset($GLOBALS['products'][$id]->meta[$key]); }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['products'][$id]->get_meta($key, $single); }
function wp_next_scheduled($hook) { return $GLOBALS['test_scheduled'][$hook] ?? false; }
function wp_schedule_single_event($time, $hook) { $GLOBALS['test_scheduled'][$hook] = $time; return true; }
function woocommerce_wp_text_input($field) { $GLOBALS['test_rendered_fields'][$field['id']] = $field; }
function woocommerce_wp_checkbox($field) { $GLOBALS['test_rendered_fields'][$field['id']] = $field; }
function woocommerce_wp_select($field) { $GLOBALS['test_rendered_fields'][$field['id']] = $field; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return $value; }
function esc_html__($value, $domain) { return esc_html($value); }
function esc_attr__($value, $domain) { return esc_attr($value); }
function wp_kses($value, $allowed) { return $value; }
function wp_nonce_field(...$args) {}
function submit_button(...$args) {}
function checked($value, $expected = true, $echo = true) { $result = $value == $expected ? 'checked' : ''; if ($echo) { echo $result; } return $result; }
function selected($value, $expected = true, $echo = true) { $result = $value == $expected ? 'selected' : ''; if ($echo) { echo $result; } return $result; }
function home_url($path = '') { return 'https://example.test' . $path; }
function add_query_arg($name, $value, $url) { return $url . '?' . urlencode($name) . '=' . urlencode($value); }

$products = array(401 => new CatalogTestProduct(401, 'simple', '8.00'), 402 => new CatalogTestProduct(402, 'variation', '8.00'));
$products[401]->meta['_anchor_price'] = '10.00';
$_POST = array('_anchor_price' => '');
sidrena_cijena_quick_edit_save($products[401]);
check($products[401]->get_meta('_anchor_price') === '', 'Quick Edit must clear the historical value instead of copying the current price.');
$_POST = array('_anchor_price' => '0');
sidrena_cijena_quick_edit_save($products[401]);
check($products[401]->get_meta('_anchor_price') !== '' && (float) $products[401]->get_meta('_anchor_price') === 0.0, 'Quick Edit must preserve an explicit zero.');
$test_can_edit = false;
$_POST = array('_anchor_price' => '99');
sidrena_cijena_quick_edit_save($products[401]);
check((float) $products[401]->get_meta('_anchor_price') === 0.0, 'Unauthorized saves must not modify historical values.');
$test_can_edit = true;

$products[402]->meta['_anchor_price'] = '12.00';
$products[401]->meta['_base_price_date'] = '2025-05-02';
$variationData = sidrena_cijena_variation_price_html(array('price_html' => ''), $products[401], $products[402]);
check(strpos($variationData['price_html'], '12.00 EUR') !== false && strpos($variationData['price_html'], '02.05.2025.') !== false, 'An equal-price variation with empty WooCommerce price HTML must still show its own base and inherited reference date.');
check(substr_count($variationData['price_html'], 'class="sidrena-cijena"') === 1, 'Variation HTML must contain one base-price label.');
$variationData = sidrena_cijena_variation_price_html($variationData, $products[401], $products[402]);
check(substr_count($variationData['price_html'], 'class="sidrena-cijena"') === 1, 'Filtering variation HTML again must not duplicate the base price.');

$previousProducts = $products;
$products = array(
    500 => new CatalogTestProduct(500, 'variable', '', array(501, 502, 503, 504, 505)),
    501 => new CatalogTestProduct(501, 'variation', '8.00'),
    502 => new CatalogTestProduct(502, 'variation', '9.00'),
    503 => new CatalogTestProduct(503, 'variation', '7.00'),
    504 => new CatalogTestProduct(504, 'variation', '6.00'),
    505 => new CatalogTestProduct(505, 'variation', '5.00', array(), '', 'visible', 'draft'),
);
$products[500]->meta['_anchor_price'] = '999.00'; // Never substitute a parent's value.
$products[500]->meta['_base_price_date'] = '2025-05-02';
$products[501]->meta['_anchor_price'] = '11.00';
$products[502]->meta['_anchor_price'] = '12.00';
$products[502]->meta['_base_price_date'] = '2026-11-15';
$products[504]->meta['_anchor_price'] = '15.00';
$products[504]->meta['_base_price_exempt'] = 'yes';
$products[505]->meta['_anchor_price'] = '16.00';
$parentHtml = sidrena_cijena_append_to_price_html('8.00 - 9.00 EUR', $products[500]);
check(strpos($parentHtml, 'Product 501 — Bazna cijena na dan 02.05.2025.: 11.00 EUR') !== false, 'Before selection, each differing variation must identify its own inherited date and base price.');
check(strpos($parentHtml, 'Product 502 — Bazna cijena na dan 15.11.2026.: 12.00 EUR') !== false, 'Before selection, a variation-specific date must not be replaced by the parent date.');
check(substr_count($parentHtml, 'class="sidrena-cijena"') === 2, 'Missing, exempt and unpublished children must not create parent base-price lines.');
check(strpos($parentHtml, '999.00') === false && strpos($parentHtml, 'Product 503') === false && strpos($parentHtml, 'Product 504') === false && strpos($parentHtml, 'Product 505') === false, 'Unknown prices must not be invented and exempt/unpublished children must be skipped.');
$products[500] = new CatalogTestProduct(500, 'variable', '', array(501, 502));
$products[501]->meta['_base_price_date'] = $products[502]->meta['_base_price_date'] = '2026-09-10';
$products[501]->meta['_anchor_price'] = $products[502]->meta['_anchor_price'] = '12.00';
$parentHtml = sidrena_cijena_append_to_price_html('8.00 - 9.00 EUR', $products[500]);
check(substr_count($parentHtml, 'class="sidrena-cijena"') === 1 && strpos($parentHtml, '12.00 EUR') !== false, 'Complete identical variation bases and dates may collapse to one clear line.');
unset($products[501]->meta['_anchor_price'], $products[502]->meta['_anchor_price']);
check(sidrena_cijena_append_to_price_html('8.00 - 9.00 EUR', $products[500]) === '8.00 - 9.00 EUR', 'A variable product with no known child bases must not manufacture a base from current prices.');
$products = $previousProducts;

$test_rendered_fields = array();
$test_options['sidrena_cijena_base_date'] = '2025-05-02';
sidrena_cijena_add_field();
check(strpos($test_rendered_fields['_anchor_price']['label'], '02.05.2025.') !== false, 'The product price-field caption must reflect the selected reference date.');
sidrena_cijena_compliance_fields();
foreach (array('_base_price_date', '_base_price_exempt', '_cjenik_net_quantity', '_cjenik_ean', '_cjenik_general_barcode', '_cjenik_unit_required') as $field) {
    check(isset($test_rendered_fields[$field]), 'Product inventory must expose the compliance field: ' . $field);
}
check($test_rendered_fields['_base_price_date']['type'] === 'date', 'The reference date must have a date input.');
$test_rendered_fields = array();
sidrena_cijena_variation_compliance_fields(0, array(), (object) array('ID' => 402));
check($test_rendered_fields['_base_price_date_0']['value'] === '', 'A blank variation date must remain blank for inheritance.');
check(isset($test_rendered_fields['_base_price_exempt_0']['options']['no']), 'Variations must expose an explicit override for inherited legal exemption.');

$_POST = array('_base_price_date' => '2026-02-30', '_cjenik_ean' => '0012345678901', '_cjenik_net_quantity' => '500', '_cjenik_unit_required' => 'yes');
sidrena_cijena_save_compliance_fields(401);
check($products[401]->get_meta('_base_price_date') === '2025-05-02', 'An invalid submitted date must not overwrite the saved date.');
check($products[401]->get_meta('_cjenik_ean') === '0012345678901', 'Form saving must preserve EAN leading zeroes.');
check($products[401]->get_meta('_cjenik_unit_required') === 'yes', 'The required-unit-price selection must save.');
$_POST = array('_base_price_date' => array(0 => ''), '_cjenik_net_quantity' => array(0 => ''), '_cjenik_ean' => array(0 => '0099999999999'));
sidrena_cijena_save_variation_compliance(402, 0);
check($products[402]->get_meta('_base_price_date') === '', 'Clearing a variation date must restore parent inheritance.');
check($products[402]->get_meta('_cjenik_ean') === '0099999999999', 'A variation EAN must save independently.');
$_POST = array('_base_price_exempt' => array(0 => 'yes'));
sidrena_cijena_save_variation_compliance(402, 0);
check($products[402]->get_meta('_base_price_exempt') === 'yes', 'A variation-specific exemption must save.');
$_POST = array('_base_price_exempt' => array(0 => 'invalid'));
sidrena_cijena_save_variation_compliance(402, 0);
check($products[402]->get_meta('_base_price_exempt') === 'yes', 'Invalid variation exemption values must not overwrite the saved selection.');
$products[402]->meta['_base_price_exempt'] = 'no';
$products[401]->meta['_cjenik_unit'] = 'g';
check((float) cjenik_build_row($products[402], $products[401])['neto_kolicina'] === 500.0, 'A cleared variation quantity must inherit its parent quantity rather than becoming zero.');

$test_upload_dir = sys_get_temp_dir() . '/sidrena-admin-' . uniqid();
mkdir($test_upload_dir);
$directory = cjenik_get_dir()['path'];
$products = array(401 => new CatalogTestProduct(401, 'simple', '8.00'));
$products[401]->meta['_anchor_price'] = '10.00';
$test_options = array('sidrena_cijena_cjenik_trader_id' => '12345678901', 'woocommerce_store_address' => 'Testna 1', 'woocommerce_store_city' => 'Zagreb');
$GLOBALS['sidrena_cjenik_request_changed'] = false;
try {
    cjenik_flush_pending_refresh();
    check(get_option('sidrena_cijena_cjenik_sequence', 0) === 0, 'A read-only request must not publish inline.');
    cjenik_queue_refresh();
    cjenik_queue_refresh();
    check(!empty($GLOBALS['sidrena_cjenik_request_changed']), 'Product updates must mark the request for deferred publication.');
    $products[401]->meta['_anchor_price'] = '13.00'; // Last form save in this request.
    cjenik_flush_pending_refresh();
    check(empty($GLOBALS['sidrena_cjenik_request_changed']), 'The publication flag must clear after flushing.');
    $sequence = get_option('sidrena_cijena_cjenik_sequence');
    $lastFiles = get_option(CJENIK_LAST_FILES_OPTION);
    check(is_array($lastFiles) && strpos(file_get_contents($directory . '/' . $lastFiles['csv_file']), '13.00') !== false, 'End-of-request publication must include the last metadata save.');
    cjenik_flush_pending_refresh();
    check(get_option('sidrena_cijena_cjenik_sequence') === $sequence, 'Repeated shutdown callbacks must not publish twice.');
    $_POST = array();
    set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
    try {
        foreach (array('roba', 'usluge') as $kind) {
            $test_options['sidrena_cijena_cjenik_kind'] = $kind;
            ob_start();
            try { cjenik_render_tab_content(); } finally { $settingsHtml = ob_get_clean(); }
            check(strpos($settingsHtml, '<th>bazna_cijena</th>') !== false, 'The admin preview must render the current base-price schema.');
            check(strpos($settingsHtml, '<th>naziv_posebnog_oblika_prodaje</th>') === false, 'The admin preview must not reference removed legacy schema columns.');
        }
    } finally {
        restore_error_handler();
    }
} finally {
    $_POST = array();
    foreach (glob($directory . '/*') as $file) { unlink($file); }
    rmdir($directory);
    rmdir($test_upload_dir);
}

echo "OK: product forms, variation display, permissions and shutdown publication checks passed.\n";
