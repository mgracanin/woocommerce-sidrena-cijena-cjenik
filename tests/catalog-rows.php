<?php
/** Regression test with in-memory WooCommerce data and the real catalog/writers. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);

class WooCommerce {}
class WC_Tax {
    public static function get_base_tax_rates($class = '') { return array('rate' => 0.25); }
    public static function calc_tax($price, $rates, $inclusive = false) {
        return array((float) $price * $rates['rate']);
    }
}
function wc_tax_enabled() { return !empty($GLOBALS['test_taxes_enabled']); }
function wc_prices_include_tax() { return !empty($GLOBALS['test_prices_include_tax']); }
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode(...$args) {}
function register_activation_hook(...$args) {}
function register_deactivation_hook(...$args) {}
function apply_filters($hook, $value, ...$args) { return $value; }
function __($text, $domain) { return $text; }
function is_wp_error($value) { return false; }
function taxonomy_exists($name) { return false; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function get_option($name, $default = false) { return $GLOBALS['test_options'][$name] ?? $default; }
function update_option($name, $value, ...$args) { $GLOBALS['test_options'][$name] = $value; return true; }
function wc_format_decimal($value, $decimals = 2) { return $value === '' ? '' : number_format((float) $value, $decimals, '.', ''); }
function wc_get_price_including_tax($product, $args = array()) {
    $price = isset($args['price']) ? $args['price'] : $product->get_price();
    return (float) $price * ($GLOBALS['test_tax_multiplier'] ?? 1);
}
function wp_date($format, $timestamp = null) { return gmdate($format, $timestamp ?? 1794821400); }
function sanitize_title($value) { return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value)); }
function trailingslashit($value) { return rtrim($value, '/\\') . '/'; }
function wp_upload_dir() { return array('basedir' => $GLOBALS['test_upload_dir'], 'baseurl' => 'https://example.test/uploads'); }
function wp_mkdir_p($path) { return mkdir($path, 0777, true); }
function wc_get_formatted_variation($product, ...$args) { return $product->get_name(); }
function wc_get_product($id) { return $GLOBALS['products'][$id] ?? false; }
function wc_get_products($args) {
    $ids = array_keys(array_filter($GLOBALS['products'], function ($product) {
        return !$product->is_type('variation') && $product->get_status() === 'publish';
    }));
    sort($ids);
    return array_slice($ids, ($args['page'] - 1) * $args['limit'], $args['limit']);
}

final class CatalogTestProduct
{
    private $id;
    private $type;
    private $price;
    private $children;
    private $override;
    private $visibility;
    private $status;
    public $meta = array();

    public function __construct($id, $type = 'simple', $price = '4.50', $children = array(), $override = '', $visibility = 'visible', $status = 'publish') {
        $this->id = $id;
        $this->type = $type;
        $this->price = $price;
        $this->children = $children;
        $this->override = $override;
        $this->visibility = $visibility;
        $this->status = $status;
    }
    public function get_id() { return $this->id; }
    public function is_type($type) { return $this->type === $type; }
    public function get_children() { return $this->children; }
    public function get_status() { return $this->status; }
    public function get_catalog_visibility() { return $this->visibility; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ($key === CJENIK_VISIBILITY_META_KEY ? $this->override : ''); }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function get_parent_id() { return 0; }
    public function get_regular_price($context = 'view') { return $this->price; }
    public function get_name() { return 'Product ' . $this->id; }
    public function get_sku() { return (string) $this->id; }
    public function get_price() { return $this->price; }
    public function get_price_html() { return $this->price . ' EUR'; }
    public function is_on_sale() { return false; }
    public function is_in_stock() { return true; }
    public function is_taxable() { return true; }
    public function get_tax_class($context = 'view') { return ''; }
}

require_once dirname(__DIR__) . '/src/Config.php';
require_once dirname(__DIR__) . '/includes/cjenik.php';

use SidrenaCijenaCjenik\Catalog\CatalogEngine;
use SidrenaCijenaCjenik\Catalog\CsvStreamWriter;
use SidrenaCijenaCjenik\Catalog\XmlStreamWriter;

function check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Two groups share children; one explicitly requests inclusion. Neither parent
// is a saleable item. Children on the next query page must appear only once.
$products = array();
for ($id = 1; $id <= 102; $id++) {
    $products[$id] = new CatalogTestProduct($id);
}
$products[1] = new CatalogTestProduct(1, 'grouped', '', array(101, 102), 'include');
$products[100] = new CatalogTestProduct(100, 'grouped', '', array(101, 102));
$products[2] = new CatalogTestProduct(2, 'simple', '0');
$products[3] = new CatalogTestProduct(3, 'simple', '4.50', array(), 'exclude');
$products[4] = new CatalogTestProduct(4, 'simple', '4.50', array(), '', 'hidden');
$products[5] = new CatalogTestProduct(5, 'simple', '4.50', array(), 'include', 'hidden');
$products[6] = new CatalogTestProduct(6, 'variable', '', array(201, 202));
$products[101] = new CatalogTestProduct(101, 'simple', '15.95');
$products[201] = new CatalogTestProduct(201, 'variation', '9.99');
$products[202] = new CatalogTestProduct(202, 'variation', '8.99', array(), '', 'visible', 'draft');

$rows = (new CatalogEngine())->collect();
$expectedIds = array_values(array_diff(range(1, 102), array(1, 3, 4, 6, 100)));
array_splice($expectedIds, array_search(5, $expectedIds, true) + 1, 0, array(201));
$actualIds = array_map('intval', array_column($rows, 'sifra_proizvoda'));
check($actualIds === $expectedIds, 'Catalog must omit grouped/variable parents, preserve children once across pages, and respect product exclusions. Actual IDs: ' . implode(',', $actualIds));
check($rows[0]['maloprodajna_cijena'] === '0.00', 'A genuine zero-priced simple product must remain in the catalog.');
check($rows[array_search(101, $actualIds, true)]['maloprodajna_cijena'] === '15.95', 'Grouped child price must remain unchanged.');

$csvPath = tempnam(sys_get_temp_dir(), 'catalog-csv-');
$xmlPath = tempnam(sys_get_temp_dir(), 'catalog-xml-');
try {
    $csv = new CsvStreamWriter($csvPath, ';', CatalogEngine::columns(), CatalogEngine::textColumns());
    $xml = new XmlStreamWriter($xmlPath, CatalogEngine::columns(), array(
        'generated_at' => '2026-11-13', 'object_type' => 'Test',
        'location_label' => 'Test', 'address' => 'Test', 'storage_number' => '01',
    ));
    foreach ($rows as $row) {
        $csv->writeRow($row);
        $xml->writeRow($row);
    }
    $csv->close();
    $xml->close();

    $handle = fopen($csvPath, 'rb');
    try {
        fread($handle, 3); // UTF-8 BOM
        $headers = fgetcsv($handle, 0, ';', '"', '');
        $csvIds = array();
        while (($values = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $csvIds[] = (int) array_combine($headers, $values)['sifra_proizvoda'];
        }
    } finally {
        fclose($handle);
    }
    $document = simplexml_load_file($xmlPath);
    check($document !== false, 'Export must be valid XML.');
    $xmlIds = array();
    foreach ($document->proizvod as $item) {
        $xmlIds[] = (int) $item->sifra_proizvoda;
    }
    check($csvIds === $expectedIds, 'CSV must contain exactly the expected saleable products.');
    check($xmlIds === $expectedIds, 'XML must contain exactly the expected saleable products.');
} finally {
    if (is_file($csvPath)) { unlink($csvPath); }
    if (is_file($xmlPath)) { unlink($xmlPath); }
}

echo "OK: catalog selection and CSV/XML regression checks passed.\n";
