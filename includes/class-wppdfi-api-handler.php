<?php
/**
 * WordPress.org Plugin API Handler
 *
 * @package WPPDFI_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress.org Plugin API integration class
 */
class WPPDFI_API_Handler {

	/**
	 * WordPress.org API base URL
	 */
	const API_BASE_URL = 'https://api.wordpress.org/plugins/info/1.2/';

	/**
	 * API timeout in seconds
	 */
	const API_TIMEOUT = 30;

	/**
	 * User agent for API requests
	 */
	const USER_AGENT = 'WordPress Plugin Directory Filters/1.0.0';

	/**
	 * Rate limiting - requests per minute
	 */
	const RATE_LIMIT_PER_MINUTE = 60;

	/**
	 * Search plugins via WordPress.org API
	 *
	 * @param string $search_term Search term.
	 * @param int    $page        Page number.
	 * @param int    $per_page    Results per page.
	 * @param array  $filters     Additional filters.
	 * @return array|WP_Error Search results or error
	 */
	public function search_plugins( $search_term = '', $page = 1, $per_page = 24, $filters = array() ) {
		// Check rate limiting.
		$rate_limit_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}

		// Generate cache key.
		$cache_key = $this->generate_search_cache_key( $search_term, $page, $per_page, $filters );

		// Check cache first - temporarily disable to force fresh API requests for testing.
		// TODO: Re-enable caching after icon issue is resolved.
		$cached_result = false; // get_transient( $cache_key ) - temporarily disabled.
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Prepare API request.
		$request_args = array(
			'search'   => sanitize_text_field( $search_term ),
			'page'     => absint( $page ),
			'per_page' => min( 48, absint( $per_page ) ), // Limit to 48 for performance.
			'fields'   => array(
				'short_description'        => true,
				'description'              => false,
				'tested'                   => true,
				'requires'                 => true,
				'rating'                   => true,
				'ratings'                  => true,
				'downloaded'               => true,
				'active_installs'          => true,
				'last_updated'             => true,
				'added'                    => true,
				'homepage'                 => true,
				'tags'                     => true,
				'support_threads'          => true,
				'support_threads_resolved' => true,
				'screenshots'              => false,
				'sections'                 => false,
				'icons'                    => true,
			),
		);

		// Apply additional filters.
		if ( ! empty( $filters['tag'] ) ) {
			$request_args['tag'] = sanitize_key( $filters['tag'] );
		}

		if ( ! empty( $filters['author'] ) ) {
			$request_args['author'] = sanitize_user( $filters['author'] );
		}

