<?php

namespace WPMapBlockPro;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * StoreEngine SDK bootstrap for WP Map Block Pro (licensing, updates, insights).
 *
 * The SDK client is created once and shared with the free plugin through the
 * `wpmb/sdk/client` filter (see WPMapBlock\Admin\Sdk) so the setup wizard's
 * opt-in routes through OUR product's insights, not a host plugin's.
 *
 * DEPLOYMENT DETECTION: StoreEngine's freemium deployment recognises the bundled
 * SDK by scanning package .php files (in 1 KB chunks) for the literal token
 * `se_license_init( [`. This early mention guarantees the token appears near the
 * top of the file so it can never fall on a chunk boundary — do not remove it,
 * and keep the real call below using the same spacing.
 */
class License
{
    /** @var \SE_License_SDK_Client|null */
    protected static $client = null;

    /** Admin page slug the SDK registers the license screen under. */
    const LICENSE_PAGE_SLUG = 'wp-map-block-license';

    /** React Settings page slug (see WPMapBlock\Admin\Menu::SLUG_SETTINGS). */
    const SETTINGS_PAGE_SLUG = 'wp-map-block-settings';

    public static function init(): void
    {
        // Hand our client to the free plugin's SDK seam. Registering the filter is
        // cheap and does NOT build the client — that happens lazily on first read.
        add_filter('wpmb/sdk/client', [__CLASS__, 'client']);

        /*
         * Build the SDK client on `init`, NOT here.
         *
         * init() runs from boot() on plugins_loaded. client() composes the SDK
         * config, which carries translated menu labels (__()), so building it
         * here called __() before `init` — tripping WP 6.7's
         * "_load_textdomain_just_in_time … triggered too early" notice. With
         * WP_DEBUG_DISPLAY on, that notice starts output and the post-activation
         * wp_redirect() then fails with "Cannot modify header information —
         * headers already sent".
         *
         * Deferring is safe: the SDK's own pre-init hooks are registered at file
         * scope when its init.php loads (plugins_loaded priorities 0 and 1) and
         * don't depend on when se_license_init() runs. Everything the client
         * itself registers (admin_menu 999, admin_init, rest_api_init) fires
         * after `init`, so priority 1 here is early enough for all of it.
         */
        add_action('init', [__CLASS__, 'boot_client'], 1);
    }

    /**
     * Build the SDK client and wire the (pro-only) menu / admin-bar / plugin-row
     * tweaks that depend on it. Runs on `init` so the translated labels inside
     * the SDK config resolve at a point where the text domain may legally load.
     */
    public static function boot_client(): void
    {
        // Build the client — insights/opt-in in every build, licensing in pro.
        // Bail if the SDK or product id isn't available.
        if (!is_object(self::client())) {
            return;
        }

        // License-management UI only exists in the PRO build; the free build
        // boots the SAME client for insights/opt-in only (is_free, no License
        // page), so the tweaks below are pro-only.
        if (!self::is_pro_build()) {
            return;
        }

        // Hide the "Maps → License" submenu item (the SDK registers it at
        // priority 999). The page stays reachable by URL for the admin-bar
        // button below — remove_submenu_page only drops the visible item.
        add_action('admin_menu', [__CLASS__, 'hide_license_submenu'], 1000);
        add_action('network_admin_menu', [__CLASS__, 'hide_license_submenu'], 1000);

        // Show a top-bar "Activate License" button while the licence is not
        // active, so admins can still reach the (now hidden) activation page.
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar_activate_button'], 100);

        // The SDK adds an "Activate License" / "Manage License" action link to
        // our plugin row that points at its own bare license page. Rewrite it to
        // land on the React Settings → License section (a nicer, on-brand UI),
        // which we already surface the SDK license bridge into. Runs after the
        // SDK's own filter (default priority 10).
        add_filter('plugin_action_links_' . WPMB_PRO_PLUGIN_BASENAME, [__CLASS__, 'retarget_license_action_link'], 20);
    }

