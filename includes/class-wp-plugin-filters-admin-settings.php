<?php
/**
 * Admin Settings for WordPress Plugin Directory Filters
 *
 * @package WP_Plugin_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress admin settings page and configuration management
 */
class WP_Plugin_Filters_Admin_Settings {

	/**
	 * Settings page slug
	 */
	const SETTINGS_PAGE_SLUG = 'wppd-filters';

	/**
	 * Settings group
	 */
	const SETTINGS_GROUP = 'wp_plugin_filters_settings_group';

	/**
	 * Settings option name
	 */
	const SETTINGS_OPTION = 'wp_plugin_filters_settings';

	/**
	 * Initialize admin settings
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add settings link to plugin page.
		add_filter( 'plugin_action_links_' . WP_PLUGIN_FILTERS_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Register WordPress settings
	 */
	public function register_settings() {
		// Register main settings.
		register_setting(
			self::SETTINGS_GROUP,
			self::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);

		// Add settings sections.

		add_settings_section(
			'cache_section',
			__( 'Cache Settings', 'wppd-filters' ),
			array( $this, 'render_cache_section_description' ),
			self::SETTINGS_PAGE_SLUG
		);

		// Add cache settings fields.
		$cache_fields = array(
			'plugin_metadata'    => __( 'Plugin Metadata Cache Duration (seconds)', 'wppd-filters' ),
			'calculated_ratings' => __( 'Calculated Ratings Cache Duration (seconds)', 'wppd-filters' ),
			'search_results'     => __( 'Search Results Cache Duration (seconds)', 'wppd-filters' ),
			'api_responses'      => __( 'API Responses Cache Duration (seconds)', 'wppd-filters' ),
		);

		foreach ( $cache_fields as $field => $label ) {
			add_settings_field(
				"cache_duration_{$field}",
				$label,
				array( $this, 'render_cache_duration_field' ),
				self::SETTINGS_PAGE_SLUG,
				'cache_section',
				array(
					'field' => $field,
					'label' => $label,
				)
			);
		}

		// Add cache management field.
		add_settings_field(
			'cache_management',
			__( 'Cache Management', 'wppd-filters' ),
			array( $this, 'render_cache_management_field' ),
			self::SETTINGS_PAGE_SLUG,
			'cache_section'
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook_suffix The admin page hook suffix.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'settings_page_' . self::SETTINGS_PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'wp-plugin-directory-filters-admin-settings',
			WP_PLUGIN_FILTERS_PLUGIN_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			WP_PLUGIN_FILTERS_VERSION,
			true
		);

		wp_localize_script(
			'wp-plugin-directory-filters-admin-settings',
			'wpPluginFiltersAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonces'  => array(
					'clear_cache'    => wp_create_nonce( 'wp_plugin_clear_cache' ),
					'test_algorithm' => wp_create_nonce( 'wp_plugin_test_algorithm' ),
				),
				'strings' => array(
					'cacheCleared'          => __( 'Cache cleared successfully', 'wppd-filters' ),
					'cacheClearError'       => __( 'Error clearing cache', 'wppd-filters' ),
					'weightValidationError' => __( 'Algorithm weights must sum to 100%', 'wppd-filters' ),
					'testingAlgorithm'      => __( 'Testing algorithm...', 'wppd-filters' ),
					'algorithmTested'       => __( 'Algorithm test completed', 'wppd-filters' ),
				),
			)
		);

