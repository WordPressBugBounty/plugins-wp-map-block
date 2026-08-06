<?php

namespace WPMapBlock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Free ⇄ Pro bridge.
 *
 * The free plugin owns the catalog of Pro features (so it can render upgrade
 * teasers even when Pro isn't installed) and exposes a small filter surface the
 * Pro plugin hooks into to flip availability. Nothing here depends on the Pro
 * plugin existing — every answer degrades to "locked".
 *
 * Filters the Pro plugin implements:
 *   - wpmb/pro/active        (bool)              Pro plugin is loaded.
 *   - wpmb/pro/has_feature   (bool, string slug) Feature is licensed + unlocked.
 *   - wpmb/pro/features      (array)             Add/override feature catalog.
 *   - wpmb/pro/upgrade_url   (string)            Buy/upgrade URL.
 *   - wpmb/pro/manage_url    (string)            License management URL.
 */
class Pro
{
    /**
     * Catalog of Pro-gated capabilities. Each entry drives an in-app teaser
     * (title/description/icon) and maps to the builder tab it belongs to.
     */
    public static function features(): array
    {
        $features = [
            'dynamic-data' => [
                'title' => __('Dynamic data sources', 'wp-map-block'),
                'desc'  => __('Auto-populate markers from posts, pages, custom post types, ACF fields and WooCommerce/StoreEngine — the map updates as your content does.', 'wp-map-block'),
                'icon'  => 'dashicons-database',
                'tab'   => 'data',
            ],
            'store-locator' => [
                'title' => __('Store locator', 'wp-map-block'),
                'desc'  => __('Radius search, geolocation, filters and a results list so visitors can find their nearest location.', 'wp-map-block'),
                'icon'  => 'dashicons-search',
                'tab'   => 'search',
            ],
            'directions' => [
                'title' => __('Directions & routing', 'wp-map-block'),
                'desc'  => __('Add turn-by-turn directions and drive-time from each marker.', 'wp-map-block'),
                'icon'  => 'dashicons-arrow-right-alt',
                'tab'   => 'search',
            ],
            'heatmap' => [
                'title' => __('Heatmaps', 'wp-map-block'),
                'desc'  => __('Render markers as a density heatmap to reveal hotspots across large datasets.', 'wp-map-block'),
                'icon'  => 'dashicons-chart-area',
                'tab'   => 'display',
            ],
            'shapes' => [
                'title' => __('Polygons & polylines', 'wp-map-block'),
                'desc'  => __('Draw regions, routes and boundaries with styled polygons and polylines, right on the map.', 'wp-map-block'),
                'icon'  => 'dashicons-image-filter',
                'tab'   => 'shapes',
            ],
            'layers' => [
                'title' => __('Traffic, transit & bicycle layers', 'wp-map-block'),
                'desc'  => __('Overlay live traffic, transit lines and bicycling routes on Google-powered maps.', 'wp-map-block'),
                'icon'  => 'dashicons-admin-site-alt3',
                'tab'   => 'provider',
            ],
            'marker-animation' => [
                'title' => __('Marker animations', 'wp-map-block'),
                'desc'  => __('Bring markers to life with bounce, drop and pulse animations.', 'wp-map-block'),
                'icon'  => 'dashicons-marker',
                'tab'   => 'markers',
            ],
            'premium-providers' => [
                'title' => __('Premium map providers', 'wp-map-block'),
                'desc'  => __('Unlock Mapbox, MapTiler, OpenFreeMap, Stadia, Esri, CARTO & Azure — OpenStreetMap and Google Maps stay free.', 'wp-map-block'),
                'icon'  => 'dashicons-admin-site',
                'tab'   => 'provider',
            ],
        ];

        return apply_filters('wpmb/pro/features', $features);
    }

    /** Providers available on the free tier. Everything else is `premium-providers`. */
    public static function free_providers(): array
    {
        return apply_filters('wpmb/pro/free_providers', ['openstreetmap', 'google']);
    }

    /**
     * Pricing tiers shown on the in-app Compare / Upgrade page. Marketing data,
     * kept filterable so the store can adjust prices (or run a promo) without a
     * plugin rebuild. Each tier: slug, name, sites, price (sale), regular,
     * discount, and `featured` to highlight the recommended plan.
     */
    public static function plans(): array
    {
        $plans = [
            [
                'slug'     => 'personal',
                'name'     => __('Personal', 'wp-map-block'),
                'sites'    => __('1 Site', 'wp-map-block'),
                'price'    => '$39',
                'regular'  => '$119',
                'discount' => __('67% OFF', 'wp-map-block'),
                'featured' => false,
                'url'      => 'https://store.kodezen.com/checkout/?product_id=399&price_id=116',
            ],
            [
                'slug'     => 'business',
                'name'     => __('Business', 'wp-map-block'),
                'sites'    => __('10 Sites', 'wp-map-block'),
                'price'    => '$79',
                'regular'  => '$199',
                'discount' => __('60% OFF', 'wp-map-block'),
                'featured' => true,
                'url'      => 'https://store.kodezen.com/checkout/?product_id=399&price_id=117',
            ],
            [
                'slug'     => 'agency',
                'name'     => __('Agency', 'wp-map-block'),
                'sites'    => __('Unlimited', 'wp-map-block'),
                'price'    => '$129',
                'regular'  => '$399',
                'discount' => __('68% OFF', 'wp-map-block'),
                'featured' => false,
                'url'      => 'https://store.kodezen.com/checkout/?product_id=399&price_id=118',
            ],
        ];

        return apply_filters('wpmb/pro/plans', $plans);
    }

    /** Is the Pro plugin installed and active? */
    public static function is_active(): bool
    {
        return (bool) apply_filters('wpmb/pro/active', false);
    }

    /** Is a specific Pro feature unlocked (Pro active AND licensed)? */
    public static function has(string $feature): bool
    {
        return (bool) apply_filters('wpmb/pro/has_feature', false, $feature);
    }

    /** Should we surface upgrade CTAs? True while any catalog feature is locked. */
    public static function should_upsell(): bool
    {
        foreach (array_keys(self::features()) as $slug) {
            if (!self::has($slug)) {
                return true;
            }
        }
        return false;
    }

    /** Special launch-offer landing page, shown only during the 3-day window. */
    const OFFER_URL = 'https://wpmapblock.com/launch-offer/';

    /** Where upgrade CTAs point once the launch-offer window has closed. */
    const HOME_URL  = 'https://wpmapblock.com/';

    /**
     * Destination for every "Upgrade to Pro" CTA.
     *
     * While the 3-day launch-offer timer is still running, all upgrade links go
     * to the special offer page (OFFER_URL). The moment the window closes they
     * fall back to the plain homepage — never a lingering "special" page — so a
     * stale link can't keep pointing at an expired promo.
     */
    public static function upgrade_url(): string
    {
        $offer_active = self::should_upsell()
            && class_exists('\WPMapBlock\Admin\Onboarding')
            && null !== \WPMapBlock\Admin\Onboarding::promo_timer();

        $default = $offer_active ? self::OFFER_URL : self::HOME_URL;

        // $offer_active is passed as context so an integration (or Pro) can react
        // to whether the launch window is live without recomputing the timer.
        return (string) apply_filters('wpmb/pro/upgrade_url', $default, $offer_active);
    }

    /**
     * Server-side enforcement: strip Pro-gated capabilities from a map config
     * whenever they aren't unlocked. This is the real paywall — it runs at
     * render time, so removing the admin teaser or crafting a shortcode can't
     * bypass it. Each feature's base implementation stays dormant in the shared
     * engine until its capability flips on.
     */
    public static function sanitize_config(array $config): array
    {
        if (!self::has('dynamic-data') && !empty($config['dataSources'])) {
            $config['dataSources'] = [];
        }
        if (!self::has('store-locator') && isset($config['storeLocator']['enabled'])) {
            $config['storeLocator']['enabled'] = false;
        }
        if (!self::has('directions') && isset($config['directions']['enabled'])) {
            $config['directions']['enabled'] = false;
        }
        if (!self::has('heatmap') && isset($config['heatmap']['enabled'])) {
            $config['heatmap']['enabled'] = false;
        }
        if (!self::has('shapes') && !empty($config['shapes'])) {
            $config['shapes'] = [];
        }
        if (!self::has('layers') && !empty($config['layers'])) {
            $config['layers'] = ['traffic' => false, 'transit' => false, 'bicycle' => false];
        }
        if (!self::has('marker-animation') && isset($config['markerOptions']['animation'])) {
            $config['markerOptions']['animation'] = 'none';
        }
        if (!self::has('premium-providers') && !empty($config['provider']) && !in_array($config['provider'], self::free_providers(), true)) {
            $config['provider'] = 'openstreetmap';
        }

        return $config;
    }

    /** Everything the admin SPA needs to gate controls + render teasers. */
    public static function localized(): array
    {
        $active   = self::is_active();
        $features = [];
        foreach (self::features() as $slug => $meta) {
            $features[$slug] = array_merge($meta, ['available' => self::has($slug)]);
        }

        return [
            'active'         => $active,
            'upsell'         => self::should_upsell(),
            // Licence validity (distinct from feature access — in a pro build
            // features are unlocked regardless; this drives the "Activate license"
            // prompt for updates). False in the free build / when unlicensed.
            'licensed'       => (bool) apply_filters('wpmb/pro/licensed', false),
            'features'       => $features,
            // Lifetime pricing tiers for the in-app Compare / Upgrade page.
            'plans'          => self::plans(),
            // Providers usable without Pro — used to badge the rest as "Pro".
            'freeProviders'  => self::free_providers(),
            'upgradeUrl'     => self::upgrade_url(),
            // In-admin Compare / Upgrade page (the dedicated submenu route). Used
            // by upgrade CTAs that should land on the in-app plan comparison
            // rather than leaving for the marketing site.
            'compareUrl'     => admin_url('admin.php?page=wp-map-block-compare'),
            'manageUrl'      => (string) apply_filters('wpmb/pro/manage_url', ''),
            // SDK license bridge (rest url + nonce + status) so the React Settings
            // page can render the license UI. Null until Pro + a product id.
            'sdk'            => apply_filters('wpmb/pro/sdk', null),
        ];
    }
}
