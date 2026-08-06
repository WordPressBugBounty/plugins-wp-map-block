<?php

namespace WPMapBlock\Rest;

use WPMapBlock\Integrations\Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exposes the registered listing sources to the builder's Data tab.
 */
class Integrations_Controller
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route(WPMAPBLOCK_REST_NAMESPACE, '/listing-sources', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'index'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function index()
    {
        $out = [];
        foreach (Manager::all() as $id => $src) {
            $out[] = [
                'id'    => $id,
                'label' => $src['label'],
                'group' => $src['group'],
            ];
        }
        return rest_ensure_response($out);
    }
}
