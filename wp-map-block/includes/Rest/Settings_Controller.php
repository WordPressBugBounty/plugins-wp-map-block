<?php

namespace WPMapBlock\Rest;

use WPMapBlock\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST access to the global plugin settings (API keys).
 */
class Settings_Controller
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route(WPMAPBLOCK_REST_NAMESPACE, '/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'show'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'save'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);
    }

    public static function can_manage()
    {
        return current_user_can('manage_options');
    }

    public static function show()
    {
        return rest_ensure_response(Settings::get());
    }

    public static function save($request)
    {
        $params = $request->get_json_params();
        return rest_ensure_response(Settings::save(is_array($params) ? $params : []));
    }
}
