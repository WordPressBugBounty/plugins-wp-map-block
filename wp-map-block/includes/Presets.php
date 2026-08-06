<?php

namespace WPMapBlock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ready-made starter maps. Shown in the builder when creating a new map so a
 * user can pick a fully-loaded template (clustering, directory, categories,
 * heatmap, store locator, …) and immediately see what the plugin can do — then
 * edit from there. Each preset is a real, sanitized config.
 */
class Presets
{
    /**
     * @return array<int,array> [{ id, name, description, icon, features[], config }]
     */
    public static function all()
    {
        return [
            self::store_locator(),
            self::real_estate(),
            self::food_guide(),
            self::travel_guide(),
            self::heatmap(),
            self::global_vector(),
        ];
    }

    private static function preset($id, $name, $description, $icon, $features, $config)
    {
        return [
            'id'          => $id,
            'name'        => $name,
            'description' => $description,
            'icon'        => $icon,
            'features'    => $features,
            'config'      => Config::sanitize(wp_parse_args($config, Config::defaults())),
        ];
    }

    private static function mk($lat, $lng, $title, $content, $category = '', $color = '')
    {
        return [
            'id'       => 'mk_' . substr(md5($title . $lat . $lng), 0, 8),
            'lat'      => $lat,
            'lng'      => $lng,
            'title'    => $title,
            'content'  => $content,
            'link'     => '',
            'category' => $category,
            'icon'     => ['type' => $color ? 'color' : 'default', 'url' => '', 'color' => $color],
        ];
    }

    private static function cat($id, $name, $color)
    {
        return ['id' => $id, 'name' => $name, 'color' => $color, 'icon' => ''];
    }

    /** Multi-store retail locator: clustering + directory + search + geolocation. */
    private static function store_locator()
    {
        $c = ['flagship' => '#006bff', 'outlet' => '#00ad6b', 'partner' => '#f15b50'];
        return self::preset(
            'store_locator',
            __('Store Locator', 'wp-map-block'),
            __('Find-a-store map with clustered pins, a searchable list and “near me”.', 'wp-map-block'),
            'dashicons-store',
            ['Clustering', 'Directory + search', 'Geolocation', 'Categories'],
            [
                'provider'   => 'openstreetmap',
                'view'       => ['center' => ['lat' => 40.735, 'lng' => -73.99], 'zoom' => 11, 'minZoom' => 0, 'maxZoom' => 20, 'pitch' => 0, 'bearing' => 0],
                'categories' => [self::cat('flagship', __('Flagship', 'wp-map-block'), $c['flagship']), self::cat('outlet', __('Outlet', 'wp-map-block'), $c['outlet']), self::cat('partner', __('Partner', 'wp-map-block'), $c['partner'])],
                'markers'    => [
                    self::mk(40.7580, -73.9855, __('Times Square Flagship', 'wp-map-block'), __('Our largest store — open 24/7.', 'wp-map-block'), 'flagship'),
                    self::mk(40.7484, -73.9857, __('Midtown Outlet', 'wp-map-block'), __('Clearance & last-season deals.', 'wp-map-block'), 'outlet'),
                    self::mk(40.7411, -73.9897, __('Flatiron Store', 'wp-map-block'), __('Flagship experience downtown.', 'wp-map-block'), 'flagship'),
                    self::mk(40.7069, -74.0113, __('FiDi Partner', 'wp-map-block'), __('Authorized reseller.', 'wp-map-block'), 'partner'),
                    self::mk(40.7295, -73.9965, __('Village Outlet', 'wp-map-block'), __('Compact neighborhood shop.', 'wp-map-block'), 'outlet'),
                    self::mk(40.7681, -73.9819, __('Columbus Circle', 'wp-map-block'), __('Inside the mall, level 2.', 'wp-map-block'), 'flagship'),
                    self::mk(40.7211, -74.0051, __('SoHo Partner', 'wp-map-block'), __('Boutique partner location.', 'wp-map-block'), 'partner'),
                    self::mk(40.7527, -73.9772, __('Grand Central', 'wp-map-block'), __('Commuter-friendly kiosk.', 'wp-map-block'), 'outlet'),
                ],
                'clustering'   => ['enabled' => true, 'radius' => 50, 'maxZoom' => 13],
                'controls'     => ['zoom' => true, 'fullscreen' => true, 'scale' => false, 'geolocate' => true, 'scrollZoom' => true],
                'directory'    => ['enabled' => true, 'layout' => 'side', 'position' => 'left', 'width' => 320, 'showSearch' => true, 'showCategories' => true],
                'storeLocator' => ['enabled' => true, 'placeholder' => __('Enter your address…', 'wp-map-block'), 'radius' => 25, 'unit' => 'km', 'showList' => true, 'geolocate' => true],
            ]
        );
    }

