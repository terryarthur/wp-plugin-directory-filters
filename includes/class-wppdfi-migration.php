<?php
/**
 * Database Migration Handler for WPPDFI Renaming
 *
 * @package WPPDFI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles migration from old wp_plugin_filters_* names to new wppdfi_* names
 */
class WPPDFI_Migration {

	/**
	 * Run the complete migration process
	 */
	public static function migrate() {
		self::migrate_options();
		self::migrate_transients();
		self::migrate_cron_hooks();
		self::set_migration_flag();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			wp_debug_log( '[WPPDFI] Migration completed from wppdfi_* to wppdfi_*' );
		}
	}

	/**
	 * Migrate database options
	 */
	private static function migrate_options() {
		$option_mapping = array(
			'wp_plugin_filters_settings'     => 'wppdfi_settings',
			'wp_plugin_filters_version'      => 'wppdfi_version',
			'wp_plugin_filters_db_version'   => 'wppdfi_db_version',
			'wp_plugin_filters_cache_stats'  => 'wppdfi_cache_stats',
			'wp_plugin_filters_last_cleanup' => 'wppdfi_last_cleanup',
		);

		foreach ( $option_mapping as $old_name => $new_name ) {
			$value = get_option( $old_name );
			if ( false !== $value ) {
				// Add new option with migrated value.
				add_option( $new_name, $value );
				// Keep old option for rollback safety (will be cleaned up later).
				update_option( $old_name . '_migrated', true );
			}
		}
	}

	/**
	 * Migrate transients
	 */
	private static function migrate_transients() {
		$transient_mapping = array(
			'wp_plugin_filters_activated'           => 'wppdfi_activated',
			'wp_plugin_filters_deactivated'         => 'wppdfi_deactivated',
			'wp_plugin_filters_activation_notice'   => 'wppdfi_activation_notice',
			'wp_plugin_filters_deactivation_notice' => 'wppdfi_deactivation_notice',
			'wp_plugin_filters_uninstall_backup'    => 'wppdfi_uninstall_backup',
		);

		foreach ( $transient_mapping as $old_name => $new_name ) {
			$value = get_transient( $old_name );
			if ( false !== $value ) {
				set_transient( $new_name, $value, 300 ); // 5 minutes default.
				delete_transient( $old_name );
			}
		}

		// Migrate cache-related transients using pattern.
		global $wpdb;
		$cache_transients = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_wp_plugin_%'
			)
		);

		foreach ( $cache_transients as $transient ) {
			$old_name = str_replace( '_transient_', '', $transient->option_name );
			if ( strpos( $old_name, 'wp_plugin_' ) === 0 ) {
				$new_name = str_replace( 'wp_plugin_', 'wppdfi_', $old_name );
				$value    = get_transient( $old_name );
				if ( false !== $value ) {
					set_transient( $new_name, $value, 3600 ); // 1 hour default.
					delete_transient( $old_name );
				}
			}
		}
	}

	/**
	 * Migrate cron hooks
	 */
	private static function migrate_cron_hooks() {
		$cron_mapping = array(
			'wp_plugin_filters_cleanup'              => 'wppdfi_cleanup',
			'wp_plugin_filters_warm_cache'           => 'wppdfi_warm_cache',
			'wp_plugin_filters_collect_stats'        => 'wppdfi_collect_stats',
			'wp_plugin_filters_maintenance'          => 'wppdfi_maintenance',
			'wp_plugin_filters_api_health_check'     => 'wppdfi_api_health_check',
			'wp_plugin_filters_rating_recalculation' => 'wppdfi_rating_recalculation',
		);

		foreach ( $cron_mapping as $old_hook => $new_hook ) {
			$timestamp = wp_next_scheduled( $old_hook );
			if ( $timestamp ) {
				$args = array();
				// Get schedule info.
				$crons = get_option( 'cron', array() );
				if ( isset( $crons[ $timestamp ][ $old_hook ] ) ) {
					$schedule_info = reset( $crons[ $timestamp ][ $old_hook ] );
					$schedule      = $schedule_info['schedule'] ?? false;
					$args          = $schedule_info['args'] ?? array();
				}

				// Clear old hook.
				wp_clear_scheduled_hook( $old_hook );

				// Schedule new hook.
				if ( ! empty( $schedule ) ) {
					wp_schedule_event( $timestamp, $schedule, $new_hook, $args );
				} else {
					wp_schedule_single_event( $timestamp, $new_hook, $args );
				}
			}
		}
	}

	/**
	 * Set migration completion flag
	 */
	private static function set_migration_flag() {
		update_option( 'wppdfi_migration_completed', current_time( 'mysql' ) );
		update_option( 'wppdfi_migration_version', '1.0.0' );
	}

	/**
	 * Check if migration has been completed
	 *
	 * @return bool Migration status.
	 */
	public static function is_migrated() {
		return (bool) get_option( 'wppdfi_migration_completed', false );
	}

	/**
	 * Clean up old options after successful migration (run this manually after testing)
	 */
	public static function cleanup_old_options() {
		if ( ! self::is_migrated() ) {
			return false;
		}

		$old_options = array(
			'wppdfi_settings',
			'wppdfi_version',
			'wppdfi_db_version',
			'wppdfi_cache_stats',
			'wppdfi_last_cleanup',
		);

		foreach ( $old_options as $option ) {
			if ( get_option( $option . '_migrated' ) ) {
				delete_option( $option );
				delete_option( $option . '_migrated' );
			}
		}

		return true;
	}
}
