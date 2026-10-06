<?php

namespace WPMapBlock\Rest;

use WPMapBlock\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST CRUD for maps under the `wpmb/v1` namespace.
 */
class Maps_Controller
{
    public static function init()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes()
    {
        $ns = WPMAPBLOCK_REST_NAMESPACE;

        register_rest_route($ns, '/maps', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'index'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'create'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
        ]);

        register_rest_route($ns, '/maps/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'show'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'update'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [self::class, 'destroy'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
        ]);

        register_rest_route($ns, '/maps/(?P<id>\d+)/duplicate', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'duplicate'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        // Toggle a single map active/inactive (publish/draft).
        register_rest_route($ns, '/maps/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'set_status'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        // Promote a block into a real, saved map. The block editor posts the
        // attributes of a "simple" or classic block and gets back a map that
        // renders identically — the on-ramp from a one-off map into the builder.
        register_rest_route($ns, '/maps/from-block', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'create_from_block'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        // Classic blocks still sitting in post content. They are not `wpmb_map`
        // posts, so the map library cannot see them without this.
        register_rest_route($ns, '/legacy-maps', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'legacy_maps'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        // Bulk actions from the dashboard table: activate | deactivate | delete.
        register_rest_route($ns, '/maps/bulk', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'bulk'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        // Public marker search used by the front-end store locator: matches the
        // map's own markers (manual + resolved dynamic sources) by title/content.
        register_rest_route($ns, '/maps/(?P<id>\d+)/search', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'search'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Search a map's markers by keyword. Resolves the map's full marker set
     * (manual + CPT/listing dynamic sources) then filters on title + content.
     * Public — but only serves active (published) maps to non-editors.
     */
    public static function search($request)
    {
        $id   = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== WPMAPBLOCK_POST_TYPE) {
            return new \WP_Error('wpmb_not_found', __('Map not found', 'wp-map-block'), ['status' => 404]);
        }
        if ($post->post_status !== 'publish' && !current_user_can('edit_posts')) {
            return new \WP_Error('wpmb_not_found', __('Map not found', 'wp-map-block'), ['status' => 404]);
        }

        $q     = isset($request['q']) ? trim((string) $request['q']) : '';
        $limit = isset($request['limit']) ? max(1, min(500, (int) $request['limit'])) : 50;

        $config  = \WPMapBlock\Frontend\DataSources::resolve(self::get_config($id));
        $markers = isset($config['markers']) && is_array($config['markers']) ? $config['markers'] : [];

        if ($q !== '') {
            $needle  = strtolower($q);
            $markers = array_values(array_filter($markers, static function ($m) use ($needle) {
                $hay = strtolower(($m['title'] ?? '') . ' ' . wp_strip_all_tags((string) ($m['content'] ?? '')));
                return strpos($hay, $needle) !== false;
            }));
        }

        return rest_ensure_response([
            'query'   => $q,
            'total'   => count($markers),
            'markers' => array_slice(array_values($markers), 0, $limit),
        ]);
    }

    public static function legacy_maps()
    {
        return rest_ensure_response(\WPMapBlock\Legacy\Block::find_in_content());
    }

    public static function can_edit()
    {
        return current_user_can('edit_posts');
    }

    /**
     * Standard 403 response for requests that clear the coarse `edit_posts`
     * gate but fail the per-object capability check for the action at hand.
     */
    private static function forbidden()
    {
        return new \WP_Error(
            'wpmb_forbidden',
            __('Sorry, you are not allowed to do that.', 'wp-map-block'),
            ['status' => rest_authorization_required_code()]
        );
    }

    public static function index()
    {
        $posts = get_posts([
            'post_type'      => WPMAPBLOCK_POST_TYPE,
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ]);

        $items = array_map([self::class, 'to_summary'], $posts);
        return rest_ensure_response($items);
    }

    public static function show($request)
    {
        $id   = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== WPMAPBLOCK_POST_TYPE) {
            return new \WP_Error('wpmb_not_found', __('Map not found', 'wp-map-block'), ['status' => 404]);
        }
        return rest_ensure_response(self::to_full($post));
    }

    public static function create($request)
    {
        $params = $request->get_json_params();
        $title  = isset($params['title']) ? sanitize_text_field($params['title']) : __('Untitled Map', 'wp-map-block');
        $config = isset($params['config']) ? Config::sanitize($params['config']) : Config::defaults();

        // `preset` builds the map from a bundled template instead. The block
        // editor uses this so it can offer the templates without shipping their
        // (large) configs into every editor page.
        if (!empty($params['preset'])) {
            $preset = \WPMapBlock\Presets::find(sanitize_key($params['preset']));
            if (!$preset) {
                return new \WP_Error('wpmb_unknown_preset', __('Unknown template', 'wp-map-block'), ['status' => 400]);
            }
            $config = $preset['config'];
            if (!isset($params['title'])) {
                $title = $preset['name'];
            }
        }

        return self::insert_map($title, $config);
    }

    /**
     * Create a saved map from the attributes of a "simple" or classic block, so
     * the editor can offer a one-click upgrade into the full builder.
     */
    public static function create_from_block($request)
    {
        $params = $request->get_json_params();
        $attrs  = isset($params['attributes']) && is_array($params['attributes']) ? $params['attributes'] : [];
        $title  = isset($params['title']) && $params['title'] !== ''
            ? sanitize_text_field($params['title'])
            : __('Map', 'wp-map-block');

        // The block's own render path builds the config, so the new map opens
        // looking exactly like the block the author is replacing.
        $config = \WPMapBlock\Blocks\Embed_Block::simple_config($attrs);

        return self::insert_map($title, $config);
    }

    private static function insert_map($title, $config)
    {
        // Every path here creates the map in the `publish` state, so the single
        // choke point enforces publish capability — not merely `edit_posts`.
        // Covers create(), create_from_block() and any future caller.
        if (!current_user_can('publish_posts')) {
            return self::forbidden();
        }

        $id = wp_insert_post([
            'post_type'   => WPMAPBLOCK_POST_TYPE,
            'post_title'  => $title,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($id)) {
            return $id;
        }

        update_post_meta($id, Config::META_KEY, wp_slash(wp_json_encode($config)));
        return rest_ensure_response(self::to_full(get_post($id)));
    }

    public static function update($request)
    {
        $id   = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== WPMAPBLOCK_POST_TYPE) {
            return new \WP_Error('wpmb_not_found', __('Map not found', 'wp-map-block'), ['status' => 404]);
        }
        if (!current_user_can('edit_post', $id)) {
            return self::forbidden();
        }

        $params = $request->get_json_params();
        if (isset($params['title'])) {
            wp_update_post([
                'ID'         => $id,
                'post_title' => sanitize_text_field($params['title']),
            ]);
        }
        if (isset($params['config'])) {
            $config = Config::sanitize($params['config']);
            update_post_meta($id, Config::META_KEY, wp_slash(wp_json_encode($config)));
        }

        return rest_ensure_response(self::to_full(get_post($id)));
    }

    public static function destroy($request)
    {
        $id   = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== WPMAPBLOCK_POST_TYPE) {
            return new \WP_Error('wpmb_not_found', __('Map not found', 'wp-map-block'), ['status' => 404]);
        }
        if (!current_user_can('delete_post', $id)) {
            return self::forbidden();
        }
        wp_delete_post($id, true);
        return rest_ensure_response(['deleted' => true, 'id' => $id]);
    }

    public static function duplicate($request)
    {
        $id   = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== WPMAPBLOCK_POST_TYPE) {
            return new \WP_Error('wpmb_not_found', __('Map not found', 'wp-map-block'), ['status' => 404]);
        }
        // Reading the source requires edit access to it; the copy is created
        // published, so publish capability is required too.
        if (!current_user_can('edit_post', $id) || !current_user_can('publish_posts')) {
            return self::forbidden();
        }

        $new_id = wp_insert_post([
            'post_type'   => WPMAPBLOCK_POST_TYPE,
            /* translators: %s: original map title */
            'post_title'  => sprintf(__('%s (copy)', 'wp-map-block'), $post->post_title),
            'post_status' => 'publish',
        ]);
        update_post_meta($new_id, Config::META_KEY, get_post_meta($id, Config::META_KEY, true));
        return rest_ensure_response(self::to_full(get_post($new_id)));
    }

    /**
     * Set a map active (publish) or inactive (draft). Inactive maps are hidden
     * from the front end for visitors (see Renderer::render_map) but stay
     * editable and previewable for admins.
     */
    public static function set_status($request)
    {
        $id   = (int) $request['id'];
        $post = get_post($id);
        if (!$post || $post->post_type !== WPMAPBLOCK_POST_TYPE) {
            return new \WP_Error('wpmb_not_found', __('Map not found', 'wp-map-block'), ['status' => 404]);
        }
        $params = $request->get_json_params();
        $active = !empty($params['active']);

        // Changing the map's state requires edit access to that specific map;
        // activating it (publishing) additionally requires publish capability.
        if (!current_user_can('edit_post', $id)) {
            return self::forbidden();
        }
        if ($active && !current_user_can('publish_post', $id)) {
            return self::forbidden();
        }

        wp_update_post(['ID' => $id, 'post_status' => $active ? 'publish' : 'draft']);
        return rest_ensure_response(self::to_summary(get_post($id)));
    }

    /**
     * Bulk action over a set of map ids: activate | deactivate | delete.
     */
    public static function bulk($request)
    {
        $params = $request->get_json_params();
        $action = isset($params['action']) ? sanitize_key($params['action']) : '';
        $ids    = isset($params['ids']) && is_array($params['ids']) ? array_map('intval', $params['ids']) : [];
        if (!in_array($action, ['activate', 'deactivate', 'delete'], true) || !$ids) {
            return new \WP_Error('wpmb_bad_request', __('Nothing to do.', 'wp-map-block'), ['status' => 400]);
        }

        $done = 0;
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== WPMAPBLOCK_POST_TYPE) {
                continue;
            }
            // Enforce the per-object capability for this action; silently skip
            // maps the caller is not allowed to touch rather than failing the
            // whole batch.
            if ($action === 'delete') {
                if (!current_user_can('delete_post', $id)) {
                    continue;
                }
                wp_delete_post($id, true);
            } elseif ($action === 'activate') {
                if (!current_user_can('edit_post', $id) || !current_user_can('publish_post', $id)) {
                    continue;
                }
                wp_update_post(['ID' => $id, 'post_status' => 'publish']);
            } else { // deactivate
                if (!current_user_can('edit_post', $id)) {
                    continue;
                }
                wp_update_post(['ID' => $id, 'post_status' => 'draft']);
            }
            $done++;
        }
        return rest_ensure_response(['action' => $action, 'affected' => $done]);
    }

    /**
     * Read + decode the stored config, always returning a valid document.
     */
    public static function get_config($post_id)
    {
        $raw = get_post_meta($post_id, Config::META_KEY, true);
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        return Config::sanitize(is_array($decoded) ? $decoded : []);
    }

    private static function to_summary($post)
    {
        $config = self::get_config($post->ID);
        return [
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'provider'     => $config['provider'],
            'markersCount' => count($config['markers']),
            'shortcode'    => '[wp_map id="' . $post->ID . '"]',
            'modified'     => get_post_modified_time('c', true, $post),
            'status'       => $post->post_status === 'publish' ? 'active' : 'inactive',
        ];
    }

    private static function to_full($post)
    {
        return [
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'config'    => self::get_config($post->ID),
            'shortcode' => '[wp_map id="' . $post->ID . '"]',
            'modified'  => get_post_modified_time('c', true, $post),
        ];
    }
}
