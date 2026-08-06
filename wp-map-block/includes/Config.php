<?php

namespace WPMapBlock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central definition + sanitization for a map's configuration document.
 * This is the single source of truth for the shape stored in `_wpmb_config`
 * and shipped to the frontend engine.
 */
class Config
{
    const META_KEY = '_wpmb_config';
    const SCHEMA_VERSION = 1;

    /**
     * The default config used for a brand new map.
     */
    public static function defaults()
    {
        return [
            'version'         => self::SCHEMA_VERSION,
            'provider'        => 'openstreetmap',
            'providerOptions' => [
                'apiKey'    => '',
                'styleUrl'  => '',
                'styleName' => '',
            ],
            'view' => [
                'center'  => ['lat' => 40.7128, 'lng' => -74.006],
                'zoom'    => 10,
                'minZoom' => 0,
                'maxZoom' => 20,
                'pitch'   => 0,
                'bearing' => 0,
            ],
            'size' => [
                'width'  => '100%',
                'height' => 500,
            ],
            'controls' => [
                'zoom'       => true,
                'fullscreen' => true,
                'scale'      => false,
                'geolocate'  => false,
                'scrollZoom' => true,
            ],
            // Base map type: roadmap | satellite | hybrid | terrain. Applied
            // natively on Google and via swappable raster sources on MapLibre.
            'mapType' => 'roadmap',
            // Google overlay layers (native to the Google backend).
            'layers'  => [
                'traffic' => false,
                'transit' => false,
                'bicycle' => false,
            ],
            // Map-wide marker behaviour.
            'markerOptions' => [
                'animation' => 'none', // none | bounce | drop | pulse
                'draggable' => false,
            ],
            'markers'    => [],
            'shapes'     => [],
            'categories' => [],
            'clustering' => [
                'enabled' => false,
                'radius'  => 50,
                'maxZoom' => 14,
            ],
            'dataSources' => [],
            'heatmap'     => [
                'enabled'   => false,
                'radius'    => 30,
                'intensity' => 1,
            ],
            'storeLocator' => [
                'enabled'     => false,
                'placeholder' => __('Search a location…', 'wp-map-block'),
                'radius'      => 25,
                'unit'        => 'km',
                'showList'    => true,
                'geolocate'   => true,
            ],
            'directory' => [
                'enabled'        => false,
                'layout'         => 'side',
                'position'       => 'left',
                'width'          => 320,
                'showSearch'     => true,
                'showCategories' => true,
                'collapsed'      => false,
            ],
            'directions' => [
                'enabled' => false,
                'service' => 'google',
            ],
            'style' => [
                // Base-map appearance: light | dark | auto (follows the visitor's
                // prefers-color-scheme). 'dark' tints the map tiles without
                // affecting markers, popups or controls.
                'theme' => 'light',
                'popup' => ['maxWidth' => 260, 'theme' => 'light', 'preset' => 'card', 'ctaLabel' => 'Learn more'],
            ],
        ];
    }

