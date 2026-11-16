<?php
/** Publication integration with a temporary uploads directory and option store. */
require __DIR__ . '/compliance.php';

use SidrenaCijenaCjenik\Catalog\CatalogEngine;

function add_option($name, $value, ...$args) {
    if (array_key_exists($name, $GLOBALS['test_options'])) { return false; }
    $GLOBALS['test_options'][$name] = $value;
    return true;
}
function delete_option($name) { unset($GLOBALS['test_options'][$name]); return true; }
function wp_cache_delete(...$args) {}
function maybe_serialize($value) { return is_array($value) ? serialize($value) : $value; }
function get_bloginfo($name) { return 'Testna trgovina'; }

final class CatalogTestDatabase
{
    public $options = 'test_options';
    public function prepare($query, ...$values) { return array($query, $values); }
    public function query($prepared) {
        list($query, $values) = $prepared;
        if (strpos($query, 'DELETE') === 0) {
            list($name, $expected) = $values;
            if (maybe_serialize(get_option($name)) !== $expected) { return 0; }
            delete_option($name);
            return 1;
        }
        list($replacement, $name, $expected) = $values;
        if (maybe_serialize(get_option($name)) !== $expected) { return 0; }
        update_option($name, unserialize($replacement));
        return 1;
    }
}
$wpdb = new CatalogTestDatabase();
$test_upload_dir = sys_get_temp_dir() . '/sidrena-publication-' . uniqid();
mkdir($test_upload_dir);
$directory = cjenik_get_dir()['path'];
$products = array(1 => new CatalogTestProduct(1, 'simple', '8.00'));
$products[1]->meta['_anchor_price'] = '10.00';
$test_options = array(
    'sidrena_cijena_cjenik_trader_id' => '12345678901',
    'woocommerce_store_address' => 'Testna 1',
    'woocommerce_store_city' => 'Zagreb',
    'sidrena_cijena_cjenik_kind' => 'roba',
    CJENIK_DELIMITER_OPTION => ',', // Legacy setting must no longer change CSV format.
);
$test_upload_dir = sys_get_temp_dir() . '/sidrena-publication-' . uniqid();
mkdir($test_upload_dir);
$directory = cjenik_get_dir()['path'];

try {
    $engine = new CatalogEngine();
    $first = $engine->generate();
    check(is_array($first), 'Complete merchant/product data must publish both formats.');
    $firstCsv = file_get_contents($directory . '/' . $first['csv_file']);
    check(strpos($firstCsv, "naziv;sifra_proizvoda;brend;neto_kolicina;") !== false, 'A legacy comma setting must not bypass the prescribed semicolon CSV delimiter.');
    $second = $engine->generate();
    check(is_array($second) && $second['csv_download_name'] !== $first['csv_download_name'], 'Repeated same-minute publication must preserve separate archives.');
    check(is_file($directory . '/' . $first['csv_download_name']), 'Replacing a catalog must retain its archive.');
    check(isset(get_option('sidrena_cijena_cjenik_replaced_at')[ $first['csv_download_name'] ]), 'Replacement time must be recorded for retention.');

    $filesBeforeFailure = get_option(CJENIK_LAST_FILES_OPTION);
    $currentBeforeFailure = file_get_contents($directory . '/' . $second['csv_file']);
    unset($products[1]->meta['_anchor_price']);
    check($engine->generate() === false, 'A product lacking a historical base value must block publication.');
    check(get_option(CJENIK_LAST_FILES_OPTION) === $filesBeforeFailure, 'A failed generation must not replace the published file pointers.');
    check(file_get_contents($directory . '/' . $second['csv_file']) === $currentBeforeFailure, 'A failed generation must preserve the last complete public catalog.');
    check(get_option(CJENIK_GENERATION_LOCK, false) === false, 'A failed generation must release its generation lock.');

    $products[1]->meta['_anchor_price'] = '-1.00';
    check((new CatalogEngine())->generate() === false, 'A negative historical price must not be published.');
    check(file_get_contents($directory . '/' . $second['csv_file']) === $currentBeforeFailure, 'Invalid negative data must not damage the current catalog.');

    $products[1]->meta['_anchor_price'] = '10.00';
    $test_options['sidrena_cijena_cjenik_kind'] = 'usluge';
    $services = $engine->generate();
    check(is_array($services), 'Services must publish using their own schema.');
    $serviceCsv = file_get_contents($directory . '/' . $services['csv_file']);
    check(strpos($serviceCsv, "naziv;maloprodajna_cijena;poseban_oblik_prodaje;bazna_cijena") !== false, 'Service CSV must use the shorter prescribed schema.');
} finally {
    foreach (glob($directory . '/*') as $file) { unlink($file); }
    rmdir($directory);
    rmdir($test_upload_dir);
}

echo "OK: publication, same-minute archives, delimiter, service schema and failed-generation preservation checks passed.\n";
