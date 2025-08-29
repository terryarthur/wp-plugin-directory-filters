<?php
/**
 * Plugin Activator for WordPress Plugin Directory Filters
 *
 * @package WPPDFI_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation
 */
class WPPDFI_Activator {

	/**
	 * Activate the plugin
	 *
	 * This method is called when the plugin is activated
	 */
	public static function activate() {
		// Check WordPress version compatibility.
		if ( ! self::check_wordpress_version() ) {
			deactivate_plugins( WPPDFI_BASENAME );
			wp_die(
				sprintf(
					/* translators: %s: required WordPress version */
					esc_html__( 'Plugin Directory Filters requires WordPress %s or higher. Please update WordPress.', 'wppd-filters' ),
					'5.8'
				),
				esc_html__( 'Plugin Activation Error', 'wppd-filters' ),
				array( 'back_link' => true )
			);
		}

		// Check PHP version compatibility.
		if ( ! self::check_php_version() ) {
			deactivate_plugins( WPPDFI_BASENAME );
			wp_die(
				sprintf(
					/* translators: %1$s: required PHP version, %2$s: current PHP version */
					esc_html__( 'Plugin Directory Filters requires PHP %1$s or higher. Your current version is %2$s.', 'wppd-filters' ),
					'7.4',
					PHP_VERSION
				),
				esc_html__( 'Plugin Activation Error', 'wppd-filters' ),
				array( 'back_link' => true )
			);
		}

		// Check required functions.
		if ( ! self::check_required_functions() ) {
			deactivate_plugins( WPPDFI_BASENAME );
			wp_die(
				esc_html__( 'Plugin Directory Filters requires functions that are not available in your hosting environment.', 'wppd-filters' ),
				esc_html__( 'Plugin Activation Error', 'wppd-filters' ),
				array( 'back_link' => true )
			);
		}

		// Run database migration if needed.
		self::run_migration();

		// Create default settings.
		self::create_default_settings();

		// Schedule cron events.
		self::schedule_cron_events();

		// Create cache directories if needed.
		self::setup_cache_directories();

		// Set plugin version.
		self::set_plugin_version();

		// Log activation.
		self::log_activation();

		// Clear any existing cache.
		self::clear_activation_cache();

		// Set activation flag for first-time setup.
		set_transient( 'wppdfi_activated', true, 60 );
	}

	/**
	 * Check WordPress version compatibility
	 *
	 * @return bool Compatible version.
	 */
	private static function check_wordpress_version() {
		global $wp_version;
		return version_compare( $wp_version, '5.8', '>=' );
	}

	/**
	 * Check PHP version compatibility
	 *
	 * @return bool Compatible version.
	 */
	private static function check_php_version() {
		return version_compare( PHP_VERSION, '7.4', '>=' );
	}