    /** Property directory: bottom card strip + colored status pins. */
    private static function real_estate()
    {
        return self::preset(
            'real_estate',
            __('Real Estate Directory', 'wp-map-block'),
            __('Property listings with a bottom card strip and status filters.', 'wp-map-block'),
            'dashicons-admin-home',
            ['Directory (cards)', 'Category filters', 'Colored pins'],
            [
                'provider'   => 'openstreetmap',
                'view'       => ['center' => ['lat' => 40.72, 'lng' => -73.99], 'zoom' => 12, 'minZoom' => 0, 'maxZoom' => 20, 'pitch' => 0, 'bearing' => 0],
                'categories' => [self::cat('sale', __('For Sale', 'wp-map-block'), '#00ad6b'), self::cat('rent', __('For Rent', 'wp-map-block'), '#006bff'), self::cat('sold', __('Sold', 'wp-map-block'), '#738496')],
                'markers'    => [
                    self::mk(40.7290, -73.9965, __('2BR Loft · Greenwich Village', 'wp-map-block'), __('$1,150,000 · 2 bed · 2 bath', 'wp-map-block'), 'sale', '#00ad6b'),
                    self::mk(40.7380, -74.0020, __('Studio · Chelsea', 'wp-map-block'), __('$3,200/mo · 1 bath', 'wp-map-block'), 'rent', '#006bff'),
                    self::mk(40.7150, -73.9840, __('Townhouse · LES', 'wp-map-block'), __('SOLD · $2,400,000', 'wp-map-block'), 'sold', '#738496'),
                    self::mk(40.7060, -74.0090, __('3BR Condo · FiDi', 'wp-map-block'), __('$1,875,000 · 3 bed', 'wp-map-block'), 'sale', '#00ad6b'),
                    self::mk(40.7250, -74.0030, __('1BR · SoHo', 'wp-map-block'), __('$4,100/mo · furnished', 'wp-map-block'), 'rent', '#006bff'),
                    self::mk(40.7440, -73.9880, __('Penthouse · NoMad', 'wp-map-block'), __('$5,600,000 · terrace', 'wp-map-block'), 'sale', '#00ad6b'),
                ],
                'directory' => ['enabled' => true, 'layout' => 'bottom', 'position' => 'left', 'width' => 320, 'showSearch' => true, 'showCategories' => true],
            ]
        );
    }

