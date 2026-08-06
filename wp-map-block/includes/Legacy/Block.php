<?php

namespace WPMapBlock\Legacy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Backward-compat layer for the original `wpmapblock/wp-map-block` block.
 *
 * The 20k existing installs have posts containing this dynamic block. We keep
 * its registration, render callback and Leaflet assets byte-identical to 2.0.4
 * so upgrading to the new all-in-one plugin never breaks an existing map.
 *
 * All legacy assets live under assets/legacy/ (copied verbatim from 2.0.4).
 */
class Block
{
    private static function legacy_uri()
    {
        return WPMAPBLOCK_ASSETS_URI . 'legacy/';
    }

    private static function legacy_path()
    {
        return WPMAPBLOCK_ASSETS_DIR_PATH . 'legacy/';
    }

    public static function init()
    {
        add_action('init', [self::class, 'register_assets']);
        add_action('init', [self::class, 'register_block']);
        add_action('wp_enqueue_scripts', [self::class, 'register_core_scripts']);
    }

    public static function register_core_scripts()
    {
        wp_register_script('wpmapblock-leaflet', self::legacy_uri() . 'js/leaflet.js', ['jquery'], WPMAPBLOCK_VERSION, true);
        wp_register_script('wpmapblock-leaflet-fullscreen', self::legacy_uri() . 'js/Control.FullScreen.js', ['jquery'], WPMAPBLOCK_VERSION, true);
    }

    public static function register_assets()
    {
        // Leaflet also needs registering on init so the editor + render path can enqueue.
        wp_register_script('wpmapblock-leaflet', self::legacy_uri() . 'js/leaflet.js', ['jquery'], WPMAPBLOCK_VERSION, true);
        wp_register_script('wpmapblock-leaflet-fullscreen', self::legacy_uri() . 'js/Control.FullScreen.js', ['jquery'], WPMAPBLOCK_VERSION, true);

        $frontend = self::legacy_path() . 'dist/wpmapblock-frontend.core.min.asset.php';
        $frontend = file_exists($frontend) ? include $frontend : ['dependencies' => [], 'version' => WPMAPBLOCK_VERSION];

        wp_register_style('wp-map-block-stylesheets', self::legacy_uri() . 'dist/wpmapblock-frontend.core.min.css', [], $frontend['version']);
        wp_register_script('wp-map-block-frontend-js', self::legacy_uri() . 'dist/wpmapblock-frontend.core.min.js', $frontend['dependencies'], $frontend['version'], true);

        // The original 2.x editor bundle (old react-leaflet/React) crashes in the
        // modern block editor. We register a safe replacement editor built from
        // src/frontend/legacy.js: it renders the classic block as a live iframe
        // preview (through the same Leaflet render path as the frontend) plus a
        // few controls, hides the block from the inserter, and keeps the dynamic
        // render + attributes intact so every existing post stays valid.
        $legacy_edit = WPMAPBLOCK_BUILD_DIR_PATH . 'legacy.asset.php';
        if (file_exists($legacy_edit)) {
            $legacy_edit = include $legacy_edit;
            wp_register_script(
                'wpmb-legacy-edit',
                WPMAPBLOCK_BUILD_URI . 'legacy.js',
                $legacy_edit['dependencies'],
                $legacy_edit['version'],
                true
            );
            // Leaflet's stylesheet (bundled from the editor JS) — needed so the
            // draggable map inside the marker modal is styled correctly.
            if (file_exists(WPMAPBLOCK_BUILD_DIR_PATH . 'legacy.css')) {
                wp_register_style('wpmb-legacy-edit', WPMAPBLOCK_BUILD_URI . 'legacy.css', [], $legacy_edit['version']);
            }
            wp_set_script_translations('wpmb-legacy-edit', 'wp-map-block');
            wp_localize_script('wpmb-legacy-edit', 'WPMB_LEGACY', [
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'previewNonce'  => wp_create_nonce('wpmb_preview'),
                'markerIconUrl' => self::legacy_uri() . 'images/marker-icon.png',
            ]);
        }
    }

    public static function register_block()
    {
        register_block_type('wpmapblock/wp-map-block', [
            'editor_style_handles'  => ['wpmb-legacy-edit', 'wp-map-block-stylesheets'],
            'editor_script_handles' => ['wpmb-legacy-edit'],
            'render_callback'       => [self::class, 'render_callback'],
            // Declare the attribute schema server-side too (identical to the JS
            // block). Without it, WordPress can hand the editor/render an undefined
            // map_marker_list for older posts, which crashed the block ("cannot be
            // previewed") and broke the frontend. With the schema, defaults are
            // always applied so map_marker_list is a real array everywhere.
            'attributes'            => [
                'map_id'            => ['type' => 'string'],
                'map_marker_list'   => ['type' => 'array', 'default' => []],
                'map_zoom'          => ['type' => 'number', 'default' => 10],
                'map_type'          => ['type' => 'string', 'default' => 'GM'],
                'map_width'         => ['type' => 'number', 'default' => 100],
                'map_height'        => ['type' => 'number', 'default' => 500],
                'scroll_wheel_zoom' => ['type' => 'boolean', 'default' => false],
                'center_index'      => ['type' => 'number', 'default' => 0],
            ],
        ]);
    }

    private static function escaping_array_data($array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = self::escaping_array_data($value);
            } else {
                $value = esc_attr(sanitize_text_field($value));
            }
        }
        return $array;
    }

    public static function render_callback($attributes, $content = '')
    {
        wp_enqueue_style('wp-map-block-stylesheets');
        wp_enqueue_script('wpmapblock-leaflet');
        wp_enqueue_script('wpmapblock-leaflet-fullscreen');
        wp_enqueue_script('wp-map-block-frontend-js');

        $settings = [
            'map_marker'        => self::escaping_array_data(isset($attributes['map_marker_list']) ? $attributes['map_marker_list'] : []),
            'map_zoom'          => (isset($attributes['map_zoom']) ? esc_attr($attributes['map_zoom']) : 10),
            'scroll_wheel_zoom' => (isset($attributes['scroll_wheel_zoom']) ? esc_attr($attributes['scroll_wheel_zoom']) : false),
            'map_type'          => (isset($attributes['map_type']) ? esc_attr($attributes['map_type']) : 'GM'),
            'center_index'      => (isset($attributes['center_index']) ? intval(esc_attr($attributes['center_index'])) : 0),
        ];

        $map_width  = (isset($attributes['map_width']) ? esc_attr($attributes['map_width']) . '%' : '100%');
        $map_height = (isset($attributes['map_height']) ? esc_attr($attributes['map_height']) . 'px' : '500px');
        $style      = "width: {$map_width}; height: {$map_height};";

        ob_start(); ?>
        <div
            id="<?php echo (isset($attributes['map_id']) ? esc_attr($attributes['map_id']) : ''); ?>"
            data-settings='<?php echo esc_attr(wp_json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'
            class="wpmapblockrender"
            style="<?php echo esc_attr($style); ?>">
        </div>
        <?php
        return ob_get_clean();
    }
}
