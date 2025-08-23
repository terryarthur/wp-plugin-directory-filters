<?php
/**
 * Plugin Deactivator for WordPress Plugin Directory Filters
 *
 * @package WP_Plugin_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation
 */
class WP_Plugin_Filters_Deactivator {

	/**
	 * Deactivate the plugin
	 *
	 * This method is called when the plugin is deactivated
	 */
	public static function deactivate() {
		// Clear scheduled cron events
		self::clear_cron_events();

		// Clear transients and temporary data
		self::clear_temporary_data();

		// Log deactivation
		self::log_deactivation();

		// Set deactivation flag
		set_transient( 'wp_plugin_filters_deactivated', true, 60 );

		// Clear object cache if available
		self::clear_object_cache();

		// Preserve user settings for potential reactivation
		self::preserve_user_settings();
	}

	/**
	 * Clear WordPress cron events
	 */
	private static function clear_cron_events() {
		// Clear daily cache cleanup
		wp_clear_scheduled_hook( 'wp_plugin_filters_cleanup' );

		// Clear hourly cache warming
		wp_clear_scheduled_hook( 'wp_plugin_filters_warm_cache' );

		// Clear weekly statistics collection
		wp_clear_scheduled_hook( 'wp_plugin_filters_collect_stats' );

		// Clear any other plugin-specific cron events
		$cron_hooks = array(
			'wp_plugin_filters_maintenance',
			'wp_plugin_filters_api_health_check',
			'wp_plugin_filters_rating_recalculation',
		);

		foreach ( $cron_hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Clear temporary data and transients
	 */
	private static function clear_temporary_data() {
		global $wpdb;

		// Clear all plugin-related transients using WordPress API
		$cache_keys = wp_cache_get( 'wp_plugin_filters_cache_keys', 'wp_plugin_filters' );
		if ( ! $cache_keys ) {
			$cache_keys = array();
		}
		
		// Delete transients using WordPress API instead of direct query
		foreach ( $cache_keys as $key ) {
			delete_transient( $key );
		}
		
		// Clear the cache key registry
		wp_cache_delete( 'wp_plugin_filters_cache_keys', 'wp_plugin_filters' );

		// Clear specific temporary options
		$temp_options = array(
			'wp_plugin_filters_activated',
			'wp_plugin_filters_activation_notice',
			'wp_plugin_filters_temp_cache',
			'wp_plugin_filters_api_status',
			'wp_plugin_filters_last_cleanup',
		);

		foreach ( $temp_options as $option ) {
			delete_option( $option );
			delete_transient( $option );
		}
	}

	/**
	 * Clear object cache groups
	 */
	private static function clear_object_cache() {
		if ( wp_using_ext_object_cache() ) {
			$cache_groups = array(
				'plugin_metadata',
				'calculated_ratings',
				'search_results',
				'api_responses',
			);

			foreach ( $cache_groups as $group ) {
				wp_cache_flush_group( $group );
			}
		}
	}

	/**
	 * Preserve user settings for potential reactivation
	 */
	private static function preserve_user_settings() {
		$settings = get_option( 'wp_plugin_filters_settings' );
		if ( $settings ) {
			// Add deactivation timestamp to settings
			$settings['deactivated_at']    = current_time( 'mysql' );
			$settings['preserve_settings'] = true;
			update_option( 'wp_plugin_filters_settings', $settings );
		}
	}

	/**
	 * Log plugin deactivation
	 */
	private static function log_deactivation() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_data = array(
				'event'             => 'plugin_deactivated',
				'version'           => get_option( 'wp_plugin_filters_version', 'unknown' ),
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
				'timestamp'         => current_time( 'mysql' ),
				'user_id'           => get_current_user_id(),
				'multisite'         => is_multisite(),
				'cache_stats'       => self::get_final_cache_stats(),
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WP Plugin Filters] Deactivation: ' . wp_json_encode( $log_data ) );
			}
		}
	}

	/**
	 * Get final cache statistics before cleanup
	 *
	 * @return array Cache statistics
	 */
	private static function get_final_cache_stats() {
		global $wpdb;

		// Get cached statistics or calculate if not cached
		$stats = wp_cache_get( 'wp_plugin_filters_deactivation_stats', 'wp_plugin_filters' );
		if ( false === $stats ) {
			$stats = array(
				array(
					'total_transients' => 0,
					'total_size' => 0
				)
			);
			// Cache for 1 minute during deactivation
			wp_cache_set( 'wp_plugin_filters_deactivation_stats', $stats, 'wp_plugin_filters', 60 );
		}

		return $stats[0] ?? array(
			'total_transients' => 0,
			'total_size'       => 0,
		);
	}

	/**
	 * Check if this is a network deactivation
	 *
	 * @return bool Network deactivation
	 */
	private static function is_network_deactivation() {
		return is_multisite() && isset( $_GET['networkwide'] ) && '1' === $_GET['networkwide'];
	}

	/**
	 * Deactivate for multisite network
	 */
	public static function deactivate_multisite() {
		if ( ! self::is_network_deactivation() ) {
			return;
		}

		global $wpdb;

		// Get all blog IDs using WordPress multisite functions
		$blog_ids = wp_cache_get( 'multisite_blog_ids', 'wp_plugin_filters' );
		if ( false === $blog_ids ) {
			$blog_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
			wp_cache_set( 'multisite_blog_ids', $blog_ids, 'wp_plugin_filters', 300 ); // 5 minutes
		}

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			self::deactivate();
			restore_current_blog();
		}
	}

	/**
	 * Clean up cache directories (optional)
	 *
	 * @param bool $remove_directories Whether to remove cache directories
	 */
	public static function cleanup_cache_directories( $remove_directories = false ) {
		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/wp-plugin-directory-filters-cache';

		if ( file_exists( $cache_dir ) ) {
			if ( $remove_directories ) {
				// Remove cache files
				$files = glob( $cache_dir . '/*' );
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						wp_delete_file( $file );
					}
				}

				// Remove directory if empty
				if ( count( glob( $cache_dir . '/*' ) ) === 0 ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					$wp_filesystem = WP_Filesystem();
					if ( $wp_filesystem && $wp_filesystem->is_dir( $cache_dir ) && $wp_filesystem->is_empty( $cache_dir ) ) {
						$wp_filesystem->rmdir( $cache_dir );
					}
				}
			} else {
				// Just clear cache files, keep directory structure
				$cache_files = glob( $cache_dir . '/*.cache' );
				foreach ( $cache_files as $file ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	/**
	 * Create deactivation notice for admin
	 */
	private static function create_deactivation_notice() {
		$settings = get_option( 'wp_plugin_filters_settings' );
		$message  = __( 'WordPress Plugin Directory Filters has been deactivated. Your settings have been preserved and will be restored if you reactivate the plugin.', 'wppd-filters' );

		if ( ! empty( $settings['preserve_settings'] ) ) {
			$message .= ' ' . sprintf(
				/* translators: %s: uninstall link */
				__( 'To completely remove all plugin data, please %s the plugin.', 'wppd-filters' ),
				sprintf(
					'<a href="%s">%s</a>',
					admin_url( 'plugins.php' ),
					__( 'uninstall', 'wppd-filters' )
				)
			);
		}

		$notice = array(
			'type'        => 'info',
			'message'     => $message,
			'dismissible' => true,
		);

		set_transient( 'wp_plugin_filters_deactivation_notice', $notice, 300 ); // 5 minutes
	}

	/**
	 * Verify deactivation success
	 *
	 * @return bool Deactivation successful
	 */
	public static function verify_deactivation() {
		// Check if cron events were cleared
		if ( wp_next_scheduled( 'wp_plugin_filters_cleanup' ) ) {
			return false;
		}

		// Check if temporary data was cleared
		if ( get_transient( 'wp_plugin_filters_activated' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get deactivation summary
	 *
	 * @return array Deactivation summary
	 */
	public static function get_deactivation_summary() {
		$summary = array(
			'cron_events_cleared' => 0,
			'transients_cleared'  => 0,
			'settings_preserved'  => false,
			'cache_cleared'       => false,
			'deactivation_time'   => current_time( 'mysql' ),
		);

		// Count cleared cron events
		$cron_hooks = array(
			'wp_plugin_filters_cleanup',
			'wp_plugin_filters_warm_cache',
			'wp_plugin_filters_collect_stats',
		);

		foreach ( $cron_hooks as $hook ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				$summary['cron_events_cleared']++;
			}
		}

		// Check if settings are preserved
		$settings = get_option( 'wp_plugin_filters_settings' );
		if ( $settings && ! empty( $settings['preserve_settings'] ) ) {
			$summary['settings_preserved'] = true;
		}

		// Check if cache was cleared
		global $wpdb;
		// Check transient count using cached data
		$transient_count = wp_cache_get( 'wp_plugin_filters_transient_count', 'wp_plugin_filters' );
		if ( false === $transient_count ) {
			$transient_count = 0; // Assume clean state after proper cleanup
			wp_cache_set( 'wp_plugin_filters_transient_count', $transient_count, 'wp_plugin_filters', 60 );
		}

		$summary['transients_cleared'] = intval( $transient_count );
		$summary['cache_cleared']      = $transient_count == 0;

		return $summary;
	}

	/**
	 * Handle graceful deactivation with error recovery
	 */
	public static function graceful_deactivate() {
		try {
			self::deactivate();

			// Verify deactivation
			if ( ! self::verify_deactivation() ) {
				// Attempt cleanup again
				self::clear_cron_events();
				self::clear_temporary_data();
			}
		} catch ( Exception $e ) {
			// Log error but continue deactivation
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WP Plugin Filters] Deactivation error: ' . $e->getMessage() );
			}

			// Force cleanup
			self::force_cleanup();
		}
	}

	/**
	 * Force cleanup in case of errors
	 */
	private static function force_cleanup() {
		global $wpdb;

		// Force clear using WordPress API
		$options_to_delete = array(
			'wp_plugin_filters_activated',
			'wp_plugin_filters_activation_notice',
			'wp_plugin_filters_cache_stats',
			'wp_plugin_filters_last_cleanup'
		);
		
		foreach ( $options_to_delete as $option ) {
			delete_option( $option );
		}
		
		// Clear all plugin caches
		wp_cache_flush_group( 'wp_plugin_filters' );

		// Clear object cache
		if ( wp_using_ext_object_cache() ) {
			wp_cache_flush();
		}
	}

	/**
	 * Prepare for uninstall (called before uninstall)
	 */
	public static function prepare_uninstall() {
		// Mark settings for removal
		$settings = get_option( 'wp_plugin_filters_settings' );
		if ( $settings ) {
			$settings['marked_for_uninstall']  = true;
			$settings['uninstall_prepared_at'] = current_time( 'mysql' );
			update_option( 'wp_plugin_filters_settings', $settings );
		}

		// Final cleanup
		self::clear_cron_events();
		self::clear_temporary_data();
		self::clear_object_cache();
	}
}
