<?php

/**
 * Plugin Name: WP Map Block
 * Plugin URI: https://wpmapblock.com/
 * Description: All-in-one interactive map builder for WordPress. Build maps visually with markers, categories, clustering, shapes and dynamic data across Google Maps, OpenStreetMap, Mapbox, MapTiler, Esri and more — no API key to start.
 * Author: Kodezen
 * Author URI: https://kodezen.com/
 * Version: 3.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-map-block
 * Domain Path: /languages/
 *
 * Freemium deployment (StoreEngine): this repo is the full/pro source. The paths
 * below are premium-only — StoreEngine's freemium deployment STRIPS them to build
 * the free wordpress.org zip, and keeps them in the pro zip. The SDK + license
 * bootstrap under includes/Pro/License.php are intentionally NOT listed, so they
 * ship in both builds (a free site can still activate a licence and pull pro).
 * SE Premium Only: /includes/Pro/Loader.php, /includes/Pro/Features
 * @fs_premium_only /includes/Pro/Loader.php, /includes/Pro/Features
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Composer autoloader — loaded at most ONCE across both builds. The free
// (wp-map-block) and premium (wp-map-block-premium) zips bundle an identical
// vendor tree whose autoloader class name — ComposerAutoloaderInit<hash> — is
// derived from the shared composer.json, so it is the SAME in both. If both are
// active, the second include fatally redeclares that class ("Cannot declare
// class ... already in use") before any hook runs, which is what blocks the Pro
// build from activating alongside free. Guard with a shared sentinel: whichever
// build loads first registers the autoloader; the other reuses it (the vendor
// trees are identical, so the SDK it needs is already there). Once loaded, the
// single-instance enforcer / admin notice below retires the redundant copy.
if (!defined('WPMAPBLOCK_VENDOR_LOADED')) {
    define('WPMAPBLOCK_VENDOR_LOADED', true);
    if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
        require_once dirname(__FILE__) . '/vendor/autoload.php';
    }
}

// StoreEngine SDK — must be loaded at FILE SCOPE, not inside boot(). The SDK
// registers its version election on `plugins_loaded` at priorities 0 and 1, so
// requiring it inside boot() (which itself runs on plugins_loaded, priority 10)
// is already too late — those callbacks never fire, se_license_init() is never
// defined, and licensing silently dies (this is exactly what broke Pro when it
// ran alone, without the free build loading the SDK on its behalf). Requiring it
// here — before plugins_loaded fires — makes the version election immune to load
// order, per the SDK's own init.php header. Composer's autoload_files usually
// pulls this in via the require above; this direct require keeps it working even
// in a build where the SDK is dropped from the autoloader. The SDK's internal
// function_exists guard makes the double require_once a no-op.
if (file_exists(dirname(__FILE__) . '/vendor/storeengine/wordpress-sdk/init.php')) {
    require_once dirname(__FILE__) . '/vendor/storeengine/wordpress-sdk/init.php';
}

if (!class_exists('WPMapBlock')) {

    /**
     * Main plugin bootstrap. Keeps the historical singleton shape so the
     * upgrade from 2.x is seamless for the existing install base.
     */
    final class WPMapBlock
    {
        private static $instances = [];

        protected function __construct()
        {
            $this->define_constant();
            register_activation_hook(__FILE__, [$this, 'activate']);
            add_action('plugins_loaded', [$this, 'boot']);
        }

        public function define_constant()
        {
            define('WPMAPBLOCK_VERSION', '3.0.0');
            define('WPMAPBLOCK_PLUGIN_FILE', __FILE__);
            define('WPMAPBLOCK_PLUGIN_BASENAME', plugin_basename(__FILE__));
            define('WPMAPBLOCK_PLUGIN_SLUG', 'wp-map-block');
            define('WPMAPBLOCK_PLUGIN_ROOT_URI', plugins_url('/', __FILE__));
            define('WPMAPBLOCK_ROOT_DIR_PATH', plugin_dir_path(__FILE__));
            define('WPMAPBLOCK_ASSETS_DIR_PATH', WPMAPBLOCK_ROOT_DIR_PATH . 'assets/');
            define('WPMAPBLOCK_ASSETS_URI', WPMAPBLOCK_PLUGIN_ROOT_URI . 'assets/');
            define('WPMAPBLOCK_BUILD_URI', WPMAPBLOCK_PLUGIN_ROOT_URI . 'build/');
            define('WPMAPBLOCK_BUILD_DIR_PATH', WPMAPBLOCK_ROOT_DIR_PATH . 'build/');
            define('WPMAPBLOCK_REST_NAMESPACE', 'wpmb/v1');
            define('WPMAPBLOCK_POST_TYPE', 'wpmb_map');

            // ── Freemium (StoreEngine deployment) ─────────────────────────────
            // The SDK bootstrap + Pro feature modules already speak these
            // WPMB_PRO_* names. In the single-codebase model they map onto THIS
            // plugin: the SDK's product/slug identify the store product, while the
            // package file/dir are just this plugin. (Overridable via wp-config.)
            if (!defined('WPMB_PRO_PRODUCT_ID')) {
                define('WPMB_PRO_PRODUCT_ID', 399);
            }
            if (!defined('WPMB_PRO_LICENSE_SERVER')) {
                define('WPMB_PRO_LICENSE_SERVER', 'https://store.kodezen.com/');
            }
            define('WPMB_PRO_VERSION', WPMAPBLOCK_VERSION);
            define('WPMB_PRO_PLUGIN_FILE', WPMAPBLOCK_PLUGIN_FILE);
            define('WPMB_PRO_PLUGIN_BASENAME', WPMAPBLOCK_PLUGIN_BASENAME);
            define('WPMB_PRO_PLUGIN_SLUG', 'wp-map-block-pro');
            define('WPMB_PRO_DIR', WPMAPBLOCK_ROOT_DIR_PATH);
            define('WPMB_PRO_URI', WPMAPBLOCK_PLUGIN_ROOT_URI);
        }

        /**
         * Autoloader-free requires kept explicit for clarity during the rebuild.
         */
        public function boot()
        {
            $inc = WPMAPBLOCK_ROOT_DIR_PATH . 'includes/';

            require_once $inc . 'Config.php';
            require_once $inc . 'Pro.php';
            require_once $inc . 'Settings.php';
            require_once $inc . 'Presets.php';
            require_once $inc . 'Recommendations.php';
            require_once $inc . 'PostType.php';
            require_once $inc . 'Integrations/Manager.php';
            require_once $inc . 'api.php';
            require_once $inc . 'Rest/Maps_Controller.php';
            require_once $inc . 'Rest/Settings_Controller.php';
            require_once $inc . 'Rest/Data_Controller.php';
            require_once $inc . 'Rest/Integrations_Controller.php';
            require_once $inc . 'Rest/Promos_Controller.php';
            require_once $inc . 'Admin/Sdk.php';
            require_once $inc . 'Admin/Onboarding.php';
            require_once $inc . 'Frontend/Assets.php';
            require_once $inc . 'Frontend/DataSources.php';
            require_once $inc . 'Frontend/Renderer.php';
            require_once $inc . 'Frontend/Shortcode.php';
            require_once $inc . 'Blocks/Embed_Block.php';
            require_once $inc . 'Legacy/Block.php';

            WPMapBlock\PostType::init();
            WPMapBlock\Integrations\Manager::init();
            WPMapBlock\Rest\Maps_Controller::init();
            WPMapBlock\Rest\Settings_Controller::init();
            WPMapBlock\Rest\Data_Controller::init();
            WPMapBlock\Rest\Integrations_Controller::init();
            WPMapBlock\Rest\Promos_Controller::init();
            WPMapBlock\Admin\Onboarding::init();
            WPMapBlock\Frontend\Assets::init();
            WPMapBlock\Frontend\Shortcode::init();
            WPMapBlock\Blocks\Embed_Block::init();
            WPMapBlock\Legacy\Block::init();

            if (is_admin()) {
                require_once $inc . 'Admin/Menu.php';
                WPMapBlock\Admin\Menu::init();

                // Only ONE WP Map Block may run at a time, and the Pro build wins.
                // If a free and a Pro copy are both active (e.g. the free wp.org
                // plugin plus a separately-installed Pro), retire the free one —
                // and if that auto-deactivation didn't take, warn with a notice.
                add_action('admin_init', [$this, 'enforce_single_instance']);
                add_action('admin_notices', [$this, 'coexistence_notice']);
            }

            // Dev-only: unlock all Pro features so the team can build/test the Pro
            // UI without a licence. Opt-in via `define('WPMB_DEV_UNLOCK', true)`;
            // the dev/ folder is excluded from the release ZIP so it never ships.
            // Loaded BEFORE the Pro loader so its `has_feature`/`active` filters
            // are in place when feature modules decide whether to hook.
            if (defined('WPMB_DEV_UNLOCK') && WPMB_DEV_UNLOCK && file_exists(__DIR__ . '/dev/pro-unlock.php')) {
                require_once __DIR__ . '/dev/pro-unlock.php';
            }

            // ── Freemium (single-codebase) ────────────────────────────────────
            // Licensing/SDK ships in EVERY build (ECM-style) so a free site can
            // activate a licence and pull the Pro package. The premium loader +
            // feature modules live under the @fs_premium_only paths (see header)
            // and are stripped from the free zip — so guard their load: a stripped
            // build simply has no Pro, no fatal.
            //
            // NB: the StoreEngine SDK itself is required at FILE SCOPE near the top
            // of this file (its version election hooks plugins_loaded 0/1, which
            // have already fired by the time boot() runs at priority 10). Here we
            // only wire OUR code that sits on top of the SDK.
            if (file_exists($inc . 'Pro/License.php')) {
                require_once $inc . 'Pro/License.php';
                WPMapBlockPro\License::init();
            }
            if (file_exists($inc . 'Pro/Loader.php')) { // absent in the free zip
                require_once $inc . 'Pro/Loader.php';
                WPMapBlockPro\Loader::init();
            }

            // Extension point: integrations hook here to register features, data
            // sources and REST routes once free + Pro are both wired.
            do_action('wpmb/loaded');
        }

        /**
         * Enforce a single active WP Map Block, with the Pro build taking
         * precedence. A build is "Pro" when it carries the premium loader
         * (includes/Pro/Loader.php) — present in the pro package, stripped from
         * the free one. So when a customer buys Pro and installs it alongside the
         * free plugin, the free copy is automatically deactivated (and vice-versa
         * the free build stands down if a Pro copy is active). Same-slug installs
         * simply replace in place, so this is a no-op there.
         */
        public function enforce_single_instance()
        {
            if (!current_user_can('activate_plugins') || !is_admin()) {
                return;
            }

            $siblings = $this->find_sibling_maps();

            if ($this->is_pro_build()) {
                // I'm Pro — retire every other WP Map Block copy (free or pro).
                $retire = array_merge($siblings['free'], $siblings['pro']);
                if ($retire) {
                    deactivate_plugins($retire);
                }
            } elseif ($siblings['pro']) {
                // I'm the free build and a Pro copy is active — stand down.
                deactivate_plugins(WPMAPBLOCK_PLUGIN_BASENAME);
            }
        }

        /**
         * Fallback for when auto-deactivation couldn't run (e.g. the request that
         * activated Pro lacked caps, or a filter re-activated free): if this is
         * the Pro build and a free copy is still active, nag the admin to
         * deactivate it, with a one-click link. Shows nothing once the free copy
         * is gone — so enforce_single_instance() succeeding suppresses it.
         */
        public function coexistence_notice()
        {
            if (!current_user_can('activate_plugins') || !$this->is_pro_build()) {
                return;
            }
            $free = $this->find_sibling_maps()['free'];
            if (!$free) {
                return;
            }

            $basename = $free[0];
            $deactivate_url = wp_nonce_url(
                self_admin_url('plugins.php?action=deactivate&plugin=' . rawurlencode($basename)),
                'deactivate-plugin_' . $basename
            );

            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . esc_html__('WP Map Block Pro is active.', 'wp-map-block') . '</strong> ';
            echo esc_html__('The free WP Map Block plugin is also active and must be deactivated to avoid a conflict (both bundle the same libraries).', 'wp-map-block');
            echo ' <a href="' . esc_url($deactivate_url) . '" class="button button-small">'
                . esc_html__('Deactivate free version', 'wp-map-block') . '</a>';
            echo '</p></div>';
        }

        /** A build is "Pro" when it carries the premium loader (stripped from free). */
        protected function is_pro_build(): bool
        {
            return file_exists(WPMAPBLOCK_ROOT_DIR_PATH . 'includes/Pro/Loader.php');
        }

        /**
         * Every OTHER active "WP Map Block" plugin (not this file), split into
         * free vs pro by whether that copy carries the premium loader. Shared by
         * the single-instance enforcer and the fallback admin notice.
         *
         * @return array{free: string[], pro: string[]}
         */
        protected function find_sibling_maps(): array
        {
            if (!function_exists('get_plugin_data')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $self = WPMAPBLOCK_PLUGIN_BASENAME;
            $free = [];
            $pro  = [];
            foreach ((array) get_option('active_plugins', []) as $basename) {
                if ($basename === $self) {
                    continue;
                }
                $file = WP_PLUGIN_DIR . '/' . $basename;
                if (!file_exists($file)) {
                    continue;
                }
                $data = get_plugin_data($file, false, false);
                if (false === stripos((string) ($data['Name'] ?? ''), 'WP Map Block')) {
                    continue; // not one of ours
                }
                if (file_exists(dirname($file) . '/includes/Pro/Loader.php')) {
                    $pro[] = $basename;
                } else {
                    $free[] = $basename;
                }
            }

            return ['free' => $free, 'pro' => $pro];
        }

        public function activate()
        {
            require_once WPMAPBLOCK_ROOT_DIR_PATH . 'includes/PostType.php';
            WPMapBlock\PostType::register();
            flush_rewrite_rules();

            $is_first = !get_option('wpmb_first_install_time');
            if ($is_first) {
                add_option('wpmb_first_install_time', time());
            }
            update_option('wpmb_version', WPMAPBLOCK_VERSION);

            // Queue the first-run Setup screen; in-place updates are handled at
            // admin_init by Onboarding::detect_version_change().
            require_once WPMAPBLOCK_ROOT_DIR_PATH . 'includes/Admin/Onboarding.php';
            if ($is_first) {
                WPMapBlock\Admin\Onboarding::flag_first_install();
            }
        }

        protected function __clone() {}

        public function __wakeup()
        {
            throw new \Exception('Cannot unserialize singleton');
        }

        public static function getInstance()
        {
            $subclass = static::class;
            if (!isset(self::$instances[$subclass])) {
                self::$instances[$subclass] = new static();
            }
            return self::$instances[$subclass];
        }
    }
}

if (!function_exists('WPMapBlock_Start')) {
    function WPMapBlock_Start()
    {
        return WPMapBlock::getInstance();
    }
}

WPMapBlock_Start();
