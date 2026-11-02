<?php

namespace WPMapBlock\Legacy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Backward-compat layer for the original `wpmapblock/wp-map-block` block.
 *
 * The 20k existing installs have posts containing this dynamic block. We keep
 * its registration, render callback and Leaflet assets byte-identical to 2.0.4
 * so upgrading to the new all-in-one plugin never breaks an existing map.
 *
 * All legacy assets live under assets/legacy/ (copied verbatim from 2.0.4).
 */
class Block
{
    /** Cache key for the content scan behind the admin's "Classic maps" list. */
    const SCAN_TRANSIENT = 'wpmb_classic_maps_scan';

    private static function legacy_uri()
    {
        return WPMAPBLOCK_ASSETS_URI . 'legacy/';
    }

    private static function legacy_path()
    {
        return WPMAPBLOCK_ASSETS_DIR_PATH . 'legacy/';
    }

    public static function init()
    {
        add_action('init', [self::class, 'register_assets']);
        add_action('init', [self::class, 'register_block']);
        add_action('wp_enqueue_scripts', [self::class, 'register_core_scripts']);
        // Editing content can add or remove a classic block, so drop the scan.
        add_action('save_post', [self::class, 'flush_scan']);
        add_action('deleted_post', [self::class, 'flush_scan']);
    }

    public static function flush_scan()
    {
        delete_transient(self::SCAN_TRANSIENT);
    }

    /**
     * Every classic block still embedded in site content, as the admin's
     * "Classic maps" list shows them.
     *
     * Classic maps are block attributes inside post_content, never `wpmb_map`
     * posts — so nothing in the admin ever listed them. A site upgrading from
     * 2.x opened an empty map library even though its pages were full of maps,
     * which reads as "my maps are gone".
     *
     * @param int $limit Most posts to scan.
     * @return array<int,array>
     */
    public static function find_in_content($limit = 200)
    {
        // Cache the whole scan, then filter per viewer — caching the filtered
        // result would leak the first caller's visible posts to everyone else.
        return array_values(array_filter(
            self::scan($limit),
            static function ($item) {
                return current_user_can('edit_post', $item['postId']);
            }
        ));
    }

    private static function scan($limit)
    {
        $cached = get_transient(self::SCAN_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        // A LIKE scan is unavoidable — this data only exists inside post_content.
        // It is capped, cached for a few minutes, and invalidated on save.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- no post meta/taxonomy to query instead; result is cached in a transient above.
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status NOT IN ('trash', 'auto-draft', 'inherit')
                   AND post_content LIKE %s
                 ORDER BY post_modified DESC
                 LIMIT %d",
                '%' . $wpdb->esc_like('wp:wpmapblock/wp-map-block') . '%',
                (int) $limit
            )
        );

