<?php

namespace WPMapBlock\Admin;

use WPMapBlock\Config;
use WPMapBlock\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the top-level "Maps" admin page and boots the React builder SPA.
 */
class Menu
{
    const SLUG          = 'wp-map-block';
    const SLUG_SETTINGS = 'wp-map-block-settings';
    const SLUG_DISCOVER = 'wp-map-block-discover';
    const SLUG_COMPARE  = 'wp-map-block-compare';

    public static function init()
    {
        add_action('admin_menu', [self::class, 'register']);
        // The menu lives on every admin screen, so the icon styling loads
        // globally (not just on our pages).
        add_action('admin_head', [self::class, 'menu_icon_style']);
    }

    /**
     * The WP Map Block brand marker in full colour — a teardrop pin (brand
     * blue), a white ring and a blue dot, matching the plugin logo. WordPress
     * renders a data-URI menu icon as-is, so the colours carry through.
     */
    private static function menu_icon_svg()
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="9 4 82 110">'
            . '<path fill="#006bff" d="M50 6C28.4 6 11 23.4 11 45c0 23 39 65 39 65s39-42 39-65C89 23.4 71.6 6 50 6z"/>'
            . '<circle cx="50" cy="45" r="21" fill="#fff"/>'
            . '<circle cx="50" cy="45" r="9.5" fill="#006bff"/></svg>';
    }

    /** Base64 data-URI of the marker — the icon passed to add_menu_page(). */
    private static function menu_icon_data_uri()
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::menu_icon_svg());
    }

    /**
     * Paint the colourful marker ourselves via a ::before background. We can't
     * rely on the add_menu_page() icon alone: admin colour-scheme / white-label
     * features on some sites decode that data-URI and repaint every fill a flat
     * grey, which would strip our brand blue. A CSS-level data-URI is left
     * untouched, so the pin stays full colour and full opacity in every state.
     */
    public static function menu_icon_style()
    {
        $icon = 'url("data:image/svg+xml,' . rawurlencode(self::menu_icon_svg()) . '") no-repeat center';
        $sel  = '#adminmenu #toplevel_page_' . self::SLUG;
        ?>
        <style id="wpmb-menu-icon">
            <?php echo esc_html($sel); ?> .wp-menu-image { background: none !important; opacity: 1 !important; }
            <?php echo esc_html($sel); ?> .wp-menu-image::before {
                content: "";
                display: block;
                width: 20px;
                height: 20px;
                /* Parent .wp-menu-image already has padding:7px 0 — horizontal
                   auto-centre only, so the pin lines up with native icons. */
                margin: 0 auto;
                opacity: 1 !important;
                background: <?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
                background-size: contain;
            }
        </style>
        <?php
    }

    public static function register()
    {
        $hook = add_menu_page(
            __('WP Map Block', 'wp-map-block'),
            __('WP Map Block', 'wp-map-block'),
            'edit_posts',
            self::SLUG,
            [self::class, 'render_root'],
            self::menu_icon_data_uri(),
            26
        );

        // Rename the auto-generated first submenu (the map library), then add the
        // Settings child. Top-level menu is the brand; this item is the map list.
        add_submenu_page(
            self::SLUG,
            __('Maps', 'wp-map-block'),
            __('Maps', 'wp-map-block'),
            'edit_posts',
            self::SLUG,
            [self::class, 'render_root']
        );
        $settings_hook = add_submenu_page(
            self::SLUG,
            __('Map Settings', 'wp-map-block'),
            __('Settings', 'wp-map-block'),
            'manage_options',
            self::SLUG_SETTINGS,
            [self::class, 'render_root']
        );
        $discover_hook = add_submenu_page(
            self::SLUG,
            __('Discover', 'wp-map-block'),
            '<span style="color:#ffb200">' . esc_html__('Discover ✨', 'wp-map-block') . '</span>',
            'edit_posts',
            self::SLUG_DISCOVER,
            [self::class, 'render_root']
        );

        // Gold "Upgrade to Pro" child — only while there's something to unlock.
        $compare_hook = null;
        if (\WPMapBlock\Pro::should_upsell()) {
            $compare_hook = add_submenu_page(
                self::SLUG,
                __('Upgrade to Pro', 'wp-map-block'),
                '<span style="color:#f0a020;font-weight:600">' . esc_html__('⚡ Upgrade to Pro', 'wp-map-block') . '</span>',
                'edit_posts',
                self::SLUG_COMPARE,
                [self::class, 'render_root']
            );
        }

        add_action('load-' . $hook, [self::class, 'enqueue']);
        if ($settings_hook) {
            add_action('load-' . $settings_hook, [self::class, 'enqueue']);
        }
        if ($discover_hook) {
            add_action('load-' . $discover_hook, [self::class, 'enqueue']);
        }
        if ($compare_hook) {
            add_action('load-' . $compare_hook, [self::class, 'enqueue']);
        }
        // Keep our full-screen builder clean: strip third-party admin notices
        // that other plugins inject on every admin screen.
        add_action('in_admin_header', [self::class, 'suppress_notices'], 1000);
    }

    /**
     * Remove all admin notices while on the WP Map Block screen so the builder
     * gets the full canvas (other plugins spam notices on every page).
     */
    public static function suppress_notices()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, self::SLUG) !== 0) {
            return;
        }
        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('user_admin_notices');
        remove_all_actions('network_admin_notices');
    }

    public static function render_root()
    {
        echo '<div id="wpmb-admin-root"></div>';
    }

    private static function initial_route($page)
    {
        if ($page === self::SLUG_SETTINGS) {
            return 'settings';
        }
        if ($page === self::SLUG_DISCOVER) {
            return 'discover';
        }
        if ($page === self::SLUG_COMPARE) {
            return 'compare';
        }
        return 'list';
    }

    public static function enqueue()
    {
        // Media library powers custom marker icon uploads.
        wp_enqueue_media();

        $asset_file = WPMAPBLOCK_BUILD_DIR_PATH . 'admin.asset.php';
        $asset = file_exists($asset_file) ? include $asset_file : ['dependencies' => [], 'version' => WPMAPBLOCK_VERSION];

        wp_enqueue_script(
            'wpmb-admin',
            WPMAPBLOCK_BUILD_URI . 'admin.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'wpmb-admin',
            WPMAPBLOCK_BUILD_URI . 'style-admin.css',
            [],
            $asset['version']
        );

        // MapLibre GL stylesheet for the in-builder live preview.
        wp_enqueue_style(
            'wpmb-maplibre',
            WPMAPBLOCK_BUILD_URI . 'maplibre.css',
            [],
            $asset['version']
        );

        // Frontend styles (markers, popups, directory/list) so the builder
        // preview renders those overlays exactly as visitors see them.
        wp_enqueue_style(
            'wpmb-frontend',
            WPMAPBLOCK_BUILD_URI . 'style-frontend.css',
            [],
            $asset['version']
        );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        // New maps start on the site-wide default provider chosen in Settings.
        $settings = Settings::get();
        $defaults = Config::defaults();
        $providers = Config::providers();
        if (!empty($settings['defaultProvider']) && isset($providers[$settings['defaultProvider']])) {
            $defaults['provider'] = $settings['defaultProvider'];
        }

        wp_localize_script('wpmb-admin', 'WPMB_ADMIN', [
            'restUrl'   => esc_url_raw(rest_url(WPMAPBLOCK_REST_NAMESPACE)),
            'nonce'     => wp_create_nonce('wp_rest'),
            'adminUrl'  => admin_url('admin.php?page=' . self::SLUG),
            'providers'    => $providers,
            'defaults'     => $defaults,
            'settings'     => $settings,
            'presets'      => \WPMapBlock\Presets::all(),
            'promos'       => \WPMapBlock\Recommendations::all(),
            'ecosystem'    => \WPMapBlock\Recommendations::ecosystem(),
            'bundle'       => \WPMapBlock\Recommendations::bundle(),
            'gift'         => \WPMapBlock\Recommendations::gift(),
            // Whether usage-tracking consent already exists (SDK opt-in notice /
            // Setup wizard). The gift uses this to skip its own opt-in layer.
            'optedIn'      => \WPMapBlock\Admin\Sdk::has_opted_in(),
            'canManage'    => current_user_can('manage_options'),
            'initialRoute' => self::initial_route($page),
            'onboarding'   => Onboarding::localized_data(),
            'pro'          => \WPMapBlock\Pro::localized(),
        ]);

        wp_set_script_translations('wpmb-admin', 'wp-map-block');
    }
}
