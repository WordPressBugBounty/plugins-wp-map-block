<?php

namespace WPMapBlock\Rest;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST helpers backing the builder's dynamic data-source UI: enumerating public
 * post types and the meta keys available on them. Registered under `wpmb/v1`.
 */
class Data_Controller
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes()
    {
        $ns = WPMAPBLOCK_REST_NAMESPACE;

        register_rest_route($ns, '/post-types', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'post_types'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        register_rest_route($ns, '/meta-keys', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'meta_keys'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        // Resolve CPT/listing dynamic sources into markers for the builder preview
        // (the client engine only fetches geojson/rest URLs itself).
        register_rest_route($ns, '/dynamic-preview', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'dynamic_preview'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        // Self-contained sample data so the GeoJSON / REST source types can be
        // tested instantly without an external endpoint. Public (read-only).
        register_rest_route($ns, '/sample/geojson', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'sample_geojson'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($ns, '/sample/rest', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'sample_rest'],
            'permission_callback' => '__return_true',
        ]);
    }

    /** A few world landmarks — the raw marker rows both sample endpoints share. */
    protected static function sample_points(): array
    {
        return [
            ['lat' => 40.6892, 'lng' => -74.0445, 'title' => 'Statue of Liberty', 'content' => 'Liberty Island, New York, USA', 'link' => 'https://www.nps.gov/stli/'],
            ['lat' => 48.8584, 'lng' => 2.2945, 'title' => 'Eiffel Tower', 'content' => 'Champ de Mars, Paris, France', 'link' => 'https://www.toureiffel.paris/en'],
            ['lat' => 51.5007, 'lng' => -0.1246, 'title' => 'Big Ben', 'content' => 'Westminster, London, UK', 'link' => 'https://www.parliament.uk/bigben'],
            ['lat' => 35.6586, 'lng' => 139.7454, 'title' => 'Tokyo Tower', 'content' => 'Minato City, Tokyo, Japan', 'link' => 'https://www.tokyotower.co.jp/en/'],
            ['lat' => -33.8568, 'lng' => 151.2153, 'title' => 'Sydney Opera House', 'content' => 'Bennelong Point, Sydney, Australia', 'link' => 'https://www.sydneyoperahouse.com/'],
            ['lat' => 41.8902, 'lng' => 12.4922, 'title' => 'Colosseum', 'content' => 'Rome, Italy', 'link' => 'https://parcocolosseo.it/en/'],
        ];
    }

    /** Sample GeoJSON FeatureCollection of point markers. */
    public static function sample_geojson()
    {
        $features = array_map(static function ($p) {
            return [
                'type'       => 'Feature',
                'geometry'   => ['type' => 'Point', 'coordinates' => [$p['lng'], $p['lat']]],
                'properties' => ['title' => $p['title'], 'description' => $p['content'], 'link' => $p['link']],
            ];
        }, self::sample_points());

        return rest_ensure_response(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /** Sample REST payload: a flat array of {lat,lng,title,content,link}. */
    public static function sample_rest()
    {
        return rest_ensure_response(self::sample_points());
    }

    /**
     * Materialize CPT/listing data sources to markers (+ taxonomy categories) so
     * the builder preview matches the front end. Empty when the Pro capability is
     * locked, exactly like the render-time path.
     */
    public static function dynamic_preview($request)
    {
        if (self::dynamic_locked()) {
            return rest_ensure_response(['markers' => [], 'categories' => []]);
        }
        $params  = $request->get_json_params();
        $sources = isset($params['dataSources']) && is_array($params['dataSources']) ? $params['dataSources'] : [];
        $resolved = \WPMapBlock\Frontend\DataSources::resolve([
            'dataSources' => $sources,
            'markers'     => [],
            'categories'  => [],
        ]);
        return rest_ensure_response([
            'markers'    => isset($resolved['markers']) ? array_values($resolved['markers']) : [],
            'categories' => isset($resolved['categories']) ? array_values($resolved['categories']) : [],
        ]);
    }

    public static function can_edit()
    {
        return current_user_can('edit_posts');
    }

    /**
     * Dynamic-data helpers (post types, meta keys) are a Pro capability. Editors
     * may reach the endpoint, but it stays empty until the feature is unlocked so
     * nothing about the site's data model leaks to an unlicensed install.
     */
    protected static function dynamic_locked(): bool
    {
        return !\WPMapBlock\Pro::has('dynamic-data');
    }

    /**
     * All public post types (excluding attachments) as value/label pairs.
     */
    public static function post_types()
    {
        if (self::dynamic_locked()) {
            return rest_ensure_response([]);
        }
        $types = get_post_types(['public' => true], 'objects');
        $items = [];

        foreach ($types as $slug => $obj) {
            if ($slug === 'attachment') {
                continue;
            }
            $label = isset($obj->labels->singular_name) && $obj->labels->singular_name
                ? $obj->labels->singular_name : $obj->label;
            $items[] = [
                'value' => $slug,
                'label' => $label,
            ];
        }

        return rest_ensure_response($items);
    }

    /**
     * Distinct public meta keys used by posts of a given type.
     */
    public static function meta_keys($request)
    {
        if (self::dynamic_locked()) {
            return rest_ensure_response([]);
        }
        $post_type = sanitize_key($request->get_param('postType'));
        if (empty($post_type)) {
            return rest_ensure_response([]);
        }

        $items = [];
        $seen  = [];

        // ACF field definitions for this post type — surfaced even before any
        // post has saved a value, and labeled with the field's human name. ACF
        // stores each value under its field name as ordinary post meta, so the
        // frontend resolver (get_post_meta) reads them with no extra work.
        if (function_exists('acf_get_field_groups') && function_exists('acf_get_fields')) {
            foreach ((array) acf_get_field_groups(['post_type' => $post_type]) as $group) {
                if (empty($group['key'])) {
                    continue;
                }
                self::collect_acf_fields(acf_get_fields($group['key']), $items, $seen);
            }
        }

        // Real distinct meta keys present on posts of this type. Admin-only,
        // editor-gated, one-off lookup for the dynamic-data field picker — a
        // direct query is appropriate and the result set is tiny.
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off admin meta-key discovery; no suitable core API.
        $keys = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE p.post_type = %s
               AND pm.meta_key NOT LIKE %s
             ORDER BY pm.meta_key ASC
             LIMIT 300",
            $post_type,
            $wpdb->esc_like('_') . '%'
        ));
        foreach ((array) $keys as $key) {
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key]  = true;
            $items[] = ['value' => $key, 'label' => $key];
        }

        return rest_ensure_response($items);
    }

    /** Flatten ACF fields (recursing into group/repeater/flexible sub-fields). */
    protected static function collect_acf_fields($fields, array &$items, array &$seen): void
    {
        if (!is_array($fields)) {
            return;
        }
        foreach ($fields as $field) {
            if (!empty($field['sub_fields'])) {
                self::collect_acf_fields($field['sub_fields'], $items, $seen);
            }
            if (empty($field['name']) || isset($seen[$field['name']])) {
                continue;
            }
            $seen[$field['name']] = true;
            $label = !empty($field['label']) ? $field['label'] . ' — ' . $field['name'] : $field['name'];
            $items[] = ['value' => $field['name'], 'label' => $label];
        }
    }
}