        $found = [];
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post) {
                continue;
            }
            $index = 0;
            foreach (self::collect_blocks(parse_blocks($post->post_content)) as $block) {
                $found[] = self::summarize($post, isset($block['attrs']) ? $block['attrs'] : [], $index++);
            }
        }

        set_transient(self::SCAN_TRANSIENT, $found, 5 * MINUTE_IN_SECONDS);
        return $found;
    }

    /** Walk nested blocks — a classic map can sit inside a group or columns. */
    private static function collect_blocks($blocks)
    {
        $out = [];
        foreach ($blocks as $block) {
            if (isset($block['blockName']) && 'wpmapblock/wp-map-block' === $block['blockName']) {
                $out[] = $block;
            }
            if (!empty($block['innerBlocks'])) {
                $out = array_merge($out, self::collect_blocks($block['innerBlocks']));
            }
        }
        return $out;
    }

    private static function summarize($post, $attrs, $index)
    {
        $markers = isset($attrs['map_marker_list']) && is_array($attrs['map_marker_list'])
            ? $attrs['map_marker_list']
            : [];
        $first     = $markers ? reset($markers) : [];
        $type      = isset($attrs['map_type']) ? $attrs['map_type'] : 'GM';
        $post_type = get_post_type_object($post->post_type);

        return [
            'postId'    => (int) $post->ID,
            'postTitle' => '' !== $post->post_title ? $post->post_title : __('(no title)', 'wp-map-block'),
            'postType'  => $post_type ? $post_type->labels->singular_name : $post->post_type,
            'status'    => $post->post_status,
            'index'     => (int) $index,
            'title'     => isset($first['title']) ? (string) $first['title'] : '',
            'markers'   => count($markers),
            'provider'  => 'OSM' === $type
                ? __('OpenStreetMap', 'wp-map-block')
                : __('Google Maps', 'wp-map-block'),
            'editUrl'   => get_edit_post_link($post->ID, 'raw'),
        ];
    }

    public static function register_core_scripts()
    {
        wp_register_script('wpmapblock-leaflet', self::legacy_uri() . 'js/leaflet.js', ['jquery'], WPMAPBLOCK_VERSION, true);
        wp_register_script('wpmapblock-leaflet-fullscreen', self::legacy_uri() . 'js/Control.FullScreen.js', ['jquery'], WPMAPBLOCK_VERSION, true);
    }

    public static function register_assets()
    {
        // Leaflet also needs registering on init so the editor + render path can enqueue.
        wp_register_script('wpmapblock-leaflet', self::legacy_uri() . 'js/leaflet.js', ['jquery'], WPMAPBLOCK_VERSION, true);
        wp_register_script('wpmapblock-leaflet-fullscreen', self::legacy_uri() . 'js/Control.FullScreen.js', ['jquery'], WPMAPBLOCK_VERSION, true);

        $frontend = self::legacy_path() . 'dist/wpmapblock-frontend.core.min.asset.php';
        $frontend = file_exists($frontend) ? include $frontend : ['dependencies' => [], 'version' => WPMAPBLOCK_VERSION];

        wp_register_style('wp-map-block-stylesheets', self::legacy_uri() . 'dist/wpmapblock-frontend.core.min.css', [], $frontend['version']);
        wp_register_script('wp-map-block-frontend-js', self::legacy_uri() . 'dist/wpmapblock-frontend.core.min.js', $frontend['dependencies'], $frontend['version'], true);

        // The original 2.x editor bundle (old react-leaflet/React) crashes in the
        // modern block editor. We register a safe replacement editor built from
        // src/frontend/legacy.js: it renders the classic block as a live iframe
        // preview (through the same Leaflet render path as the frontend) plus a
        // few controls, hides the block from the inserter, and keeps the dynamic
        // render + attributes intact so every existing post stays valid.
        $legacy_edit = WPMAPBLOCK_BUILD_DIR_PATH . 'legacy.asset.php';
        if (file_exists($legacy_edit)) {
            $legacy_edit = include $legacy_edit;
            wp_register_script(
                'wpmb-legacy-edit',
                WPMAPBLOCK_BUILD_URI . 'legacy.js',
                $legacy_edit['dependencies'],
                $legacy_edit['version'],
                true
            );
            // Leaflet's stylesheet (bundled from the editor JS) — needed so the
            // draggable map inside the marker modal is styled correctly. Depends
            // on dashicons so the first-run picker's icons render inside the
            // editor canvas iframe, which does not inherit wp-admin's styles.
            if (file_exists(WPMAPBLOCK_BUILD_DIR_PATH . 'legacy.css')) {
                wp_register_style('wpmb-legacy-edit', WPMAPBLOCK_BUILD_URI . 'legacy.css', ['dashicons'], $legacy_edit['version']);
            }
            wp_set_script_translations('wpmb-legacy-edit', 'wp-map-block');
            wp_localize_script('wpmb-legacy-edit', 'WPMB_LEGACY', [
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'previewNonce'  => wp_create_nonce('wpmb_preview'),
                'markerIconUrl' => self::legacy_uri() . 'images/marker-icon.png',
                // Deep link into the builder, for the "upgrade this map" path.
                'adminUrl'      => admin_url('admin.php?page=wp-map-block'),
            ]);
            // Shared first-run picker config (same as the modern block), so a
            // freshly inserted classic block offers the same chooser + upsell.
            wp_localize_script('wpmb-legacy-edit', 'WPMB_PICKER', \WPMapBlock\Blocks\Embed_Block::picker_config());
        }
    }

    public static function register_block()
    {
        register_block_type('wpmapblock/wp-map-block', [
            'editor_style_handles'  => ['wpmb-legacy-edit', 'wp-map-block-stylesheets'],
            'editor_script_handles' => ['wpmb-legacy-edit'],
            'render_callback'       => [self::class, 'render_callback'],
            // Declare the attribute schema server-side too (identical to the JS
            // block). Without it, WordPress can hand the editor/render an undefined
            // map_marker_list for older posts, which crashed the block ("cannot be
            // previewed") and broke the frontend. With the schema, defaults are
            // always applied so map_marker_list is a real array everywhere.
            'attributes'            => [
                'map_id'            => ['type' => 'string'],
                'map_marker_list'   => ['type' => 'array', 'default' => []],
                'map_zoom'          => ['type' => 'number', 'default' => 10],
                'map_type'          => ['type' => 'string', 'default' => 'GM'],
                'map_width'         => ['type' => 'number', 'default' => 100],
                'map_height'        => ['type' => 'number', 'default' => 500],
                'scroll_wheel_zoom' => ['type' => 'boolean', 'default' => false],
                'center_index'      => ['type' => 'number', 'default' => 0],
            ],
        ]);
    }

    private static function escaping_array_data($array)
    {
        if (!is_array($array)) {
            return [];
        }
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = self::escaping_array_data($value);
            } elseif ('content' === $key) {
                $value = is_scalar($value) ? self::escape_marker_content((string) $value) : '';
            } else {
                $value = is_scalar($value) ? esc_attr(sanitize_text_field((string) $value)) : '';
            }
        }
        return $array;
    }

    /**
     * Marker text is typed into a textarea, so it can contain line breaks — but
     * `sanitize_text_field()` flattens them into spaces, and the popup builds its
     * HTML as `<p>{content}</p>`, where a bare newline collapses anyway. So the
     * author's paragraphs were lost twice over.
     *
     * Keep the newlines (`sanitize_textarea_field`), escape the text, and only
     * THEN insert `<br>` — inserting it earlier would get the tag escaped along
     * with the rest. The `<br>` survives `wp_json_encode()`'s JSON_HEX_TAG pass
     * as `<br>`, so it reaches the popup as real markup while every
     * character the author typed stays inert text.
     */
    private static function escape_marker_content($value)
    {
        return nl2br(esc_attr(sanitize_textarea_field($value)), false);
    }

    public static function render_callback($attributes, $content = '')
    {
        wp_enqueue_style('wp-map-block-stylesheets');
        wp_enqueue_script('wpmapblock-leaflet');
        wp_enqueue_script('wpmapblock-leaflet-fullscreen');
        wp_enqueue_script('wp-map-block-frontend-js');

        $settings = [
            'map_marker'        => self::escaping_array_data(isset($attributes['map_marker_list']) ? $attributes['map_marker_list'] : []),
            'map_zoom'          => (isset($attributes['map_zoom']) ? max(0, min(24, (int) $attributes['map_zoom'])) : 10),
            'scroll_wheel_zoom' => (isset($attributes['scroll_wheel_zoom']) ? filter_var($attributes['scroll_wheel_zoom'], FILTER_VALIDATE_BOOLEAN) : false),
            'map_type'          => (isset($attributes['map_type']) && 'OSM' === $attributes['map_type'] ? 'OSM' : 'GM'),
            'center_index'      => (isset($attributes['center_index']) ? max(0, (int) $attributes['center_index']) : 0),
        ];

        // Block attributes come from post content, so force them to numbers
        // before they reach the inline style.
        $map_width  = (isset($attributes['map_width']) ? max(1, min(100, (int) $attributes['map_width'])) : 100) . '%';
        $map_height = (isset($attributes['map_height']) ? max(50, min(5000, (int) $attributes['map_height'])) : 500) . 'px';
        $style      = "width: {$map_width}; height: {$map_height};";

        $map_id = isset($attributes['map_id']) && is_scalar($attributes['map_id']) ? $attributes['map_id'] : '';

        return sprintf(
            '<div id="%1$s" data-settings="%2$s" class="wpmapblockrender" style="%3$s"></div>',
            esc_attr($map_id),
            esc_attr(wp_json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)),
            esc_attr($style)
        );
    }
}
