<?php
/**
 * WordPress Plugin Directory Filters Uninstaller
 *
 * This file is called directly when the plugin is deleted
 *
 * @package WPPDFI_Directory_Filters
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin constants.
if ( ! defined( 'WPPDFI_PLUGIN_DIR' ) ) {
	define( 'WPPDFI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Load uninstaller class.
require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-uninstaller.php';

// Execute uninstall.
WPPDFI_Uninstaller::uninstall();
