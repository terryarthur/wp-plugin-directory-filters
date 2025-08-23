<?php
/**
 * Cache Manager for WordPress Plugin Directory Filters
 *
 * @package WP_Plugin_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache management class using WordPress Transients and Object Cache
 */
class WP_Plugin_Filters_Cache_Manager {

	/**
	 * Single instance of the class
	 *
	 * @var WP_Plugin_Filters_Cache_Manager
	 */
	private static $instance = null;

	/**
	 * Cache configuration
	 *
	 * @var array
	 */
	private $cache_config;

	/**
	 * Default cache durations (in seconds)
	 */
	const DEFAULT_CACHE_DURATIONS = array(
		'plugin_metadata'    => 86400,    // 24 hours
		'calculated_ratings' => 21600, // 6 hours
		'search_results'     => 3600,      // 1 hour
		'api_responses'      => 1800,        // 30 minutes
	);

	/**
	 * Get single instance of the class
	 *
	 * @return WP_Plugin_Filters_Cache_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_cache_config();
		$this->init_hooks();
	}

	/**
	 * Load cache configuration from WordPress options
	 */
	private function load_cache_config() {
		$settings           = get_option( 'wp_plugin_filters_settings', array() );
		$this->cache_config = isset( $settings['cache_durations'] ) ? $settings['cache_durations'] : self::DEFAULT_CACHE_DURATIONS;
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Schedule daily cache cleanup
		if ( ! wp_next_scheduled( 'wp_plugin_filters_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_plugin_filters_cleanup' );
		}

		// Schedule cache warming for popular plugins
		if ( ! wp_next_scheduled( 'wp_plugin_filters_warm_cache' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'wp_plugin_filters_warm_cache' );
		}
	}

	/**
	 * Get cached data with fallback strategy
	 *
	 * @param string $key        Cache key
	 * @param string $cache_type Type of cache (plugin_metadata, calculated_ratings, etc.)
	 * @return mixed|null Cached data or null if not found
	 */
	public function get( $key, $cache_type = 'plugin_metadata' ) {
		// Try object cache first if available
		if ( wp_using_ext_object_cache() ) {
			$data = wp_cache_get( $key, $cache_type );
			if ( $data !== false ) {
				return $this->maybe_decompress_data( $data );
			}
		}

		// Fall back to transients
		$transient_key = $this->build_transient_key( $key, $cache_type );
		$data          = get_transient( $transient_key );

		if ( $data !== false ) {
			// Store in object cache for subsequent requests if available
			if ( wp_using_ext_object_cache() ) {
				wp_cache_set( $key, $data, $cache_type, 3600 );
			}
			return $this->maybe_decompress_data( $data );
		}

		return null;
	}

	/**
	 * Set cached data with appropriate TTL
	 *
	 * @param string $key        Cache key
	 * @param mixed  $data       Data to cache
	 * @param string $cache_type Type of cache
	 * @return bool Success status
	 */
	public function set( $key, $data, $cache_type = 'plugin_metadata' ) {
		$ttl = isset( $this->cache_config[ $cache_type ] ) ? $this->cache_config[ $cache_type ] : self::DEFAULT_CACHE_DURATIONS[ $cache_type ];

		// Compress data if it's large
		$compressed_data = $this->maybe_compress_data( $data );

		// Store in object cache if available
		if ( wp_using_ext_object_cache() ) {
			$object_cache_ttl = min( $ttl, 3600 ); // Limit object cache TTL
			wp_cache_set( $key, $compressed_data, $cache_type, $object_cache_ttl );
		}

		// Store in transients for persistence
		$transient_key = $this->build_transient_key( $key, $cache_type );
		return set_transient( $transient_key, $compressed_data, $ttl );
	}