		wp_enqueue_style(
			'wp-plugin-directory-filters-admin-settings',
			WP_PLUGIN_FILTERS_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			WP_PLUGIN_FILTERS_VERSION
		);
	}

	/**
	 * Add settings link to plugin actions
	 *
	 * @param array $links The existing plugin action links.
	 * @return array Modified links array.
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE_SLUG ),
			__( 'Settings', 'wppd-filters' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Render main settings page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wppd-filters' ) );
		}

		// Handle form submission.
		if ( isset( $_POST['submit'] ) && check_admin_referer( self::SETTINGS_GROUP . '-options' ) ) {
			$this->handle_settings_update();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Plugin Directory Filters Settings', 'wppd-filters' ); ?></h1>

			<?php settings_errors(); ?>

			<form method="post" action="">
				<?php
				wp_nonce_field( self::SETTINGS_GROUP . '-options' );
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::SETTINGS_PAGE_SLUG );
				submit_button();
				?>
			</form>

			<div class="wp-plugin-directory-filters-admin-info">
				<h2><?php esc_html_e( 'Algorithm Information', 'wppd-filters' ); ?></h2>

				<div class="algorithm-explanations">
					<p><?php esc_html_e( 'The plugin directory filters provide enhanced usability ratings and health scores to help you make better plugin choices. These metrics are calculated automatically based on WordPress.org data and are not user-configurable.', 'wppd-filters' ); ?></p>

					<div class="algorithm-explanation">
						<h3><?php esc_html_e( 'Usability Rating', 'wppd-filters' ); ?></h3>
						<p><?php esc_html_e( 'Combines user ratings, rating count, installation count, and support responsiveness to provide an overall assessment of plugin usability and quality.', 'wppd-filters' ); ?></p>
					</div>

					<div class="algorithm-explanation">
						<h3><?php esc_html_e( 'Health Score', 'wppd-filters' ); ?></h3>
						<p><?php esc_html_e( 'Evaluates plugin maintenance quality based on update frequency, WordPress compatibility, support response, and other reliability indicators.', 'wppd-filters' ); ?></p>

						<h4><?php esc_html_e( 'Color Coding:', 'wppd-filters' ); ?></h4>
						<ul>
							<li><span class="health-badge health-badge-green">86-100</span> <?php esc_html_e( 'Excellent', 'wppd-filters' ); ?></li>
							<li><span class="health-badge health-badge-light-green">71-85</span> <?php esc_html_e( 'Good', 'wppd-filters' ); ?></li>
							<li><span class="health-badge health-badge-orange">41-70</span> <?php esc_html_e( 'Fair', 'wppd-filters' ); ?></li>
							<li><span class="health-badge health-badge-red">0-40</span> <?php esc_html_e( 'Poor', 'wppd-filters' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render network admin settings page
	 */
	public function render_network_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wppd-filters' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Plugin Directory Filters Network Settings', 'wppd-filters' ); ?></h1>
			<p><?php esc_html_e( 'Configure plugin directory filter settings for the entire network.', 'wppd-filters' ); ?></p>

			<form method="post" action="">
				<?php
				wp_nonce_field( 'wp_plugin_filters_network_settings' );

				// Network-specific settings would go here.
				$network_settings = get_site_option( 'wp_plugin_filters_network_settings', array() );
				?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Override Site Settings', 'wppd-filters' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="override_site_settings" value="1" <?php checked( ! empty( $network_settings['override_site_settings'] ) ); ?> />
								<?php esc_html_e( 'Force network-wide settings (override individual site settings)', 'wppd-filters' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable for Sites', 'wppd-filters' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_for_sites" value="1" <?php checked( ! empty( $network_settings['enable_for_sites'] ), true ); ?> />
								<?php esc_html_e( 'Enable plugin directory filters for all sites in the network', 'wppd-filters' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Update Network Settings', 'wppd-filters' ) ); ?>
			</form>
		</div>
		<?php
	}


	/**
	 * Render cache section description
	 */
	public function render_cache_section_description() {
		echo '<p>' . esc_html__( 'Configure cache durations and manage cached data.', 'wppd-filters' ) . '</p>';

		// Display cache statistics.
		$cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
		$stats         = $cache_manager->get_cache_statistics();

		if ( $stats['total_transients'] > 0 ) {
			echo '<div class="cache-statistics">';
			echo '<h4>' . esc_html__( 'Current Cache Statistics', 'wppd-filters' ) . '</h4>';
			echo '<p>' . sprintf(
				/* translators: %1$d: number of cached items, %2$s: human-readable size */
				esc_html__( 'Total cached items: %1$d | Total size: %2$s', 'wppd-filters' ),
				intval( $stats['total_transients'] ),
				esc_html( $stats['total_size_human'] )
			) . '</p>';
			echo '</div>';
		}
	}


	/**
	 * Render cache duration field
	 *
	 * @param array $args Field arguments containing field name and label.
	 */
	public function render_cache_duration_field( $args ) {
		$settings = get_option( self::SETTINGS_OPTION, $this->get_default_settings() );
		$field    = $args['field'];
		$value    = $settings['cache_durations'][ $field ] ?? 3600;

		printf(
			'<input type="number" id="cache_duration_%s" name="%s[cache_durations][%s]" value="%d" min="60" max="604800" class="regular-text" />',
			esc_attr( $field ),
			esc_attr( self::SETTINGS_OPTION ),
			esc_attr( $field ),
			intval( $value )
		);

		// Add helpful description.
		$descriptions = array(
			'plugin_metadata'    => __( 'How long to cache plugin information from WordPress.org (recommended: 86400 = 24 hours)', 'wppd-filters' ),
			'calculated_ratings' => __( 'How long to cache calculated usability ratings and health scores (recommended: 21600 = 6 hours)', 'wppd-filters' ),
			'search_results'     => __( 'How long to cache search results (recommended: 3600 = 1 hour)', 'wppd-filters' ),
			'api_responses'      => __( 'How long to cache raw API responses (recommended: 1800 = 30 minutes)', 'wppd-filters' ),
		);

		if ( isset( $descriptions[ $field ] ) ) {
			echo '<p class="description">' . esc_html( $descriptions[ $field ] ) . '</p>';
		}
	}

	/**
	 * Render cache management field
	 */
	public function render_cache_management_field() {
		echo '<div class="cache-management-controls">';
		echo '<button type="button" id="clear-all-cache" class="button button-secondary">' . esc_html__( 'Clear All Cache', 'wppd-filters' ) . '</button> ';
		echo '<button type="button" id="clear-search-cache" class="button button-secondary">' . esc_html__( 'Clear Search Cache', 'wppd-filters' ) . '</button> ';
		echo '<button type="button" id="clear-ratings-cache" class="button button-secondary">' . esc_html__( 'Clear Ratings Cache', 'wppd-filters' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Clear cached data to force fresh API requests and recalculations.', 'wppd-filters' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Handle settings update
	 */
	private function handle_settings_update() {
		// Note: Nonce verification is already done in render_admin_page() before calling this method.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in caller
		$raw_settings = isset( $_POST[ self::SETTINGS_OPTION ] ) ? map_deep( wp_unslash( $_POST[ self::SETTINGS_OPTION ] ), 'sanitize_text_field' ) : array();
		$settings     = $this->sanitize_settings( $raw_settings );

		if ( update_option( self::SETTINGS_OPTION, $settings ) ) {
			add_settings_error(
				self::SETTINGS_OPTION,
				'settings_updated',
				__( 'Settings saved successfully.', 'wppd-filters' ),
				'updated'
			);
		} else {
			add_settings_error(
				self::SETTINGS_OPTION,
				'settings_error',
				__( 'Error saving settings.', 'wppd-filters' ),
				'error'
			);
		}
	}

	/**
	 * Sanitize settings input
	 *
	 * @param array $input Raw input data to sanitize.
	 * @return array Sanitized settings array.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = $this->get_default_settings();

		// Sanitize cache durations.
		if ( isset( $input['cache_durations'] ) && is_array( $input['cache_durations'] ) ) {
			foreach ( $sanitized['cache_durations'] as $key => $default_value ) {
				if ( isset( $input['cache_durations'][ $key ] ) ) {
					$sanitized['cache_durations'][ $key ] = max( 60, min( 604800, intval( $input['cache_durations'][ $key ] ) ) );
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Get default settings
	 */
	private function get_default_settings() {
		return array(
			'cache_durations' => array(
				'plugin_metadata'    => 86400,    // 24 hours
				'calculated_ratings' => 21600, // 6 hours
				'search_results'     => 3600,      // 1 hour
				'api_responses'      => 1800,        // 30 minutes
			),
		);
	}

	/**
	 * Get current settings
	 */
	public function get_settings() {
		return get_option( self::SETTINGS_OPTION, $this->get_default_settings() );
	}

	/**
	 * Reset settings to defaults
	 */
	public function reset_to_defaults() {
		return update_option( self::SETTINGS_OPTION, $this->get_default_settings() );
	}
}
