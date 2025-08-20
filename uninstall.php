<?php
/**
 * WordPress Plugin Directory Filters Uninstaller
 *
 * This file is called directly when the plugin is deleted
 *
 * @package WP_Plugin_Directory_Filters
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Plugin constants
if (!defined('WP_PLUGIN_FILTERS_PLUGIN_DIR')) {
    define('WP_PLUGIN_FILTERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Load uninstaller class
require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-plugin-uninstaller.php';

// Execute uninstall
WP_Plugin_Filters_Uninstaller::uninstall();