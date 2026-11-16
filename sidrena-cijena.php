<?php
/**
 * Plugin Name: Sidrena Cijena i Cjenik | Matija Gračanin
 * Description: Prikaz sidrene cijene i javni strojno čitljivi cjenik za WooCommerce prema pravilnicima NN 132/2026.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.4
 * Author: Matija Gračanin
 * Author URI: mailto:matijag@gmail.com
 * Author Email: matijag@gmail.com
 * Text Domain: sidrena-cijena
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
 * Portions of the original GPLv2-or-later implementation were published by
 * Aplitap digital. Later architecture and features are by Matija Gračanin.
 * See NOTICE.md for provenance and modification details.
 */

use SidrenaCijenaCjenik\Config;

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'src/Config.php';
Config::registerLegacyConstants();
require_once plugin_dir_path(__FILE__) . 'includes/cjenik.php';

/**
 * This extension only manages products and public price-list files. It does not
 * read or write orders and does not alter the Cart or Checkout flow.
 */
add_action('before_woocommerce_init', 'sidrena_cijena_declare_wc_compatibility');
function sidrena_cijena_declare_wc_compatibility() {
    if (!class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
}

function sidrena_cijena_get_current_price($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return '';
    }

    $price = $product->get_price('edit');
    if ($price === '' || $price === null) {
        return '';
    }

    return wc_format_decimal($price);
}

/**
 * Stores a snapshot of the current active price only when no anchor exists.
 */
function sidrena_cijena_initialize_product_anchor($product_id) { return 0; }

add_action('woocommerce_update_product', 'sidrena_cijena_initialize_anchor_after_product_update', 5, 1);
add_action('woocommerce_update_product_variation', 'sidrena_cijena_initialize_anchor_after_product_update', 5, 1);
function sidrena_cijena_initialize_anchor_after_product_update($product_id) {
    if (sidrena_cijena_initialize_product_anchor($product_id) > 0 && function_exists('cjenik_queue_refresh')) {
        cjenik_queue_refresh();
    }
}

add_action('admin_init', 'sidrena_cijena_maybe_initialize_existing_anchors');
function sidrena_cijena_maybe_initialize_existing_anchors() { /* No automatic historical values. */ }

add_action('admin_notices', 'sidrena_cijena_initialized_notice');
function sidrena_cijena_initialized_notice() {
    delete_transient('sidrena_cijena_initialized_notice_' . get_current_user_id());
}

function sidrena_cijena_font_family_choices() {
    return array(
        'inherit'                              => __('Naslijeđeno od teme (preporučeno)', 'sidrena-cijena'),
        'Arial, Helvetica, sans-serif'          => 'Arial',
        'Georgia, serif'                        => 'Georgia',
        'Verdana, sans-serif'                   => 'Verdana',
        "'Courier New', Courier, monospace"     => 'Courier New',
        "'Times New Roman', Times, serif"       => 'Times New Roman',
    );
}

function sidrena_cijena_font_weight_choices() {
    return array(
        'normal' => __('Normalno', 'sidrena-cijena'),
        'bold'   => __('Podebljano (bold)', 'sidrena-cijena'),
    );
}

function sidrena_cijena_font_style_choices() {
    return array(
        'normal' => __('Normalno', 'sidrena-cijena'),
        'italic' => __('Kurziv (italic)', 'sidrena-cijena'),
    );
}

add_action('plugins_loaded', 'sidrena_cijena_check_woocommerce');
function sidrena_cijena_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'sidrena_cijena_missing_woocommerce_notice');
    }
}

function sidrena_cijena_missing_woocommerce_notice() {
    echo '<div class="notice notice-error"><p>' . esc_html__('Dodatak "Sidrena Cijena" zahtijeva aktivan WooCommerce.', 'sidrena-cijena') . '</p></div>';
}

// 1. Polje na stranici za uređivanje proizvoda (tab "General", odmah ispod redovne/akcijske cijene)
add_action('woocommerce_product_options_pricing', 'sidrena_cijena_add_field');
function sidrena_cijena_add_field() {
    woocommerce_wp_text_input(array(
        'id'          => Config::ANCHOR_META_KEY,
        'label'       => sprintf(
            /* translators: %s: reference date */
            __('Bazna cijena (zadano %s)', 'sidrena-cijena'),
            (new DateTimeImmutable(sidrena_cijena_base_date(null)))->format('d.m.Y.')
        ),
        'desc_tip'    => true,
        'description' => __('Unesite povijesni iznos prema evidenciji, u istoj poreznoj osnovi kao WooCommerce cijene. Prazno ostaje nepoznato; nema automatskog kopiranja. Datum se uređuje u kartici Inventar.', 'sidrena-cijena'),
        'data_type'   => 'price',
    ));
}