    /** Food guide: directions + geolocation + rich categories. */
    private static function food_guide()
    {
        return self::preset(
            'food_guide',
            __('Restaurant Finder', 'wp-map-block'),
            __('Eat-nearby map with categories, directions and “use my location”.', 'wp-map-block'),
            'dashicons-food',
            ['Directions', 'Geolocation', 'Directory', 'Categories'],
            [
                'provider'   => 'openstreetmap',
                'view'       => ['center' => ['lat' => 40.722, 'lng' => -73.997], 'zoom' => 13, 'minZoom' => 0, 'maxZoom' => 20, 'pitch' => 0, 'bearing' => 0],
                'categories' => [self::cat('cafe', __('Café', 'wp-map-block'), '#f59e0b'), self::cat('restaurant', __('Restaurant', 'wp-map-block'), '#ef4444'), self::cat('bar', __('Bar', 'wp-map-block'), '#8b5cf6')],
                'markers'    => [
                    self::mk(40.7231, -73.9975, __('Blue Bottle', 'wp-map-block'), __('Pour-over & pastries.', 'wp-map-block'), 'cafe'),
                    self::mk(40.7195, -74.0020, __('Lucali', 'wp-map-block'), __('Legendary wood-fired pizza.', 'wp-map-block'), 'restaurant'),
                    self::mk(40.7268, -73.9930, __('Attaboy', 'wp-map-block'), __('Cocktails, no menu.', 'wp-map-block'), 'bar'),
                    self::mk(40.7250, -74.0010, __('Balthazar', 'wp-map-block'), __('Classic French brasserie.', 'wp-map-block'), 'restaurant'),
                    self::mk(40.7180, -73.9955, __('La Colombe', 'wp-map-block'), __('Draft latte pioneers.', 'wp-map-block'), 'cafe'),
                    self::mk(40.7300, -73.9990, __('Employees Only', 'wp-map-block'), __('Speakeasy vibes.', 'wp-map-block'), 'bar'),
                    self::mk(40.7205, -73.9905, __('Katz’s Deli', 'wp-map-block'), __('Pastrami since 1888.', 'wp-map-block'), 'restaurant'),
                ],
                'controls'     => ['zoom' => true, 'fullscreen' => true, 'scale' => false, 'geolocate' => true, 'scrollZoom' => true],
                'directory'    => ['enabled' => true, 'layout' => 'side', 'position' => 'left', 'width' => 300, 'showSearch' => true, 'showCategories' => true],
                'directions'   => ['enabled' => true, 'service' => 'google'],
                'storeLocator' => ['enabled' => true, 'placeholder' => __('Where are you?', 'wp-map-block'), 'radius' => 5, 'unit' => 'km', 'showList' => true, 'geolocate' => true],
            ]
        );
    }

    /** Travel guide: attractions with categories + popups. */
    private static function travel_guide()
    {
        return self::preset(
            'travel_guide',
            __('Travel Guide', 'wp-map-block'),
            __('Curated attractions grouped by type — perfect for a city guide.', 'wp-map-block'),
            'dashicons-palmtree',
            ['Categories', 'Directory', 'Rich popups'],
            [
                'provider'   => 'openstreetmap',
                'view'       => ['center' => ['lat' => 48.858, 'lng' => 2.347], 'zoom' => 12, 'minZoom' => 0, 'maxZoom' => 20, 'pitch' => 0, 'bearing' => 0],
                'categories' => [self::cat('landmark', __('Landmark', 'wp-map-block'), '#006bff'), self::cat('museum', __('Museum', 'wp-map-block'), '#8b5cf6'), self::cat('park', __('Park', 'wp-map-block'), '#00ad6b')],
                'markers'    => [
                    self::mk(48.8584, 2.2945, __('Eiffel Tower', 'wp-map-block'), __('Iron icon of Paris.', 'wp-map-block'), 'landmark'),
                    self::mk(48.8606, 2.3376, __('Louvre Museum', 'wp-map-block'), __('World’s largest art museum.', 'wp-map-block'), 'museum'),
                    self::mk(48.8462, 2.3372, __('Luxembourg Gardens', 'wp-map-block'), __('Formal gardens & palace.', 'wp-map-block'), 'park'),
                    self::mk(48.8530, 2.3499, __('Notre-Dame', 'wp-map-block'), __('Gothic cathedral.', 'wp-map-block'), 'landmark'),
                    self::mk(48.8600, 2.3266, __('Musée d’Orsay', 'wp-map-block'), __('Impressionist masterpieces.', 'wp-map-block'), 'museum'),
                    self::mk(48.8867, 2.3431, __('Montmartre', 'wp-map-block'), __('Hilltop artist quarter.', 'wp-map-block'), 'landmark'),
                ],
                'directory' => ['enabled' => true, 'layout' => 'side', 'position' => 'right', 'width' => 300, 'showSearch' => true, 'showCategories' => true],
            ]
        );
    }

