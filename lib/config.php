<?php
/**
 * WebTick configuration loader.
 *
 * Looks for an INI file at ../webtick.ini (i.e. one directory above the
 * webtick project root — outside the web document root). If it's missing,
 * unreadable, or leaves a setting unspecified, that setting falls back to
 * the built-in default below so the app keeps working out of the box.
 *
 * See webtick.ini.example for the full, documented format.
 */

function webtick_config_path(): string {
    return dirname(__DIR__, 2) . '/webtick.ini';
}

function webtick_default_config(): array {
    return [
        'tool' => [
            'python_bin'  => '/usr/bin/python3',
            'script_path' => '/opt/sage/local/platform/scripts/sysmatt.escpos.ticket.print',
        ],
        'printer' => [
            'default_queue' => 'CITIZEN_CT_S310_clocky4',
            'widths'        => [384, 576, 832],
            'default_width' => 576,
            'impls'         => ['bitImageRaster', 'graphics', 'bitImageColumn'],
            'default_impl'  => 'bitImageRaster',
            'default_cut'   => true,
            'default_beep'  => false,
        ],
        'rendering' => [
            'new_text_render' => false,
        ],
        'fonts' => [
            'liberation-sans'        => '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'liberation-sans-bold'   => '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            'liberation-sans-italic' => '/usr/share/fonts/truetype/liberation/LiberationSans-Italic.ttf',
            'liberation-mono'        => '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
            'liberation-mono-bold'   => '/usr/share/fonts/truetype/liberation/LiberationMono-Bold.ttf',
            'liberation-serif'       => '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
            'liberation-serif-bold'  => '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf',
            'ubuntu'                 => '/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf',
            'ubuntu-bold'            => '/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf',
            'ubuntu-mono'            => '/usr/share/fonts/truetype/ubuntu/UbuntuMono-R.ttf',
        ],
        'font_defaults' => [
            'header' => '',
            'title'  => '',
            'body'   => '',
        ],
    ];
}

/**
 * Parses a comma-separated ini value into a trimmed, non-empty list.
 * Accepts either a string ("384,576,832") or an array (already parsed).
 */
function webtick_parse_list($value): array {
    if (is_array($value)) return $value;
    $parts = array_map('trim', explode(',', (string)$value));
    return array_values(array_filter($parts, fn($p) => $p !== ''));
}

function webtick_load_config(): array {
    $config = webtick_default_config();
    $path = webtick_config_path();

    if (!is_readable($path)) {
        return $config;
    }

    $ini = @parse_ini_file($path, true, INI_SCANNER_TYPED);
    if ($ini === false) {
        return $config;
    }

    if (isset($ini['tool']) && is_array($ini['tool'])) {
        $config['tool'] = array_merge($config['tool'], $ini['tool']);
    }

    if (isset($ini['printer']) && is_array($ini['printer'])) {
        $config['printer'] = array_merge($config['printer'], $ini['printer']);
        $config['printer']['widths'] = array_map('intval', webtick_parse_list($config['printer']['widths']));
        $config['printer']['impls']  = webtick_parse_list($config['printer']['impls']);
        $config['printer']['default_width'] = (int)$config['printer']['default_width'];
        $config['printer']['default_cut']   = (bool)$config['printer']['default_cut'];
        $config['printer']['default_beep']  = (bool)$config['printer']['default_beep'];
    }

    if (isset($ini['rendering']) && is_array($ini['rendering'])) {
        $config['rendering'] = array_merge($config['rendering'], $ini['rendering']);
        $config['rendering']['new_text_render'] = (bool)$config['rendering']['new_text_render'];
    }

    if (isset($ini['fonts']) && is_array($ini['fonts'])) {
        $config['fonts'] = array_merge($config['fonts'], $ini['fonts']);
    }

    if (isset($ini['font_defaults']) && is_array($ini['font_defaults'])) {
        $config['font_defaults'] = array_merge($config['font_defaults'], $ini['font_defaults']);
    }

    return $config;
}