// 2. Spremanje vrijednosti
add_action('woocommerce_process_product_meta', 'sidrena_cijena_save_field');
function sidrena_cijena_save_field($post_id) {
    if (!current_user_can('edit_post', $post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    if (isset($_POST[Config::ANCHOR_META_KEY])) {
        $raw = sanitize_text_field(wp_unslash($_POST[Config::ANCHOR_META_KEY]));

        if ($raw === '') {
            delete_post_meta($post_id, Config::ANCHOR_META_KEY);
        } else {
            update_post_meta($post_id, Config::ANCHOR_META_KEY, wc_format_decimal($raw));
        }
    }
}

// 2b. Podrška za Quick Edit (brzo uređivanje iz popisa proizvoda)
add_action('woocommerce_product_quick_edit_end', 'sidrena_cijena_quick_edit_field');
function sidrena_cijena_quick_edit_field() {
    ?>
    <div class="inline-edit-group sidrena-cijena-quick-edit-row" style="clear:both;display:block;width:100%;float:none;">
        <label class="alignleft" style="width:100%;">
            <span class="title"><?php echo esc_html__('Bazna cijena', 'sidrena-cijena'); ?></span>
            <span class="input-text-wrap">
                <input type="text" name="<?php echo esc_attr(Config::ANCHOR_META_KEY); ?>" class="text sidrena_cijena_quick_edit_field" value="" />
            </span>
        </label>
    </div>
    <?php
}

add_action('manage_product_posts_custom_column', 'sidrena_cijena_output_hidden_value', 20, 2);
function sidrena_cijena_output_hidden_value($column, $post_id) {
    if ($column === 'price') {
        $value = get_post_meta($post_id, Config::ANCHOR_META_KEY, true);
        echo '<div class="sidrena_cijena_hidden_value" style="display:none;">' . esc_html($value) . '</div>';
    }
}

add_action('woocommerce_product_quick_edit_save', 'sidrena_cijena_quick_edit_save');
function sidrena_cijena_quick_edit_save($product) {
    if (!current_user_can('edit_post', $product->get_id())) {
        return;
    }

    if (isset($_POST[Config::ANCHOR_META_KEY])) {
        $raw = sanitize_text_field(wp_unslash($_POST[Config::ANCHOR_META_KEY]));
        $post_id = $product->get_id();

        if ($raw === '') {
            delete_post_meta($post_id, Config::ANCHOR_META_KEY);
        } else {
            update_post_meta($post_id, Config::ANCHOR_META_KEY, wc_format_decimal($raw));
        }
    }
}

add_action('admin_enqueue_scripts', 'sidrena_cijena_quick_edit_script');
function sidrena_cijena_quick_edit_script($hook) {
    global $post_type;

    if ($hook !== 'edit.php' || $post_type !== 'product') {
        return;
    }

    $js = "
        (function($){
            if (typeof inlineEditPost === 'undefined') { return; }
            var sidrena_cijena_wc_inline_edit = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                sidrena_cijena_wc_inline_edit.apply(this, arguments);
                var postId = 0;
                if (typeof(id) === 'object') {
                    postId = parseInt(this.getId(id), 10);
                } else {
                    postId = parseInt(id, 10);
                }
                if (postId > 0) {
                    var \$row = $('#post-' + postId);
                    var value = \$row.find('.sidrena_cijena_hidden_value').first().text();
                    $('input.sidrena_cijena_quick_edit_field').val(value);

                    var \$saleLabel = $('input[name=\"_sale_price\"]').closest('label');
                    var \$ourField = $('.sidrena-cijena-quick-edit-row');
                    if (\$saleLabel.length && \$ourField.length) {
                        \$saleLabel.after(\$ourField);
                    }
                }
            };
        })(jQuery);
    ";

    wp_add_inline_script('inline-edit-post', $js);
}

// 3. Prikaz ispod cijene - pokriva stranicu proizvoda, kategorije/shop, related/upsell/cross-sell, widgete itd.
function sidrena_cijena_get_style_settings() {
    $label = get_option(Config::LABEL_OPTION, Config::DEFAULT_LABEL);
    if ($label === 'Sidrena cijena na dan 10.09.2026.') { $label = Config::DEFAULT_LABEL; }
    if ($label === '') {
        $label = Config::DEFAULT_LABEL;
    }

    $font_size = (int) get_option(Config::FONT_SIZE_OPTION, Config::DEFAULT_FONT_SIZE);
    if ($font_size < 8) {
        $font_size = 8;
    } elseif ($font_size > 32) {
        $font_size = 32;
    }

    $font_size_mobile = (int) get_option(Config::MOBILE_FONT_SIZE_OPTION, Config::DEFAULT_MOBILE_FONT_SIZE);
    if ($font_size_mobile < 8) {
        $font_size_mobile = 8;
    } elseif ($font_size_mobile > 32) {
        $font_size_mobile = 32;
    }

    $font_family = get_option(Config::FONT_FAMILY_OPTION, Config::DEFAULT_FONT_FAMILY);
    if (!array_key_exists($font_family, sidrena_cijena_font_family_choices())) {
        $font_family = Config::DEFAULT_FONT_FAMILY;
    }

    $font_weight = get_option(Config::FONT_WEIGHT_OPTION, Config::DEFAULT_FONT_WEIGHT);
    if (!array_key_exists($font_weight, sidrena_cijena_font_weight_choices())) {
        $font_weight = Config::DEFAULT_FONT_WEIGHT;
    }

    $font_style = get_option(Config::FONT_STYLE_OPTION, Config::DEFAULT_FONT_STYLE);
    if (!array_key_exists($font_style, sidrena_cijena_font_style_choices())) {
        $font_style = Config::DEFAULT_FONT_STYLE;
    }

    $color = get_option(Config::COLOR_OPTION, Config::DEFAULT_COLOR);
    $sanitized_color = sanitize_hex_color($color);
    if (!$sanitized_color) {
        $sanitized_color = Config::DEFAULT_COLOR;
    }

    return array(
        'label'             => $label,
        'font_size'         => $font_size,
        'font_size_mobile'  => $font_size_mobile,
        'font_family'       => $font_family,
        'font_weight'       => $font_weight,
        'font_style'        => $font_style,
        'color'             => $sanitized_color,
    );
}

function sidrena_cijena_build_line($anchor_price) {
    return sidrena_cijena_build_formatted_line(wc_price($anchor_price));
}

function sidrena_cijena_build_formatted_line($formatted_price, $label = null) {
    $s = sidrena_cijena_get_style_settings();

    return sprintf(
        '<span class="sidrena-cijena">%s: %s</span>',
        esc_html($label === null ? sidrena_cijena_label() : $label),
        wp_kses_post($formatted_price)
    );
}

add_action('wp_head', 'sidrena_cijena_output_css');
function sidrena_cijena_output_css() {
    if (get_option(Config::DISPLAY_ENABLED_OPTION, 'yes') !== 'yes') {
        return;
    }

    $s = sidrena_cijena_get_style_settings();
    $breakpoint = (int) Config::MOBILE_BREAKPOINT;
    ?>
    <style id="sidrena-cijena-css">
        .sidrena-cijena {
            display: block;
            font-size: <?php echo (int) $s['font_size']; ?>px;
            font-family: <?php echo $s['font_family']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted value. ?>;
            font-weight: <?php echo $s['font_weight']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted value. ?>;
            font-style: <?php echo $s['font_style']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- whitelisted value. ?>;
            color: <?php echo $s['color']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitize_hex_color. ?>;
        }
        @media (max-width: <?php echo $breakpoint; ?>px) {
            .sidrena-cijena {
                font-size: <?php echo (int) $s['font_size_mobile']; ?>px;
            }
        }
    </style>
    <?php
}

add_filter('woocommerce_get_price_html', 'sidrena_cijena_append_to_price_html', 10, 2);
function sidrena_cijena_variable_anchor_range($product) {
    $cache_key = 'anchor_range_' . $product->get_id();
    $cached = wp_cache_get($cache_key, 'sidrena_cijena');
    if (is_array($cached)) {
        return $cached;
    }

    $prices = array();
    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation || $variation->get_status() !== 'publish') {
            continue;
        }
        $value = $variation->get_meta(Config::ANCHOR_META_KEY, true);
        if ($value !== '') {
            $prices[] = (float) wc_format_decimal($value);
        }
    }

    $range = $prices ? array(min($prices), max($prices)) : array();
    wp_cache_set($cache_key, $range, 'sidrena_cijena', HOUR_IN_SECONDS);
    return $range;
}

