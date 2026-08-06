<?php

namespace WPMapBlock\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a lightweight "Map Location" metabox (latitude/longitude + address
 * geocode) to any post type an integration opts in. Stores `_wpmb_lat` /
 * `_wpmb_lng` — the meta keys the bundled listing sources read.
 */
class Location_Meta
{
    const LAT = '_wpmb_lat';
    const LNG = '_wpmb_lng';

    /** @var string[] */
    private static $post_types = [];

    private static $hooked = false;

    /**
     * Enable the metabox for a post type (idempotent).
     */
    public static function enable_for($post_type)
    {
        if (!in_array($post_type, self::$post_types, true)) {
            self::$post_types[] = $post_type;
        }
        if (!self::$hooked) {
            self::$hooked = true;
            add_action('add_meta_boxes', [self::class, 'add_box']);
            add_action('save_post', [self::class, 'save'], 10, 2);
        }
    }

    public static function add_box()
    {
        foreach (self::$post_types as $pt) {
            add_meta_box(
                'wpmb-location',
                __('Map Location', 'wp-map-block'),
                [self::class, 'render'],
                $pt,
                'side',
                'default'
            );
        }
    }

    public static function render($post)
    {
        wp_nonce_field('wpmb_location', 'wpmb_location_nonce');
        $lat = get_post_meta($post->ID, self::LAT, true);
        $lng = get_post_meta($post->ID, self::LNG, true);
        ?>
        <p style="margin:0 0 8px">
            <input type="text" id="wpmb-geo-address" placeholder="<?php esc_attr_e('Type an address…', 'wp-map-block'); ?>" style="width:72%" />
            <button type="button" class="button" id="wpmb-geo-btn"><?php esc_html_e('Find', 'wp-map-block'); ?></button>
        </p>
        <p style="margin:0 0 6px">
            <label style="display:block;font-size:11px;color:#646970"><?php esc_html_e('Latitude', 'wp-map-block'); ?></label>
            <input type="text" name="wpmb_lat" id="wpmb-lat" value="<?php echo esc_attr($lat); ?>" style="width:100%" />
        </p>
        <p style="margin:0">
            <label style="display:block;font-size:11px;color:#646970"><?php esc_html_e('Longitude', 'wp-map-block'); ?></label>
            <input type="text" name="wpmb_lng" id="wpmb-lng" value="<?php echo esc_attr($lng); ?>" style="width:100%" />
        </p>
        <script>
        (function () {
            var btn = document.getElementById('wpmb-geo-btn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var q = document.getElementById('wpmb-geo-address').value.trim();
                if (!q) return;
                btn.disabled = true;
                btn.textContent = '…';
                fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d && d[0]) {
                            document.getElementById('wpmb-lat').value = (+d[0].lat).toFixed(6);
                            document.getElementById('wpmb-lng').value = (+d[0].lon).toFixed(6);
                        }
                    })
                    .finally(function () { btn.disabled = false; btn.textContent = '<?php echo esc_js(__('Find', 'wp-map-block')); ?>'; });
            });
        })();
        </script>
        <?php
    }

    public static function save($post_id, $post)
    {
        if (!isset($_POST['wpmb_location_nonce']) || !wp_verify_nonce(sanitize_key($_POST['wpmb_location_nonce']), 'wpmb_location')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        foreach ([self::LAT => 'wpmb_lat', self::LNG => 'wpmb_lng'] as $meta => $field) {
            if (isset($_POST[$field])) {
                $val = trim(sanitize_text_field(wp_unslash($_POST[$field])));
                if ($val === '') {
                    delete_post_meta($post_id, $meta);
                } else {
                    update_post_meta($post_id, $meta, (float) $val);
                }
            }
        }
    }
}
