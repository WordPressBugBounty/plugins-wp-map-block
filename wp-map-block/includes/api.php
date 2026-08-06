<?php

/**
 * Public developer API for WP Map Block.
 *
 * Directory / listing themes and plugins use these functions to plug their own
 * content into the map builder. Call them on the `wpmb_register_integrations`
 * action (fires on `init`, priority 20).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wpmb_register_listing_source')) {
    /**
     * Register a listing source that appears in the builder's Data tab and
     * resolves to map markers + directory categories automatically.
     *
     * @param string $id   Unique key, e.g. 'my_listings'.
     * @param array  $args {
     *     @type string   $label     Human label shown in the picker.
     *     @type string   $group     Optional group heading (e.g. plugin name).
     *     @type string   $post_type Post type to query.
     *     @type string   $lat_meta  Meta key holding latitude.
     *     @type string   $lng_meta  Meta key holding longitude.
     *     @type string   $taxonomy  Optional taxonomy → directory filter chips.
     *     @type int      $limit     Max posts (default 500).
     *     @type callable $mapper    Optional callback( WP_Post, $source ):array|null.
     * }
     */
    function wpmb_register_listing_source($id, array $args)
    {
        \WPMapBlock\Integrations\Manager::register($id, $args);
    }
}

if (!function_exists('wpmb_get_listing_sources')) {
    /**
     * @return array<string,array> All registered listing sources.
     */
    function wpmb_get_listing_sources()
    {
        return \WPMapBlock\Integrations\Manager::all();
    }
}

if (!function_exists('wpmb_render_map')) {
    /**
     * Render a saved map by id (theme templates can echo this directly).
     */
    function wpmb_render_map($map_id)
    {
        return \WPMapBlock\Frontend\Renderer::render_map($map_id);
    }
}
