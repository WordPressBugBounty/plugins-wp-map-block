<?php

namespace WPMapBlock\Frontend;

use WPMapBlock\Rest\Maps_Controller;
use WPMapBlock\Settings;
use WPMapBlock\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Turns a saved map into a hydration-ready container for the frontend engine.
 */
class Renderer
{
    /**
     * Render a saved map by post id.
     */
    public static function render_map($map_id)
    {
        $map_id = (int) $map_id;
        $post   = get_post($map_id);
        if (!$post || $post->post_type !== WPMAPBLOCK_POST_TYPE) {
            return '';
        }
        // Inactive (draft) maps are hidden from visitors, but still render for
        // editors so the block editor preview and logged-in checks keep working.
        if ($post->post_status !== 'publish' && !current_user_can('edit_posts')) {
            return '';
        }

        $config = Maps_Controller::get_config($map_id);
        // The store locator searches the map's own markers via our REST API, so it
        // needs the map id + REST base. Only attached when the locator is on.
        if (!empty($config['storeLocator']['enabled'])) {
            $config['mapId']    = $map_id;
            $config['restBase'] = esc_url_raw(rest_url(WPMAPBLOCK_REST_NAMESPACE));
        }
        return self::render_config($config, 'wpmb-map-' . $map_id);
    }

    /**
     * Render an arbitrary (already-sanitized) config document. Injects the
     * resolved provider API key so the frontend never has to expose settings.
     */
    public static function render_config($config, $dom_id)
    {
        Assets::enqueue_frontend();

        // Paywall: drop any Pro-gated capabilities that aren't unlocked, before
        // they can resolve or reach the browser. Pro modules enrich via the
        // filter once their capability is on.
        $config = \WPMapBlock\Pro::sanitize_config((array) $config);
        $config = apply_filters('wpmb/render/config', $config, $dom_id);

        $providers = Config::providers();
        $provider  = isset($config['provider']) ? $config['provider'] : 'openstreetmap';

        // Resolve the key: a per-map override wins, else the global setting.
        if (empty($config['providerOptions']['apiKey']) && !empty($providers[$provider]['needsKey'])) {
            $config['providerOptions']['apiKey'] = Settings::api_key($provider);
        }

        // Materialize server-side dynamic data sources (CPT) into markers.
        $config = \WPMapBlock\Frontend\DataSources::resolve($config);

        $width  = isset($config['size']['width']) ? $config['size']['width'] : '100%';
        $height = isset($config['size']['height']) ? (int) $config['size']['height'] : 500;
        // Numeric width is treated as a percentage for backward familiarity.
        if (is_numeric($width)) {
            $width .= '%';
        }
        $style = sprintf('width:%s;height:%dpx;', esc_attr($width), $height);

        $json = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return sprintf(
            '<div class="wpmb-map" id="%1$s" data-wpmb-config="%2$s" style="%3$s"></div>',
            esc_attr($dom_id),
            esc_attr($json),
            $style
        );
    }
}
