<?php
/**
 * Plugin Name: WPPD Filters
 * Plugin URI: https://wppd-filters.terryarthur.com/
 * Description: Enhances the WordPress admin plugin installer with advanced filtering, sorting, and rating capabilities. Features colored status indicators, WordPress compatibility checking, and modern UI improvements.
 * Version: 1.2.0
 * Author: Terry Arthur
 * Author URI: https://terryarthur.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wppd-filters
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: true
 *
 * @package WPPDFI_Directory_Filters
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WPPDFI_VERSION', '1.2.0' );
define( 'WPPDFI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPPDFI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPPDFI_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin initialization
 */
function wppdfi_init() {
	// Text domain automatically loaded by WordPress for plugins hosted on WordPress.org.

	// Require core plugin class.
	require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-directory-filters.php';

	// Initialize the plugin.
	WPPDFI_Directory_Filters::get_instance();
}

/**
 * Plugin activation hook
 */
function wppdfi_activate() {
	require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-activator.php';
	WPPDFI_Activator::activate();
}

/**
 * Plugin deactivation hook
 */
function wppdfi_deactivate() {
	require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-deactivator.php';
	WPPDFI_Deactivator::deactivate();
}

// Register hooks.
register_activation_hook( __FILE__, 'wppdfi_activate' );
register_deactivation_hook( __FILE__, 'wppdfi_deactivate' );
// Note: Uninstall is handled by uninstall.php file.

// Initialize plugin after WordPress is loaded.
add_action( 'plugins_loaded', 'wppdfi_init' );