add_action('woocommerce_update_product', 'sidrena_cijena_clear_anchor_range_cache', 20, 1);
add_action('woocommerce_update_product_variation', 'sidrena_cijena_clear_anchor_range_cache', 20, 1);
function sidrena_cijena_clear_anchor_range_cache($product_id) {
    wp_cache_delete('anchor_range_' . (int) $product_id, 'sidrena_cijena');
    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    if ($product && $product->get_parent_id()) {
        wp_cache_delete('anchor_range_' . $product->get_parent_id(), 'sidrena_cijena');
    }
}

function sidrena_cijena_append_to_price_html($price_html, $product) {
    if (empty($price_html) || get_option(Config::DISPLAY_ENABLED_OPTION, 'yes') !== 'yes' || $product->is_type('grouped') || $product->is_type('variable')) { return $price_html; }
    $parent = $product->get_parent_id() ? wc_get_product($product->get_parent_id()) : null;
    if (sidrena_cijena_base_exempt($product, $parent)) { return $price_html; }
    $value = sidrena_cijena_base_value($product, $parent);
    if ($value === '') { return $price_html; }
    return $price_html . sidrena_cijena_build_formatted_line(wc_price(cjenik_gross_price($product, $value)), sidrena_cijena_label($product, $parent));
}

// 3b. Varijabilni proizvodi - polje po varijaciji (npr. veličina/boja), i prikaz kad kupac odabere varijaciju
add_action('woocommerce_product_after_variable_attributes', 'sidrena_cijena_variation_field', 10, 3);
function sidrena_cijena_variation_field($loop, $variation_data, $variation) {
    $value = get_post_meta($variation->ID, Config::ANCHOR_META_KEY, true);
    ?>
    <p class="form-row form-row-full">
        <label>
            <?php
            printf(
                /* translators: %s: reference date */
                esc_html__('Bazna cijena (zadano %s)', 'sidrena-cijena'),
                esc_html((new DateTimeImmutable(sidrena_cijena_base_date(null)))->format('d.m.Y.'))
            );
            ?>
        </label>
        <input
            type="text"
            name="variable_anchor_price[<?php echo esc_attr($loop); ?>]"
            class="wc_input_price"
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr__('Prazno = bazna cijena nije unesena', 'sidrena-cijena'); ?>"
        />
    </p>
    <?php
}