    /**
     * The catalogue of providers the engine knows how to render, exposed to
     * the builder UI so backends and UI never drift.
     */
    public static function providers()
    {
        $providers = [
            'openstreetmap' => [
                'label'    => __('OpenStreetMap', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => '',
            ],
            'google' => [
                'label'    => __('Google Maps', 'wp-map-block'),
                'engine'   => 'google',
                'needsKey' => true,
                'keyLabel' => __('Google Maps API Key', 'wp-map-block'),
            ],
            'mapbox' => [
                'label'    => __('Mapbox', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => true,
                'keyLabel' => __('Mapbox Access Token', 'wp-map-block'),
            ],
            'maptiler' => [
                'label'    => __('MapTiler', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => true,
                'keyLabel' => __('MapTiler API Key', 'wp-map-block'),
            ],
            'openfreemap' => [
                'label'    => __('OpenFreeMap', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => '',
            ],
            'stadia' => [
                'label'    => __('Stadia Maps', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => __('Stadia API Key (optional)', 'wp-map-block'),
            ],
            'esri' => [
                'label'    => __('Esri / ArcGIS', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => '',
            ],
            'carto' => [
                'label'    => __('CARTO', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => '',
            ],
            'azure' => [
                'label'    => __('Azure Maps', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => true,
                'keyLabel' => __('Azure Maps Subscription Key', 'wp-map-block'),
            ],
        ];

        // Flag which providers are Pro. OpenStreetMap & Google stay free; the
        // rest are a Pro capability (single source of truth in Pro::free_providers).
        $free = class_exists('\WPMapBlock\Pro') ? \WPMapBlock\Pro::free_providers() : ['openstreetmap', 'google'];
        foreach ($providers as $slug => &$p) {
            $p['is_pro'] = !in_array($slug, $free, true);
        }
        unset($p);

        return $providers;
    }

    /**
     * Recursively coerce an incoming config into the known schema. Unknown keys
     * are dropped; every value is type-cast and range-checked.
     */
    public static function sanitize($input)
    {
        $d = self::defaults();
        if (!is_array($input)) {
            return $d;
        }

        $out              = $d;
        $out['version']   = self::SCHEMA_VERSION;
        $providers        = self::providers();
        $out['provider']  = isset($input['provider']) && isset($providers[$input['provider']])
            ? $input['provider'] : $d['provider'];

        if (isset($input['providerOptions']) && is_array($input['providerOptions'])) {
            $out['providerOptions'] = [
                'apiKey'    => isset($input['providerOptions']['apiKey']) ? sanitize_text_field($input['providerOptions']['apiKey']) : '',
                'styleUrl'  => isset($input['providerOptions']['styleUrl']) ? esc_url_raw($input['providerOptions']['styleUrl']) : '',
                'styleName' => isset($input['providerOptions']['styleName']) ? sanitize_text_field($input['providerOptions']['styleName']) : '',
            ];
        }

        if (isset($input['view']) && is_array($input['view'])) {
            $v = $input['view'];
            $out['view'] = [
                'center'  => [
                    'lat' => isset($v['center']['lat']) ? (float) $v['center']['lat'] : $d['view']['center']['lat'],
                    'lng' => isset($v['center']['lng']) ? (float) $v['center']['lng'] : $d['view']['center']['lng'],
                ],
                'zoom'    => isset($v['zoom']) ? self::clampf($v['zoom'], 0, 24) : $d['view']['zoom'],
                'minZoom' => isset($v['minZoom']) ? self::clampf($v['minZoom'], 0, 24) : $d['view']['minZoom'],
                'maxZoom' => isset($v['maxZoom']) ? self::clampf($v['maxZoom'], 0, 24) : $d['view']['maxZoom'],
                'pitch'   => isset($v['pitch']) ? self::clampf($v['pitch'], 0, 85) : 0,
                'bearing' => isset($v['bearing']) ? self::clampf($v['bearing'], 0, 360) : 0,
            ];
        }

        if (isset($input['size']) && is_array($input['size'])) {
            $out['size'] = [
                'width'  => isset($input['size']['width']) ? sanitize_text_field($input['size']['width']) : $d['size']['width'],
                'height' => isset($input['size']['height']) ? (int) $input['size']['height'] : $d['size']['height'],
            ];
        }

        if (isset($input['controls']) && is_array($input['controls'])) {
            foreach ($d['controls'] as $k => $def) {
                $out['controls'][$k] = isset($input['controls'][$k]) ? (bool) $input['controls'][$k] : $def;
            }
        }

        if (isset($input['mapType'])) {
            $mt = sanitize_key($input['mapType']);
            $out['mapType'] = in_array($mt, ['roadmap', 'satellite', 'hybrid', 'terrain'], true) ? $mt : 'roadmap';
        }

        if (isset($input['layers']) && is_array($input['layers'])) {
            foreach ($d['layers'] as $k => $def) {
                $out['layers'][$k] = isset($input['layers'][$k]) ? (bool) $input['layers'][$k] : $def;
            }
        }

        if (isset($input['markerOptions']) && is_array($input['markerOptions'])) {
            $anim = isset($input['markerOptions']['animation']) ? sanitize_key($input['markerOptions']['animation']) : 'none';
            $out['markerOptions']['animation'] = in_array($anim, ['none', 'bounce', 'drop', 'pulse'], true) ? $anim : 'none';
            $out['markerOptions']['draggable'] = !empty($input['markerOptions']['draggable']);
        }

        if (isset($input['markers']) && is_array($input['markers'])) {
            $out['markers'] = array_values(array_filter(array_map([self::class, 'sanitize_marker'], $input['markers'])));
        }

        if (isset($input['shapes']) && is_array($input['shapes'])) {
            $out['shapes'] = array_values(array_filter(array_map([self::class, 'sanitize_shape'], $input['shapes'])));
        }

        if (isset($input['categories']) && is_array($input['categories'])) {
            $out['categories'] = array_values(array_map([self::class, 'sanitize_category'], $input['categories']));
        }

        if (isset($input['clustering']) && is_array($input['clustering'])) {
            $out['clustering'] = [
                'enabled' => isset($input['clustering']['enabled']) ? (bool) $input['clustering']['enabled'] : false,
                'radius'  => isset($input['clustering']['radius']) ? (int) $input['clustering']['radius'] : 50,
                'maxZoom' => isset($input['clustering']['maxZoom']) ? (int) $input['clustering']['maxZoom'] : 14,
            ];
        }

        if (isset($input['dataSources']) && is_array($input['dataSources'])) {
            $out['dataSources'] = array_values(array_map([self::class, 'sanitize_data_source'], $input['dataSources']));
        }

        if (isset($input['heatmap']) && is_array($input['heatmap'])) {
            $out['heatmap'] = [
                'enabled'   => isset($input['heatmap']['enabled']) ? (bool) $input['heatmap']['enabled'] : false,
                'radius'    => isset($input['heatmap']['radius']) ? (int) $input['heatmap']['radius'] : 30,
                'intensity' => isset($input['heatmap']['intensity']) ? (float) $input['heatmap']['intensity'] : 1,
            ];
        }

        if (isset($input['storeLocator']) && is_array($input['storeLocator'])) {
            $sl = $input['storeLocator'];
            $unit = isset($sl['unit']) && in_array($sl['unit'], ['km', 'mi'], true) ? $sl['unit'] : 'km';
            $out['storeLocator'] = [
                'enabled'     => isset($sl['enabled']) ? (bool) $sl['enabled'] : false,
                'placeholder' => isset($sl['placeholder']) ? sanitize_text_field($sl['placeholder']) : '',
                'radius'      => isset($sl['radius']) ? (int) $sl['radius'] : 25,
                'unit'        => $unit,
                'showList'    => isset($sl['showList']) ? (bool) $sl['showList'] : true,
                'geolocate'   => isset($sl['geolocate']) ? (bool) $sl['geolocate'] : true,
            ];
        }

        if (isset($input['directory']) && is_array($input['directory'])) {
            $dir = $input['directory'];
            $out['directory'] = [
                'enabled'        => isset($dir['enabled']) ? (bool) $dir['enabled'] : false,
                'layout'         => isset($dir['layout']) && $dir['layout'] === 'bottom' ? 'bottom' : 'side',
                'position'       => isset($dir['position']) && $dir['position'] === 'right' ? 'right' : 'left',
                'width'          => isset($dir['width']) ? (int) $dir['width'] : 320,
                'showSearch'     => isset($dir['showSearch']) ? (bool) $dir['showSearch'] : true,
                'showCategories' => isset($dir['showCategories']) ? (bool) $dir['showCategories'] : true,
                'collapsed'      => isset($dir['collapsed']) ? (bool) $dir['collapsed'] : false,
            ];
        }

        if (isset($input['directions']) && is_array($input['directions'])) {
            $svc = isset($input['directions']['service']) && $input['directions']['service'] === 'osm' ? 'osm' : 'google';
            $out['directions'] = [
                'enabled' => isset($input['directions']['enabled']) ? (bool) $input['directions']['enabled'] : false,
                'service' => $svc,
            ];
        }

        if (isset($input['style']) && is_array($input['style'])) {
            $mapTheme = isset($input['style']['theme']) ? sanitize_key($input['style']['theme']) : 'light';
            $out['style']['theme'] = in_array($mapTheme, ['light', 'dark', 'auto'], true) ? $mapTheme : 'light';
            $out['style']['popup']['maxWidth'] = isset($input['style']['popup']['maxWidth'])
                ? (int) $input['style']['popup']['maxWidth'] : 260;
            $out['style']['popup']['theme'] = isset($input['style']['popup']['theme']) && $input['style']['popup']['theme'] === 'dark'
                ? 'dark' : 'light';
            $preset = isset($input['style']['popup']['preset']) ? sanitize_key($input['style']['popup']['preset']) : 'card';
            $out['style']['popup']['preset'] = in_array($preset, ['card', 'overlay', 'compact', 'minimal', 'classic'], true) ? $preset : 'card';
            $cta = isset($input['style']['popup']['ctaLabel']) ? sanitize_text_field($input['style']['popup']['ctaLabel']) : 'Learn more';
            $out['style']['popup']['ctaLabel'] = $cta !== '' ? $cta : 'Learn more';
        }

        return $out;
    }

    public static function sanitize_marker($m)
    {
        if (!is_array($m) || !isset($m['lat']) || !isset($m['lng'])) {
            return null;
        }
        return [
            'id'       => isset($m['id']) ? sanitize_text_field($m['id']) : uniqid('mk_'),
            'lat'      => (float) $m['lat'],
            'lng'      => (float) $m['lng'],
            'title'    => isset($m['title']) ? sanitize_text_field($m['title']) : '',
            'content'  => isset($m['content']) ? wp_kses_post($m['content']) : '',
            'image'    => isset($m['image']) ? esc_url_raw($m['image']) : '',
            'link'     => isset($m['link']) ? esc_url_raw($m['link']) : '',
            'category' => isset($m['category']) ? sanitize_text_field($m['category']) : '',
            'icon'     => [
                'type'   => isset($m['icon']['type']) ? sanitize_key($m['icon']['type']) : 'default',
                'preset' => isset($m['icon']['preset']) ? sanitize_key($m['icon']['preset']) : '',
                'url'    => isset($m['icon']['url']) ? esc_url_raw($m['icon']['url']) : '',
                'color'  => isset($m['icon']['color']) ? sanitize_hex_color($m['icon']['color']) : '',
            ],
        ];
    }

    public static function sanitize_shape($s)
    {
        if (!is_array($s)) {
            return null;
        }
        $type   = isset($s['type']) && $s['type'] === 'polyline' ? 'polyline' : 'polygon';
        $coords = [];
        if (isset($s['coords']) && is_array($s['coords'])) {
            foreach ($s['coords'] as $pt) {
                if (is_array($pt) && isset($pt[0], $pt[1])) {
                    $coords[] = [(float) $pt[0], (float) $pt[1]];
                }
            }
        }
        return [
            'id'          => isset($s['id']) ? sanitize_text_field($s['id']) : uniqid('sh_'),
            'type'        => $type,
            'color'       => isset($s['color']) ? sanitize_hex_color($s['color']) : '#006bff',
            'fillColor'   => isset($s['fillColor']) ? sanitize_hex_color($s['fillColor']) : '#006bff',
            'fillOpacity' => isset($s['fillOpacity']) ? self::clampf($s['fillOpacity'], 0, 1) : 0.2,
            'weight'      => isset($s['weight']) ? (int) $s['weight'] : 2,
            'coords'      => $coords,
        ];
    }

    public static function sanitize_category($c)
    {
        return [
            'id'    => isset($c['id']) ? sanitize_text_field($c['id']) : uniqid('cat_'),
            'name'  => isset($c['name']) ? sanitize_text_field($c['name']) : '',
            'color' => isset($c['color']) ? sanitize_hex_color($c['color']) : '',
            'icon'  => isset($c['icon']) ? esc_url_raw($c['icon']) : '',
        ];
    }

    public static function sanitize_data_source($s)
    {
        $type = isset($s['type']) ? sanitize_key($s['type']) : 'static';
        return [
            'id'      => isset($s['id']) ? sanitize_text_field($s['id']) : uniqid('ds_'),
            'type'    => in_array($type, ['static', 'cpt', 'geojson', 'rest', 'listing'], true) ? $type : 'static',
            'label'   => isset($s['label']) ? sanitize_text_field($s['label']) : '',
            'url'     => isset($s['url']) ? esc_url_raw($s['url']) : '',
            'source'  => isset($s['source']) ? sanitize_key($s['source']) : '',
            'postType' => isset($s['postType']) ? sanitize_key($s['postType']) : '',
            'latMeta' => isset($s['latMeta']) ? sanitize_key($s['latMeta']) : '',
            'lngMeta' => isset($s['lngMeta']) ? sanitize_key($s['lngMeta']) : '',
            'limit'   => isset($s['limit']) ? (int) $s['limit'] : 100,
        ];
    }

    private static function clampf($v, $min, $max)
    {
        $v = (float) $v;
        return max($min, min($max, $v));
    }
}
