<?php

namespace WPMapBlock\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * StoreEngine integration — exposes StoreEngine products as a map listing
 * source and adds the location metabox to the product editor. Reference
 * implementation of the public `wpmb_register_listing_source()` API.
 */
class StoreEngine
{
    public static function init()
    {
        if (!self::active()) {
            return;
        }
        add_action('wpmb_register_integrations', [self::class, 'register']);
        // The product post type registers on `init`; bind the metabox after that.
        add_action('init', [self::class, 'enable_metabox'], 25);
    }

    public static function enable_metabox()
    {
        Location_Meta::enable_for(self::post_type());
    }

    public static function active()
    {
        return defined('STOREENGINE_VERSION') || class_exists('StoreEngine') || post_type_exists('storeengine_product');
    }

    private static function post_type()
    {
        return post_type_exists('storeengine_product') ? 'storeengine_product' : 'product';
    }

    private static function taxonomy()
    {
        $pt = self::post_type();
        foreach (['storeengine_product_category', 'product_cat'] as $tax) {
            if (taxonomy_exists($tax) && in_array($tax, get_object_taxonomies($pt), true)) {
                return $tax;
            }
        }
        return '';
    }

    public static function register()
    {
        wpmb_register_listing_source('storeengine_products', [
            'label'     => __('StoreEngine Products', 'wp-map-block'),
            'group'     => 'StoreEngine',
            'post_type' => self::post_type(),
            'lat_meta'  => Location_Meta::LAT,
            'lng_meta'  => Location_Meta::LNG,
            'taxonomy'  => self::taxonomy(),
        ]);
    }
}