    /** Deep link to the React Settings page's License section (App scrolls there). */
    public static function settings_license_url(): string
    {
        return admin_url('admin.php?page=' . self::SETTINGS_PAGE_SLUG) . '#/settings/license';
    }

    /**
     * Rewrite the SDK-added license action link on our plugin row so it points at
     * the Settings → License section instead of the SDK's standalone page. We match
     * the link by its href (the hidden SDK license slug) so both the "Activate
     * License" and "Manage License" labels are covered without string-matching i18n.
     *
     * @param array $links plugin action links.
     * @return array
     */
    public static function retarget_license_action_link(array $links): array
    {
        $target = esc_url(self::settings_license_url());
        foreach ($links as $key => $html) {
            if (is_string($html) && strpos($html, 'page=' . self::LICENSE_PAGE_SLUG) !== false) {
                $links[$key] = preg_replace('/href="[^"]*"/', 'href="' . $target . '"', $html, 1);
            }
        }

        return $links;
    }

    /**
     * Remove the SDK's "License" submenu item from under Maps. The page callback
     * remains registered, so admin.php?page=wp-map-block-license still renders.
     */
    public static function hide_license_submenu(): void
    {
        remove_submenu_page('wp-map-block', self::LICENSE_PAGE_SLUG);
    }

    /** URL of the (hidden) license activation screen. */
    public static function license_page_url(): string
    {
        return admin_url('admin.php?page=' . self::LICENSE_PAGE_SLUG);
    }

    /**
     * Add a top-bar button prompting license activation — only when there is no
     * active license and the current user can manage the site.
     *
     * @param \WP_Admin_Bar $wp_admin_bar
     */
    public static function admin_bar_activate_button($wp_admin_bar): void
    {
        if (!current_user_can('manage_options') || self::is_valid()) {
            return;
        }

        $wp_admin_bar->add_node([
            'id'    => 'wpmb-activate-license',
            'title' => '<span class="ab-icon dashicons dashicons-admin-network" style="top:2px;"></span>'
                . esc_html__('Activate WP Map Block Pro', 'wp-map-block'),
            // Land on the React Settings → License section (same UI as the plugin-row
            // link) rather than the SDK's bare, now-hidden activation page.
            'href'  => self::settings_license_url(),
            'meta'  => [
                'title' => __('Activate your WP Map Block Pro license to unlock premium features', 'wp-map-block'),
            ],
        ]);
    }

    /**
     * Is this the pro build? The premium Loader (includes/Pro/Loader.php) is
     * stripped from the free zip, so its presence is the single runtime marker
     * that separates pro from free — the same signal wp-map-block.php uses. It
     * drives the SDK config below: the pro build overrides the free (insights-
     * only) configuration with full licensing + updates.
     */
    public static function is_pro_build(): bool
    {
        return file_exists(WPMB_PRO_DIR . 'includes/Pro/Loader.php');
    }