	/**
	 * Check required functions are available
	 *
	 * @return bool Required functions available.
	 */
	private static function check_required_functions() {
		$required_functions = array(
			'curl_init',
			'json_decode',
			'json_encode',
			'serialize',
			'unserialize',
			'md5',
			'time',
			'strtotime',
		);

		foreach ( $required_functions as $function ) {
			if ( ! function_exists( $function ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Create default plugin settings
	 */
	private static function create_default_settings() {
		$default_settings = array(
			'usability_weights' => array(
				'user_rating'            => 40,
				'rating_count'           => 20,
				'installation_count'     => 25,
				'support_responsiveness' => 15,
			),
			'health_weights'    => array(
				'update_frequency'  => 30,
				'wp_compatibility'  => 25,
				'support_response'  => 20,
				'time_since_update' => 15,
				'reported_issues'   => 10,
			),
			'cache_durations'   => array(
				'plugin_metadata'    => 86400,    // 24 hours
				'calculated_ratings' => 21600, // 6 hours
				'search_results'     => 3600,      // 1 hour
				'api_responses'      => 1800,        // 30 minutes
			),
			'first_activation'  => true,
			'activated_at'      => current_time( 'mysql' ),
		);

		// Only add default settings if they don't exist.
		if ( ! get_option( 'wppdfi_settings' ) ) {
			add_option( 'wppdfi_settings', $default_settings );
		}
	}

	/**
	 * Schedule WordPress cron events
	 */
	private static function schedule_cron_events() {
		// Schedule daily cache cleanup.
		if ( ! wp_next_scheduled( 'wppdfi_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wppdfi_cleanup' );
		}

		// Schedule hourly cache warming for popular plugins.
		if ( ! wp_next_scheduled( 'wppdfi_warm_cache' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'wppdfi_warm_cache' ); // Start after 5 minutes.
		}

		// Schedule weekly statistics collection.
		if ( ! wp_next_scheduled( 'wppdfi_collect_stats' ) ) {
			wp_schedule_event( time() + 600, 'weekly', 'wppdfi_collect_stats' ); // Start after 10 minutes.
		}
	}

	/**
	 * Setup cache directories if using file-based caching
	 */
	private static function setup_cache_directories() {
		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/wp-plugin-directory-filters-cache';

		// Create cache directory if it doesn't exist.
		if ( ! file_exists( $cache_dir ) ) {
			if ( wp_mkdir_p( $cache_dir ) ) {
				// Create .htaccess to protect cache directory.
				$htaccess_content  = "# WordPress Plugin Directory Filters Cache Protection\n";
				$htaccess_content .= "Order deny,allow\n";
				$htaccess_content .= "Deny from all\n";
				$htaccess_content .= "<Files ~ \"\\.(php|html|htm)$\">\n";
				$htaccess_content .= "    Deny from all\n";
				$htaccess_content .= "</Files>\n";

				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct file operations needed for .htaccess creation during activation.
				file_put_contents( $cache_dir . '/.htaccess', $htaccess_content );

				// Create index.php to prevent directory listing.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct file operations needed for index.php creation during activation.
				file_put_contents( $cache_dir . '/index.php', '<?php // Silence is golden' );
			}
		}
	}

	/**
	 * Set plugin version in database
	 */
	private static function set_plugin_version() {
		update_option( 'wppdfi_version', WPPDFI_VERSION );
		update_option( 'wppdfi_db_version', '1.0.0' );
	}

	/**
	 * Log plugin activation
	 */
	private static function log_activation() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_data = array(
				'event'             => 'plugin_activated',
				'version'           => WPPDFI_VERSION,
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
				'timestamp'         => current_time( 'mysql' ),
				'user_id'           => get_current_user_id(),
				'multisite'         => is_multisite(),
				'active_theme'      => get_option( 'stylesheet' ),
				'active_plugins'    => get_option( 'active_plugins' ),
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] Activation: ' . wp_json_encode( $log_data ) );
			}
		}
	}

	/**
	 * Clear any existing cache during activation
	 */
	private static function clear_activation_cache() {
		// Clear WordPress object cache if available.
		if ( wp_using_ext_object_cache() ) {
			wp_cache_flush();
		}

		// Clear relevant transients using WordPress functions.
		delete_transient( 'wp_plugin_metadata' );
		delete_transient( 'wp_plugin_api_cache' );
		delete_transient( 'wp_plugin_health_cache' );
		delete_transient( 'wp_plugin_rating_cache' );

		// Clear any additional plugin-specific transients.
		wp_cache_delete( 'wp_plugin_metadata', 'wppd-filters' );
		wp_cache_delete( 'wp_plugin_api_cache', 'wppd-filters' );
	}

	/**
	 * Check if this is a network activation
	 *
	 * @return bool Network activation
	 */
	private static function is_network_activation() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is for activation context detection only
		return is_multisite() && isset( $_GET['networkwide'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['networkwide'] ) );
	}

