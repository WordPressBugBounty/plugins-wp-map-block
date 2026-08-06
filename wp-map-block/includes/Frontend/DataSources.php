<?php

namespace WPMapBlock\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolves server-side dynamic data sources into concrete markers before the
 * config is handed to the frontend engine. CPT (and static) sources are folded
 * into `markers` here; geojson/rest sources are left in place for the client
 * JS engine to fetch at runtime.
 */
class DataSources
{
    /**
     * Return a modified config with server-resolvable data sources materialized
     * into markers. Never fatals: missing/invalid keys are skipped defensively.
     */
    public static function resolve(array $config)
    {
        if (empty($config['dataSources']) || !is_array($config['dataSources'])) {
            return $config;
        }

        if (!isset($config['markers']) || !is_array($config['markers'])) {
            $config['markers'] = [];
        }

        $remaining = [];

        foreach ($config['dataSources'] as $ds) {
            if (!is_array($ds)) {
                continue;
            }
            $type = isset($ds['type']) ? $ds['type'] : 'static';

            if ($type === 'cpt') {
                $markers = self::resolve_cpt($ds);
                if (!empty($markers)) {
                    $config['markers'] = array_merge($config['markers'], $markers);
                }
                // Resolved server-side; drop so the client doesn't re-process.
                continue;
            }

            if ($type === 'listing') {
                $resolved = self::resolve_listing($ds);
                if (!empty($resolved['markers'])) {
                    $config['markers'] = array_merge($config['markers'], $resolved['markers']);
                }
                if (!empty($resolved['categories'])) {
                    $config['categories'] = self::merge_categories(
                        isset($config['categories']) && is_array($config['categories']) ? $config['categories'] : [],
                        $resolved['categories']
                    );
                }
                continue;
            }

            if ($type === 'static') {
                // Static markers already live in $config['markers']; drop the source.
                continue;
            }

            // geojson / rest are fetched client-side; keep them.
            $remaining[] = $ds;
        }

        $config['dataSources'] = array_values($remaining);

        return $config;
    }

    /**
     * Resolve a registered listing source (StoreEngine, WooCommerce, or a theme's
     * own) into markers + taxonomy-derived categories.
     */
    private static function resolve_listing(array $ds)
    {
        $source_id = isset($ds['source']) ? (string) $ds['source'] : '';
        if ($source_id === '' || !class_exists('WPMapBlock\\Integrations\\Manager')) {
            return ['markers' => [], 'categories' => []];
        }
        $limit = isset($ds['limit']) ? (int) $ds['limit'] : 0;
        return \WPMapBlock\Integrations\Manager::resolve($source_id, $limit);
    }

    /**
     * Merge taxonomy-derived categories into the map's categories, keyed by id,
     * so existing manual categories are never clobbered.
     */
    private static function merge_categories(array $existing, array $incoming)
    {
        $byId = [];
        foreach ($existing as $cat) {
            if (isset($cat['id'])) {
                $byId[$cat['id']] = $cat;
            }
        }
        foreach ($incoming as $cat) {
            if (isset($cat['id']) && !isset($byId[$cat['id']])) {
                $byId[$cat['id']] = $cat;
            }
        }
        return array_values($byId);
    }

    /**
     * Build markers from a CPT data source via WP_Query.
     */
    private static function resolve_cpt(array $ds)
    {
        $post_type = isset($ds['postType']) ? (string) $ds['postType'] : '';
        if ($post_type === '' || !post_type_exists($post_type)) {
            return [];
        }

        $lat_meta = isset($ds['latMeta']) ? (string) $ds['latMeta'] : '';
        $lng_meta = isset($ds['lngMeta']) ? (string) $ds['lngMeta'] : '';
        if ($lat_meta === '' || $lng_meta === '') {
            return [];
        }

        $limit = isset($ds['limit']) ? (int) $ds['limit'] : 100;
        if ($limit <= 0) {
            $limit = 100;
        }

        $query = new \WP_Query([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
        ]);

        $markers = [];

        foreach ($query->posts as $post) {
            $lat = (float) get_post_meta($post->ID, $lat_meta, true);
            $lng = (float) get_post_meta($post->ID, $lng_meta, true);
            if (empty($lat) || empty($lng)) {
                continue;
            }

            $content = has_excerpt($post) ? get_the_excerpt($post) : $post->post_content;

            $markers[] = [
                'id'       => 'ds_' . $post->ID,
                'lat'      => $lat,
                'lng'      => $lng,
                'title'    => get_the_title($post),
                'content'  => wp_trim_words(wp_strip_all_tags($content), 30),
                'link'     => get_permalink($post),
                'category' => '',
                'icon'     => [
                    'type'  => 'default',
                    'url'   => '',
                    'color' => '',
                ],
            ];
        }

        return $markers;
    }
}
