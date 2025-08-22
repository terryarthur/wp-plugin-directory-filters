<?php
/**
 * Plugin Name: WordPress Plugin Directory Filters
 * Plugin URI: https://wppd-filters.terryarthur.com/
 * Description: Enhances the WordPress admin plugin installer with advanced filtering, sorting, and rating capabilities. Features colored status indicators, WordPress compatibility checking, and modern UI improvements.
 * Version: 1.0.0
 * Author: Terry Arthur
 * Author URI: https://terryarthur.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-plugin-directory-filters
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.7.1
 * Requires PHP: 7.4
 * Network: true
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WP_PLUGIN_FILTERS_VERSION', '1.0.0');
define('WP_PLUGIN_FILTERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_PLUGIN_FILTERS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_PLUGIN_FILTERS_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin initialization
 */
function wp_plugin_filters_init() {
    // Load text domain for internationalization
    load_plugin_textdomain('wp-plugin-directory-filters', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Require core plugin class
    require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-plugin-core.php';
    
    // Initialize the plugin
    WP_Plugin_Directory_Filters::get_instance();
}

/**
 * Plugin activation hook
 */
function wp_plugin_filters_activate() {
    require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-plugin-activator.php';
    WP_Plugin_Filters_Activator::activate();
}

/**
 * Plugin deactivation hook
 */
function wp_plugin_filters_deactivate() {
    require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-plugin-deactivator.php';
    WP_Plugin_Filters_Deactivator::deactivate();
}

// Register hooks
register_activation_hook(__FILE__, 'wp_plugin_filters_activate');
register_deactivation_hook(__FILE__, 'wp_plugin_filters_deactivate');
// Note: Uninstall is handled by uninstall.php file

// Initialize plugin after WordPress is loaded
add_action('plugins_loaded', 'wp_plugin_filters_init');