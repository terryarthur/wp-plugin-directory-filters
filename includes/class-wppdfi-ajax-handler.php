<?php
/**
 * AJAX Request Handler
 *
 * @package WPPDFI_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX request handling class.
 */
class WPPDFI_AJAX_Handler {

	/**
	 * Rate limiting - requests per minute per user.
	 */
	const RATE_LIMIT_PER_MINUTE = 30;

	/**
	 * Security handler instance.
	 *
	 * @var WPPDFI_Security_Handler
	 */
	private $security_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Ensure plugin directory constant is defined.
		if ( ! defined( 'WPPDFI_PLUGIN_DIR' ) ) {
			define( 'WPPDFI_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) );
		}

		require_once WPPDFI_PLUGIN_DIR . 'includes/class-security-handler.php';
		$this->security_handler = new WPPDFI_Security_Handler();
	}

	/**
	 * Handle plugin filter request.
	 */
	public function handle_filter_request() {
		// Add error handling for debugging.
		try {
			// Ensure all required classes are loaded.
			if ( ! class_exists( 'WPPDFI_API_Handler' ) ) {
				require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-api-handler.php';
			}
			if ( ! class_exists( 'WPPDFI_Cache_Manager' ) ) {
				require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-cache-manager.php';
			}

			// Security validation..
			$security_check = $this->security_handler->validate_ajax_request( 'wppdfi_filter_action', 'install_plugins' );
			if ( is_wp_error( $security_check ) ) {
				wp_send_json_error(
					array(
						'message' => $security_check->get_error_message(),
						'code'    => $security_check->get_error_code(),
					),
					403
				);
			}

			// Rate limiting check..
			$rate_limit_check = $this->check_rate_limit();
			if ( is_wp_error( $rate_limit_check ) ) {
				wp_send_json_error(
					array(
						'message' => $rate_limit_check->get_error_message(),
						'code'    => 'rate_limit_exceeded',
					),
					429
				);
			}

			// Sanitize and validate input - only process required fields.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via security_handler
			$request_data      = $this->sanitize_filter_request();
			$validation_result = $this->validate_filter_request( $request_data );

			if ( is_wp_error( $validation_result ) ) {
				wp_send_json_error(
					array(
						'message' => $validation_result->get_error_message(),
						'code'    => 'validation_error',
					),
					400
				);
			}

			// Execute filtered search.
			$results = $this->execute_filtered_search( $request_data );

			if ( is_wp_error( $results ) ) {
				wp_send_json_error(
					array(
						'message' => $results->get_error_message(),
						'code'    => $results->get_error_code(),
					),
					500
				);
			}

			// Return successful response.
			wp_send_json_success( $results );

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] Filter request exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );
			}
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while processing your request.', 'wppd-filters' ),
					'code'    => 'internal_error',
					'debug'   => WP_DEBUG ? $e->getMessage() : null,
				),
				500
			);
		} catch ( Error $e ) {
			// Catch PHP 7+ fatal errors.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] PHP Fatal error in handle_filter_request: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );
			}
			wp_send_json_error(
				array(
					'message' => __( 'A PHP fatal error occurred. Please check error logs.', 'wppd-filters' ),
					'code'    => 'php_fatal_error',
					'debug'   => WP_DEBUG ? $e->getMessage() : null,
				),
				500
			);
		}
	}

	/**
	 * Handle plugin sort request.
	 */
	public function handle_sort_request() {
		// Security validation.
		$security_check = $this->security_handler->validate_ajax_request( 'wppdfi_sort_action', 'install_plugins' );
		if ( is_wp_error( $security_check ) ) {
			wp_send_json_error(
				array(
					'message' => $security_check->get_error_message(),
					'code'    => $security_check->get_error_code(),
				),
				403
			);
		}

		// Rate limiting check.
		$rate_limit_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			wp_send_json_error(
				array(
					'message' => $rate_limit_check->get_error_message(),
					'code'    => 'rate_limit_exceeded',
				),
				429
			);
		}

		// Sanitize and validate input - only process required fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via security_handler
		$request_data = $this->sanitize_sort_request();

		try {
			// Execute sorted search.
			$results = $this->execute_sorted_search( $request_data );

			if ( is_wp_error( $results ) ) {
				wp_send_json_error(
					array(
						'message' => $results->get_error_message(),
						'code'    => $results->get_error_code(),
					),
					500
				);
			}

			wp_send_json_success( $results );

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] Sort request exception: ' . $e->getMessage() );
			}
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while processing your request.', 'wppd-filters' ),
					'code'    => 'internal_error',
				),
				500
			);
		}
	}

	/**
	 * Handle rating calculation request.
	 */
	public function handle_rating_calculation() {
		// Security validation.
		$security_check = $this->security_handler->validate_ajax_request( 'wppdfi_rating_action', 'install_plugins' );
		if ( is_wp_error( $security_check ) ) {
			wp_send_json_error(
				array(
					'message' => $security_check->get_error_message(),
					'code'    => $security_check->get_error_code(),
				),
				403
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via security_handler
		$plugin_slug = sanitize_key( $_POST['plugin_slug'] ?? '' );
		if ( empty( $plugin_slug ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Plugin slug is required', 'wppd-filters' ),
					'code'    => 'missing_slug',
				),
				400
			);
		}

		try {
			$cache_manager = WPPDFI_Cache_Manager::get_instance();

			// Check cache first.
			$cached_rating = $cache_manager->get( $plugin_slug . '_ratings', 'calculated_ratings' );
			if ( null !== $cached_rating ) {
				wp_send_json_success( $cached_rating );
				return;
			}

			// Get plugin details.
			$api_handler    = new WPPDFI_API_Handler();
			$plugin_details = $api_handler->get_plugin_details( $plugin_slug );

			if ( is_wp_error( $plugin_details ) ) {
				wp_send_json_error(
					array(
						'message' => $plugin_details->get_error_message(),
						'code'    => $plugin_details->get_error_code(),
					),
					500
				);
			}

			// Calculate ratings.
			$rating_calculator = new WPPDFI_Rating_Calculator();
			$health_calculator = new WPPDFI_Health_Calculator();

			$usability_rating = $rating_calculator->calculate_usability_rating( $plugin_details );
			$health_score     = $health_calculator->calculate_health_score( $plugin_details );

			$result = array(
				'plugin_slug'           => $plugin_slug,
				'usability_rating'      => $usability_rating,
				'health_score'          => $health_score,
				'health_color'          => $health_calculator->get_health_color( $health_score ),
				'health_description'    => $health_calculator->get_health_description( $health_score ),
				'calculated_at'         => current_time( 'mysql' ),
				'calculation_breakdown' => array(
					'usability' => $rating_calculator->get_calculation_breakdown(),
					'health'    => $health_calculator->get_calculation_breakdown(),
				),
			);

			// Cache the result.
			$cache_manager->set( $plugin_slug . '_ratings', $result, 'calculated_ratings' );

			wp_send_json_success( $result );

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] Rating calculation exception: ' . $e->getMessage() );
			}
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while calculating ratings.', 'wppd-filters' ),
					'code'    => 'calculation_error',
				),
				500
			);
		}
	}

	/**
	 * Handle cache clear request.
	 */
	public function handle_cache_clear() {
		// Security validation. (requires manage_options capability).
		$security_check = $this->security_handler->validate_ajax_request( 'wppdfi_clear_cache', 'manage_options' );
		if ( is_wp_error( $security_check ) ) {
			wp_send_json_error(
				array(
					'message' => $security_check->get_error_message(),
					'code'    => $security_check->get_error_code(),
				),
				403
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via security_handler
		$cache_type = sanitize_key( $_POST['cache_type'] ?? 'all' );

		try {
			$cache_manager = WPPDFI_Cache_Manager::get_instance();
			$cleared_count = $cache_manager->clear_all_cache( $cache_type );

			wp_send_json_success(
				array(
					/* translators: %d: number of cache entries cleared */
					'message'       => sprintf( __( 'Cleared %d cache entries', 'wppd-filters' ), $cleared_count ),
					'cache_type'    => $cache_type,
					'cleared_count' => $cleared_count,
					'timestamp'     => current_time( 'mysql' ),
				)
			);

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] Cache clear exception: ' . $e->getMessage() );
			}
			wp_send_json_error(
				array(
					'message' => __( 'An error occurred while clearing cache.', 'wppd-filters' ),
					'code'    => 'cache_clear_error',
				),
				500
			);
		}
	}

	/**
	 * Execute filtered plugin search
	 *
	 * @param array $request_data Sanitized request data.
	 * @return array|WP_Error Search results or error.
	 */
	private function execute_filtered_search( $request_data ) {
		$api_handler   = new WPPDFI_API_Handler();
		$cache_manager = WPPDFI_Cache_Manager::get_instance();

		// Generate cache key for this search.
		$cache_key = 'search_' . md5( serialize( $request_data ) );

		// Check cache first.
		$cached_results = $cache_manager->get( $cache_key, 'search_results' );
		if ( null !== $cached_results ) {
			return $cached_results;
		}

		// Perform API search.
		$api_results = $api_handler->search_plugins(
			$request_data['search_term'],
			$request_data['page'],
			$request_data['per_page']
		);

		if ( is_wp_error( $api_results ) ) {
			return $api_results;
		}

		// Apply filters to results.
		$filtered_plugins = $this->apply_filters_to_plugins( $api_results['plugins'], $request_data );

		// Calculate ratings for filtered plugins.
		$enhanced_plugins = $this->enhance_plugins_with_ratings( $filtered_plugins );

		// Apply sorting.
		$sorted_plugins = $this->sort_plugins( $enhanced_plugins, $request_data );

		// Format response.
		$response = array(
			'plugins'         => $sorted_plugins,
			'pagination'      => array(
				'current_page'  => $request_data['page'],
				'total_pages'   => ceil( count( $filtered_plugins ) / $request_data['per_page'] ),
				'total_results' => count( $filtered_plugins ),
				'per_page'      => $request_data['per_page'],
			),
			'filters_applied' => $this->get_applied_filters( $request_data ),
			'cache_info'      => array(
				'cached'     => false,
				'cache_key'  => $cache_key,
				'expires_in' => 3600,
			),
		);

		// Cache the results.
		$cache_manager->set( $cache_key, $response, 'search_results' );

		return $response;
	}

	/**
	 * Execute sorted plugin search
	 *
	 * @param array $request_data Sanitized request data.
	 * @return array|WP_Error Search results or error.
	 */
	private function execute_sorted_search( $request_data ) {
		// This can reuse the filtered search logic.
		return $this->execute_filtered_search( $request_data );
	}

	/**
	 * Apply filters to plugin results
	 *
	 * @param array $plugins      Plugin data.
	 * @param array $request_data Request parameters.
	 * @return array Filtered plugins.
	 */
	private function apply_filters_to_plugins( $plugins, $request_data ) {
		$filtered = array();

		foreach ( $plugins as $plugin ) {
			// Installation range filter.
			if ( ! empty( $request_data['installation_range'] ) && 'all' !== $request_data['installation_range'] ) {
				if ( ! $this->plugin_matches_installation_range( $plugin, $request_data['installation_range'] ) ) {
					continue;
				}
			}

			// Update timeframe filter.
			if ( ! empty( $request_data['update_timeframe'] ) && 'all' !== $request_data['update_timeframe'] ) {
				if ( ! $this->plugin_matches_update_timeframe( $plugin, $request_data['update_timeframe'] ) ) {
					continue;
				}
			}

			// Rating filter (will be applied after rating calculation).
			$filtered[] = $plugin;
		}

		return $filtered;
	}

	/**
	 * Check if plugin matches installation range
	 *
	 * @param array  $plugin Plugin data.
	 * @param string $range  Installation range.
	 * @return bool Matches range.
	 */
	private function plugin_matches_installation_range( $plugin, $range ) {
		$installs = intval( $plugin['active_installs'] );

		switch ( $range ) {
			case '0-1k':
				return 1000 > $installs;
			case '1k-10k':
				return 1000 <= $installs && 10000 > $installs;
			case '10k-100k':
				return 10000 <= $installs && 100000 > $installs;
			case '100k-1m':
				return 100000 <= $installs && 1000000 > $installs;
			case '1m-plus':
				return 1000000 <= $installs;
			default:
				return true;
		}
	}

	/**
	 * Check if plugin matches update timeframe
	 *
	 * @param array  $plugin    Plugin data.
	 * @param string $timeframe Update timeframe.
	 * @return bool Matches timeframe.
	 */
	private function plugin_matches_update_timeframe( $plugin, $timeframe ) {
		if ( empty( $plugin['last_updated'] ) ) {
			return false;
		}

		$last_updated = strtotime( $plugin['last_updated'] );
		$now          = current_time( 'timestamp' );
		$days_ago     = ( $now - $last_updated ) / DAY_IN_SECONDS;

		switch ( $timeframe ) {
			case 'last_week':
				return 7 >= $days_ago;
			case 'last_month':
				return 30 >= $days_ago;
			case 'last_3months':
				return 90 >= $days_ago;
			case 'last_6months':
				return 180 >= $days_ago;
			case 'last_year':
				return 365 >= $days_ago;
			case 'older':
				return 365 < $days_ago;
			default:
				return true;
		}
	}

	/**
	 * Enhance plugins with calculated ratings
	 *
	 * @param array $plugins Plugin data.
	 * @return array Enhanced plugins.
	 */
	private function enhance_plugins_with_ratings( $plugins ) {
		$rating_calculator = new WPPDFI_Rating_Calculator();
		$health_calculator = new WPPDFI_Health_Calculator();
		$cache_manager     = WPPDFI_Cache_Manager::get_instance();

		foreach ( $plugins as &$plugin ) {
			// Check cache first.
			$cached_ratings = $cache_manager->get( $plugin['slug'] . '_ratings', 'calculated_ratings' );

			if ( null !== $cached_ratings ) {
				$plugin['usability_rating'] = $cached_ratings['usability_rating'];
				$plugin['health_score']     = $cached_ratings['health_score'];
				$plugin['health_color']     = $cached_ratings['health_color'] ?? $health_calculator->get_health_color( $cached_ratings['health_score'] );
			} else {
				// Calculate ratings.
				$plugin['usability_rating'] = $rating_calculator->calculate_usability_rating( $plugin );
				$plugin['health_score']     = $health_calculator->calculate_health_score( $plugin );
				$plugin['health_color']     = $health_calculator->get_health_color( $plugin['health_score'] );

				// Cache the calculations.
				$rating_data = array(
					'usability_rating' => $plugin['usability_rating'],
					'health_score'     => $plugin['health_score'],
					'health_color'     => $plugin['health_color'],
					'calculated_at'    => current_time( 'mysql' ),
				);
				$cache_manager->set( $plugin['slug'] . '_ratings', $rating_data, 'calculated_ratings' );
			}

			// Add human-readable last updated.
			if ( ! empty( $plugin['last_updated'] ) ) {
				$plugin['last_updated_human'] = human_time_diff( strtotime( $plugin['last_updated'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wppd-filters' );
			}
		}

		return $plugins;
	}

	/**
	 * Sort plugins based on request parameters
	 *
	 * @param array $plugins      Plugin data.
	 * @param array $request_data Request parameters.
	 * @return array Sorted plugins.
	 */
	private function sort_plugins( $plugins, $request_data ) {
		if ( empty( $request_data['sort_by'] ) || 'relevance' === $request_data['sort_by'] ) {
			return $plugins; // Keep original order for relevance.
		}

		$sort_direction = 'asc' === $request_data['sort_direction'] ? 1 : -1;

		usort(
			$plugins,
			function( $a, $b ) use ( $request_data, $sort_direction ) {
				switch ( $request_data['sort_by'] ) {
					case 'installations':
						$comparison = ( $a['active_installs'] ?? 0 ) <=> ( $b['active_installs'] ?? 0 );
						break;
					case 'rating':
						$comparison = ( $a['rating'] ?? 0 ) <=> ( $b['rating'] ?? 0 );
						break;
					case 'updated':
						$a_time     = strtotime( $a['last_updated'] ?? '1970-01-01' );
						$b_time     = strtotime( $b['last_updated'] ?? '1970-01-01' );
						$comparison = $a_time <=> $b_time;
						break;
					case 'usability_rating':
						$comparison = ( $a['usability_rating'] ?? 0 ) <=> ( $b['usability_rating'] ?? 0 );
						break;
					case 'health_score':
						$comparison = ( $a['health_score'] ?? 0 ) <=> ( $b['health_score'] ?? 0 );
						break;
					default:
						$comparison = strcasecmp( $a['name'] ?? '', $b['name'] ?? '' );
						break;
				}

				return $comparison * $sort_direction;
			}
		);

		return $plugins;
	}

	/**
	 * Get applied filters summary
	 *
	 * @param array $request_data Request data.
	 * @return array Applied filters.
	 */
	private function get_applied_filters( $request_data ) {
		$filters = array();

		if ( ! empty( $request_data['search_term'] ) ) {
			$filters['search_term'] = $request_data['search_term'];
		}

		if ( ! empty( $request_data['installation_range'] ) && 'all' !== $request_data['installation_range'] ) {
			$filters['installation_range'] = $request_data['installation_range'];
		}

		if ( ! empty( $request_data['update_timeframe'] ) && 'all' !== $request_data['update_timeframe'] ) {
			$filters['update_timeframe'] = $request_data['update_timeframe'];
		}

		if ( ! empty( $request_data['sort_by'] ) && 'relevance' !== $request_data['sort_by'] ) {
			$filters['sort_by']        = $request_data['sort_by'];
			$filters['sort_direction'] = $request_data['sort_direction'];
		}

		return $filters;
	}

	/**
	 * Sanitize filter request data - only process required fields
	 *
	 * @return array Sanitized data.
	 */
	private function sanitize_filter_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via security_handler
		return array(
			'search_term'        => sanitize_text_field( wp_unslash( $_POST['search_term'] ?? '' ) ),
			'installation_range' => sanitize_key( $_POST['installation_range'] ?? 'all' ),
			'update_timeframe'   => sanitize_key( $_POST['update_timeframe'] ?? 'all' ),
			'usability_rating'   => floatval( $_POST['usability_rating'] ?? 0 ),
			'health_score'       => intval( $_POST['health_score'] ?? 0 ),
			'sort_by'            => sanitize_key( $_POST['sort_by'] ?? 'relevance' ),
			'sort_direction'     => in_array( $_POST['sort_direction'] ?? 'desc', array( 'asc', 'desc' ), true ) ? $_POST['sort_direction'] : 'desc',
			'page'               => max( 1, intval( $_POST['page'] ?? 1 ) ),
			'per_page'           => max( 1, min( 48, intval( $_POST['per_page'] ?? 24 ) ) ),
		);
	}

	/**
	 * Sanitize sort request data - only process required fields
	 *
	 * @return array Sanitized data.
	 */
	private function sanitize_sort_request() {
		return $this->sanitize_filter_request();
	}

	/**
	 * Validate filter request data
	 *
	 * @param array $request_data Request data.
	 * @return bool|WP_Error Validation result.
	 */
	private function validate_filter_request( $request_data ) {
		// Search term length validation.
		if ( 200 < strlen( $request_data['search_term'] ) ) {
			return new WP_Error( 'search_term_too_long', __( 'Search term is too long', 'wppd-filters' ) );
		}

		// Results per page validation.
		if ( 48 < $request_data['per_page'] ) {
			return new WP_Error( 'per_page_limit', __( 'Results per page exceeds maximum', 'wppd-filters' ) );
		}

		// Page number validation.
		if ( 1000 < $request_data['page'] ) {
			return new WP_Error( 'page_limit', __( 'Page number exceeds maximum', 'wppd-filters' ) );
		}

		return true;
	}

	/**
	 * Check rate limiting for current user
	 *
	 * @return bool|WP_Error Rate limit result
	 */
	private function check_rate_limit() {
		$user_id  = get_current_user_id();
		$rate_key = 'wppdfi_ajax_rate_' . $user_id;

		$current_count = get_transient( $rate_key );
		if ( false === $current_count ) {
			$current_count = 0;
		}

		if ( self::RATE_LIMIT_PER_MINUTE <= $current_count ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'Too many requests. Please slow down and try again in a minute.', 'wppd-filters' ) );
		}

		// Increment counter.
		set_transient( $rate_key, $current_count + 1, 60 );

		return true;
	}
}
