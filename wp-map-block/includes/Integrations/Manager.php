<?php

namespace WPMapBlock\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Listing-source registry — the public extension point that lets any directory /
 * listing theme (or plugin) feed its CPT into WP Map Block as map markers.
 *
 * A theme registers a source once:
 *
 *   add_action('wpmb_register_integrations', function () {
 *       wpmb_register_listing_source('my_listings', [
 *           'label'     => 'My Listings',
 *           'post_type' => 'listing',
 *           'lat_meta'  => 'geo_lat',
 *           'lng_meta'  => 'geo_lng',
 *           'taxonomy'  => 'listing_category', // drives directory filter chips
 *           'group'     => 'My Theme',
 *       ]);
 *   });
 *
 * The source then appears in the builder's Data tab and resolves to markers +
 * categories on the frontend automatically. Bundled StoreEngine / WooCommerce
 * integrations use this exact API as reference implementations.
 */
class Manager
{
    /** @var array<string,array> */
    private static $sources = [];

    private static $booted = false;

    public static function init()
    {
        require_once __DIR__ . '/Location_Meta.php';
        require_once __DIR__ . '/StoreEngine.php';
        require_once __DIR__ . '/WooCommerce.php';

        StoreEngine::init();
        WooCommerce::init();

        // Register on demand so post types / plugins are fully loaded first.
        add_action('init', [self::class, 'boot'], 20);
    }

    /**
     * Fire the registration hook once. Themes/plugins hook `wpmb_register_integrations`.
     */
    public static function boot()
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        /**
         * Register listing sources here. Runs on `init` (priority 20).
         */
        do_action('wpmb_register_integrations');

        /**
         * Final chance to filter the full source list.
         *
         * @param array<string,array> $sources
         */
        self::$sources = apply_filters('wpmb_listing_sources', self::$sources);
    }

    /**
     * Register (or replace) a listing source. Missing keys are defaulted.
     */
    public static function register($id, array $args)
    {
        $id = sanitize_key($id);
        if (!$id) {
            return;
        }
        self::$sources[$id] = wp_parse_args($args, [
            'id'        => $id,
            'label'     => $id,
            'group'     => __('Custom', 'wp-map-block'),
            'post_type' => 'post',
            'lat_meta'  => '',
            'lng_meta'  => '',
            'taxonomy'  => '',
            'status'    => 'publish',
            'limit'     => 500,
            'mapper'    => null, // optional callable(WP_Post):array|null
        ]);
        self::$sources[$id]['id'] = $id;
    }

    public static function all()
    {
        self::boot();
        return self::$sources;
    }

    public static function get($id)
    {
        self::boot();
        return isset(self::$sources[$id]) ? self::$sources[$id] : null;
    }

    /**
     * Resolve a source id into ['markers' => [...], 'categories' => [...]].
     * Categories come from the source taxonomy so the directory filter chips
     * work out of the box.
     *
     * @param string $id
     * @param int    $limit Overrides the source default when > 0.
     */
    public static function resolve($id, $limit = 0)
    {
        $source = self::get($id);
        if (!$source) {
            return ['markers' => [], 'categories' => []];
        }

        $limit = $limit > 0 ? (int) $limit : (int) $source['limit'];

        $query = new \WP_Query([
            'post_type'      => $source['post_type'],
            'post_status'    => $source['status'],
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
            'fields'         => 'all',
        ]);

        $markers    = [];
        $categories = [];

        foreach ($query->posts as $post) {
            if (is_callable($source['mapper'])) {
                $marker = call_user_func($source['mapper'], $post, $source);
            } else {
                $marker = self::default_mapper($post, $source, $categories);
            }
            if ($marker) {
                $markers[] = $marker;
            }
        }

        wp_reset_postdata();

        return [
            'markers'    => $markers,
            'categories' => array_values($categories),
        ];
    }

    /**
     * Default post → marker mapping using the source's meta + taxonomy config.
     * Collects encountered taxonomy terms into $categories (by reference).
     */
    private static function default_mapper($post, $source, array &$categories)
    {
        $lat = $source['lat_meta'] ? (float) get_post_meta($post->ID, $source['lat_meta'], true) : 0;
        $lng = $source['lng_meta'] ? (float) get_post_meta($post->ID, $source['lng_meta'], true) : 0;
        if (!$lat || !$lng) {
            return null;
        }

        $category = '';
        if (!empty($source['taxonomy'])) {
            $terms = get_the_terms($post->ID, $source['taxonomy']);
            if ($terms && !is_wp_error($terms)) {
                $term      = $terms[0];
                $category  = 'tax_' . $term->term_id;
                if (!isset($categories[$category])) {
                    $categories[$category] = [
                        'id'    => $category,
                        'name'  => $term->name,
                        'color' => self::color_for_term($term->term_id),
                        'icon'  => '',
                    ];
                }
            }
        }

        $thumb = get_the_post_thumbnail_url($post->ID, 'thumbnail');
        $excerpt = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags($post->post_content), 24);

        return [
            'id'       => 'ls_' . $post->ID,
            'lat'      => $lat,
            'lng'      => $lng,
            'title'    => get_the_title($post),
            'content'  => $thumb
                ? '<img src="' . esc_url($thumb) . '" style="max-width:100%;border-radius:6px;margin-bottom:6px" />' . esc_html($excerpt)
                : esc_html($excerpt),
            'link'     => get_permalink($post),
            'category' => $category,
            'icon'     => ['type' => 'default', 'url' => '', 'color' => ''],
        ];
    }

    /**
     * Deterministic pleasant color per term so chips/pins are stable.
     */
    private static function color_for_term($term_id)
    {
        $palette = ['#006bff', '#00ad6b', '#f15b50', '#fdb022', '#8b5cf6', '#0ea5e9', '#ec4899', '#14b8a6'];
        return $palette[$term_id % count($palette)];
    }
}