add_action('woocommerce_save_product_variation', 'sidrena_cijena_save_variation_field', 10, 2);
function sidrena_cijena_save_variation_field($variation_id, $i) {
    if (!current_user_can('edit_post', $variation_id)) {
        return;
    }

    if (isset($_POST['variable_anchor_price'][$i])) {
        $raw = sanitize_text_field(wp_unslash($_POST['variable_anchor_price'][$i]));

        if ($raw === '') {
            delete_post_meta($variation_id, Config::ANCHOR_META_KEY);
        } else {
            update_post_meta($variation_id, Config::ANCHOR_META_KEY, wc_format_decimal($raw));
        }
    }
}

add_filter('woocommerce_available_variation', 'sidrena_cijena_variation_price_html', 10, 3);
function sidrena_cijena_variation_price_html($variation_data, $product, $variation) {
    if (sidrena_cijena_base_exempt($variation, $product)) { return $variation_data; }
    if (get_option(Config::DISPLAY_ENABLED_OPTION, 'yes') !== 'yes') { return $variation_data; }
    if (empty($variation_data['price_html'])) { $variation_data['price_html'] = $variation->get_price_html(); }
    if (strpos($variation_data['price_html'], 'class="sidrena-cijena"') === false) {
        $value = sidrena_cijena_base_value($variation, $product);
        if ($value !== '') { $variation_data['price_html'] .= sidrena_cijena_build_formatted_line(wc_price(cjenik_gross_price($variation, $value)), sidrena_cijena_label($variation, $product)); }
    }
    return $variation_data;
}

// 4. Vlastita stavka u admin izborniku s postavkama dodatka
function sidrena_cijena_menu_icon() {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="black" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="4" r="2"/><line x1="10" y1="6" x2="10" y2="17"/><line x1="5" y1="8" x2="15" y2="8"/><path d="M5 13 a5 5 0 0 0 10 0"/></svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

add_action('admin_menu', 'sidrena_cijena_add_menu');
function sidrena_cijena_add_menu() {
    $hook = add_menu_page(
        __('Sidrena cijena', 'sidrena-cijena'),
        __('Sidrena cijena', 'sidrena-cijena'),
        'manage_woocommerce',
        'sidrena-cijena',
        'sidrena_cijena_render_settings_page',
        sidrena_cijena_menu_icon(),
        56
    );

    add_action('admin_enqueue_scripts', function ($current_hook) use ($hook) {
        if ($current_hook !== $hook) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        $js = "
            (function($){
                $('.sidrena-cijena-color-field').wpColorPicker({ change: sidrena_cijena_update_preview, clear: sidrena_cijena_update_preview });

                function sidrena_cijena_update_preview() {
                    setTimeout(function() {
                        var label = ($('#sidrena-cijena-label').val() || 'Bazna cijena na dan {datum}').replace('{datum}', ($('#sidrena-base-date').val() || '2026-09-10').split('-').reverse().join('.') + '.');
                        var size = $('#sidrena-cijena-font-size').val() || 12;
                        var sizeMobile = $('#sidrena-cijena-font-size-mobile').val() || 11;
                        var family = $('#sidrena-cijena-font-family').val() || 'inherit';
                        var weight = $('#sidrena-cijena-font-weight').val() || 'normal';
                        var style = $('#sidrena-cijena-font-style').val() || 'normal';
                        var color = $('.sidrena-cijena-color-field').val() || '#666666';

                        var baseCss = {
                            'font-family': family,
                            'font-weight': weight,
                            'font-style': style,
                            'color': color
                        };

                        $('#sidrena-cijena-preview').css($.extend({ 'font-size': size + 'px' }, baseCss)).text(label + ': 199,00 €');
                        $('#sidrena-cijena-preview-mobile').css($.extend({ 'font-size': sizeMobile + 'px' }, baseCss)).text(label + ': 199,00 €');
                    }, 10);
                }

                $('#sidrena-base-date, #sidrena-cijena-label, #sidrena-cijena-font-size, #sidrena-cijena-font-size-mobile, #sidrena-cijena-font-family, #sidrena-cijena-font-weight, #sidrena-cijena-font-style').on('input change', sidrena_cijena_update_preview);

                sidrena_cijena_update_preview();
            })(jQuery);
        ";

        wp_add_inline_script('wp-color-picker', $js);
    });
}

function sidrena_cijena_render_settings_page() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $tabs = array(
        'sidrena-cijena' => __('Sidrena cijena', 'sidrena-cijena'),
        'cjenik'         => __('Cjenik', 'sidrena-cijena'),
        'pomoc'           => __('Pomoć', 'sidrena-cijena'),
    );

    $requested_tab = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    $active_tab = array_key_exists($requested_tab, $tabs) ? $requested_tab : 'sidrena-cijena';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Sidrena cijena i cjenik', 'sidrena-cijena'); ?></h1>
        <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
            <?php foreach ($tabs as $tab_slug => $tab_label) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sidrena-cijena&tab=' . $tab_slug)); ?>" class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php
        if ($active_tab === 'cjenik') {
            cjenik_render_tab_content();
        } elseif ($active_tab === 'pomoc') {
            sidrena_cijena_render_help_tab();
        } else {
            sidrena_cijena_render_tab_content();
        }
        ?>
    </div>
    <?php
}

