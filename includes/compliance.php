<?php
/** Data shared by storefront and catalog (NN 132/2026). */
use SidrenaCijenaCjenik\Config;

if (!defined('ABSPATH')) { exit; }

function sidrena_cijena_valid_date($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) { return false; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function sidrena_cijena_base_date($product, $parent = null) {
    $value = $product ? $product->get_meta(Config::BASE_DATE_META_KEY, true) : '';
    if ($value === '' && $parent) { $value = $parent->get_meta(Config::BASE_DATE_META_KEY, true); }
    if (!sidrena_cijena_valid_date($value)) { $value = get_option(Config::BASE_DATE_OPTION, '2026-09-10'); }
    return sidrena_cijena_valid_date($value) ? $value : '2026-09-10';
}

function sidrena_cijena_base_value($product, $parent = null) {
    // A variation is a separately priced item; never invent its price from its parent.
    $value = $product->get_meta(Config::ANCHOR_META_KEY, true);
    $value = $value === '' || $value === null ? '' : wc_format_decimal($value);
    return $value !== '' && is_numeric($value) && (float) $value >= 0 ? $value : '';
}

function sidrena_cijena_base_exempt($product, $parent = null) {
    $value = $product->get_meta('_base_price_exempt', true);
    if ($value === '' && $parent) { $value = $parent->get_meta('_base_price_exempt', true); }
    return $value === 'yes';
}

function sidrena_cijena_label($product = null, $parent = null) {
    $label = get_option(Config::LABEL_OPTION, Config::DEFAULT_LABEL);
    // Migrate only the known previous default. Preserve merchant-written labels.
    if ($label === '' || $label === 'Sidrena cijena na dan 10.09.2026.') { $label = Config::DEFAULT_LABEL; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', sidrena_cijena_base_date($product, $parent));
    return str_replace('{datum}', $date->format('d.m.Y.'), $label);
}

function sidrena_cijena_default_date_label() {
    return (new DateTimeImmutable(sidrena_cijena_base_date(null)))->format('d.m.Y.');
}

function cjenik_gross_price($product, $price) {
    if ($price === '' || $price === null) { return ''; }
    // Use store-base tax rates; exports must not depend on a logged-in customer's exemption/location.
    if (wc_tax_enabled() && $product->is_taxable()) {
        $rates = WC_Tax::get_base_tax_rates($product->get_tax_class('unfiltered'));
        if (!wc_prices_include_tax()) {
            $price = (float) $price + array_sum(WC_Tax::calc_tax((float) $price, $rates, false));
        }
    }
    return wc_format_decimal($price, 2);
}

function cjenik_catalog_kind() {
    return get_option('sidrena_cijena_cjenik_kind', 'roba') === 'usluge' ? 'usluge' : 'roba';
}

function cjenik_valid_trader_id($value) {
    // Legal persons: OIB (11 digits); natural-person merchants: registered identification number.
    return is_string($value) && preg_match('/^[0-9]{1,20}$/D', $value);
}

function cjenik_validate_meta(array $meta) {
    if (empty($meta['trader_id']) || !cjenik_valid_trader_id($meta['trader_id'])) {
        throw new RuntimeException('Unesite OIB pravne osobe ili matični broj trgovca fizičke osobe u postavke cjenika.');
    }
    if (empty($meta['all_locations']) && (empty($meta['object_type']) || empty($meta['address']) || empty($meta['location_label']))) {
        throw new RuntimeException('Dovršite podatke o prodajnom mjestu i adresu trgovine.');
    }
    if (empty($meta['storage_number'])) { throw new RuntimeException('Unesite broj pohrane cjenika.'); }
}

function cjenik_unit_price($gross, $quantity, $unit) {
    if ($gross === '' || !is_numeric($quantity) || (float) $quantity <= 0) { return ''; }
    $factors = array('g' => 0.001, 'kg' => 1, 'ml' => 0.001, 'L' => 1, 'm' => 1, 'm2' => 1, 'm3' => 1, 'kom' => 1);
    if (!isset($factors[$unit])) { return ''; }
    return wc_format_decimal((float) $gross / ((float) $quantity * $factors[$unit]), 2);
}

add_action('woocommerce_product_options_inventory_product_data', 'sidrena_cijena_compliance_fields');
function sidrena_cijena_compliance_fields() {
    woocommerce_wp_text_input(array('id' => Config::BASE_DATE_META_KEY, 'type' => 'date', 'label' => __('Datum bazne cijene', 'sidrena-cijena'), 'description' => __('Prazno = zadani datum trgovine. Za novu ponudu unesite datum prvog nuđenja, ne datum zadnje promjene cijene.', 'sidrena-cijena'), 'desc_tip' => true));
    woocommerce_wp_text_input(array('id' => '_cjenik_net_quantity', 'label' => __('Neto količina u odabranoj jedinici', 'sidrena-cijena'), 'data_type' => 'decimal', 'description' => __('Primjer: 500 uz g, ili 0,5 uz kg. Za komad unesite 1. Ne koristi se transportna težina.', 'sidrena-cijena'), 'desc_tip' => true));
    woocommerce_wp_text_input(array('id' => '_cjenik_ean', 'label' => __('EAN barkod za cjenik', 'sidrena-cijena'), 'description' => __('Zasebno od općeg barkoda. Prazno koristi postojeći EAN/GTIN ako je dostupan.', 'sidrena-cijena'), 'desc_tip' => true));
    woocommerce_wp_text_input(array('id' => '_cjenik_general_barcode', 'label' => __('Opći barkod (ako se razlikuje od EAN-a)', 'sidrena-cijena')));
    woocommerce_wp_checkbox(array('id' => '_cjenik_unit_required', 'label' => __('Obvezna jedinična cijena', 'sidrena-cijena'), 'description' => __('Označite kada je za robu propisana jedinična cijena; neto količina tada je obvezna.', 'sidrena-cijena')));
    woocommerce_wp_checkbox(array('id' => '_base_price_exempt', 'label' => __('Pravno izuzeće proizvoda', 'sidrena-cijena'), 'description' => __('Samo za proizvod koji je izuzet prema čl. 4. pravilnika. Isključuje bazni prikaz i cjenik. Nije za redovnu robu koja samo ima personalizirani popust.', 'sidrena-cijena')));
}

add_action('woocommerce_process_product_meta', 'sidrena_cijena_save_compliance_fields', 20);
function sidrena_cijena_save_compliance_fields($id) {
    if (!current_user_can('edit_post', $id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { return; }
    if (isset($_POST[Config::BASE_DATE_META_KEY])) {
        $date = sanitize_text_field(wp_unslash($_POST[Config::BASE_DATE_META_KEY]));
        if ($date === '' || sidrena_cijena_valid_date($date)) { update_post_meta($id, Config::BASE_DATE_META_KEY, $date); }
    }
    foreach (array('_cjenik_net_quantity', '_cjenik_ean', '_cjenik_general_barcode') as $key) {
        if (isset($_POST[$key])) {
            $value = sanitize_text_field(wp_unslash($_POST[$key]));
            update_post_meta($id, $key, $key === '_cjenik_net_quantity' ? wc_format_decimal($value) : $value);
        }
    }
    // This action belongs to the full product form, not Quick Edit.
    update_post_meta($id, '_cjenik_unit_required', isset($_POST['_cjenik_unit_required']) ? 'yes' : 'no');
    update_post_meta($id, '_base_price_exempt', isset($_POST['_base_price_exempt']) ? 'yes' : 'no');
    if (function_exists('cjenik_queue_refresh')) { cjenik_queue_refresh(); }
}

add_action('woocommerce_product_after_variable_attributes', 'sidrena_cijena_variation_compliance_fields', 20, 3);
function sidrena_cijena_variation_compliance_fields($loop, $data, $variation) {
    foreach (array(Config::BASE_DATE_META_KEY => 'Datum bazne cijene (prazno = roditelj)', '_cjenik_net_quantity' => 'Neto količina (prazno = roditelj)', '_cjenik_ean' => 'EAN barkod', '_cjenik_general_barcode' => 'Opći barkod') as $key => $label) {
        woocommerce_wp_text_input(array('id' => $key . '_' . $loop, 'name' => $key . '[' . $loop . ']', 'type' => $key === Config::BASE_DATE_META_KEY ? 'date' : 'text', 'label' => __($label, 'sidrena-cijena'), 'value' => get_post_meta($variation->ID, $key, true), 'wrapper_class' => 'form-row form-row-full'));
    }
    woocommerce_wp_select(array('id' => '_base_price_exempt_' . $loop, 'name' => '_base_price_exempt[' . $loop . ']', 'label' => __('Pravno izuzeće varijacije', 'sidrena-cijena'), 'value' => get_post_meta($variation->ID, '_base_price_exempt', true), 'options' => array('' => 'Naslijedi roditelja', 'no' => 'Nije izuzeto', 'yes' => 'Izuzeto — bez bazne cijene i cjenika'), 'wrapper_class' => 'form-row form-row-full'));
}

add_action('woocommerce_save_product_variation', 'sidrena_cijena_save_variation_compliance', 20, 2);
function sidrena_cijena_save_variation_compliance($id, $loop) {
    if (!current_user_can('edit_post', $id)) { return; }
    foreach (array(Config::BASE_DATE_META_KEY, '_cjenik_net_quantity', '_cjenik_ean', '_cjenik_general_barcode') as $key) {
        if (!isset($_POST[$key][$loop])) { continue; }
        $value = sanitize_text_field(wp_unslash($_POST[$key][$loop]));
        if ($key === Config::BASE_DATE_META_KEY && $value !== '' && !sidrena_cijena_valid_date($value)) { continue; }
        update_post_meta($id, $key, $key === '_cjenik_net_quantity' ? wc_format_decimal($value) : $value);
    }
    if (isset($_POST['_base_price_exempt'][$loop])) {
        $value = sanitize_text_field(wp_unslash($_POST['_base_price_exempt'][$loop]));
        if (in_array($value, array('', 'yes', 'no'), true)) { update_post_meta($id, '_base_price_exempt', $value); }
    }
    cjenik_queue_refresh();
}

// One generation at the end of a product-changing request, after all metadata
// has been saved. Anonymous downloads never set this flag or generate inline.
add_action('shutdown', 'cjenik_flush_pending_refresh', 20);
function cjenik_flush_pending_refresh() {
    if (!empty($GLOBALS['sidrena_cjenik_request_changed'])) {
        $GLOBALS['sidrena_cjenik_request_changed'] = false;
        cjenik_maybe_generate();
    }
}

add_action('woocommerce_new_product', 'cjenik_queue_refresh', 30, 0);
add_action('woocommerce_new_product_variation', 'cjenik_queue_refresh', 30, 0);
add_action('woocommerce_update_product_variation', 'cjenik_queue_refresh', 30, 0);
add_action('woocommerce_delete_product', 'cjenik_queue_refresh', 30, 0);
add_action('woocommerce_delete_product_variation', 'cjenik_queue_refresh', 30, 0);
add_action('transition_post_status', 'cjenik_product_status_changed', 20, 3);
function cjenik_product_status_changed($new, $old, $post) {
    if ($new !== $old && in_array($post->post_type, array('product', 'product_variation'), true)) { cjenik_queue_refresh(); }
}

add_action('admin_notices', 'sidrena_cijena_compliance_notice');
function sidrena_cijena_compliance_notice() {
    if (!current_user_can('manage_woocommerce')) { return; }
    if (get_option('sidrena_cijena_compliance_reviewed', '') !== '2.0.0') {
        echo '<div class="notice notice-warning"><p>Sidrena Cijena 2.0: provjerite povijesne iznose i datume baznih cijena prema evidenciji. U postavkama Cjenik unesite identifikator trgovca, odaberite robu/usluge i ponovno generirajte cjenik. Ranije automatski uneseni iznosi nisu potvrda povijesne cijene.</p></div>';
    }
}
