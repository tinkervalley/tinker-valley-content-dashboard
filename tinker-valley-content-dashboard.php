<?php
/**
 * Plugin Name: Tinker Valley Content Dashboard
 * Description: A modern, front-end content dashboard for editing WordPress and ACF content.
 * Version: 0.8.6
 * Author: Tinker Valley
 * Text Domain: tinker-valley-content-dashboard
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Update URI: https://github.com/tinkervalley/tinker-valley-content-dashboard
 */

defined( 'ABSPATH' ) || exit;

define( 'TVCD_VERSION', '0.8.6' );
define( 'TVCD_FILE', __FILE__ );
define( 'TVCD_PATH', plugin_dir_path( __FILE__ ) );
define( 'TVCD_URL', plugin_dir_url( __FILE__ ) );

require_once TVCD_PATH . 'includes/class-tvcd-settings.php';
require_once TVCD_PATH . 'includes/class-tvcd-rest.php';
require_once TVCD_PATH . 'includes/class-tvcd-updater.php';
require_once TVCD_PATH . 'includes/class-tvcd-plugin.php';

register_activation_hook( __FILE__, array( 'TVCD_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TVCD_Plugin', 'deactivate' ) );

TVCD_Plugin::instance();
TVCD_Updater::instance();