		// Debug: Log the request args.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			wp_debug_log( '[WP Plugin Filters] API Request Args: ' . wp_json_encode( $request_args ) );
		}

		// Icons ARE supported in query_plugins - the Chrome extension proves this works.
		// Keep icons in the request.

		// Make API request.
		$response = $this->make_api_request( 'query_plugins', $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Process and sanitize response.
		$processed_response = $this->process_search_response( $response );

		// Debug: Log first plugin to see if icons are included after processing.
		if ( isset( $processed_response['plugins'] ) && count( $processed_response['plugins'] ) > 0 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] First processed plugin: ' . wp_json_encode( $processed_response['plugins'][0] ) );
			}
		}

		// Cache the result (1 hour TTL).
		set_transient( $cache_key, $processed_response, 3600 );

		return $processed_response;
	}

	/**
	 * Get plugin details via WordPress.org API
	 *
	 * @param string $slug Plugin slug.
	 * @return array|WP_Error Plugin details or error
	 */
	public function get_plugin_details( $slug ) {
		// Check rate limiting.
		$rate_limit_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			return $rate_limit_check;
		}

		$slug      = sanitize_key( $slug );
		$cache_key = 'wp_plugin_details_' . $slug;

		// Check cache first (24 hour TTL) - temporarily disable to force fresh API requests.
		// TODO: Re-enable caching after icon issue is resolved.
		$cached_result = false; // get_transient( $cache_key ) - temporarily disabled.
		if ( false !== $cached_result ) {
			return $cached_result;
		}

		// Prepare API request.
		$request_args = array(
			'slug'   => $slug,
			'fields' => array(
				'description'              => true,
				'tested'                   => true,
				'requires'                 => true,
				'rating'                   => true,
				'ratings'                  => true,
				'downloaded'               => true,
				'active_installs'          => true,
				'last_updated'             => true,
				'added'                    => true,
				'homepage'                 => true,
				'tags'                     => true,
				'support_threads'          => true,
				'support_threads_resolved' => true,
				'installation'             => false,
				'faq'                      => false,
				'screenshots'              => false,
				'changelog'                => false,
				'reviews'                  => false,
				'sections'                 => false,
				'icons'                    => true,
			),
		);

		// Make API request.
		$response = $this->make_api_request( 'plugin_information', $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Process and sanitize response.
		$processed_response = $this->process_plugin_details_response( $response );

		// Cache the result (6 hours TTL).
		set_transient( $cache_key, $processed_response, 21600 );

		return $processed_response;
	}

	/**
	 * Make WordPress.org API request
	 *
	 * @param string $action      API action.
	 * @param array  $request_args Request arguments.
	 * @return array|WP_Error API response or error
	 */
	private function make_api_request( $action, $request_args ) {
		// WordPress.org API uses GET with query parameters, not POST.
		$query_args = array(
			'action'  => $action,
			'request' => wp_json_encode( $request_args ),
		);

		$api_url = add_query_arg( $query_args, self::API_BASE_URL );

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout'    => self::API_TIMEOUT,
				'user-agent' => self::USER_AGENT,
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] API Request Failed: ' . $response->get_error_message() );
			}
			return new WP_Error( 'api_error', __( 'Failed to connect to WordPress.org API', 'wppd-filters' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] API Request HTTP Error: ' . $response_code );
			}
			/* translators: %d: HTTP status code */
			return new WP_Error( 'api_http_error', sprintf( __( 'API request failed with status code: %d', 'wppd-filters' ), $response_code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] JSON Decode Error: ' . json_last_error_msg() );
			}
			return new WP_Error( 'json_decode_error', __( 'Invalid JSON response from WordPress.org API', 'wppd-filters' ) );
		}

		return $data;
	}

	/**
	 * Process search response
	 *
	 * @param array $response Raw API response.
	 * @return array Processed response
	 */
	private function process_search_response( $response ) {
		if ( ! isset( $response['plugins'] ) || ! is_array( $response['plugins'] ) ) {
			return array(
				'plugins' => array(),
				'info'    => array(
					'page'    => 1,
					'pages'   => 0,
					'results' => 0,
				),
			);
		}

		$processed_plugins = array();

		foreach ( $response['plugins'] as $plugin ) {
			$processed_plugins[] = $this->sanitize_plugin_data( $plugin );
		}

		return array(
			'plugins' => $processed_plugins,
			'info'    => array(
				'page'    => absint( $response['info']['page'] ?? 1 ),
				'pages'   => absint( $response['info']['pages'] ?? 0 ),
				'results' => absint( $response['info']['results'] ?? 0 ),
			),
		);
	}

	/**
	 * Process plugin details response
	 *
	 * @param array $response Raw API response.
	 * @return array Processed response
	 */
	private function process_plugin_details_response( $response ) {
		return $this->sanitize_plugin_data( $response );
	}

	/**
	 * Sanitize plugin data
	 *
	 * @param array $plugin Raw plugin data.
	 * @return array Sanitized plugin data
	 */
	private function sanitize_plugin_data( $plugin ) {
		return array(
			'slug'                     => sanitize_key( $plugin['slug'] ?? '' ),
			'name'                     => sanitize_text_field( $plugin['name'] ?? '' ),
			'version'                  => sanitize_text_field( $plugin['version'] ?? '' ),
			'author'                   => sanitize_text_field( wp_strip_all_tags( $plugin['author'] ?? '' ) ),
			'author_profile'           => esc_url_raw( $plugin['author_profile'] ?? '' ),
			'rating'                   => floatval( $plugin['rating'] ?? 0 ) / 20, // Convert 0-100 to 0-5 scale.
			'num_ratings'              => absint( $plugin['num_ratings'] ?? 0 ),
			'active_installs'          => absint( $plugin['active_installs'] ?? 0 ),
			'last_updated'             => sanitize_text_field( $plugin['last_updated'] ?? '' ),
			'added'                    => sanitize_text_field( $plugin['added'] ?? '' ),
			'tested'                   => sanitize_text_field( $plugin['tested'] ?? '' ),
			'requires'                 => sanitize_text_field( $plugin['requires'] ?? '' ),
			'short_description'        => wp_kses_post( $plugin['short_description'] ?? '' ),
			'description'              => wp_kses_post( $plugin['description'] ?? '' ),
			'homepage'                 => esc_url_raw( $plugin['homepage'] ?? '' ),
			'download_link'            => esc_url_raw( $plugin['download_link'] ?? '' ),
			'tags'                     => is_array( $plugin['tags'] ) ? array_map( 'sanitize_key', $plugin['tags'] ) : array(),
			'support_threads'          => absint( $plugin['support_threads'] ?? 0 ),
			'support_threads_resolved' => absint( $plugin['support_threads_resolved'] ?? 0 ),
			'downloaded'               => absint( $plugin['downloaded'] ?? 0 ),
			'ratings'                  => is_array( $plugin['ratings'] ) ? array_map( 'absint', $plugin['ratings'] ) : array(),
			'icons'                    => is_array( $plugin['icons'] ) ? array_map( 'esc_url_raw', $plugin['icons'] ) : array(),
		);
	}

	/**
	 * Check API rate limiting
	 *
	 * @return bool|WP_Error True if okay, WP_Error if rate limited
	 */
	private function check_rate_limit() {
		$user_id  = get_current_user_id();
		$rate_key = 'wp_plugin_api_rate_' . $user_id;

		$current_count = get_transient( $rate_key );
		if ( false === $current_count ) {
			$current_count = 0;
		}

		if ( $current_count >= self::RATE_LIMIT_PER_MINUTE ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'API rate limit exceeded. Please try again in a minute.', 'wppd-filters' ) );
		}

		// Increment counter.
		set_transient( $rate_key, $current_count + 1, 60 );

		return true;
	}

	/**
	 * Generate cache key for search
	 *
	 * @param string $search_term Search term.
	 * @param int    $page        Page number.
	 * @param int    $per_page    Results per page.
	 * @param array  $filters     Additional filters.
	 * @return string Cache key
	 */
	private function generate_search_cache_key( $search_term, $page, $per_page, $filters ) {
		$cache_data = array(
			'search'   => $search_term,
			'page'     => $page,
			'per_page' => $per_page,
			'filters'  => $filters,
		);

		return 'wp_plugin_search_' . md5( wp_json_encode( $cache_data ) );
	}

	/**
	 * Get popular plugins for cache warming
	 *
	 * @return array Popular plugin slugs
	 */
	public function get_popular_plugin_slugs() {
		return array(
			'woocommerce',
			'yoast-seo',
			'elementor',
			'contact-form-7',
			'wordfence',
			'jetpack',
			'akismet',
			'advanced-custom-fields',
			'wp-super-cache',
			'classic-editor',
			'wp-rocket',
			'mailchimp-for-wp',
			'updraftplus',
			'duplicate-post',
			'wpforms-lite',
		);
	}

	/**
	 * Clear API cache
	 *
	 * @param string $cache_type Type of cache to clear ('all', 'search', 'details').
	 * @return int Number of cache entries cleared
	 */
	public function clear_api_cache( $cache_type = 'all' ) {
		$deleted_count = 0;

		// Define known transient keys that we use.
		$transient_keys = array();

		switch ( $cache_type ) {
			case 'search':
				$transient_keys = $this->get_search_transient_keys();
				break;
			case 'details':
				$transient_keys = $this->get_details_transient_keys();
				break;
			case 'all':
			default:
				$transient_keys = array_merge(
					$this->get_search_transient_keys(),
					$this->get_details_transient_keys()
				);
				break;
		}

		// Delete transients using WordPress functions.
		foreach ( $transient_keys as $transient_key ) {
			if ( delete_transient( $transient_key ) ) {
				$deleted_count++;
			}
		}

		// Clear object cache for these keys.
		foreach ( $transient_keys as $transient_key ) {
			wp_cache_delete( $transient_key, 'wppd-filters' );
		}

		// Clear any remaining object cache.
		if ( wp_using_ext_object_cache() ) {
			wp_cache_flush_group( 'wppd-filters' );
		}

		return $deleted_count;
	}

	/**
	 * Get search transient keys
	 *
	 * @return array List of search transient keys
	 */
	private function get_search_transient_keys() {
		// Return commonly used search transient keys.
		// In practice, these would be tracked when created.
		return array(
			'wp_plugin_search_default',
			'wp_plugin_search_popular',
			'wp_plugin_search_featured',
		);
	}

	/**
	 * Get details transient keys
	 *
	 * @return array List of details transient keys
	 */
	private function get_details_transient_keys() {
		// Return commonly used details transient keys.
		// In practice, these would be tracked when created.
		return array(
			'wp_plugin_details_popular',
			'wp_plugin_details_metadata',
		);
	}
}
