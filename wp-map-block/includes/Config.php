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
                'keyDocsUrl' => '',
            ],
            'google' => [
                'label'    => __('Google Maps', 'wp-map-block'),
                'engine'   => 'google',
                'needsKey' => true,
                'keyLabel' => __('Google Maps API Key', 'wp-map-block'),
                'keyGenUrl'  => 'https://console.cloud.google.com/google/maps-apis/credentials',
                'keyDocsUrl' => 'https://developers.google.com/maps/documentation/javascript/get-api-key',
            ],
            'mapbox' => [
                'label'    => __('Mapbox', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => true,
                'keyLabel' => __('Mapbox Access Token', 'wp-map-block'),
                'keyGenUrl'  => 'https://account.mapbox.com/access-tokens/',
                'keyDocsUrl' => 'https://docs.mapbox.com/help/getting-started/access-tokens/',
            ],
            'maptiler' => [
                'label'    => __('MapTiler', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => true,
                'keyLabel' => __('MapTiler API Key', 'wp-map-block'),
                'keyGenUrl'  => 'https://cloud.maptiler.com/account/keys/',
                'keyDocsUrl' => 'https://docs.maptiler.com/cloud/api/authentication-key/',
            ],
            'openfreemap' => [
                'label'    => __('OpenFreeMap', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => '',
                'keyDocsUrl' => '',
            ],
            'stadia' => [
                'label'    => __('Stadia Maps', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => __('Stadia API Key (optional)', 'wp-map-block'),
                'keyGenUrl'  => 'https://client.stadiamaps.com/dashboard/',
                'keyDocsUrl' => 'https://docs.stadiamaps.com/authentication/',
            ],
            'esri' => [
                'label'    => __('Esri / ArcGIS', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => '',
                'keyDocsUrl' => '',
            ],
            'carto' => [
                'label'    => __('CARTO', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => false,
                'keyLabel' => '',
                'keyDocsUrl' => '',
            ],
            'azure' => [
                'label'    => __('Azure Maps', 'wp-map-block'),
                'engine'   => 'maplibre',
                'needsKey' => true,
                'keyLabel' => __('Azure Maps Subscription Key', 'wp-map-block'),
                'keyGenUrl'  => 'https://portal.azure.com/#create/Microsoft.MapsAccount',
                'keyDocsUrl' => 'https://learn.microsoft.com/azure/azure-maps/how-to-manage-authentication',
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
        $out['provider']  = isset($input['provider']) && is_string($input['provider']) && isset($providers[$input['provider']])
            ? $input['provider'] : $d['provider'];

        if (isset($input['providerOptions']) && is_array($input['providerOptions'])) {
            $out['providerOptions'] = [
                'apiKey'    => self::text($input['providerOptions'], 'apiKey'),
                'styleUrl'  => self::url($input['providerOptions'], 'styleUrl'),
                'styleName' => self::text($input['providerOptions'], 'styleName'),
            ];
        }

        if (isset($input['view']) && is_array($input['view'])) {
            $v = $input['view'];
            $out['view'] = [
                'center'  => [
                    'lat' => isset($v['center']['lat']) ? self::clampf($v['center']['lat'], -90, 90) : $d['view']['center']['lat'],
                    'lng' => isset($v['center']['lng']) ? self::clampf($v['center']['lng'], -180, 180) : $d['view']['center']['lng'],
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
                'width'  => isset($input['size']['width']) ? self::css_width($input['size']['width']) : $d['size']['width'],
                'height' => isset($input['size']['height']) ? self::clampi($input['size']['height'], 50, 5000) : $d['size']['height'],
            ];
        }

        if (isset($input['controls']) && is_array($input['controls'])) {
            foreach ($d['controls'] as $k => $def) {
                $out['controls'][$k] = isset($input['controls'][$k]) ? (bool) $input['controls'][$k] : $def;
            }
        }

        if (isset($input['mapType'])) {
            $mt = self::key($input['mapType']);
            $out['mapType'] = in_array($mt, ['roadmap', 'satellite', 'hybrid', 'terrain'], true) ? $mt : 'roadmap';
        }

        if (isset($input['layers']) && is_array($input['layers'])) {
            foreach ($d['layers'] as $k => $def) {
                $out['layers'][$k] = isset($input['layers'][$k]) ? (bool) $input['layers'][$k] : $def;
            }
        }

        if (isset($input['markerOptions']) && is_array($input['markerOptions'])) {
            $anim = isset($input['markerOptions']['animation']) ? self::key($input['markerOptions']['animation']) : 'none';
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
                'radius'  => isset($input['clustering']['radius']) ? self::clampi($input['clustering']['radius'], 1, 500) : 50,
                'maxZoom' => isset($input['clustering']['maxZoom']) ? self::clampi($input['clustering']['maxZoom'], 0, 24) : 14,
            ];
        }

        if (isset($input['dataSources']) && is_array($input['dataSources'])) {
            $out['dataSources'] = array_values(array_map([self::class, 'sanitize_data_source'], $input['dataSources']));
        }

        if (isset($input['heatmap']) && is_array($input['heatmap'])) {
            $out['heatmap'] = [
                'enabled'   => isset($input['heatmap']['enabled']) ? (bool) $input['heatmap']['enabled'] : false,
                'radius'    => isset($input['heatmap']['radius']) ? self::clampi($input['heatmap']['radius'], 1, 200) : 30,
                'intensity' => isset($input['heatmap']['intensity']) ? self::clampf($input['heatmap']['intensity'], 0, 100) : 1,
            ];
        }

        if (isset($input['storeLocator']) && is_array($input['storeLocator'])) {
            $sl = $input['storeLocator'];
            $unit = isset($sl['unit']) && 'mi' === $sl['unit'] ? 'mi' : 'km';
            $out['storeLocator'] = [
                'enabled'     => isset($sl['enabled']) ? (bool) $sl['enabled'] : false,
                'placeholder' => self::text($sl, 'placeholder'),
                'radius'      => isset($sl['radius']) ? self::clampi($sl['radius'], 1, 20000) : 25,
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
                'width'          => isset($dir['width']) ? self::clampi($dir['width'], 120, 1200) : 320,
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
            $mapTheme = isset($input['style']['theme']) ? self::key($input['style']['theme']) : 'light';
            $out['style']['theme'] = in_array($mapTheme, ['light', 'dark', 'auto'], true) ? $mapTheme : 'light';
            $out['style']['popup']['maxWidth'] = isset($input['style']['popup']['maxWidth'])
                ? self::clampi($input['style']['popup']['maxWidth'], 120, 1000) : 260;
            $out['style']['popup']['theme'] = isset($input['style']['popup']['theme']) && $input['style']['popup']['theme'] === 'dark'
                ? 'dark' : 'light';
            $preset = isset($input['style']['popup']['preset']) ? self::key($input['style']['popup']['preset']) : 'card';
            $out['style']['popup']['preset'] = in_array($preset, ['card', 'overlay', 'compact', 'minimal', 'classic'], true) ? $preset : 'card';
            $cta = isset($input['style']['popup']['ctaLabel']) && is_array($input['style']['popup'])
                ? self::text($input['style']['popup'], 'ctaLabel') : 'Learn more';
            $out['style']['popup']['ctaLabel'] = $cta !== '' ? $cta : 'Learn more';
        }

        return $out;
    }

    public static function sanitize_marker($m)
    {
        if (!is_array($m) || !isset($m['lat']) || !isset($m['lng']) || !is_numeric($m['lat']) || !is_numeric($m['lng'])) {
            return null;
        }
        $icon = isset($m['icon']) && is_array($m['icon']) ? $m['icon'] : [];
        $id   = self::text($m, 'id');
        return [
            'id'       => '' !== $id ? $id : uniqid('mk_'),
            'lat'      => self::clampf($m['lat'], -90, 90),
            'lng'      => self::clampf($m['lng'], -180, 180),
            'title'    => self::text($m, 'title'),
            'content'  => isset($m['content']) && is_string($m['content']) ? wp_kses_post($m['content']) : '',
            'image'    => self::url($m, 'image'),
            'link'     => self::url($m, 'link'),
            'category' => self::text($m, 'category'),
            'icon'     => [
                'type'   => isset($icon['type']) ? self::key($icon['type']) : 'default',
                'preset' => isset($icon['preset']) ? self::key($icon['preset']) : '',
                'url'    => self::url($icon, 'url'),
                'color'  => self::color($icon, 'color', ''),
            ],
        ];
    }

    public static function sanitize_shape($s)
    {
        if (!is_array($s)) {
            return null;
        }
        $type   = isset($s['type']) && 'polyline' === $s['type'] ? 'polyline' : 'polygon';
        $coords = [];
        if (isset($s['coords']) && is_array($s['coords'])) {
            foreach ($s['coords'] as $pt) {
                if (is_array($pt) && isset($pt[0], $pt[1]) && is_numeric($pt[0]) && is_numeric($pt[1])) {
                    $coords[] = [self::clampf($pt[0], -90, 90), self::clampf($pt[1], -180, 180)];
                }
            }
        }
        $id = self::text($s, 'id');
        return [
            'id'          => '' !== $id ? $id : uniqid('sh_'),
            'type'        => $type,
            'color'       => self::color($s, 'color', '#006bff'),
            'fillColor'   => self::color($s, 'fillColor', '#006bff'),
            'fillOpacity' => isset($s['fillOpacity']) ? self::clampf($s['fillOpacity'], 0, 1) : 0.2,
            'weight'      => isset($s['weight']) ? self::clampi($s['weight'], 0, 50) : 2,
            'coords'      => $coords,
        ];
    }

    public static function sanitize_category($c)
    {
        if (!is_array($c)) {
            $c = [];
        }
        $id = self::text($c, 'id');
        return [
            'id'    => '' !== $id ? $id : uniqid('cat_'),
            'name'  => self::text($c, 'name'),
            'color' => self::color($c, 'color', ''),
            'icon'  => self::url($c, 'icon'),
        ];
    }

    public static function sanitize_data_source($s)
    {
        if (!is_array($s)) {
            $s = [];
        }
        $type = isset($s['type']) ? self::key($s['type']) : 'static';
        $id   = self::text($s, 'id');
        return [
            'id'       => '' !== $id ? $id : uniqid('ds_'),
            'type'     => in_array($type, ['static', 'cpt', 'geojson', 'rest', 'listing'], true) ? $type : 'static',
            'label'    => self::text($s, 'label'),
            'url'      => self::url($s, 'url'),
            'source'   => isset($s['source']) ? self::key($s['source']) : '',
            'postType' => isset($s['postType']) ? self::key($s['postType']) : '',
            'latMeta'  => isset($s['latMeta']) ? self::meta_key($s['latMeta']) : '',
            'lngMeta'  => isset($s['lngMeta']) ? self::meta_key($s['lngMeta']) : '',
            'limit'    => isset($s['limit']) ? self::clampi($s['limit'], 1, 1000) : 100,
        ];
    }

    /** Text field `$key` of `$arr` as a sanitized string; '' when absent or not a scalar. */
    private static function text($arr, $key)
    {
        return isset($arr[$key]) && is_scalar($arr[$key]) ? sanitize_text_field((string) $arr[$key]) : '';
    }

    /** URL field `$key` of `$arr` (http/https/mailto/tel only); '' when absent or invalid. */
    private static function url($arr, $key)
    {
        if (!isset($arr[$key]) || !is_string($arr[$key])) {
            return '';
        }
        return esc_url_raw($arr[$key], ['http', 'https', 'mailto', 'tel']);
    }

    /** Hex colour field, falling back to `$default` when missing or invalid. */
    private static function color($arr, $key, $default)
    {
        if (!isset($arr[$key]) || !is_string($arr[$key])) {
            return $default;
        }
        $hex = sanitize_hex_color($arr[$key]);
        return $hex ? $hex : $default;
    }

    /** Slug-like value; '' for anything that is not a scalar. */
    private static function key($value)
    {
        return is_scalar($value) ? sanitize_key((string) $value) : '';
    }

    /**
     * Meta keys keep their case and may start with an underscore, so they can't go
     * through sanitize_key(). Allow the characters WordPress itself uses in keys.
     */
    private static function meta_key($value)
    {
        if (!is_string($value)) {
            return '';
        }
        return substr(preg_replace('/[^A-Za-z0-9_\-.:]/', '', $value), 0, 191);
    }

    /**
     * A CSS width that is safe to place inside a `style` attribute: a number with
     * an optional %, px, vw, em or rem unit. Anything else falls back to 100%.
     */
    public static function css_width($value)
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/^\d{1,4}(\.\d{1,2})?(%|px|vw|em|rem)?$/', $value)) {
            return $value;
        }
        return '100%';
    }

    private static function clampi($v, $min, $max)
    {
        return (int) self::clampf($v, $min, $max);
    }

    private static function clampf($v, $min, $max)
    {
        $v = is_numeric($v) ? (float) $v : (float) $min;
        return max($min, min($max, $v));
    }
}