	/**
	 * Delete cached data
	 *
	 * @param string $key        Cache key
	 * @param string $cache_type Type of cache
	 * @return bool Success status
	 */
	public function delete( $key, $cache_type = 'plugin_metadata' ) {
		// Delete from object cache if available
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $key, $cache_type );
		}

		// Delete from transients
		$transient_key = $this->build_transient_key( $key, $cache_type );
		return delete_transient( $transient_key );
	}

	/**
	 * Get multiple cached items efficiently
	 *
	 * @param array  $keys       Array of cache keys
	 * @param string $cache_type Type of cache
	 * @return array Cached data keyed by original keys
	 */
	public function get_multiple( $keys, $cache_type = 'plugin_metadata' ) {
		$results      = array();
		$missing_keys = array();

		// Try object cache first if available
		if ( wp_using_ext_object_cache() ) {
			foreach ( $keys as $key ) {
				$data = wp_cache_get( $key, $cache_type );
				if ( $data !== false ) {
					$results[ $key ] = $this->maybe_decompress_data( $data );
				} else {
					$missing_keys[] = $key;
				}
			}
		} else {
			$missing_keys = $keys;
		}

		// Get missing keys from transients
		if ( ! empty( $missing_keys ) ) {
			$transient_results = $this->get_multiple_transients( $missing_keys, $cache_type );

			foreach ( $transient_results as $key => $data ) {
				$results[ $key ] = $this->maybe_decompress_data( $data );

				// Store in object cache for future requests
				if ( wp_using_ext_object_cache() ) {
					wp_cache_set( $key, $data, $cache_type, 3600 );
				}
			}
		}

		return $results;
	}

	/**
	 * Get multiple transients efficiently
	 *
	 * @param array  $keys       Cache keys
	 * @param string $cache_type Cache type
	 * @return array Results
	 */
	private function get_multiple_transients( $keys, $cache_type ) {
		global $wpdb;

		// Build transient option names
		$transient_keys = array();
		foreach ( $keys as $key ) {
			$transient_keys[ $key ] = '_transient_' . $this->build_transient_key( $key, $cache_type );
		}

		// Create placeholders for IN clause
		$placeholders       = array_fill( 0, count( $transient_keys ), '%s' );
		$placeholder_string = implode( ',', $placeholders );

		// Try to get results from object cache first
		$cache_key = 'bulk_transients_' . md5( serialize( $transient_keys ) );
		$results = wp_cache_get( $cache_key, 'wp_plugin_filters_bulk' );
		
		if ( false === $results ) {
			// Single query to get all transients
			$query = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($placeholder_string) AND option_name NOT LIKE %s";
			$query_args = array_merge( array_values( $transient_keys ), array( '%\\_transient\\_timeout\\_%' ) );
			$results = $wpdb->get_results(
				$wpdb->prepare( $query, $query_args ),
				ARRAY_A
			);
			// Cache for 1 minute
			wp_cache_set( $cache_key, $results, 'wp_plugin_filters_bulk', 60 );
		}

		// Check expiration for found transients
		$valid_results = array();
		$timeout_keys  = array();

		foreach ( $results as $result ) {
			$transient_name = substr( $result['option_name'], 11 ); // Remove '_transient_' prefix
			$timeout_key    = "_transient_timeout_{$transient_name}";
			$timeout_keys[] = $timeout_key;

			// Find original key
			$original_key = array_search( $result['option_name'], $transient_keys );
			if ( $original_key !== false ) {
				$valid_results[ $original_key ] = $result['option_value'];
			}
		}

		// Check timeouts if we have any
		if ( ! empty( $timeout_keys ) ) {
			$timeout_placeholders       = array_fill( 0, count( $timeout_keys ), '%s' );
			$timeout_placeholder_string = implode( ',', $timeout_placeholders );

			// Try to get timeout results from cache first
			$timeout_cache_key = 'timeout_check_' . md5( serialize( $timeout_keys ) );
			$timeout_results = wp_cache_get( $timeout_cache_key, 'wp_plugin_filters_timeout' );
			
			if ( false === $timeout_results ) {
				$timeout_query = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($timeout_placeholder_string)";
				$timeout_results = $wpdb->get_results(
					$wpdb->prepare( $timeout_query, $timeout_keys ),
					ARRAY_A
				);
				// Cache for 30 seconds
				wp_cache_set( $timeout_cache_key, $timeout_results, 'wp_plugin_filters_timeout', 30 );
			}

			$current_time = time();
			foreach ( $timeout_results as $timeout_result ) {
				$transient_name = substr( $timeout_result['option_name'], 19 ); // Remove '_transient_timeout_' prefix
				$timeout        = intval( $timeout_result['option_value'] );

				if ( $timeout > 0 && $timeout < $current_time ) {
					// Transient expired, find and remove it
					$original_key = array_search( "_transient_{$transient_name}", $transient_keys );
					if ( $original_key !== false ) {
						unset( $valid_results[ $original_key ] );
					}
				}
			}
		}

		// Unserialize results
		$final_results = array();
		foreach ( $valid_results as $key => $value ) {
			$unserialized = maybe_unserialize( $value );
			if ( $unserialized !== false ) {
				$final_results[ $key ] = $unserialized;
			}
		}

		return $final_results;
	}

	/**
	 * Warm cache for popular plugins
	 *
	 * @return int Number of plugins cached
	 */
	public function warm_popular_plugins_cache() {
		$api_handler   = new WP_Plugin_Filters_API_Handler();
		$popular_slugs = $api_handler->get_popular_plugin_slugs();

		$warmed_count = 0;

		foreach ( $popular_slugs as $slug ) {
			// Only warm cache if data is not already cached
			if ( $this->get( $slug, 'plugin_metadata' ) === null ) {
				$plugin_details = $api_handler->get_plugin_details( $slug );

				if ( ! is_wp_error( $plugin_details ) ) {
					$this->set( $slug, $plugin_details, 'plugin_metadata' );
					$warmed_count++;

					// Calculate and cache ratings
					$rating_calculator = new WP_Plugin_Filters_Rating_Calculator();
					$health_calculator = new WP_Plugin_Filters_Health_Calculator();

					$usability_rating = $rating_calculator->calculate_usability_rating( $plugin_details );
					$health_score     = $health_calculator->calculate_health_score( $plugin_details );

					$rating_data = array(
						'usability_rating' => $usability_rating,
						'health_score'     => $health_score,
						'calculated_at'    => current_time( 'mysql' ),
					);

					$this->set( $slug . '_ratings', $rating_data, 'calculated_ratings' );

					// Limit warming to prevent resource exhaustion
					if ( $warmed_count >= 10 ) {
						break;
					}

					// Small delay between requests to be respectful
					usleep( 100000 ); // 0.1 second
				}
			}
		}

		return $warmed_count;
	}

	/**
	 * Clean up expired cache entries
	 *
	 * @param int $limit Maximum entries to process
	 * @return int Number of entries cleaned up
	 */
	public function cleanup_expired_cache( $limit = 1000 ) {
		global $wpdb;

		$current_time = time();

		// Find expired transients  
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for expired transient cleanup
		$expired_transients = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for expired transient cleanup
				"SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_value > 0 
                 AND option_value < %d 
                 LIMIT %d",
				'%\\_transient\\_timeout\\_wp\\_plugin\\_%',
				$current_time,
				$limit
			)
		);

		if ( empty( $expired_transients ) ) {
			return 0;
		}

		// Build corresponding transient names
		$transient_names = array();
		foreach ( $expired_transients as $timeout_name ) {
			$transient_name    = '_transient_' . substr( $timeout_name, 19 ); // Remove '_transient_timeout_' prefix
			$transient_names[] = $transient_name;
		}

		// Delete in batch
		$all_names          = array_merge( $expired_transients, $transient_names );
		$placeholders       = array_fill( 0, count( $all_names ), '%s' );
		$placeholder_string = implode( ',', $placeholders );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for bulk transient deletion
		$delete_query = "DELETE FROM {$wpdb->options} WHERE option_name IN ($placeholder_string)";
		$deleted_count = $wpdb->query(
			$wpdb->prepare( $delete_query, $all_names )
		);

		// Clear object cache for deleted transients
		if ( wp_using_ext_object_cache() ) {
			foreach ( $expired_transients as $timeout_name ) {
				$key = substr( $timeout_name, 19 );
				wp_cache_delete( $key, 'plugin_metadata' );
			}
		}

		// Invalidate cache statistics since cache contents changed
		if ( $deleted_count > 0 ) {
			wp_cache_delete( 'wp_plugin_cache_stats', 'wp_plugin_filters_stats' );
		}

		return $deleted_count;
	}

	/**
	 * Clear all plugin filter cache
	 *
	 * @param string $cache_type Type of cache to clear ('all' or specific type)
	 * @return int Number of entries cleared
	 */
	public function clear_all_cache( $cache_type = 'all' ) {
		global $wpdb;

		$patterns = array();

		switch ( $cache_type ) {
			case 'plugin_metadata':
				$patterns[] = '_transient_wp_plugin_meta_%';
				$patterns[] = '_transient_timeout_wp_plugin_meta_%';
				break;
			case 'calculated_ratings':
				$patterns[] = '_transient_wp_plugin_rating_%';
				$patterns[] = '_transient_timeout_wp_plugin_rating_%';
				break;
			case 'search_results':
				$patterns[] = '_transient_wp_plugin_search_%';
				$patterns[] = '_transient_timeout_wp_plugin_search_%';
				break;
			case 'api_responses':
				$patterns[] = '_transient_wp_plugin_details_%';
				$patterns[] = '_transient_timeout_wp_plugin_details_%';
				break;
			case 'all':
			default:
				$patterns[] = '_transient_wp_plugin_%';
				$patterns[] = '_transient_timeout_wp_plugin_%';
				break;
		}

		$deleted_count = 0;

		foreach ( $patterns as $pattern ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for pattern-based cache deletion
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$pattern
				)
			);

			if ( $deleted !== false ) {
				$deleted_count += $deleted;
			}
		}

		// Clear object cache groups
		if ( wp_using_ext_object_cache() ) {
			wp_cache_flush_group( 'plugin_metadata' );
			wp_cache_flush_group( 'calculated_ratings' );
			wp_cache_flush_group( 'search_results' );
			wp_cache_flush_group( 'api_responses' );
		}

		// Invalidate cache statistics since cache contents changed
		if ( $deleted_count > 0 ) {
			wp_cache_delete( 'wp_plugin_cache_stats', 'wp_plugin_filters_stats' );
		}

		return $deleted_count;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache statistics
	 */
	public function get_cache_statistics() {
		global $wpdb;

		// Try to get stats from object cache first (cache for 5 minutes)
		$cache_key = 'wp_plugin_cache_stats';
		$stats = wp_cache_get( $cache_key, 'wp_plugin_filters_stats' );
		
		if ( false === $stats ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for cache statistics
			$stats = $wpdb->get_results(
				"SELECT 
	                CASE 
	                    WHEN option_name LIKE '_transient_wp_plugin_meta_%' THEN 'metadata'
	                    WHEN option_name LIKE '_transient_wp_plugin_rating_%' THEN 'ratings'
	                    WHEN option_name LIKE '_transient_wp_plugin_search_%' THEN 'searches'
	                    WHEN option_name LIKE '_transient_wp_plugin_details_%' THEN 'api'
	                    ELSE 'other'
	                END as cache_type,
	                COUNT(*) as count,
	                SUM(LENGTH(option_value)) as total_size,
	                AVG(LENGTH(option_value)) as avg_size
	             FROM {$wpdb->options} 
	             WHERE option_name LIKE '_transient_wp_plugin_%'
	             AND option_name NOT LIKE '_transient_timeout_%'
	             GROUP BY cache_type",
				ARRAY_A
			);
			
			// Cache the results for 5 minutes (300 seconds)
			wp_cache_set( $cache_key, $stats, 'wp_plugin_filters_stats', 300 );
		}

		$analysis = array(
			'total_transients'       => 0,
			'total_size_bytes'       => 0,
			'by_type'                => array(),
			'object_cache_available' => wp_using_ext_object_cache(),
			'cache_config'           => $this->cache_config,
		);

		foreach ( $stats as $stat ) {
			$analysis['total_transients']              += intval( $stat['count'] );
			$analysis['total_size_bytes']              += intval( $stat['total_size'] );
			$analysis['by_type'][ $stat['cache_type'] ] = array(
				'count'      => intval( $stat['count'] ),
				'total_size' => intval( $stat['total_size'] ),
				'avg_size'   => intval( $stat['avg_size'] ),
				'size_human' => size_format( $stat['total_size'] ),
			);
		}

		$analysis['total_size_human'] = size_format( $analysis['total_size_bytes'] );

		return $analysis;
	}

	/**
	 * Build transient key with prefix
	 *
	 * @param string $key        Original key
	 * @param string $cache_type Cache type
	 * @return string Transient key
	 */
	private function build_transient_key( $key, $cache_type ) {
		return 'wp_plugin_' . $cache_type . '_' . md5( $key );
	}

	/**
	 * Compress data if it's large
	 *
	 * @param mixed $data Data to potentially compress
	 * @return mixed Original or compressed data
	 */
	private function maybe_compress_data( $data ) {
		if ( function_exists( 'gzcompress' ) ) {
			$serialized = serialize( $data );
			if ( strlen( $serialized ) > 1024 ) { // Only compress if > 1KB
				return array(
					'compressed' => true,
					'data'       => base64_encode( gzcompress( $serialized, 6 ) ),
				);
			}
		}
		return $data;
	}

	/**
	 * Decompress data if it was compressed
	 *
	 * @param mixed $data Data to potentially decompress
	 * @return mixed Decompressed data
	 */
	private function maybe_decompress_data( $data ) {
		if ( is_array( $data ) && isset( $data['compressed'] ) && $data['compressed'] && function_exists( 'gzuncompress' ) ) {
			$decompressed = gzuncompress( base64_decode( $data['data'] ) );
			return unserialize( $decompressed );
		}
		return $data;
	}

	/**
	 * Update cache configuration
	 *
	 * @param array $new_config New cache configuration
	 * @return bool Success status
	 */
	public function update_cache_config( $new_config ) {
		$this->cache_config = array_merge( $this->cache_config, $new_config );

		$settings                    = get_option( 'wp_plugin_filters_settings', array() );
		$settings['cache_durations'] = $this->cache_config;

		return update_option( 'wp_plugin_filters_settings', $settings );
	}
}
