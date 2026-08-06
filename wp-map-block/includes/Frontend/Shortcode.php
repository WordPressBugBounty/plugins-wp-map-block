<?php

namespace WPMapBlock\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * `[wp_map id="123"]` — render a saved map anywhere.
 */
class Shortcode
{
    public static function init()
    {
        add_shortcode('wp_map', [self::class, 'render']);
    }

    public static function render($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts, 'wp_map');
        $id   = (int) $atts['id'];
        if (!$id) {
            return '';
        }
        return Renderer::render_map($id);
    }
}