	/**
	 * Activate for multisite network
	 */
	public static function activate_multisite() {
		if ( ! self::is_network_activation() ) {
			return;
		}

		// Get all blog IDs using WordPress function.
		$blog_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0, // Get all sites.
			)
		);

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			self::activate();
			restore_current_blog();
		}
	}

	/**
	 * Test API connectivity during activation
	 *
	 * @return bool API is accessible
	 */
	private static function test_api_connectivity() {
		$test_url = 'https://api.wordpress.org/plugins/info/1.2/';
		$response = wp_remote_get(
			$test_url,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress Plugin Directory Filters/' . WPPDFI_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		return 200 === $response_code;
	}

	/**
	 * Create activation notice for admin
	 */
	private static function create_activation_notice() {
		$notice = array(
			'type'        => 'success',
			'message'     => sprintf(
				/* translators: %s: settings page link */
				__( 'WordPress Plugin Directory Filters has been activated! Visit the %s to configure filtering algorithms.', 'wppd-filters' ),
				sprintf(
					'<a href="%s">%s</a>',
					admin_url( 'options-general.php?page=wp-plugin-directory-filters' ),
					__( 'settings page', 'wppd-filters' )
				)
			),
			'dismissible' => true,
		);

		set_transient( 'wppdfi_activation_notice', $notice, 300 ); // 5 minutes
	}

	/**
	 * Check for potential conflicts with other plugins
	 *
	 * @return array Potential conflicts
	 */
	private static function check_plugin_conflicts() {
		$potential_conflicts = array();
		$active_plugins      = get_option( 'active_plugins', array() );

		// List of plugins that might conflict.
		$conflict_plugins = array(
			'plugin-installer-speedup/plugin-installer-speedup.php' => __( 'Plugin Installer Speedup', 'wppd-filters' ),
			'advanced-plugin-search/advanced-plugin-search.php' => __( 'Advanced Plugin Search', 'wppd-filters' ),
			'plugin-organizer/plugin-organizer.php' => __( 'Plugin Organizer', 'wppd-filters' ),
		);

		foreach ( $conflict_plugins as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) ) {
				$potential_conflicts[] = $plugin_name;
			}
		}

		return $potential_conflicts;
	}

	/**
	 * Performance check during activation
	 *
	 * @return array Performance metrics
	 */
	private static function performance_check() {
		$start_time   = microtime( true );
		$start_memory = memory_get_usage( true );

		// Simulate some plugin operations.
		for ( $i = 0; $i < 100; $i++ ) {
			$test_data = array(
				'test' => $i,
				'data' => str_repeat( 'x', 1000 ),
			);
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Serialization testing is legitimate during performance benchmarking.
			$serialized = serialize( $test_data );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Unserialization testing is legitimate during performance benchmarking.
			$unserialized = unserialize( $serialized );
		}

		$end_time   = microtime( true );
		$end_memory = memory_get_usage( true );

		return array(
			'execution_time' => ( $end_time - $start_time ) * 1000, // Milliseconds.
			'memory_usage'   => $end_memory - $start_memory,
			'peak_memory'    => memory_get_peak_usage( true ),
		);
	}

	/**
	 * Verify activation success
	 *
	 * @return bool Activation successful
	 */
	public static function verify_activation() {
		// Check if settings were created.
		$settings = get_option( 'wppdfi_settings' );
		if ( ! $settings ) {
			return false;
		}

		// Check if version was set.
		$version = get_option( 'wppdfi_version' );
		if ( ! $version ) {
			return false;
		}

		// Check if cron events were scheduled.
		if ( ! wp_next_scheduled( 'wppdfi_cleanup' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Rollback activation if something goes wrong
	 */
	public static function rollback_activation() {
		// Remove settings.
		delete_option( 'wppdfi_settings' );
		delete_option( 'wppdfi_version' );
		delete_option( 'wppdfi_db_version' );

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'wppdfi_cleanup' );
		wp_clear_scheduled_hook( 'wppdfi_warm_cache' );
		wp_clear_scheduled_hook( 'wppdfi_collect_stats' );

		// Clear transients.
		delete_transient( 'wppdfi_activated' );
		delete_transient( 'wppdfi_activation_notice' );

		// Log rollback.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			wp_debug_log( '[WP Plugin Filters] Activation rolled back due to errors' );
		}
	}

	/**
	 * Run database migration from old naming to new wppdfi naming
	 */
	private static function run_migration() {
		// Load migration class if not already loaded.
		if ( ! class_exists( 'WPPDFI_Migration' ) ) {
			require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-migration.php';
		}

		// Only run migration if not already completed.
		if ( ! WPPDFI_Migration::is_migrated() ) {
			WPPDFI_Migration::migrate();
		}
	}
}
