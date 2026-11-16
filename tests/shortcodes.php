<?php
require __DIR__ . '/admin-flows.php';
function shortcode_atts($defaults, $atts, $tag = '') { return array_merge($defaults, array_intersect_key((array) $atts, $defaults)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }

$test_upload_dir = sys_get_temp_dir() . '/sidrena-shortcodes-' . uniqid();
mkdir($test_upload_dir);
$directory = cjenik_get_dir()['path'];
$test_options = array(CJENIK_LAST_GENERATED_OPTION => time(), CJENIK_LAST_FILES_OPTION => array('csv_file' => 'cjenik_roba_trenutni.csv', 'xml_file' => 'cjenik_roba_trenutni.xml'));
file_put_contents($directory . '/cjenik_test.csv', 'csv');
file_put_contents($directory . '/cjenik_test.xml', 'xml');
try {
    $footer = cjenik_download_shortcode(array('tekst' => 'Preuzmi cjenik'));
    check(strpos($footer, '<details') === false && strpos($footer, 'Preuzmi cjenik') !== false, 'The existing footer shortcode must show its download without an archive.');
    check(strpos($footer, '>Preuzmi cjenik</a>') !== false, 'Existing custom download text must work.');
    check(strpos($footer, '<details') === false, 'Default download must not embed archive in footer.');
    check(strpos($footer, 'Ažurirano:') !== false, 'Existing update timestamp remains enabled by default.');
    check(strpos(cjenik_download_shortcode(array('azurirano' => 'ne')), 'Ažurirano:') === false, 'Footer can hide timestamp.');
    $both = cjenik_download_shortcode(array('format' => 'oba', 'tekst_csv' => 'CSV datoteka', 'tekst_xml' => 'XML datoteka', 'arhiva' => 'da'));
    check(strpos($both, '>CSV datoteka</a>') !== false && strpos($both, '>XML datoteka</a>') !== false, 'Both formats support separate custom link labels.');
    check(substr_count($both, '<details') === 2, 'Explicit inline archive preserves both-format behavior.');
    check(function_exists('cjenik_archive_shortcode'), 'Archive must be available independently on another page.');
    $archive = cjenik_archive_shortcode(array('format' => 'oba'));
    check(substr_count($archive, '<details') === 2 && strpos($archive, 'cjenik_test.csv') !== false && strpos($archive, 'cjenik_test.xml') !== false, 'Standalone archive contains both downloadable formats.');
    check(strpos($archive, 'sidrena-cjenik-button') === false && strpos($archive, 'Ažurirano:') === false, 'Archive-only shortcode must not duplicate current download or timestamp.');
    check(strpos(cjenik_download_shortcode(array('format' => 'invalid')), 'sidrena-cjenik-button') === false, 'Invalid format cannot produce a download link.');
    check(strpos(cjenik_updated_shortcode(array('tekst' => 'Cjenik ažuriran:')), 'Cjenik ažuriran:') !== false, 'The timestamp can be placed independently with custom wording.');
    check(strpos(cjenik_download_shortcode(array('tekst' => '<script>alert(1)</script>')), '<script>') === false, 'Custom link text must be escaped.');
    check(strpos(cjenik_updated_shortcode(array('tekst' => '<script>')), '<script>') === false, 'Custom timestamp text must be escaped.');
    check(substr_count(cjenik_archive_shortcode(array('format' => 'xml')), '<details') === 1, 'An archive page can show XML alone.');
    unlink($directory . '/cjenik_test.csv');
    unlink($directory . '/cjenik_test.xml');
    check(strpos(cjenik_archive_shortcode(array()), 'Arhiva trenutačno nije dostupna.') !== false, 'Empty standalone archive has a clear message.');
} finally {
    foreach (glob($directory . '/*') as $file) { unlink($file); }
    rmdir($directory);
    rmdir($test_upload_dir);
}
echo "OK: independent downloads, archives, timestamps and link labels.\n";
