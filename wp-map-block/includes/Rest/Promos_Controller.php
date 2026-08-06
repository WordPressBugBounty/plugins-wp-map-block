<?php

namespace WPMapBlock\Rest;

use WPMapBlock\Recommendations;
use WPMapBlock\Admin\Sdk;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Records dismissals of cross-promotion cards (per user).
 */
class Promos_Controller
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route(WPMAPBLOCK_REST_NAMESPACE, '/promos/dismiss', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'dismiss'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route(WPMAPBLOCK_REST_NAMESPACE, '/promos/track', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'track'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // Founding-member gift opt-in: forwards consent + email to the
        // StoreEngine SDK insights channel (see Admin\Sdk::record_consent).
        register_rest_route(WPMAPBLOCK_REST_NAMESPACE, '/gift/optin', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'optin'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function optin($request)
    {
        $p     = $request->get_json_params();
        $email = isset($p['email']) ? sanitize_email($p['email']) : '';
        $name  = isset($p['name']) ? sanitize_text_field($p['name']) : '';

        // One-click opt-in (like the Setup wizard): fall back to the logged-in
        // user's account email/name when the client doesn't supply one.
        $user = wp_get_current_user();
        if (empty($email)) {
            $email = $user->exists() && $user->user_email ? $user->user_email : get_option('admin_email');
        }
        if (empty($name)) {
            $name = $user->exists() ? $user->display_name : '';
        }

        if (empty($email) || !is_email($email)) {
            return new \WP_Error('wpmb_bad_email', __('A valid email is required.', 'wp-map-block'), ['status' => 400]);
        }

        // Persist consent + push to the SDK insights opt-in (records + sends
        // usage data) and broadcast wpmb/subscriber/consent for CRM bridges.
        Sdk::record_consent(true, $name, $email);

        return rest_ensure_response(['ok' => true]);
    }

    public static function track($request)
    {
        $p = $request->get_json_params();
        if (empty($p['id'])) {
            return rest_ensure_response(['ok' => false]);
        }
        Recommendations::track(
            $p['id'],
            isset($p['placement']) ? $p['placement'] : 'unknown',
            isset($p['event']) ? $p['event'] : 'impression',
            isset($p['variant']) ? $p['variant'] : 0
        );
        return rest_ensure_response(['ok' => true]);
    }

    public static function dismiss($request)
    {
        $params = $request->get_json_params();
        $id     = isset($params['id']) ? $params['id'] : '';
        if (!$id) {
            return new \WP_Error('wpmb_missing_id', __('Missing promo id', 'wp-map-block'), ['status' => 400]);
        }
        return rest_ensure_response(['dismissed' => Recommendations::dismiss($id)]);
    }
}