    /** Heatmap dashboard: density visualization from many points. */
    private static function heatmap()
    {
        $markers = [];
        // Deterministic scatter around a center so the heatmap looks organic.
        $cx = 51.5074;
        $cy = -0.1278;
        for ($i = 0; $i < 45; $i++) {
            $a = ($i * 137) % 360;
            $r = (($i * 97) % 100) / 1000; // up to ~0.1 deg
            $lat = $cx + $r * cos(deg2rad($a));
            $lng = $cy + $r * sin(deg2rad($a)) * 1.6;
            /* translators: %d: sequential signal number in the demo heatmap. */
            $markers[] = self::mk(round($lat, 5), round($lng, 5), sprintf(__('Signal #%d', 'wp-map-block'), $i + 1), '', '');
        }
        return self::preset(
            'heatmap',
            __('Heatmap Dashboard', 'wp-map-block'),
            __('Turn hundreds of points into a density heatmap — great for analytics.', 'wp-map-block'),
            'dashicons-chart-area',
            ['Heatmap', 'High performance', 'Many points'],
            [
                'provider' => 'openstreetmap',
                'view'     => ['center' => ['lat' => 51.5074, 'lng' => -0.1278], 'zoom' => 11, 'minZoom' => 0, 'maxZoom' => 20, 'pitch' => 0, 'bearing' => 0],
                'markers'  => $markers,
                'heatmap'  => ['enabled' => true, 'radius' => 35, 'intensity' => 1.3],
            ]
        );
    }

    /** Vector global map (OpenFreeMap) with clustered world markers. */
    private static function global_vector()
    {
        return self::preset(
            'global_vector',
            __('Global Vector Map', 'wp-map-block'),
            __('Smooth GPU vector tiles (OpenFreeMap) with worldwide clustered markers.', 'wp-map-block'),
            'dashicons-admin-site',
            ['Vector tiles', 'No API key', 'Worldwide clustering'],
            [
                'provider'        => 'openfreemap',
                'providerOptions' => ['apiKey' => '', 'styleUrl' => '', 'styleName' => 'liberty'],
                'view'            => ['center' => ['lat' => 20, 'lng' => 0], 'zoom' => 1.6, 'minZoom' => 0, 'maxZoom' => 20, 'pitch' => 0, 'bearing' => 0],
                'markers'         => [
                    self::mk(40.7128, -74.0060, __('New York', 'wp-map-block'), '', ''),
                    self::mk(51.5074, -0.1278, __('London', 'wp-map-block'), '', ''),
                    self::mk(48.8566, 2.3522, __('Paris', 'wp-map-block'), '', ''),
                    self::mk(35.6762, 139.6503, __('Tokyo', 'wp-map-block'), '', ''),
                    self::mk(-33.8688, 151.2093, __('Sydney', 'wp-map-block'), '', ''),
                    self::mk(1.3521, 103.8198, __('Singapore', 'wp-map-block'), '', ''),
                    self::mk(-23.5505, -46.6333, __('São Paulo', 'wp-map-block'), '', ''),
                    self::mk(19.0760, 72.8777, __('Mumbai', 'wp-map-block'), '', ''),
                    self::mk(25.2048, 55.2708, __('Dubai', 'wp-map-block'), '', ''),
                    self::mk(37.7749, -122.4194, __('San Francisco', 'wp-map-block'), '', ''),
                ],
                'clustering' => ['enabled' => true, 'radius' => 60, 'maxZoom' => 6],
            ]
        );
    }
}