    /** Lazily build (and cache) the SDK client. Null if the SDK isn't loaded. */
    public static function client()
    {
        if (null !== self::$client) {
            return self::$client;
        }
        // Stay inert until the store product id is configured — avoids licence
        // checks / notices / network calls against a product that doesn't exist
        // yet. Define WPMB_PRO_PRODUCT_ID (wp-config or a small mu-plugin) to go
        // live; features remain teased until then.
        if (!WPMB_PRO_PRODUCT_ID || !function_exists('se_license_init')) {
            return null;
        }

        // The config below contains __() strings, so building the client before
        // `init` trips WP 6.7's too-early-textdomain notice. boot_client() builds
        // it on `init`; this guard stops any *other* caller (e.g. something
        // reading the `wpmb/sdk/client` filter during plugins_loaded) from
        // forcing it earlier. Nothing needs the client before `init` — every
        // consumer runs on admin_menu, admin_init or rest_api_init.
        if (!did_action('init')) {
            return null;
        }

        // Freemium: ONE product id (399) for both builds. The premium Loader is
        // bundled only in the pro zip, so its presence flips the SDK from the
        // free insights-only config to the full licensing + updater config.
        $is_pro = self::is_pro_build();

        // NB: the `se_license_init( [` spacing is load-bearing — StoreEngine's
        // deployment detects the SDK by scanning package files for that exact
        // literal (or `se_license_init( array(`). Without the spaces it reports
        // "Uploaded zip does not contain StoreEngine WordPress SDK". Keep WP-CS
        // spacing here even though the rest of this file is space-tight.
        self::$client = se_license_init( [
            'package_file'        => WPMB_PRO_PLUGIN_FILE,
            'package_name'        => $is_pro ? 'WP Map Block Pro' : 'WP Map Block',
            'product_id'          => (int) WPMB_PRO_PRODUCT_ID,
            // Single-codebase freemium. The pro zip carries the premium modules →
            // is_free:false so the SDK does licensing + delivers the Pro package.
            // The free zip (premium paths stripped) → is_free:true: insights/opt-in
            // only, no licence checks, and updates come from wp.org.
            'is_free'             => !$is_pro,
            // Only the pro build runs the updater (pulls the premium package for
            // this slug); the free build updates through wp.org.
            'use_update'          => $is_pro,
            'slug'                => WPMB_PRO_PLUGIN_SLUG,
            'basename'            => WPMB_PRO_PLUGIN_BASENAME,
            'package_type'        => 'plugin',
            'package_version'     => WPMB_PRO_VERSION,
            'allow_local'         => true,
            // The StoreEngine SDK is git-ignored in this repo and bundled at build
            // time; if a mis-built update package dropped it the plugin would fatal.
            // List it as critical so the SDK aborts such an update BEFORE swapping
            // the live folder, rather than after.
            'critical_paths'      => ['vendor/storeengine/wordpress-sdk/init.php'],
            // Pro only: array form → the SDK REGISTERS a "License" page (Maps →
            // License) for entering/activating the key. Free has no licence page,
            // so pass false (no menu, no REST licence routes).
            'menu'                => $is_pro ? [
                'type'        => 'submenu',
                'parent_slug' => 'wp-map-block',
                'page_title'  => __('WP Map Block License', 'wp-map-block'),
                'menu_title'  => __('License', 'wp-map-block'),
                'capability'  => 'manage_options',
                'menu_slug'   => 'wp-map-block-license',
            ] : false,
            'init_restapi'        => $is_pro,
            // Usage insights + opt-in run in BOTH builds — this is the free
            // version's opt-in channel. Our Setup wizard / founding-member gift is
            // the opt-in surface, so suppress the SDK's own opt-in admin notice.
            'init_insights'       => true,
            'should_show_optin'   => false,
            'license_server'      => WPMB_PRO_LICENSE_SERVER,
            'purchase_url'        => 'https://wpmapblock.com/pricing/',
            'store_dashboard_url' => 'https://store.kodezen.com/dashboard/license-keys/',
            'terms_url'           => 'https://kodezen.com/terms-conditions/',
            'privacy_policy_url'  => 'https://kodezen.com/privacy-policy/',
            'support_url'         => 'https://wpmapblock.com/support/',
            'primary_color'       => '#006bff',
            'first_install_time'  => get_option('wpmb_first_install_time') ?: null,
            'optin_notice_delay'  => 3 * DAY_IN_SECONDS,
        ]);

        return self::$client;
    }

    /**
     * Is there a valid license? Falls back to false whenever the SDK, the
     * product id, or the license API can't confirm activation — so unlicensed
     * installs stay in the teased (locked) state.
     */
    public static function is_valid(): bool
    {
        $client = self::client();
        if (!is_object($client) || !WPMB_PRO_PRODUCT_ID) {
            return false;
        }

        try {
            // license(false) = don't redirect on activation; get_license() reads
            // the cached license array whose 'status' the SDK keeps in sync with
            // the store (with a grace period on transient outages).
            $data = $client->license(false)->get_license();
            return isset($data['status']) && 'active' === $data['status'];
        } catch (\Throwable $e) {
            return false;
        }
    }
}
