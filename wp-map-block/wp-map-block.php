<?php

/**
 * Plugin Name: WP Map Block by aBlocks
 * Plugin URI: https://academylms.net/wp-map-block
 * Description: Gutenberg Map Block for Google Map and OpenStreet Map build with LeafletJS
 * Author: aBlocks - Most Powerful blocks Library
 * Author URI: https://ablocks.pro/
 * Version: 2.0.1
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-map-block
 * Domain Path: /languages/
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

if (!class_exists('WPMapBlock')) {
    final class WPMapBlock
    {
        private static $instances = [];
        protected function __construct()
        {
            $this->define_constant();
            register_activation_hook(__FILE__, [$this, 'activate']);
            $this->dispatch_hook();
        }
        public function define_constant()
        {
            /**
             * Defines CONSTANTS for Whole plugins.
             */
            define('WPMAPBLOCK_VERSION', '2.0.1');
            define('WPMAPBLOCK_PLUGIN_FILE', __FILE__);
            define('WPMAPBLOCK_PLUGIN_BASENAME', plugin_basename(__FILE__));
            define('WPMAPBLOCK_PLUGIN_SLUG', 'wp-map-block');
            define('WPMAPBLOCK_PLUGIN_ROOT_URI', plugins_url('/', __FILE__));
            define('WPMAPBLOCK_ROOT_DIR_PATH', plugin_dir_path(__FILE__));
            define('WPMAPBLOCK_ASSETS_DIR_PATH', WPMAPBLOCK_ROOT_DIR_PATH . 'assets/');
            define('WPMAPBLOCK_ASSETS_URI', WPMAPBLOCK_PLUGIN_ROOT_URI . 'assets/');
        }

        public function dispatch_hook()
        {
            WPMapBlock\Assets::init();
            WPMapBlock\Block::init();
            WPMapBlock\Migration::init();
			if(is_admin()){
                WPMapBlock\Admin::init();
			}
        }


        public function activate()
        {
            WPMapBlock\Installer::init();
        }

        protected function __clone()
        {
        }

        public function __wakeup()
        {
            throw new \Exception("Cannot unserialize singleton");
        }

        public static function getInstance()
        {
            $subclass = static::class;
            if (!isset(self::$instances[$subclass])) {
                self::$instances[$subclass] = new static();
            }
            return self::$instances[$subclass];
        }
    }
}

/**
 * Initializes the main plugin
 *
 * @return \WPMapBlcok
 */
if (!function_exists('WPMapBlock_Start')) {
    function WPMapBlock_Start()
    {
        return WPMapBlock::getInstance();
    }
}

// Plugin Start
WPMapBlock_Start();
