<?php
/**
 * Plugin Health Score Calculator
 *
 * @package WP_Plugin_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin health score calculation class
 */
class WP_Plugin_Filters_Health_Calculator {

	/**
	 * Default algorithm weights.
	 */
	const DEFAULT_WEIGHTS = array(
		'update_frequency'  => 30,
		'wp_compatibility'  => 25,
		'support_response'  => 20,
		'time_since_update' => 15,
		'reported_issues'   => 10,
	);

	/**
	 * Algorithm weights configuration.
	 *
	 * @var array
	 */
	private $weights;

	/**
	 * Calculation breakdown for debugging.
	 *
	 * @var array
	 */
	private $breakdown = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings      = get_option( 'wp_plugin_filters_settings', array() );
		$this->weights = isset( $settings['health_weights'] ) ? $settings['health_weights'] : self::DEFAULT_WEIGHTS;

		// Validate weights sum to 100.
		$total_weight = array_sum( $this->weights );
		if ( abs( $total_weight - 100 ) > 1 ) {
			$this->weights = self::DEFAULT_WEIGHTS;
		}
	}

	/**
	 * Calculate health score for a plugin
	 *
	 * @param array $plugin_data Plugin metadata from WordPress.org API.
	 * @return int Health score from 0 to 100.
	 */
	public function calculate_health_score( $plugin_data ) {
		$this->breakdown = array();

		// Calculate individual components.
		$components = array(
			'update_frequency'  => $this->calculate_update_frequency_score( $plugin_data ),
			'wp_compatibility'  => $this->calculate_compatibility_score( $plugin_data ),
			'support_response'  => $this->calculate_support_response_score( $plugin_data ),
			'time_since_update' => $this->calculate_recency_score( $plugin_data ),
			'reported_issues'   => $this->calculate_issues_score( $plugin_data ),
		);

		// Calculate weighted score.
		$weighted_score = 0;
		$total_weight   = 0;

		foreach ( $components as $component => $score ) {
			if ( null !== $score ) {
				$weight          = $this->weights[ $component ] / 100;
				$weighted_score += $score * $weight;
				$total_weight   += $weight;
			}
		}

		// Normalize if some components are missing.
		$normalized_score = $total_weight > 0 ? ( $weighted_score / $total_weight ) * 100 : 0;

		// Ensure score is within 0-100 range.
		$final_score = max( 0, min( 100, $normalized_score ) );

		// Store calculation breakdown.
		$this->breakdown = array(
			'components'       => $components,
			'weights'          => $this->weights,
			'weighted_score'   => $weighted_score,
			'total_weight'     => $total_weight,
			'normalized_score' => $normalized_score,
			'final_score'      => $final_score,
		);

		return intval( round( $final_score ) );
	}

	/**
	 * Calculate update frequency score
	 *
	 * @param array $plugin_data Plugin metadata.
	 * @return float|null Component score (0.0-1.0) or null if no data.
	 */
	private function calculate_update_frequency_score( $plugin_data ) {
		// This is a simplified heuristic based on version number structure.
		// In a real implementation, this would analyze historical update patterns.

		if ( ! isset( $plugin_data['version'] ) || ! isset( $plugin_data['last_updated'] ) ) {
			return null;
		}

		$version      = $plugin_data['version'];
		$last_updated = $plugin_data['last_updated'];

		// Parse version for complexity (more segments = more active development).
		$version_parts            = explode( '.', $version );
		$version_complexity_score = 0;

		if ( count( $version_parts ) >= 3 ) {
			$version_complexity_score = 0.8; // Semantic versioning suggests active development.
		} elseif ( count( $version_parts ) === 2 ) {
			$version_complexity_score = 0.6; // Some versioning structure.
		} else {
			$version_complexity_score = 0.4; // Simple versioning.
		}

		// Check if version suggests active development (patch versions).
		$patch_version = isset( $version_parts[2] ) ? intval( $version_parts[2] ) : 0;
		if ( $patch_version > 5 ) {
			$version_complexity_score += 0.1; // Bonus for many patches.
		}

		// Factor in recency (plugins updated recently are likely more active).
		$days_since_update = $this->get_days_since_update( $last_updated );
		$recency_factor    = 1.0;

		if ( $days_since_update <= 30 ) {
			$recency_factor = 1.0;   // Recently updated.
		} elseif ( $days_since_update <= 90 ) {
			$recency_factor = 0.9;   // Updated within 3 months.
		} elseif ( $days_since_update <= 180 ) {
			$recency_factor = 0.7;   // Updated within 6 months.
		} else {
			$recency_factor = 0.5;   // Not recently updated.
		}

		return min( 1.0, $version_complexity_score * $recency_factor );
	}

	/**
	 * Calculate WordPress compatibility score
	 *
	 * @param array $plugin_data Plugin metadata.
	 * @return float|null Component score (0.0-1.0) or null if no data.
	 */
	private function calculate_compatibility_score( $plugin_data ) {
		if ( ! isset( $plugin_data['tested'] ) ) {
			return null;
		}

		$tested_version     = $plugin_data['tested'];
		$current_wp_version = get_bloginfo( 'version' );

		// Parse versions for comparison.
		$tested_parts  = explode( '.', $tested_version );
		$current_parts = explode( '.', $current_wp_version );

		$tested_major  = isset( $tested_parts[0] ) ? intval( $tested_parts[0] ) : 0;
		$tested_minor  = isset( $tested_parts[1] ) ? intval( $tested_parts[1] ) : 0;
		$current_major = isset( $current_parts[0] ) ? intval( $current_parts[0] ) : 0;
		$current_minor = isset( $current_parts[1] ) ? intval( $current_parts[1] ) : 0;

		// Score based on version compatibility.
		if ( $tested_major > $current_major ) {
			return 1.0; // Future compatibility.
		} elseif ( $tested_major === $current_major ) {
			if ( $tested_minor >= $current_minor ) {
				return 1.0; // Current or newer minor version.
			} elseif ( $tested_minor === $current_minor - 1 ) {
				return 0.9; // One minor version behind.
			} elseif ( $tested_minor === $current_minor - 2 ) {
				return 0.8; // Two minor versions behind.
			} else {
				return 0.6; // More than two minor versions behind.
			}
		} else {
			return 0.4; // Major version behind.
		}
	}

	/**
	 * Calculate support response score
	 *
	 * @param array $plugin_data Plugin metadata.
	 * @return float|null Component score (0.0-1.0) or null if no data.
	 */
	private function calculate_support_response_score( $plugin_data ) {
		if ( ! isset( $plugin_data['support_threads'] ) || ! isset( $plugin_data['support_threads_resolved'] ) ) {
			return null;
		}

		$total_threads    = intval( $plugin_data['support_threads'] );
		$resolved_threads = intval( $plugin_data['support_threads_resolved'] );

		// If no support threads, give neutral score.
		if ( 0 === $total_threads ) {
			return 0.6; // Neutral score for no support activity.
		}

		// Calculate resolution rate.
		$resolution_rate = floatval( $resolved_threads ) / floatval( $total_threads );

		// Adjust score based on both resolution rate and activity level.
		$base_score = $resolution_rate;

		// Bonus for having some support activity (shows developer engagement).
		if ( $total_threads >= 10 ) {
			$base_score += 0.1; // Bonus for decent support activity.
		}

		// Penalty for too many unresolved issues (potential red flag).
		$unresolved_threads = $total_threads - $resolved_threads;
		if ( $unresolved_threads > 20 ) {
			$base_score -= 0.1; // Penalty for many unresolved issues.
		}

		return max( 0.0, min( 1.0, $base_score ) );
	}

	/**
	 * Calculate recency score (time since last update)
	 *
	 * @param array $plugin_data Plugin metadata.
	 * @return float|null Component score (0.0-1.0) or null if no data.
	 */
	private function calculate_recency_score( $plugin_data ) {
		if ( ! isset( $plugin_data['last_updated'] ) ) {
			return null;
		}

		$days_since_update = $this->get_days_since_update( $plugin_data['last_updated'] );

		// Score based on recency (more recent = better maintenance).
		if ( $days_since_update <= 30 ) {
			return 1.0;      // Updated within a month (excellent).
		} elseif ( $days_since_update <= 90 ) {
			return 0.9;      // Updated within 3 months (very good).
		} elseif ( $days_since_update <= 180 ) {
			return 0.8;      // Updated within 6 months (good).
		} elseif ( $days_since_update <= 365 ) {
			return 0.6;      // Updated within a year (fair).
		} elseif ( $days_since_update <= 730 ) {
			return 0.4;      // Updated within 2 years (poor).
		} else {
			return 0.2;      // Not updated in over 2 years (very poor).
		}
	}

	/**
	 * Calculate reported issues score (based on rating distribution)
	 *
	 * @param array $plugin_data Plugin metadata.
	 * @return float|null Component score (0.0-1.0) or null if no data.
	 */
	private function calculate_issues_score( $plugin_data ) {
		// Use rating distribution as a proxy for reported issues.
		if ( ! isset( $plugin_data['ratings'] ) || ! is_array( $plugin_data['ratings'] ) ) {
			return 0.6; // Neutral score if no rating data.
		}

		$ratings       = $plugin_data['ratings'];
		$total_ratings = array_sum( $ratings );

		if ( 0 === $total_ratings ) {
			return 0.6; // Neutral score for no ratings.
		}

		// Calculate percentage of low ratings (1-2 stars) as issue indicator.
		$low_ratings           = ( isset( $ratings[1] ) ? $ratings[1] : 0 ) + ( isset( $ratings[2] ) ? $ratings[2] : 0 );
		$low_rating_percentage = $low_ratings / $total_ratings;

		// Convert low rating percentage to health score.
		// Less low ratings = better health score.
		$issues_score = 1.0 - ( $low_rating_percentage * 1.5 ); // Amplify the impact slightly.

		// Consider absolute number of low ratings.
		if ( $low_ratings > 50 ) {
			$issues_score -= 0.1; // Penalty for many low ratings.
		}

		return max( 0.0, min( 1.0, $issues_score ) );
	}

	/**
	 * Calculate days since last update
	 *
	 * @param string $last_updated Last updated timestamp.
	 * @return int Days since update.
	 */
	private function get_days_since_update( $last_updated ) {
		$update_timestamp = strtotime( $last_updated );
		if ( false === $update_timestamp ) {
			return 9999; // Return large number for invalid dates.
		}

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Using timestamp for date calculation is acceptable in this context.
		$now       = current_time( 'timestamp' );
		$days_diff = ( $now - $update_timestamp ) / DAY_IN_SECONDS;

		return max( 0, intval( $days_diff ) );
	}

	/**
	 * Get health score color coding
	 *
	 * @param int $score Health score (0-100).
	 * @return string Color code.
	 */
	public function get_health_color( $score ) {
		if ( $score >= 86 ) {
			return 'green';    // Excellent health.
		} elseif ( $score >= 71 ) {
			return 'light-green'; // Good health.
		} elseif ( $score >= 41 ) {
			return 'orange';   // Fair health.
		} else {
			return 'red';      // Poor health.
		}
	}

	/**
	 * Get health score description
	 *
	 * @param int $score Health score (0-100).
	 * @return string Description.
	 */
	public function get_health_description( $score ) {
		if ( $score >= 86 ) {
			return __( 'Excellent - Well maintained and actively supported', 'wppd-filters' );
		} elseif ( $score >= 71 ) {
			return __( 'Good - Regularly maintained with good support', 'wppd-filters' );
		} elseif ( $score >= 41 ) {
			return __( 'Fair - Occasionally maintained, some concerns', 'wppd-filters' );
		} else {
			return __( 'Poor - Infrequently maintained, potential issues', 'wppd-filters' );
		}
	}

	/**
	 * Get detailed calculation breakdown
	 *
	 * @return array Calculation breakdown.
	 */
	public function get_calculation_breakdown() {
		return $this->breakdown;
	}

	/**
	 * Update algorithm weights
	 *
	 * @param array $weights New weight configuration.
	 * @return bool|WP_Error Success status or error.
	 */
	public function update_weights( $weights ) {
		// Validate weights.
		if ( ! is_array( $weights ) ) {
			return new WP_Error( 'invalid_weights', __( 'Weights must be an array', 'wppd-filters' ) );
		}

		// Check required components.
		$required_components = array( 'update_frequency', 'wp_compatibility', 'support_response', 'time_since_update', 'reported_issues' );
		foreach ( $required_components as $component ) {
			if ( ! isset( $weights[ $component ] ) || ! is_numeric( $weights[ $component ] ) ) {
				/* translators: %s: component name */
				return new WP_Error( 'missing_component', sprintf( __( 'Missing or invalid weight for %s', 'wppd-filters' ), $component ) );
			}
		}

		// Validate weights sum to 100 (allow 1% tolerance).
		$total_weight = array_sum( $weights );
		if ( abs( $total_weight - 100 ) > 1 ) {
			return new WP_Error( 'invalid_total', __( 'Algorithm weights must sum to 100%', 'wppd-filters' ) );
		}

		// Validate individual weights (0-100).
		foreach ( $weights as $component => $weight ) {
			if ( $weight < 0 || $weight > 100 ) {
				/* translators: %s: component name */
				return new WP_Error( 'invalid_range', sprintf( __( 'Weight for %s must be between 0 and 100', 'wppd-filters' ), $component ) );
			}
		}

		// Update instance weights.
		$this->weights = array_map( 'intval', $weights );

		// Update stored settings.
		$settings                   = get_option( 'wp_plugin_filters_settings', array() );
		$settings['health_weights'] = $this->weights;
		update_option( 'wp_plugin_filters_settings', $settings );

		return true;
	}

	/**
	 * Get current algorithm weights
	 *
	 * @return array Current weights.
	 */
	public function get_weights() {
		return $this->weights;
	}

	/**
	 * Get algorithm explanation for users
	 *
	 * @return array Algorithm explanation.
	 */
	public function get_algorithm_explanation() {
		return array(
			'title'        => __( 'Health Score Algorithm', 'wppd-filters' ),
			'description'  => __( 'The health score evaluates plugin maintenance quality and reliability indicators.', 'wppd-filters' ),
			'components'   => array(
				'update_frequency'  => array(
					'label'       => __( 'Update Frequency', 'wppd-filters' ),
					'description' => __( 'How regularly the plugin is updated and maintained', 'wppd-filters' ),
					'weight'      => $this->weights['update_frequency'],
				),
				'wp_compatibility'  => array(
					'label'       => __( 'WordPress Compatibility', 'wppd-filters' ),
					'description' => __( 'Compatibility with current WordPress versions', 'wppd-filters' ),
					'weight'      => $this->weights['wp_compatibility'],
				),
				'support_response'  => array(
					'label'       => __( 'Support Response', 'wppd-filters' ),
					'description' => __( 'Developer responsiveness to support requests', 'wppd-filters' ),
					'weight'      => $this->weights['support_response'],
				),
				'time_since_update' => array(
					'label'       => __( 'Update Recency', 'wppd-filters' ),
					'description' => __( 'How recently the plugin was last updated', 'wppd-filters' ),
					'weight'      => $this->weights['time_since_update'],
				),
				'reported_issues'   => array(
					'label'       => __( 'Issue Indicators', 'wppd-filters' ),
					'description' => __( 'Indicators of potential issues from user feedback', 'wppd-filters' ),
					'weight'      => $this->weights['reported_issues'],
				),
			),
			'scale'        => __( 'Final score ranges from 0 to 100 (higher is better)', 'wppd-filters' ),
			'color_coding' => array(
				'green'       => __( 'Excellent (86-100)', 'wppd-filters' ),
				'light-green' => __( 'Good (71-85)', 'wppd-filters' ),
				'orange'      => __( 'Fair (41-70)', 'wppd-filters' ),
				'red'         => __( 'Poor (0-40)', 'wppd-filters' ),
			),
		);
	}

	/**
	 * Calculate health score for multiple plugins efficiently
	 *
	 * @param array $plugins_data Array of plugin data.
	 * @return array Array of health scores keyed by plugin slug.
	 */
	public function calculate_batch_health_scores( $plugins_data ) {
		$scores = array();

		foreach ( $plugins_data as $plugin_data ) {
			if ( isset( $plugin_data['slug'] ) ) {
				$scores[ $plugin_data['slug'] ] = $this->calculate_health_score( $plugin_data );
			}
		}

		return $scores;
	}
}
