<?php

namespace WPMapBlock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The `wpmb_map` custom post type. Each map is a post; its full configuration
 * lives in the `_wpmb_config` meta as a JSON document.
 */
class PostType
{
    public static function init()
    {
        add_action('init', [self::class, 'register']);
    }

    public static function register()
    {
        register_post_type(WPMAPBLOCK_POST_TYPE, [
            'labels' => [
                'name'          => __('Maps', 'wp-map-block'),
                'singular_name' => __('Map', 'wp-map-block'),
            ],
            'public'              => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_rest'        => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'hierarchical'        => false,
            'supports'            => ['title', 'author'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ]);
    }
}
