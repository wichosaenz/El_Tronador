<?php
/**
 * Plugin Name:       El Tronador
 * Plugin URI:        https://github.com/wichosaenz/El_Tronador
 * Description:       Cache and performance optimization plugin for WordPress. Static page cache, Delay JS, and more.
 * Version:           1.0.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            wichosaenz
 * Author URI:        https://github.com/wichosaenz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       el-tronador
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants.
 */
define( 'ETR_VERSION', '1.0.2' );
define( 'ETR_PLUGIN_FILE', __FILE__ );
define( 'ETR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ETR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ETR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ETR_CACHE_DIR', WP_CONTENT_DIR . '/cache/el-tronador/' );

/**
 * Autoloader.
 */
require_once ETR_PLUGIN_DIR . 'includes/class-etr-autoloader.php';
ETR_Autoloader::register();

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, function (): void {
    $compatibility = new ETR_Compatibility();

    if ( $compatibility->is_breeze_active() ) {
        set_transient( 'etr_breeze_conflict', true, 0 );
    } else {
        delete_transient( 'etr_breeze_conflict' );
        ETR_Page_Cache::install_advanced_cache();
    }
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function (): void {
    ETR_Page_Cache::uninstall_advanced_cache();
    ETR_Cache_Filesystem::purge_all();
    ETR_Database_Optimizer::unschedule_cron();
    ETR_Preload_Engine::unschedule_cron();
} );

/**
 * Boot the plugin.
 */
add_action( 'plugins_loaded', function (): void {
    ETR_Plugin::instance()->init();
} );
