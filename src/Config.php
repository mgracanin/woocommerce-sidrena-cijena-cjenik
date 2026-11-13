<?php

namespace SidrenaCijenaCjenik;

if (!defined('ABSPATH')) {
    exit;
}

final class Config
{
    const VERSION = '1.2.4';
    const REFERENCE_DATE = '10.09.2026.';
    const ANCHOR_META_KEY = '_anchor_price';

    const DISPLAY_ENABLED_OPTION = 'sidrena_cijena_enabled';
    const AUTO_INITIALIZED_OPTION = 'sidrena_cijena_auto_initialized';
    const LABEL_OPTION = 'sidrena_cijena_label_text';
    const FONT_SIZE_OPTION = 'sidrena_cijena_font_size';
    const FONT_FAMILY_OPTION = 'sidrena_cijena_font_family';
    const FONT_WEIGHT_OPTION = 'sidrena_cijena_font_weight';
    const FONT_STYLE_OPTION = 'sidrena_cijena_font_style';
    const COLOR_OPTION = 'sidrena_cijena_color';
    const MOBILE_FONT_SIZE_OPTION = 'sidrena_cijena_font_size_mobile';

    const MOBILE_BREAKPOINT = 768;
    const DEFAULT_LABEL = 'Sidrena cijena na dan 10.09.2026.';
    const DEFAULT_FONT_SIZE = 12;
    const DEFAULT_MOBILE_FONT_SIZE = 11;
    const DEFAULT_FONT_FAMILY = 'inherit';
    const DEFAULT_FONT_WEIGHT = 'normal';
    const DEFAULT_FONT_STYLE = 'normal';
    const DEFAULT_COLOR = '#666666';

    /**
     * Keep the original public constants available for third-party integrations.
     */
    public static function registerLegacyConstants()
    {
        $aliases = array(
            'SIDRENA_CIJENA_DATE' => self::REFERENCE_DATE,
            'SIDRENA_CIJENA_META_KEY' => self::ANCHOR_META_KEY,
            'SIDRENA_CIJENA_VERSION' => self::VERSION,
            'SIDRENA_CIJENA_OPTION' => self::DISPLAY_ENABLED_OPTION,
            'SIDRENA_CIJENA_AUTO_INITIALIZED_OPTION' => self::AUTO_INITIALIZED_OPTION,
            'SIDRENA_CIJENA_LABEL_OPTION' => self::LABEL_OPTION,
            'SIDRENA_CIJENA_FONT_SIZE_OPTION' => self::FONT_SIZE_OPTION,
            'SIDRENA_CIJENA_FONT_FAMILY_OPTION' => self::FONT_FAMILY_OPTION,
            'SIDRENA_CIJENA_FONT_WEIGHT_OPTION' => self::FONT_WEIGHT_OPTION,
            'SIDRENA_CIJENA_FONT_STYLE_OPTION' => self::FONT_STYLE_OPTION,
            'SIDRENA_CIJENA_COLOR_OPTION' => self::COLOR_OPTION,
            'SIDRENA_CIJENA_FONT_SIZE_MOBILE_OPTION' => self::MOBILE_FONT_SIZE_OPTION,
            'SIDRENA_CIJENA_MOBILE_BREAKPOINT' => self::MOBILE_BREAKPOINT,
            'SIDRENA_CIJENA_DEFAULT_LABEL' => self::DEFAULT_LABEL,
            'SIDRENA_CIJENA_DEFAULT_FONT_SIZE' => self::DEFAULT_FONT_SIZE,
            'SIDRENA_CIJENA_DEFAULT_FONT_SIZE_MOBILE' => self::DEFAULT_MOBILE_FONT_SIZE,
            'SIDRENA_CIJENA_DEFAULT_FONT_FAMILY' => self::DEFAULT_FONT_FAMILY,
            'SIDRENA_CIJENA_DEFAULT_FONT_WEIGHT' => self::DEFAULT_FONT_WEIGHT,
            'SIDRENA_CIJENA_DEFAULT_FONT_STYLE' => self::DEFAULT_FONT_STYLE,
            'SIDRENA_CIJENA_DEFAULT_COLOR' => self::DEFAULT_COLOR,
        );

        foreach ($aliases as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
    }
}
