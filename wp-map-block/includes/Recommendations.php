<?php

namespace WPMapBlock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cross-promotion engine for the Kodezen ecosystem. Surfaces contextually-relevant
 * products to map builders (a "Discover" page + inline slots + peak-moment
 * tips). Tasteful by design: already-active products are hidden, dismissals are
 * remembered per-user, every link is UTM-tagged, and impressions/clicks are
 * recorded so conversions are measurable. Each product ships two taglines that
 * are A/B split deterministically per user.
 */
class Recommendations
{
    const DISMISSED_META = 'wpmb_dismissed_promos';
    const STATS_OPTION   = 'wpmb_promo_stats';

    /**
     * Raw product catalog. `plugin` detects an install (so we never advertise
     * something already active). `tags` map a product to builder contexts for
     * inline slots. `taglines` are A/B variants.
     */
    private static function catalog()
    {
        return [
            [
                'id' => 'academy', 'name' => 'Academy LMS', 'icon' => 'dashicons-welcome-learn-more',
                'category' => __('Learning', 'wp-map-block'), 'color' => '#6366f1',
                'about'    => __('AI-powered LMS to create, manage and sell online courses.', 'wp-map-block'),
                'home' => 'https://academylms.net', 'plugin' => 'academy/academy.php',
                'tags' => ['community', 'evergreen'],
                'taglines' => [
                    __('Map your campuses & classes, then sell the courses with Academy.', 'wp-map-block'),
                    __('Add online courses alongside your location map.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'ablocks', 'name' => 'aBlocks', 'icon' => 'dashicons-layout',
                'category' => __('Page building', 'wp-map-block'), 'color' => '#006bff',
                'about'    => __('Advanced Gutenberg builder with 60+ blocks — a clean alternative to Elementor.', 'wp-map-block'),
                'home' => 'https://ablocks.pro', 'plugin' => 'ablocks/ablocks.php',
                'tags' => ['evergreen'],
                'taglines' => [
                    __('Design the whole page around your map with aBlocks — 60+ blocks.', 'wp-map-block'),
                    __('Build fast, bloat-free layouts around your maps with aBlocks.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'storeengine', 'name' => 'StoreEngine', 'icon' => 'dashicons-cart',
                'category' => __('eCommerce', 'wp-map-block'), 'color' => '#0f9d63',
                'about'    => __('All-in-one eCommerce — sell products, courses, memberships and services.', 'wp-map-block'),
                'home' => 'https://storeengine.pro', 'plugin' => 'storeengine/storeengine.php',
                'tags' => ['store', 'listing', 'data', 'store_locator'],
                'taglines' => [
                    __('StoreEngine + your map = a store locator that sells.', 'wp-map-block'),
                    __('Sell products & services from every location on your map.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'gemcrm', 'name' => 'GemCRM', 'icon' => 'dashicons-groups',
                'category' => __('CRM', 'wp-map-block'), 'color' => '#8b5cf6',
                'about'    => __('Native WordPress CRM with pipelines and email automation.', 'wp-map-block'),
                'home' => 'https://gemcrm.net', 'plugin' => 'gemcrm/gemcrm.php',
                'tags' => ['listing', 'data', 'success'],
                'taglines' => [
                    __('Capture leads from your map and follow up automatically.', 'wp-map-block'),
                    __('Turn map inquiries into customers with GemCRM.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'gembooking', 'name' => 'GemBooking', 'icon' => 'dashicons-calendar-alt',
                'category' => __('Booking', 'wp-map-block'), 'color' => '#f43f5e',
                'about'    => __('Complete booking & appointment system for WordPress.', 'wp-map-block'),
                'home' => 'https://gembooking.net', 'plugin' => 'gembooking/gembooking.php',
                'tags' => ['store_locator', 'store', 'success'],
                'taglines' => [
                    __('Let customers book at every location on your map.', 'wp-map-block'),
                    __('GemBooking + map = book-at-this-location.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'zencommunity', 'name' => 'ZenCommunity', 'icon' => 'dashicons-buddicons-buddypress-logo',
                'category' => __('Community', 'wp-map-block'), 'color' => '#14b8a6',
                'about'    => __('Community, support and live chat — build a hub around your site.', 'wp-map-block'),
                'home' => 'https://zencommunity.pro', 'plugin' => 'zencommunity/zencommunity.php',
                'tags' => ['community'],
                'taglines' => [
                    __('Build a community around your locations.', 'wp-map-block'),
                    __('Give your map audience groups & discussions.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'gameengine', 'name' => 'GameEngine', 'icon' => 'dashicons-awards',
                'category' => __('Gamification', 'wp-map-block'), 'color' => '#f59e0b',
                'about'    => __('Points, badges, ranks and leaderboards to boost engagement.', 'wp-map-block'),
                'home' => 'https://gameengine.pro', 'plugin' => 'gameengine/gameengine.php',
                'tags' => ['community', 'success'],
                'taglines' => [
                    __('Reward check-ins & directory engagement with points & badges.', 'wp-map-block'),
                    __('Gamify visits to the places on your map.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'zaplane', 'name' => 'Zaplane', 'icon' => 'dashicons-randomize',
                'category' => __('Automation', 'wp-map-block'), 'color' => '#7c3aed',
                'about'    => __('No-code WordPress automation with 50+ integrations.', 'wp-map-block'),
                'home' => 'https://zaplane.app', 'plugin' => 'zaplane/zaplane.php',
                'tags' => ['data', 'evergreen'],
                'taglines' => [
                    __('Automate what happens when a map lead comes in — 50+ integrations.', 'wp-map-block'),
                    __('Connect your maps & forms to anything with Zaplane.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'zenappbuilder', 'name' => 'ZenAppBuilder', 'icon' => 'dashicons-smartphone',
                'category' => __('Mobile apps', 'wp-map-block'), 'color' => '#06b6d4',
                'about'    => __('Turn your WordPress site into a native mobile app — no code.', 'wp-map-block'),
                'home' => 'https://zenappbuilder.com', 'plugin' => 'zenappbuilder/zenappbuilder.php',
                'tags' => ['store_locator', 'listing', 'success'],
                'taglines' => [
                    __('Turn your map directory into a mobile app — no code.', 'wp-map-block'),
                    __('Ship your store locator as a mobile app with ZenAppBuilder.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'quizpress', 'name' => 'QuizPress', 'icon' => 'dashicons-forms',
                'category' => __('Assessments', 'wp-map-block'), 'color' => '#ea580c',
                'about'    => __('Advanced quiz, poll, survey and exam builder.', 'wp-map-block'),
                'home' => 'https://quizpress.pro', 'plugin' => 'quizpress/quizpress.php',
                'tags' => ['community'],
                'taglines' => [
                    __('Add location-based quizzes & tours to your map.', 'wp-map-block'),
                    __('Engage map visitors with interactive quizzes.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'gemboards', 'name' => 'GemBoards', 'icon' => 'dashicons-clipboard',
                'category' => __('Project management', 'wp-map-block'), 'color' => '#10b981',
                'about'    => __('Project & task management with boards, tasks and teams.', 'wp-map-block'),
                'home' => 'https://gemboards.com', 'plugin' => 'gemboards/gemboards.php',
                'tags' => ['evergreen'],
                'taglines' => [
                    __('Plan your map & site projects with tasks and teams.', 'wp-map-block'),
                    __('Manage client map projects with GemBoards.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'ecm', 'name' => 'Easy Content Manager', 'icon' => 'dashicons-database',
                'category' => __('Content', 'wp-map-block'), 'color' => '#64748b',
                'about'    => __('Custom fields, post types and taxonomies — the easy way.', 'wp-map-block'),
                'home' => 'https://easycontentmanager.pro', 'plugin' => 'easy-content-manager/easy-content-manager.php',
                'tags' => ['data', 'listing'],
                'taglines' => [
                    __('ECM + WP Map Block = a directory. Build listing types, plot them on the map.', 'wp-map-block'),
                    __('Create listing post types & custom fields — then map them automatically.', 'wp-map-block'),
                ],
            ],
            [
                'id' => 'template-kits', 'name' => 'Template Kits', 'icon' => 'dashicons-art',
                'category' => __('Templates', 'wp-map-block'), 'color' => '#ec4899',
                'about'    => __('Premium, ready-made template library for aBlocks.', 'wp-map-block'),
                'home' => 'https://template-kits.com', 'plugin' => 'template-kits/template-kits.php',
                'tags' => ['evergreen'],
                'taglines' => [
                    __('Launch faster with ready-made aBlocks templates.', 'wp-map-block'),
                    __('Premium templates to build the page around your map.', 'wp-map-block'),
                ],
            ],
        ];
    }

    /**
     * The "one ecosystem" upsell shown at the top of the Discover page.
     */
    public static function bundle()
    {
        return [
            'name'    => __('Kodezen Bundle', 'wp-map-block'),
            'tagline' => __('Power your entire business with one WordPress system — every Kodezen plugin in a single bundle.', 'wp-map-block'),
            'url'     => self::utm('https://kodezen.com/kodezen-ecosystem/', 'bundle'),
        ];
    }

    /**
     * A special thank-you gift for WP Map Block users — the plugin that started
     * Kodezen. Filterable so the coupon code / discount / claim URL can be set
     * without touching code:  add_filter('wpmb_gift', fn($g) => [...]).
     */
    public static function gift()
    {
        return apply_filters('wpmb_gift', [
            'coupon'   => 'MAPFIRST40',
            'discount' => __('40% off', 'wp-map-block'),
            'note'     => __('Valid on any single Kodezen plugin — not the Kodezen Bundle.', 'wp-map-block'),
            // The coupon is appended as a ?coupon= query param on the claim link.
            'url'      => 'https://kodezen.com/special-offer-by-wpmapblock',
        ]);
    }

    /** Bundled brand logo for a product (assets/ecosystem/), or '' if none. */
    private static function logo_url($id)
    {
        $logos = [
            'academy'       => 'academy.svg',
            'ablocks'       => 'ablocks.svg',
            'storeengine'   => 'storeengine.svg',
            'gemcrm'        => 'gemcrm.svg',
            'gembooking'    => 'gembooking.svg',
            'zencommunity'  => 'zencommunity.png',
            'gameengine'    => 'gameengine.svg',
            'zaplane'       => 'zaplane.svg',
            'zenappbuilder' => 'zenappbuilder.svg',
            'quizpress'     => 'quizpress.svg',
            'ecm'           => 'ecm.svg',
            'gemboards'     => 'gemboards.svg',
            'template-kits' => 'template-kits.svg',
        ];
        return isset($logos[$id]) ? WPMAPBLOCK_ASSETS_URI . 'ecosystem/' . $logos[$id] : '';
    }

    private static function utm($base, $id)
    {
        return add_query_arg(
            [
                'utm_source'   => 'wp-map-block',
                'utm_medium'   => 'plugin',
                'utm_campaign' => $id,
            ],
            $base
        );
    }

    private static function is_active($plugin)
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }

    /** Deterministic A/B variant for the current user (stable across sessions). */
    private static function variant_for($id, $count)
    {
        if ($count <= 1) {
            return 0;
        }
        return abs(crc32(get_current_user_id() . '|' . $id)) % $count;
    }

    public static function dismissed()
    {
        $val = get_user_meta(get_current_user_id(), self::DISMISSED_META, true);
        return is_array($val) ? $val : [];
    }

    /**
     * Products decorated with url/active/dismissed/variant. Active installs are
     * excluded entirely (never advertise what the user already runs).
     */
    public static function all()
    {
        $dismissed = self::dismissed();
        $out = [];
        foreach (self::catalog() as $p) {
            if (self::is_active($p['plugin'])) {
                continue;
            }
            $variant = self::variant_for($p['id'], count($p['taglines']));
            $out[] = [
                'id'        => $p['id'],
                'name'      => $p['name'],
                'icon'      => $p['icon'],
                'color'     => $p['color'],
                'logo'      => self::logo_url($p['id']),
                'category'  => $p['category'],
                'about'     => $p['about'],
                'tags'      => $p['tags'],
                'tagline'   => $p['taglines'][$variant],
                'variant'   => $variant,
                'url'       => self::utm($p['home'], $p['id']),
                'dismissed' => in_array($p['id'], $dismissed, true),
            ];
        }
        return $out;
    }

    /**
     * The full Kodezen ecosystem for the Discover showcase — every product,
     * including ones already installed (flagged `active`) so the page presents
     * the whole suite rather than only what's promotable.
     */
    public static function ecosystem()
    {
        $dismissed = self::dismissed();
        $out = [];
        foreach (self::catalog() as $p) {
            $out[] = [
                'id'        => $p['id'],
                'name'      => $p['name'],
                'icon'      => $p['icon'],
                'color'     => $p['color'],
                'logo'      => self::logo_url($p['id']),
                'category'  => $p['category'],
                'about'     => $p['about'],
                'url'       => self::utm($p['home'], $p['id']),
                'active'    => self::is_active($p['plugin']),
                'dismissed' => in_array($p['id'], $dismissed, true),
            ];
        }
        return $out;
    }

    public static function dismiss($id)
    {
        $id = sanitize_key($id);
        $dismissed = self::dismissed();
        if (!in_array($id, $dismissed, true)) {
            $dismissed[] = $id;
            update_user_meta(get_current_user_id(), self::DISMISSED_META, $dismissed);
        }
        return $dismissed;
    }

    /**
     * Record an impression or click. Aggregated counts keyed by
     * id|placement|event|variant so effectiveness is comparable per placement
     * and A/B variant. Stored in a single (unautoloaded) option.
     */
    public static function track($id, $placement, $event, $variant)
    {
        $id        = sanitize_key($id);
        $placement = sanitize_key($placement);
        $event     = $event === 'click' ? 'click' : 'impression';
        $variant   = (int) $variant;

        $stats = get_option(self::STATS_OPTION, []);
        if (!is_array($stats)) {
            $stats = [];
        }
        $key = "{$id}|{$placement}|{$event}|v{$variant}";
        $stats[$key] = isset($stats[$key]) ? (int) $stats[$key] + 1 : 1;
        update_option(self::STATS_OPTION, $stats, false);
        return true;
    }

    public static function stats()
    {
        $stats = get_option(self::STATS_OPTION, []);
        return is_array($stats) ? $stats : [];
    }
}
