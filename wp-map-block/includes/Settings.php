<?php

namespace WPMapBlock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Global plugin settings (API keys + defaults) stored in a single option.
 */
class Settings
{
    const OPTION = 'wpmb_settings';

    public static function defaults()
    {
        return [
            'apiKeys' => [
                'google'   => '',
                'mapbox'   => '',
                'maptiler' => '',
            ],
            'theme'           => 'light',
            'defaultProvider' => 'openstreetmap',
        ];
    }

    public static function get()
    {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return wp_parse_args($stored, self::defaults());
    }

    public static function api_key($provider)
    {
        $s = self::get();
        return isset($s['apiKeys'][$provider]) ? $s['apiKeys'][$provider] : '';
    }

    public static function save($input)
    {
        $current = self::get();
        $out     = $current;

        if (isset($input['apiKeys']) && is_array($input['apiKeys'])) {
            foreach ($out['apiKeys'] as $k => $v) {
                if (isset($input['apiKeys'][$k])) {
                    $out['apiKeys'][$k] = sanitize_text_field($input['apiKeys'][$k]);
                }
            }
        }

        if (isset($input['theme'])) {
            $out['theme'] = $input['theme'] === 'dark' ? 'dark' : 'light';
        }

        if (isset($input['defaultProvider'])) {
            $providers = \WPMapBlock\Config::providers();
            $out['defaultProvider'] = isset($providers[$input['defaultProvider']])
                ? $input['defaultProvider'] : 'openstreetmap';
        }

        update_option(self::OPTION, $out);
        return $out;
    }
}
