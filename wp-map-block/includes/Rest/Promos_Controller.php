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
            // Opting in enables site-wide usage tracking, so it needs an admin.
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public static function optin($request)
    {
        $p     = $request->get_json_params();
        $p     = is_array($p) ? $p : [];
        $email = isset($p['email']) && is_string($p['email']) ? sanitize_email($p['email']) : '';
        $name  = isset($p['name']) && is_string($p['name']) ? sanitize_text_field($p['name']) : '';

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
        if (!is_array($p) || empty($p['id']) || !is_string($p['id'])) {
            return rest_ensure_response(['ok' => false]);
        }
        $ok = Recommendations::track(
            $p['id'],
            isset($p['placement']) && is_string($p['placement']) ? $p['placement'] : 'unknown',
            isset($p['event']) && is_string($p['event']) ? $p['event'] : 'impression',
            isset($p['variant']) && is_scalar($p['variant']) ? $p['variant'] : 0
        );
        return rest_ensure_response(['ok' => $ok]);
    }

    public static function dismiss($request)
    {
        $params = $request->get_json_params();
        $id     = is_array($params) && isset($params['id']) && is_string($params['id']) ? $params['id'] : '';
        if (!$id) {
            return new \WP_Error('wpmb_missing_id', __('Missing promo id', 'wp-map-block'), ['status' => 400]);
        }
        return rest_ensure_response(['dismissed' => Recommendations::dismiss($id)]);
    }
}
