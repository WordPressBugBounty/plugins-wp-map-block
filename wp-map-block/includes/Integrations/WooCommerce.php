<?php

namespace WPMapBlock\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce integration — exposes WooCommerce products (with a location) as a
 * map listing source and adds the location metabox to the product editor.
 */
class WooCommerce
{
    public static function init()
    {
        if (!self::active()) {
            return;
        }
        add_action('wpmb_register_integrations', [self::class, 'register']);
        Location_Meta::enable_for('product');
    }

    public static function active()
    {
        return class_exists('WooCommerce') || defined('WC_VERSION');
    }

    public static function register()
    {
        wpmb_register_listing_source('woocommerce_products', [
            'label'     => __('WooCommerce Products', 'wp-map-block'),
            'group'     => 'WooCommerce',
            'post_type' => 'product',
            'lat_meta'  => Location_Meta::LAT,
            'lng_meta'  => Location_Meta::LNG,
            'taxonomy'  => 'product_cat',
            'mapper'    => [self::class, 'map_product'],
        ]);
    }

    /**
     * Product → marker with price appended to the popup. Falls back to the
     * default mapping for coordinates/title/thumbnail.
     */
    public static function map_product($post, $source)
    {
        $lat = (float) get_post_meta($post->ID, $source['lat_meta'], true);
        $lng = (float) get_post_meta($post->ID, $source['lng_meta'], true);
        if (!$lat || !$lng) {
            return null;
        }

        $category = '';
        $terms = get_the_terms($post->ID, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $category = 'tax_' . $terms[0]->term_id;
        }

        $thumb = get_the_post_thumbnail_url($post->ID, 'thumbnail');
        $price = function_exists('wc_get_product') ? self::price_html($post->ID) : '';
        $excerpt = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags($post->post_content), 20);

        $content = '';
        if ($thumb) {
            $content .= '<img src="' . esc_url($thumb) . '" style="max-width:100%;border-radius:6px;margin-bottom:6px" />';
        }
        $content .= esc_html($excerpt);
        if ($price) {
            $content .= '<div style="margin-top:4px;font-weight:600">' . $price . '</div>';
        }

        return [
            'id'       => 'wc_' . $post->ID,
            'lat'      => $lat,
            'lng'      => $lng,
            'title'    => get_the_title($post),
            'content'  => $content,
            'link'     => get_permalink($post),
            'category' => $category,
            'icon'     => ['type' => 'default', 'url' => '', 'color' => ''],
        ];
    }

    private static function price_html($product_id)
    {
        $product = wc_get_product($product_id);
        return $product ? wp_kses_post($product->get_price_html()) : '';
    }
}
