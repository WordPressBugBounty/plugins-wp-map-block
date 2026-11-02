<?php

namespace WPMapBlock\Blocks;

use WPMapBlock\Frontend\Renderer;
use WPMapBlock\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * `wpmapblock/map-embed` — the primary "WP Map Block". On insert it offers a
 * choice: embed a map built in the builder, or drop in a quick simple map
 * (single location). Server-rendered so the engine handles all providers.
 */
class Embed_Block
{
    public static function init()
    {
        add_action('init', [self::class, 'register']);
        add_action('wp_ajax_wpmb_map_preview', [self::class, 'ajax_preview']);
    }

    /**
     * Outputs a standalone HTML page rendering the map, for the block editor to
     * embed in an <iframe>. This renders through the exact same frontend engine
     * as the published page, so the editor preview is a real, live map — and it
     * avoids ServerSideRender (which other plugins break by injecting block
     * attributes) and the Gutenberg editor-iframe WebGL pitfalls.
     */
    public static function ajax_preview()
    {
        if (!current_user_can('edit_posts') || !check_ajax_referer('wpmb_preview', false, false)) {
            status_header(403);
            exit;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : '';
        if ($mode === 'simple') {
            $html = self::render_simple(wp_unslash($_GET));
        } elseif ($mode === 'legacy') {
            // Render the classic (legacy) block from its attributes.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload validated as an array below; the request is nonce-checked at the top of ajax_preview().
            $attrs = isset($_GET['legacy']) && is_string($_GET['legacy']) ? json_decode(wp_unslash($_GET['legacy']), true) : [];
            $html  = is_array($attrs) ? \WPMapBlock\Legacy\Block::render_callback($attrs) : '';
        } else {
            $id   = isset($_GET['map']) ? absint(wp_unslash($_GET['map'])) : 0;
            $html = $id ? Renderer::render_map($id) : '';
        }
        // phpcs:enable

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8">';
        // Fill the iframe: the block editor sizes the iframe itself, so the map
        // (new engine .wpmb-map) fills it 100% — width%/height are applied to the
        // iframe, not re-fetched, so resizing never reloads the preview.
        echo '<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;background:#fff}.wpmb-map{width:100%!important;height:100%!important;border-radius:0 !important}.wpmapblockrender{border-radius:0 !important}</style>';
        // Assets were registered/enqueued by the renderer above; print the set
        // matching the render path (legacy = Leaflet, others = the new engine).
        if ($mode === 'legacy') {
            wp_print_styles(['wp-map-block-stylesheets']);
        } else {
            wp_print_styles(['wpmb-maplibre', 'wpmb-frontend']);
        }
        echo '</head><body>';
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the Renderer / engine.
        if ($mode === 'legacy') {
            wp_print_scripts(['jquery', 'wpmapblock-leaflet', 'wpmapblock-leaflet-fullscreen', 'wp-map-block-frontend-js']);
        } else {
            wp_print_scripts(['wpmb-frontend']);
        }
        echo '</body></html>';
        exit;
    }

    /**
     * Config for the shared first-run picker (new block + classic block): the
     * templates, a link into the builder, and the Pro catalog for the upsell.
     * Localized as `window.WPMB_PICKER` on both editors so the chooser is
     * identical wherever it appears.
     */
    public static function picker_config(): array
    {
        $pro      = \WPMapBlock\Pro::localized();
        $catalog  = isset($pro['features']) && is_array($pro['features']) ? $pro['features'] : [];
        $features = [];
        foreach ($catalog as $slug => $meta) {
            $features[] = [
                'slug'      => $slug,
                'title'     => isset($meta['title']) ? $meta['title'] : $slug,
                'desc'      => isset($meta['desc']) ? $meta['desc'] : '',
                'icon'      => isset($meta['icon']) ? $meta['icon'] : 'dashicons-lock',
                'available' => !empty($meta['available']),
            ];
        }

        return [
            'presets' => \WPMapBlock\Presets::summaries(),
            'newUrl'  => admin_url('admin.php?page=wp-map-block#/new'),
            'pro'     => [
                'active'     => !empty($pro['active']),
                'upsell'     => !empty($pro['upsell']),
                'compareUrl' => isset($pro['compareUrl']) ? $pro['compareUrl'] : admin_url('admin.php?page=wp-map-block-compare'),
                'features'   => $features,
            ],
        ];
    }

    public static function register()
    {
        $asset_file = WPMAPBLOCK_BUILD_DIR_PATH . 'embed.asset.php';
        if (file_exists($asset_file)) {
            $asset = include $asset_file;
            wp_register_script(
                'wpmb-embed-block',
                WPMAPBLOCK_BUILD_URI . 'embed.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );

            wp_localize_script('wpmb-embed-block', 'WPMB_EMBED', [
                'restUrl'      => esc_url_raw(rest_url(WPMAPBLOCK_REST_NAMESPACE)),
                'nonce'        => wp_create_nonce('wp_rest'),
                'adminUrl'     => admin_url('admin.php?page=wp-map-block'),
                'newUrl'       => admin_url('admin.php?page=wp-map-block#/new'),
                'providers'        => Config::providers(),
                'premiumUnlocked'  => \WPMapBlock\Pro::has('premium-providers'),
                'ajaxUrl'          => admin_url('admin-ajax.php'),
                'previewNonce'     => wp_create_nonce('wpmb_preview'),
                // Templates for the block's first-run screen. Without them the
                // block opens on an empty chooser and nothing tells the author
                // the builder exists at all.
                'presets'          => \WPMapBlock\Presets::summaries(),
            ]);

            // Shared first-run picker config — used by both this block and the
            // classic block, so the chooser (and its Pro upsell) stays identical.
            wp_localize_script('wpmb-embed-block', 'WPMB_PICKER', self::picker_config());
        }

        // The block editor canvas is its own iframe, which does not inherit
        // wp-admin's stylesheets — so the icons on the block's first-run screen
        // render as empty boxes unless dashicons is pulled in as a block style.
        // A src-less handle is just the dependency, no extra request.
        if (!wp_style_is('wpmb-embed-block', 'registered')) {
            wp_register_style('wpmb-embed-block', false, ['dashicons'], WPMAPBLOCK_VERSION);
        }

        register_block_type('wpmapblock/map-embed', [
            'api_version'          => 3,
            'editor_script'        => 'wpmb-embed-block',
            'editor_style'         => 'wpmb-embed-block',
            'attributes'      => [
                'mode'  => ['type' => 'string', 'default' => ''],
                'mapId' => ['type' => 'number', 'default' => 0],
                // Simple-map attributes use the ORIGINAL wp-map-block names so the
                // schema stays 1:1 with the classic block (backward compatible +
                // trivial to migrate). `map_type` accepts the expanded provider
                // slugs AND legacy 'GM'/'OSM'.
                'map_type'          => ['type' => 'string', 'default' => 'openstreetmap'],
                'map_zoom'          => ['type' => 'number', 'default' => 10],
                'map_width'         => ['type' => 'number', 'default' => 100],
                'map_height'        => ['type' => 'number', 'default' => 500],
                'scroll_wheel_zoom' => ['type' => 'boolean', 'default' => false],
                'center_index'      => ['type' => 'number', 'default' => 0],
                // [{ id, lat, lng, title, content }] — same shape as the classic block.
                'map_marker_list'   => ['type' => 'array', 'default' => []],
            ],
            'render_callback' => [self::class, 'render'],
        ]);
    }

    public static function render($attributes)
    {
        $mode = isset($attributes['mode']) ? $attributes['mode'] : '';

        if ('simple' === $mode) {
            return self::render_simple($attributes);
        }

        $id = isset($attributes['mapId']) ? (int) $attributes['mapId'] : 0;
        if (!$id) {
            return '';
        }
        return Renderer::render_map($id);
    }

    /**
     * Render a quick single-location "simple map" straight from block attributes
     * (no CPT map needed).
     */
    private static function render_simple($a)
    {
        return Renderer::render_config(self::simple_config($a), 'wpmb-simple-' . wp_rand(1000, 999999));
    }

    /**
     * Build the map config a simple block renders from.
     *
     * Public because it is also the "upgrade this block into a real map"
     * conversion: the simple block and the classic block share this attribute
     * shape, so promoting either into a saved map is exactly this config — which
     * guarantees the saved map opens looking like the block it replaced.
     *
     * @param array $a Block attributes (simple or classic).
     * @return array Sanitized config.
     */
    public static function simple_config($a)
    {
        // Provider from `map_type`; map legacy GM/OSM to provider slugs. Falls
        // back to the earlier `provider` attr for any pre-rename simple blocks.
        $type = isset($a['map_type']) ? $a['map_type'] : (isset($a['provider']) ? $a['provider'] : 'openstreetmap');
        $type = is_string($type) ? $type : 'openstreetmap';
        $legacy_type = ['GM' => 'google', 'OSM' => 'openstreetmap'];
        $provider = isset($legacy_type[$type]) ? $legacy_type[$type] : $type;

        // Markers from `map_marker_list` (array from block attrs, or JSON from the
        // ajax preview). Fallbacks: the earlier `markers` attr, then the original
        // single flat marker — so no simple block, past or present, breaks.
        $raw = isset($a['map_marker_list']) ? $a['map_marker_list'] : (isset($a['markers']) ? $a['markers'] : null);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $markers = [];
        if (is_array($raw)) {
            foreach ($raw as $m) {
                if (!is_array($m) || !isset($m['lat'], $m['lng']) || $m['lat'] === '' || $m['lng'] === '') {
                    continue;
                }
                $markers[] = self::simple_marker($m, count($markers));
            }
        }
        if (!$markers && isset($a['lat'], $a['lng']) && $a['lat'] !== '' && $a['lng'] !== '') {
            $markers[] = self::simple_marker([
                'lat' => $a['lat'], 'lng' => $a['lng'],
                'title' => isset($a['title']) ? $a['title'] : '',
                'content' => isset($a['content']) ? $a['content'] : '',
            ], 0);
        }
        if (!$markers) {
            $markers[] = self::simple_marker(['lat' => 40.7128, 'lng' => -74.006], 0);
        }

        // Center on the chosen marker (classic `center_index`).
        $ci = isset($a['center_index']) ? (int) $a['center_index'] : 0;
        if ($ci < 0 || $ci >= count($markers)) {
            $ci = 0;
        }

        $scroll = isset($a['scroll_wheel_zoom']) ? filter_var($a['scroll_wheel_zoom'], FILTER_VALIDATE_BOOLEAN) : false;

        $config = Config::defaults();
        $config['provider']              = $provider;
        $config['view']['center']        = ['lat' => $markers[$ci]['lat'], 'lng' => $markers[$ci]['lng']];
        $config['view']['zoom']          = isset($a['map_zoom']) ? (int) $a['map_zoom'] : (isset($a['zoom']) ? (int) $a['zoom'] : 10);
        $config['size']['width']         = isset($a['map_width']) ? ((int) $a['map_width']) . '%' : '100%';
        $config['size']['height']        = isset($a['map_height']) ? (int) $a['map_height'] : (isset($a['height']) ? (int) $a['height'] : 500);
        $config['controls']['scrollZoom'] = $scroll;
        $config['markers']               = $markers;

        return Config::sanitize($config);
    }

    private static function simple_marker($m, $i)
    {
        // The classic block stored custom pins as iconType/customIconUrl. Carry
        // them across so converting a classic block into a real map keeps its
        // pins instead of silently resetting them to the default marker.
        $custom_icon = isset($m['iconType'], $m['customIconUrl']) && 'custom' === $m['iconType']
            ? (string) $m['customIconUrl']
            : '';

        return [
            'id'       => isset($m['id']) && $m['id'] !== '' ? (string) $m['id'] : 'mk_' . $i,
            'lat'      => (float) $m['lat'],
            'lng'      => (float) $m['lng'],
            'title'    => isset($m['title']) ? $m['title'] : '',
            'content'  => isset($m['content']) ? $m['content'] : '',
            'link'     => '',
            'category' => '',
            'icon'     => [
                'type'   => $custom_icon ? 'image' : 'default',
                'preset' => '',
                'url'    => $custom_icon,
                'color'  => '',
            ],
        ];
    }
}
