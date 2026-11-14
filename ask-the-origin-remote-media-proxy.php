<?php

/**
 * Plugin Name:       Ask the Origin – Remote Media Proxy
 * Plugin URI:        https://github.com/timohubois/ask-the-origin-remote-media-proxy/
 * Description:       Show media from the origin. Without copying the uploads library.
 * Version:           1.1.2
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Author:            Timo Hubois
 * Author URI:        https://pixelsaft.wtf
 * Text Domain:       ask-the-origin-remote-media-proxy
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace AskTheOrigin;

defined('ABSPATH') || exit;

if (!defined('ASK_THE_ORIGIN_PLUGIN_FILE')) {
    define('ASK_THE_ORIGIN_PLUGIN_FILE', __FILE__);
}

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $className): void {
        $prefix = __NAMESPACE__ . '\\';
        if (!str_starts_with($className, $prefix)) {
            return;
        }
        $relativeClass = substr($className, strlen($prefix));
        $file = __DIR__ . '/classes/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

register_activation_hook(ASK_THE_ORIGIN_PLUGIN_FILE, [Plugin::class, 'onPluginActivation']);
register_deactivation_hook(ASK_THE_ORIGIN_PLUGIN_FILE, [Plugin::class, 'onPluginDeactivation']);
add_action('plugins_loaded', [Plugin::class, 'init']);