function sidrena_cijena_render_tab_content() {
    $tab_url = admin_url('admin.php?page=sidrena-cijena&tab=sidrena-cijena');

    if (isset($_POST['sidrena_cijena_save']) && check_admin_referer('sidrena_cijena_settings_save', 'sidrena_cijena_nonce')) {
        $enabled = isset($_POST[Config::DISPLAY_ENABLED_OPTION]) ? 'yes' : 'no';
        update_option(Config::DISPLAY_ENABLED_OPTION, $enabled);
        if (isset($_POST['sidrena_cijena_review_confirm'])) { update_option('sidrena_cijena_compliance_reviewed', '2.0.0'); }

        $label = isset($_POST[Config::LABEL_OPTION]) ? sanitize_text_field(wp_unslash($_POST[Config::LABEL_OPTION])) : '';
        if ($label === '') {
            $label = Config::DEFAULT_LABEL;
        }
        update_option(Config::LABEL_OPTION, $label);
        $base_date = isset($_POST[Config::BASE_DATE_OPTION]) ? sanitize_text_field(wp_unslash($_POST[Config::BASE_DATE_OPTION])) : '';
        if (in_array($base_date, array('2025-05-02', '2026-09-10'), true)) { update_option(Config::BASE_DATE_OPTION, $base_date); }
        cjenik_queue_refresh();

        $font_size = isset($_POST[Config::FONT_SIZE_OPTION]) ? (int) $_POST[Config::FONT_SIZE_OPTION] : Config::DEFAULT_FONT_SIZE;
        if ($font_size < 8) {
            $font_size = 8;
        } elseif ($font_size > 32) {
            $font_size = 32;
        }
        update_option(Config::FONT_SIZE_OPTION, $font_size);

        $font_size_mobile = isset($_POST[Config::MOBILE_FONT_SIZE_OPTION]) ? (int) $_POST[Config::MOBILE_FONT_SIZE_OPTION] : Config::DEFAULT_MOBILE_FONT_SIZE;
        if ($font_size_mobile < 8) {
            $font_size_mobile = 8;
        } elseif ($font_size_mobile > 32) {
            $font_size_mobile = 32;
        }
        update_option(Config::MOBILE_FONT_SIZE_OPTION, $font_size_mobile);

        $font_family = isset($_POST[Config::FONT_FAMILY_OPTION]) ? sanitize_text_field(wp_unslash($_POST[Config::FONT_FAMILY_OPTION])) : Config::DEFAULT_FONT_FAMILY;
        if (!array_key_exists($font_family, sidrena_cijena_font_family_choices())) {
            $font_family = Config::DEFAULT_FONT_FAMILY;
        }
        update_option(Config::FONT_FAMILY_OPTION, $font_family);

        $font_weight = isset($_POST[Config::FONT_WEIGHT_OPTION]) ? sanitize_text_field(wp_unslash($_POST[Config::FONT_WEIGHT_OPTION])) : Config::DEFAULT_FONT_WEIGHT;
        if (!array_key_exists($font_weight, sidrena_cijena_font_weight_choices())) {
            $font_weight = Config::DEFAULT_FONT_WEIGHT;
        }
        update_option(Config::FONT_WEIGHT_OPTION, $font_weight);

        $font_style = isset($_POST[Config::FONT_STYLE_OPTION]) ? sanitize_text_field(wp_unslash($_POST[Config::FONT_STYLE_OPTION])) : Config::DEFAULT_FONT_STYLE;
        if (!array_key_exists($font_style, sidrena_cijena_font_style_choices())) {
            $font_style = Config::DEFAULT_FONT_STYLE;
        }
        update_option(Config::FONT_STYLE_OPTION, $font_style);

        $color = isset($_POST[Config::COLOR_OPTION]) ? sanitize_hex_color(wp_unslash($_POST[Config::COLOR_OPTION])) : '';
        if (!$color) {
            $color = Config::DEFAULT_COLOR;
        }
        update_option(Config::COLOR_OPTION, $color);

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Postavke spremljene.', 'sidrena-cijena') . '</p></div>';
    }

    $enabled = get_option(Config::DISPLAY_ENABLED_OPTION, 'yes');
    $s = sidrena_cijena_get_style_settings();
    $label = $s['label'];
    $font_size = $s['font_size'];
    $font_size_mobile = $s['font_size_mobile'];
    $font_family = $s['font_family'];
    $font_weight = $s['font_weight'];
    $font_style = $s['font_style'];
    $color = $s['color'];
    $anchor_status = sidrena_cijena_get_anchor_status();
    ?>
        <p><?php echo esc_html__('Bazna cijena prema NN 132/2026. Odaberite zadani referentni datum prema asortimanu; za pojedine proizvode i nove ponude datum uredite u Inventaru.', 'sidrena-cijena'); ?></p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0 20px;">
            <div style="background:#fff;border:1px solid #dcdcde;padding:14px 18px;min-width:170px;"><strong style="font-size:22px;display:block;"><?php echo (int) $anchor_status['total']; ?></strong><?php echo esc_html__('proizvoda i varijacija', 'sidrena-cijena'); ?></div>
            <div style="background:#fff;border:1px solid #dcdcde;padding:14px 18px;min-width:170px;"><strong style="font-size:22px;display:block;color:#008a20;"><?php echo (int) $anchor_status['set']; ?></strong><?php echo esc_html__('s unesenom cijenom', 'sidrena-cijena'); ?></div>
            <div style="background:#fff;border:1px solid #dcdcde;padding:14px 18px;min-width:170px;"><strong style="font-size:22px;display:block;color:<?php echo $anchor_status['missing'] ? '#b32d2e' : '#008a20'; ?>;"><?php echo (int) $anchor_status['missing']; ?></strong><?php echo esc_html__('bez sidrene cijene', 'sidrena-cijena'); ?></div>
        </div>
        <form method="post" action="<?php echo esc_url($tab_url); ?>">
            <?php wp_nonce_field('sidrena_cijena_settings_save', 'sidrena_cijena_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Prikaz sidrene cijene', 'sidrena-cijena'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(Config::DISPLAY_ENABLED_OPTION); ?>" value="1" <?php checked($enabled, 'yes'); ?> />
                            <?php echo esc_html__('Prikaži sidrenu cijenu uz trenutačnu cijenu na cijeloj web stranici', 'sidrena-cijena'); ?>
                        </label>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: reference date */
                                esc_html__('Kad je uključeno, sidrena cijena (na dan %s) prikazuje se ispod redovne cijene svugdje gdje WooCommerce prikazuje cijenu proizvoda. Sam iznos unosite po proizvodu u Products → uredi proizvod → tab General.', 'sidrena-cijena'),
                                esc_html((new DateTimeImmutable(sidrena_cijena_base_date(null)))->format('d.m.Y.'))
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sidrena-cijena-label"><?php echo esc_html__('Tekst oznake', 'sidrena-cijena'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="sidrena-cijena-label" name="<?php echo esc_attr(Config::LABEL_OPTION); ?>" value="<?php echo esc_attr($label); ?>" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Koristite {datum} za datum proizvoda, npr. Bazna cijena na dan {datum}. Povijesni datum ne mijenja se svaki dan.', 'sidrena-cijena'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="sidrena-base-date">Zadani datum bazne cijene</label></th>
                    <td>
                        <select id="sidrena-base-date" name="<?php echo esc_attr(Config::BASE_DATE_OPTION); ?>">
                            <option value="2026-09-10" <?php selected(get_option(Config::BASE_DATE_OPTION, '2026-09-10'), '2026-09-10'); ?>>10.09.2026. — ostala roba i usluge</option>
                            <option value="2025-05-02" <?php selected(get_option(Config::BASE_DATE_OPTION, '2026-09-10'), '2025-05-02'); ?>>02.05.2025. — hrana, piće, kozmetika, čišćenje, toaletne potrepštine i kućanstvo</option>
                        </select>
                        <p class="description">Za mješoviti asortiman i novu ponudu datum uredite na proizvodu u Inventaru ili na varijaciji. Ne unosi se današnji datum osim ako je to datum prvog nuđenja.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sidrena-cijena-font-family"><?php echo esc_html__('Vrsta fonta', 'sidrena-cijena'); ?></label>
                    </th>
                    <td>
                        <select id="sidrena-cijena-font-family" name="<?php echo esc_attr(Config::FONT_FAMILY_OPTION); ?>">
                            <?php foreach (sidrena_cijena_font_family_choices() as $value => $option_label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($font_family, $value); ?>><?php echo esc_html($option_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sidrena-cijena-font-weight"><?php echo esc_html__('Debljina fonta', 'sidrena-cijena'); ?></label>
                    </th>
                    <td>
                        <select id="sidrena-cijena-font-weight" name="<?php echo esc_attr(Config::FONT_WEIGHT_OPTION); ?>">
                            <?php foreach (sidrena_cijena_font_weight_choices() as $value => $option_label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($font_weight, $value); ?>><?php echo esc_html($option_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sidrena-cijena-font-style"><?php echo esc_html__('Stil fonta', 'sidrena-cijena'); ?></label>
                    </th>
                    <td>
                        <select id="sidrena-cijena-font-style" name="<?php echo esc_attr(Config::FONT_STYLE_OPTION); ?>">
                            <?php foreach (sidrena_cijena_font_style_choices() as $value => $option_label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($font_style, $value); ?>><?php echo esc_html($option_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sidrena-cijena-font-size"><?php echo esc_html__('Veličina fonta', 'sidrena-cijena'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="sidrena-cijena-font-size" name="<?php echo esc_attr(Config::FONT_SIZE_OPTION); ?>" value="<?php echo esc_attr($font_size); ?>" min="8" max="32" step="1" class="small-text" /> px
                        <p class="description"><?php echo esc_html__('Veličina fonta teksta sidrene cijene na desktop ekranima, u pikselima (8-32).', 'sidrena-cijena'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sidrena-cijena-font-size-mobile"><?php echo esc_html__('Veličina fonta (mobitel)', 'sidrena-cijena'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="sidrena-cijena-font-size-mobile" name="<?php echo esc_attr(Config::MOBILE_FONT_SIZE_OPTION); ?>" value="<?php echo esc_attr($font_size_mobile); ?>" min="8" max="32" step="1" class="small-text" /> px
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %d: breakpoint in pixels */
                                esc_html__('Veličina fonta na ekranima širine do %d px (mobiteli i manji tableti). Iznad te širine koristi se desktop veličina.', 'sidrena-cijena'),
                                (int) Config::MOBILE_BREAKPOINT
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sidrena-cijena-color"><?php echo esc_html__('Boja teksta', 'sidrena-cijena'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="sidrena-cijena-color" class="sidrena-cijena-color-field" name="<?php echo esc_attr(Config::COLOR_OPTION); ?>" value="<?php echo esc_attr($color); ?>" data-default-color="<?php echo esc_attr(Config::DEFAULT_COLOR); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Pregled', 'sidrena-cijena'); ?></th>
                    <td>
                        <p class="description" style="margin-top:0;"><?php echo esc_html__('Desktop', 'sidrena-cijena'); ?></p>
                        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:400px;margin-bottom:12px;">
                            <div style="font-size:16px;color:#333;">189,00 €</div>
                            <span id="sidrena-cijena-preview"></span>
                        </div>
                        <p class="description"><?php echo esc_html__('Mobitel', 'sidrena-cijena'); ?></p>
                        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:250px;">
                            <div style="font-size:16px;color:#333;">189,00 €</div>
                            <span id="sidrena-cijena-preview-mobile"></span>
                        </div>
                    </td>
                </tr>
            </table>
            <p><label><input type="checkbox" name="sidrena_cijena_review_confirm" value="1" /> Provjerio/la sam povijesne iznose, datume i poreznu osnovu prema evidenciji (uklanja podsjetnik za nadogradnju).</label></p>
            <?php submit_button(__('Spremi promjene', 'sidrena-cijena'), 'primary', 'sidrena_cijena_save'); ?>
        </form>

        <div class="notice notice-warning inline"><p><?php echo esc_html__('Postojeći iznosi su sačuvani. Ranije automatski kopirane cijene provjerite prema evidenciji. Za referentni dan pod sniženjem primijenite čl. 3. Pravilnika: za skupinu 2.5.2025. najnižu cijenu 30 dana prije početka sniženja, a za ostalu robu/usluge prethodno primijenjenu redovnu cijenu. Prazni iznosi više se ne popunjavaju automatski.', 'sidrena-cijena'); ?></p></div>
    <?php
}

/**
 * Counts published products and their variations that need an anchor price.
 */
function sidrena_cijena_published_product_ids() {
    if (!function_exists('wc_get_products')) {
        return;
    }

    $page = 1;
    $page_size = 100;
    do {
        $ids = wc_get_products(array(
            'status' => 'publish',
            'limit' => $page_size,
            'page' => $page,
            'orderby' => 'ID',
            'order' => 'ASC',
            'return' => 'ids',
        ));
        if (is_wp_error($ids) || !is_array($ids)) {
            return;
        }
        foreach ($ids as $id) {
            yield $id;
        }
        $page++;
    } while (count($ids) === $page_size);
}

function sidrena_cijena_get_anchor_status() {
    $status = array('total' => 0, 'set' => 0, 'missing' => 0);

    if (!function_exists('wc_get_products')) {
        return $status;
    }

    foreach (sidrena_cijena_published_product_ids() as $id) {
        $product = wc_get_product($id);
        if (!$product) {
            continue;
        }

        $targets = $product->is_type('variable') ? $product->get_children() : array($id);
        foreach ($targets as $target_id) {
            $target = wc_get_product($target_id);
            if (!$target || $target->get_status() !== 'publish') {
                continue;
            }
            $status['total']++;
            if ($target->get_meta(Config::ANCHOR_META_KEY, true) === '') {
                $status['missing']++;
            } else {
                $status['set']++;
            }
        }
    }

    return $status;
}

/**
 * Fills only missing anchor prices. Existing values are intentionally preserved.
 */
function sidrena_cijena_fill_missing_from_current_prices() { return 0; }

function sidrena_cijena_render_help_tab() {
    if (!current_user_can('manage_woocommerce')) { return; }
    ?>
    <h2>Bazne cijene i cjenik — NN 132/2026</h2>
    <p>Naziv dodatka ostaje Sidrena Cijena i Cjenik. Potrošaču se prikazuje prilagodljiva oznaka bazne cijene s datumom proizvoda.</p>
    <ol>
        <li>U kartici Sidrena cijena odaberite zadani datum prema asortimanu. Za mješoviti asortiman i novu ponudu na proizvodu u Inventaru unesite drugi datum. Varijacije mogu imati zaseban datum i iznos.</li>
        <li>Bazni iznos unesite prema evidenciji, u istoj poreznoj osnovi kao WooCommerce cijene. Današnja cijena i datum izmjene proizvoda nisu dokaz povijesnog iznosa. Postojeći podaci ostaju sačuvani, uključujući ranije automatski unesene vrijednosti koje treba provjeriti.</li>
        <li>Za sniženje na referentni dan provjerite čl. 3. pravilnika: pravila nisu ista za skupinu 2.5.2025. i ostalu robu/usluge. Dodatak ne rekonstruira povijest cijena niti prepoznaje pravnu kategoriju proizvoda.</li>
        <li>U Cjeniku unesite OIB pravne osobe ili matični broj trgovca fizičke osobe, vrstu i oznaku prodajnog mjesta, adresu i broj pohrane. Odaberite robu ili usluge. Jedan cjenik vrijedi za jednu takvu vrstu ponude; prije izvoza izostavite stavke koje ne pripadaju odabranoj vrsti.</li>
        <li>Za robu unesite jedinicu i neto količinu. Za robu s obveznom jediničnom cijenom označite pripadajuće polje. Za g/ml izračun je po kg/L. Neto količina nije transportna težina. Provjerite PDV i poreznu klasu svakog proizvoda.</li>
        <li>Izmijenjeni cjenik objavite do 11:00 na dan izmjene; promjene nakon toga objavite odmah. Dodatak pokušava objavu po završetku spremanja te ima WP-Cron ponovni pokušaj. Postavite poslužiteljski cron, nadzirite zadnju pogrešku i provjerite javne datoteke.</li>
        <li>Javnu poveznicu postavite pomoću <code>[sidrena_cjenik format="oba" arhiva="da"]</code>. Oba formata koriste isti redoslijed podataka; CSV koristi UTF-8 i točku-zarez. Ranije objavljeni cjenici dostupni su najmanje 15 dana od zamjene.</li>
        <li>Isključivanje proizvoda u Inventaru ili brzom uređivanju koristite samo za opravdane iznimke. Skriveni proizvod nije automatski zakonski izuzet.</li>
    </ol>
    <p><a href="https://narodne-novine.nn.hr/eli/sluzbeni/2026/132/pdf" target="_blank" rel="noopener noreferrer">Službeni pravilnici — NN 132/2026, str. 5–7</a></p>
    <h3>Dijagnostika</h3>
    <p>Verzija: <?php echo esc_html(Config::VERSION); ?>. Zadnja pogreška: <?php echo esc_html(get_option(CJENIK_LAST_ERROR_OPTION, '') ?: 'Nema zabilježene pogreške'); ?></p>
    <p>Ako javna poveznica vraća 404, generirajte cjenik i spremite Postavke → Stalne veze. Izuzmite cjenik iz predmemorije i zaštita koje blokiraju automatizirano preuzimanje.</p>
    <p>Potrebna je provjera stvarne trgovine, teme, prilagođenih cijena i iznimaka. Dodatak ne jamči pravnu usklađenost.</p>
    <?php
}
