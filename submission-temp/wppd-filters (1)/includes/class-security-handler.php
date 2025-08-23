<?php
/**
 * Security Handler for WordPress Plugin Directory Filters
 *
 * @package WP_Plugin_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security validation and sanitization class
 */
class WP_Plugin_Filters_Security_Handler {

	/**
	 * Validate AJAX request security
	 *
	 * @param string $nonce_action  Nonce action to verify
	 * @param string $required_capability WordPress capability required
	 * @return bool|WP_Error True if valid, WP_Error if invalid
	 */
	public function validate_ajax_request( $nonce_action, $required_capability = 'install_plugins' ) {
		// Check if request is AJAX
		if ( ! wp_doing_ajax() ) {
			return new WP_Error( 'not_ajax', __( 'This endpoint only accepts AJAX requests', 'wppd-filters' ) );
		}

		// Verify nonce
		$nonce_key = 'nonce';
		if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), $nonce_action ) ) {
			return new WP_Error( 'nonce_verification_failed', __( 'Security verification failed', 'wppd-filters' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( $required_capability ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action', 'wppd-filters' ) );
		}

		// Additional security checks
		if ( ! $this->validate_referrer() ) {
			return new WP_Error( 'invalid_referrer', __( 'Invalid request origin', 'wppd-filters' ) );
		}

		return true;
	}

	/**
	 * Validate request referrer
	 *
	 * @return bool Valid referrer
	 */
	private function validate_referrer() {
		// Check if request comes from admin area
		$referrer = wp_get_referer();
		if ( ! $referrer ) {
			return false;
		}

		// Ensure referrer is from our admin area
		$admin_url = admin_url();
		return strpos( $referrer, $admin_url ) === 0;
	}

	/**
	 * Sanitize plugin search parameters
	 *
	 * @param array $params Raw parameters
	 * @return array Sanitized parameters
	 */
	public function sanitize_search_params( $params ) {
		return array(
			'search_term'        => $this->sanitize_search_term( $params['search_term'] ?? '' ),
			'installation_range' => $this->sanitize_installation_range( $params['installation_range'] ?? 'all' ),
			'update_timeframe'   => $this->sanitize_timeframe( $params['update_timeframe'] ?? 'all' ),
			'usability_rating'   => $this->sanitize_rating( $params['usability_rating'] ?? 0 ),
			'health_score'       => $this->sanitize_health_score( $params['health_score'] ?? 0 ),
			'sort_by'            => $this->sanitize_sort_field( $params['sort_by'] ?? 'relevance' ),
			'sort_direction'     => $this->sanitize_sort_direction( $params['sort_direction'] ?? 'desc' ),
			'page'               => $this->sanitize_page_number( $params['page'] ?? 1 ),
			'per_page'           => $this->sanitize_per_page( $params['per_page'] ?? 24 ),
		);
	}

	/**
	 * Sanitize search term
	 *
	 * @param string $term Search term
	 * @return string Sanitized term
	 */
	private function sanitize_search_term( $term ) {
		$term = sanitize_text_field( $term );
		$term = wp_strip_all_tags( $term );
		return substr( $term, 0, 200 ); // Limit length
	}

	/**
	 * Sanitize installation range
	 *
	 * @param string $range Installation range
	 * @return string Valid range
	 */
	private function sanitize_installation_range( $range ) {
		$valid_ranges = array( 'all', '0-1k', '1k-10k', '10k-100k', '100k-1m', '1m-plus' );
		return in_array( $range, $valid_ranges, true ) ? $range : 'all';
	}

	/**
	 * Sanitize timeframe
	 *
	 * @param string $timeframe Update timeframe
	 * @return string Valid timeframe
	 */
	private function sanitize_timeframe( $timeframe ) {
		$valid_timeframes = array( 'all', 'last_week', 'last_month', 'last_3months', 'last_6months', 'last_year', 'older' );
		return in_array( $timeframe, $valid_timeframes, true ) ? $timeframe : 'all';
	}

	/**
	 * Sanitize rating value
	 *
	 * @param mixed $rating Rating value
	 * @return float Valid rating
	 */
	private function sanitize_rating( $rating ) {
		$rating = floatval( $rating );
		return max( 0, min( 5, $rating ) );
	}

	/**
	 * Sanitize health score
	 *
	 * @param mixed $score Health score
	 * @return int Valid score
	 */
	private function sanitize_health_score( $score ) {
		$score = intval( $score );
		return max( 0, min( 100, $score ) );
	}

	/**
	 * Sanitize sort field
	 *
	 * @param string $field Sort field
	 * @return string Valid field
	 */
	private function sanitize_sort_field( $field ) {
		$valid_fields = array(
			'relevance',
			'popularity',
			'rating',
			'updated',
			'installations',
			'usability_rating',
			'health_score',
		);
		return in_array( $field, $valid_fields, true ) ? $field : 'relevance';
	}

	/**
	 * Sanitize sort direction
	 *
	 * @param string $direction Sort direction
	 * @return string Valid direction
	 */
	private function sanitize_sort_direction( $direction ) {
		return in_array( $direction, array( 'asc', 'desc' ), true ) ? $direction : 'desc';
	}

	/**
	 * Sanitize page number
	 *
	 * @param mixed $page Page number
	 * @return int Valid page number
	 */
	private function sanitize_page_number( $page ) {
		$page = intval( $page );
		return max( 1, min( 1000, $page ) ); // Limit to reasonable range
	}

	/**
	 * Sanitize per page value
	 *
	 * @param mixed $per_page Results per page
	 * @return int Valid per page value
	 */
	private function sanitize_per_page( $per_page ) {
		$per_page = intval( $per_page );
		return max( 1, min( 48, $per_page ) ); // WordPress plugin installer limits
	}

	/**
	 * Validate and sanitize plugin slug
	 *
	 * @param string $slug Plugin slug
	 * @return string|WP_Error Sanitized slug or error
	 */
	public function validate_plugin_slug( $slug ) {
		$slug = sanitize_key( $slug );

		if ( empty( $slug ) ) {
			return new WP_Error( 'invalid_slug', __( 'Plugin slug cannot be empty', 'wppd-filters' ) );
		}

		if ( strlen( $slug ) > 100 ) {
			return new WP_Error( 'slug_too_long', __( 'Plugin slug is too long', 'wppd-filters' ) );
		}

		// Plugin slugs should only contain lowercase letters, numbers, and hyphens
		if ( ! preg_match( '/^[a-z0-9\-]+$/', $slug ) ) {
			return new WP_Error( 'invalid_slug_format', __( 'Invalid plugin slug format', 'wppd-filters' ) );
		}

		return $slug;
	}

	/**
	 * Rate limiting check for API requests
	 *
	 * @param string $action    Action being performed
	 * @param int    $limit     Requests per minute limit
	 * @param int    $window    Time window in seconds
	 * @return bool|WP_Error True if allowed, WP_Error if rate limited
	 */
	public function check_rate_limit( $action = 'general', $limit = 60, $window = 60 ) {
		$user_id    = get_current_user_id();
		$ip_address = $this->get_client_ip();

		// Create rate limit key based on user and IP
		$rate_key = "wp_plugin_rate_{$action}_{$user_id}_{$ip_address}";

		$current_count = get_transient( $rate_key );
		if ( $current_count === false ) {
			$current_count = 0;
		}

		if ( $current_count >= $limit ) {
			return new WP_Error(
				'rate_limit_exceeded',
				sprintf(
					/* translators: %1$d: maximum number of requests, %2$d: time window in seconds */
					__( 'Rate limit exceeded. Maximum %1$d requests per %2$d seconds allowed.', 'wppd-filters' ),
					$limit,
					$window
				)
			);
		}

		// Increment counter
		set_transient( $rate_key, $current_count + 1, $window );

		return true;
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) && ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// Handle comma-separated list of IPs
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1'; // Fallback
	}

	/**
	 * Escape output for HTML context
	 *
	 * @param mixed $data Data to escape
	 * @return mixed Escaped data
	 */
	public function escape_output( $data ) {
		if ( is_string( $data ) ) {
			return esc_html( $data );
		} elseif ( is_array( $data ) ) {
			return array_map( array( $this, 'escape_output' ), $data );
		} elseif ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = $this->escape_output( $value );
			}
			return $data;
		}

		return $data;
	}

	/**
	 * Validate and escape URL
	 *
	 * @param string $url URL to validate
	 * @return string|false Valid URL or false
	 */
	public function validate_url( $url ) {
		$url = esc_url_raw( $url );

		// Only allow http(s) URLs
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		return $url;
	}

	/**
	 * Log security events
	 *
	 * @param string $event_type Event type
	 * @param string $message    Log message
	 * @param array  $context    Additional context
	 */
	public function log_security_event( $event_type, $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_data = array(
				'timestamp'  => current_time( 'mysql' ),
				'user_id'    => get_current_user_id(),
				'ip_address' => $this->get_client_ip(),
				'event_type' => $event_type,
				'message'    => $message,
				'context'    => $context,
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WP Plugin Filters Security] ' . wp_json_encode( $log_data ) );
			}
		}
	}

	/**
	 * Generate secure random token
	 *
	 * @param int $length Token length
	 * @return string Random token
	 */
	public function generate_token( $length = 32 ) {
		if ( function_exists( 'random_bytes' ) ) {
			try {
				return bin2hex( random_bytes( $length / 2 ) );
			} catch ( Exception $e ) {
				// Fall back to wp_generate_password
			}
		}

		return wp_generate_password( $length, false );
	}
}
