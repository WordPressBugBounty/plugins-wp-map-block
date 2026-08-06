<?php

namespace WPMapBlock\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers and lazily enqueues the frontend engine bundle. The script is only
 * loaded on pages that actually render a map, keeping the footprint at zero
 * elsewhere (a core goal of the "high performance" rebuild).
 */
class Assets
{
    private static $registered = false;

    public static function init()
    {
        add_action('wp_enqueue_scripts', [self::class, 'register']);
    }

    public static function register()
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $asset_file = WPMAPBLOCK_BUILD_DIR_PATH . 'frontend.asset.php';
        $asset = file_exists($asset_file) ? include $asset_file : ['dependencies' => [], 'version' => WPMAPBLOCK_VERSION];

        wp_register_script(
            'wpmb-frontend',
            WPMAPBLOCK_BUILD_URI . 'frontend.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_register_style(
            'wpmb-frontend',
            WPMAPBLOCK_BUILD_URI . 'style-frontend.css',
            [],
            $asset['version']
        );

        wp_register_style(
            'wpmb-maplibre',
            WPMAPBLOCK_BUILD_URI . 'maplibre.css',
            [],
            $asset['version']
        );
    }

    /**
     * Called by any renderer to guarantee the engine assets are on the page.
     */
    public static function enqueue_frontend()
    {
        if (!self::$registered) {
            self::register();
        }
        wp_enqueue_style('wpmb-maplibre');
        wp_enqueue_style('wpmb-frontend');
        wp_enqueue_script('wpmb-frontend');
    }
}
