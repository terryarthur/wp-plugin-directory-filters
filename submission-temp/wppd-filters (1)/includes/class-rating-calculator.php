<?php
/**
 * Plugin Usability Rating Calculator
 *
 * @package WP_Plugin_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Usability rating calculation class
 */
class WP_Plugin_Filters_Rating_Calculator {

	/**
	 * Default algorithm weights
	 */
	const DEFAULT_WEIGHTS = array(
		'user_rating'            => 40,
		'rating_count'           => 20,
		'installation_count'     => 25,
		'support_responsiveness' => 15,
	);

	/**
	 * Algorithm weights configuration
	 *
	 * @var array
	 */
	private $weights;

	/**
	 * Calculation breakdown for debugging
	 *
	 * @var array
	 */
	private $breakdown = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$settings      = get_option( 'wp_plugin_filters_settings', array() );
		$this->weights = isset( $settings['usability_weights'] ) ? $settings['usability_weights'] : self::DEFAULT_WEIGHTS;

		// Validate weights sum to 100
		$total_weight = array_sum( $this->weights );
		if ( abs( $total_weight - 100 ) > 1 ) {
			$this->weights = self::DEFAULT_WEIGHTS;
		}
	}

	/**
	 * Calculate usability rating for a plugin
	 *
	 * @param array $plugin_data Plugin metadata from WordPress.org API
	 * @return float Usability rating from 1.0 to 5.0
	 */
	public function calculate_usability_rating( $plugin_data ) {
		$this->breakdown = array();

		// Calculate individual components
		$components = array(
			'user_rating'            => $this->calculate_user_rating_component( $plugin_data ),
			'rating_count'           => $this->calculate_rating_count_component( $plugin_data ),
			'installation_count'     => $this->calculate_installation_component( $plugin_data ),
			'support_responsiveness' => $this->calculate_support_component( $plugin_data ),
		);

		// Calculate weighted score
		$weighted_score = 0;
		$total_weight   = 0;

		foreach ( $components as $component => $score ) {
			if ( $score !== null ) {
				$weight          = $this->weights[ $component ] / 100;
				$weighted_score += $score * $weight;
				$total_weight   += $weight;
			}
		}

		// Normalize if some components are missing
		$normalized_score = $total_weight > 0 ? $weighted_score / $total_weight : 0;

		// Scale to 1-5 range and ensure minimum of 1.0
		$final_score = max( 1.0, min( 5.0, $normalized_score * 5 ) );

		// Store calculation breakdown
		$this->breakdown = array(
			'components'       => $components,
			'weights'          => $this->weights,
			'weighted_score'   => $weighted_score,
			'total_weight'     => $total_weight,
			'normalized_score' => $normalized_score,
			'final_score'      => $final_score,
		);

		return round( $final_score, 1 );
	}

	/**
	 * Calculate user rating component (existing WordPress.org ratings)
	 *
	 * @param array $plugin_data Plugin metadata
	 * @return float|null Component score (0.0-1.0) or null if no data
	 */
	private function calculate_user_rating_component( $plugin_data ) {
		if ( ! isset( $plugin_data['rating'] ) || $plugin_data['rating'] <= 0 ) {
			return null;
		}

		// Rating is already on 0-5 scale, normalize to 0-1
		return floatval( $plugin_data['rating'] ) / 5.0;
	}

	/**
	 * Calculate rating count component (credibility based on number of ratings)
	 *
	 * @param array $plugin_data Plugin metadata
	 * @return float|null Component score (0.0-1.0) or null if no data
	 */
	private function calculate_rating_count_component( $plugin_data ) {
		if ( ! isset( $plugin_data['num_ratings'] ) || $plugin_data['num_ratings'] <= 0 ) {
			return null;
		}

		$rating_count = intval( $plugin_data['num_ratings'] );

		// Logarithmic scale for rating count significance
		// More ratings = higher credibility, but with diminishing returns
		if ( $rating_count >= 1000 ) {
			return 1.0;    // Excellent credibility
		}
		if ( $rating_count >= 500 ) {
			return 0.9;     // Very good credibility
		}
		if ( $rating_count >= 100 ) {
			return 0.8;     // Good credibility
		}
		if ( $rating_count >= 50 ) {
			return 0.7;      // Fair credibility
		}
		if ( $rating_count >= 20 ) {
			return 0.6;      // Some credibility
		}
		if ( $rating_count >= 10 ) {
			return 0.5;      // Limited credibility
		}
		if ( $rating_count >= 5 ) {
			return 0.4;       // Low credibility
		}

		return 0.3; // Very low credibility
	}

	/**
	 * Calculate installation count component (popularity indicator)
	 *
	 * @param array $plugin_data Plugin metadata
	 * @return float|null Component score (0.0-1.0) or null if no data
	 */
	private function calculate_installation_component( $plugin_data ) {
		if ( ! isset( $plugin_data['active_installs'] ) || $plugin_data['active_installs'] <= 0 ) {
			return null;
		}

		$installs = intval( $plugin_data['active_installs'] );

		// Logarithmic scale for installation count
		// More installs generally indicate better usability
		if ( $installs >= 5000000 ) {
			return 1.0;     // Extremely popular (5M+)
		}
		if ( $installs >= 1000000 ) {
			return 0.95;    // Very popular (1M-5M)
		}
		if ( $installs >= 500000 ) {
			return 0.9;      // Popular (500K-1M)
		}
		if ( $installs >= 100000 ) {
			return 0.8;      // Well-used (100K-500K)
		}
		if ( $installs >= 50000 ) {
			return 0.7;       // Moderately used (50K-100K)
		}
		if ( $installs >= 10000 ) {
			return 0.6;       // Some usage (10K-50K)
		}
		if ( $installs >= 5000 ) {
			return 0.5;        // Limited usage (5K-10K)
		}
		if ( $installs >= 1000 ) {
			return 0.4;        // Low usage (1K-5K)
		}
		if ( $installs >= 100 ) {
			return 0.3;         // Very low usage (100-1K)
		}

		return 0.2; // Minimal usage (<100)
	}

	/**
	 * Calculate support responsiveness component
	 *
	 * @param array $plugin_data Plugin metadata
	 * @return float|null Component score (0.0-1.0) or null if no data
	 */
	private function calculate_support_component( $plugin_data ) {
		if ( ! isset( $plugin_data['support_threads'] ) || ! isset( $plugin_data['support_threads_resolved'] ) ) {
			return null;
		}

		$total_threads    = intval( $plugin_data['support_threads'] );
		$resolved_threads = intval( $plugin_data['support_threads_resolved'] );

		// If no support threads, give neutral score
		if ( $total_threads === 0 ) {
			return 0.5;
		}

		// Calculate resolution rate
		$resolution_rate = floatval( $resolved_threads ) / floatval( $total_threads );

		// Adjust score based on resolution rate and activity level
		if ( $resolution_rate >= 0.9 ) {
			return 1.0;    // Excellent support (90%+)
		}
		if ( $resolution_rate >= 0.8 ) {
			return 0.9;    // Very good support (80-89%)
		}
		if ( $resolution_rate >= 0.7 ) {
			return 0.8;    // Good support (70-79%)
		}
		if ( $resolution_rate >= 0.6 ) {
			return 0.7;    // Fair support (60-69%)
		}
		if ( $resolution_rate >= 0.5 ) {
			return 0.6;    // Poor support (50-59%)
		}
		if ( $resolution_rate >= 0.4 ) {
			return 0.5;    // Bad support (40-49%)
		}
		if ( $resolution_rate >= 0.3 ) {
			return 0.4;    // Very bad support (30-39%)
		}

		return 0.3; // Terrible support (<30%)
	}

	/**
	 * Get detailed calculation breakdown
	 *
	 * @return array Calculation breakdown
	 */
	public function get_calculation_breakdown() {
		return $this->breakdown;
	}

	/**
	 * Update algorithm weights
	 *
	 * @param array $weights New weight configuration
	 * @return bool|WP_Error Success status or error
	 */
	public function update_weights( $weights ) {
		// Validate weights
		if ( ! is_array( $weights ) ) {
			return new WP_Error( 'invalid_weights', __( 'Weights must be an array', 'wppd-filters' ) );
		}

		// Check required components
		$required_components = array( 'user_rating', 'rating_count', 'installation_count', 'support_responsiveness' );
		foreach ( $required_components as $component ) {
			if ( ! isset( $weights[ $component ] ) || ! is_numeric( $weights[ $component ] ) ) {
				/* translators: %s: component name */
				return new WP_Error( 'missing_component', sprintf( __( 'Missing or invalid weight for %s', 'wppd-filters' ), $component ) );
			}
		}

		// Validate weights sum to 100 (allow 1% tolerance)
		$total_weight = array_sum( $weights );
		if ( abs( $total_weight - 100 ) > 1 ) {
			return new WP_Error( 'invalid_total', __( 'Algorithm weights must sum to 100%', 'wppd-filters' ) );
		}

		// Validate individual weights (0-100)
		foreach ( $weights as $component => $weight ) {
			if ( $weight < 0 || $weight > 100 ) {
				/* translators: %s: component name */
				return new WP_Error( 'invalid_range', sprintf( __( 'Weight for %s must be between 0 and 100', 'wppd-filters' ), $component ) );
			}
		}

		// Update instance weights
		$this->weights = array_map( 'intval', $weights );

		// Update stored settings
		$settings                      = get_option( 'wp_plugin_filters_settings', array() );
		$settings['usability_weights'] = $this->weights;
		update_option( 'wp_plugin_filters_settings', $settings );

		return true;
	}

	/**
	 * Get current algorithm weights
	 *
	 * @return array Current weights
	 */
	public function get_weights() {
		return $this->weights;
	}

	/**
	 * Reset weights to default values
	 *
	 * @return bool Success status
	 */
	public function reset_weights_to_default() {
		$this->weights = self::DEFAULT_WEIGHTS;

		$settings                      = get_option( 'wp_plugin_filters_settings', array() );
		$settings['usability_weights'] = $this->weights;
		update_option( 'wp_plugin_filters_settings', $settings );

		return true;
	}

	/**
	 * Get algorithm explanation for users
	 *
	 * @return array Algorithm explanation
	 */
	public function get_algorithm_explanation() {
		return array(
			'title'       => __( 'Usability Rating Algorithm', 'wppd-filters' ),
			'description' => __( 'The usability rating combines multiple factors to provide an overall assessment of plugin usability and quality.', 'wppd-filters' ),
			'components'  => array(
				'user_rating'            => array(
					'label'       => __( 'User Rating', 'wppd-filters' ),
					'description' => __( 'Average rating given by users on WordPress.org', 'wppd-filters' ),
					'weight'      => $this->weights['user_rating'],
				),
				'rating_count'           => array(
					'label'       => __( 'Rating Credibility', 'wppd-filters' ),
					'description' => __( 'Number of ratings (more ratings = higher credibility)', 'wppd-filters' ),
					'weight'      => $this->weights['rating_count'],
				),
				'installation_count'     => array(
					'label'       => __( 'Popularity', 'wppd-filters' ),
					'description' => __( 'Number of active installations (popularity indicator)', 'wppd-filters' ),
					'weight'      => $this->weights['installation_count'],
				),
				'support_responsiveness' => array(
					'label'       => __( 'Support Quality', 'wppd-filters' ),
					'description' => __( 'Percentage of resolved support threads', 'wppd-filters' ),
					'weight'      => $this->weights['support_responsiveness'],
				),
			),
			'scale'       => __( 'Final rating is scaled from 1.0 to 5.0 stars', 'wppd-filters' ),
		);
	}

	/**
	 * Calculate rating for multiple plugins efficiently
	 *
	 * @param array $plugins_data Array of plugin data
	 * @return array Array of ratings keyed by plugin slug
	 */
	public function calculate_batch_ratings( $plugins_data ) {
		$ratings = array();

		foreach ( $plugins_data as $plugin_data ) {
			if ( isset( $plugin_data['slug'] ) ) {
				$ratings[ $plugin_data['slug'] ] = $this->calculate_usability_rating( $plugin_data );
			}
		}

		return $ratings;
	}
}
