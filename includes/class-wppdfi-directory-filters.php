<?php
/**
 * Core plugin functionality
 *
 * @package WPPDFI_Directory_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class using Singleton pattern
 */
class WPPDFI_Directory_Filters {

	/**
	 * Single instance of the class
	 *
	 * @var WPPDFI_Directory_Filters
	 */
	private static $instance = null;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Admin settings instance
	 *
	 * @var WPPDFI_Admin_Settings
	 */
	private $admin_settings;

	/**
	 * Get single instance of the class
	 *
	 * @return WPPDFI_Directory_Filters
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
		$this->version = WPPDFI_VERSION;
		$this->init_hooks();
		$this->load_dependencies();
		$this->init_admin_settings();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Admin hooks.
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Plugin installer specific hooks.
		add_action( 'load-plugin-install.php', array( $this, 'enhance_plugin_installer' ) );

		// AJAX hooks for logged-in users.
		add_action( 'wp_ajax_wppdfi_filter', array( $this, 'handle_filter_request' ) );
		add_action( 'wp_ajax_wppdfi_sort', array( $this, 'handle_sort_request' ) );
		add_action( 'wp_ajax_wppdfi_rating', array( $this, 'handle_rating_calculation' ) );
		add_action( 'wp_ajax_wppdfi_clear_cache', array( $this, 'handle_cache_clear' ) );

		// Debug AJAX endpoint.
		add_action( 'wp_ajax_wppdfi_test', array( $this, 'handle_test_request' ) );

		// Multisite hooks.
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'add_network_admin_menu' ) );
		}

		// Cron hooks.
		add_action( 'wppdfi_cleanup', array( $this, 'cleanup_cache' ) );
		add_action( 'wppdfi_warm_cache', array( $this, 'warm_cache' ) );
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		// Core classes.
		require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-api-handler.php';
		require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-rating-calculator.php';
		require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-health-calculator.php';
		require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-cache-manager.php';
		require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-ajax-handler.php';
		require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-admin-settings.php';
		require_once WPPDFI_PLUGIN_DIR . 'includes/class-wppdfi-security-handler.php';
	}

	/**
	 * Initialize admin settings once
	 */
	private function init_admin_settings() {
		$this->admin_settings = new WPPDFI_Admin_Settings();
		$this->admin_settings->init();
	}

	/**
	 * Admin initialization
	 */
	public function admin_init() {
		// Admin settings are already initialized in constructor
		// This method is kept for future admin initialization needs.
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook_suffix The admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Only load on plugin installer pages.
		if ( 'plugin-install.php' !== $hook_suffix && 'settings_page_wp-plugin-directory-filters' !== $hook_suffix ) {
			return;
		}

		// Enqueue JavaScript.
		wp_enqueue_script(
			'wppd-filters',
			WPPDFI_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			$this->version,
			true
		);

		// Localize script with WordPress admin data.
		wp_localize_script(
			'wppd-filters',
			'wpPluginFilters',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'pluginUrl' => WPPDFI_PLUGIN_URL,
				'nonces'    => array(
					'filter_plugins'   => wp_create_nonce( 'wppdfi_filter_action' ),
					'sort_plugins'     => wp_create_nonce( 'wppdfi_sort_action' ),
					'calculate_rating' => wp_create_nonce( 'wppdfi_rating_action' ),
					'clear_cache'      => wp_create_nonce( 'wppdfi_clear_cache' ),
				),
				'strings'   => array(
					'loading'   => __( 'Loading...', 'wppd-filters' ),
					'error'     => __( 'An error occurred. Please try again.', 'wppd-filters' ),
					'noResults' => __( 'No plugins found matching your criteria.', 'wppd-filters' ),
					'rateLimit' => __( 'Too many requests. Please slow down.', 'wppd-filters' ),
				),
			)
		);

		// Enqueue CSS.
		wp_enqueue_style(
			'wp-plugin-directory-filters-admin',
			WPPDFI_PLUGIN_URL . 'assets/css/admin.css',
			array( 'wp-admin', 'dashicons' ),
			$this->version
		);
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Plugin Directory Filters', 'wppd-filters' ),
			__( 'Plugin Filters', 'wppd-filters' ),
			'manage_options',
			'wppd-filters',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Add network admin menu for multisite
	 */
	public function add_network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Plugin Directory Filters', 'wppd-filters' ),
			__( 'Plugin Filters', 'wppd-filters' ),
			'manage_network_options',
			'wp-plugin-directory-filters-network',
			array( $this, 'render_network_admin_page' )
		);
	}

	/**
	 * Enhance plugin installer page
	 */
	public function enhance_plugin_installer() {
		// Enhancement styles are now included in the main admin.css file.
	}



	/**
	 * Handle filter requests
	 */
	public function handle_filter_request() {
		$ajax_handler = new WPPDFI_AJAX_Handler();
		$ajax_handler->handle_filter_request();
	}

	/**
	 * Handle sort requests
	 */
	public function handle_sort_request() {
		$ajax_handler = new WPPDFI_AJAX_Handler();
		$ajax_handler->handle_sort_request();
	}

	/**
	 * Handle rating calculation
	 */
	public function handle_rating_calculation() {
		$ajax_handler = new WPPDFI_AJAX_Handler();
		$ajax_handler->handle_rating_calculation();
	}

	/**
	 * Handle cache clearing
	 */
	public function handle_cache_clear() {
		$ajax_handler = new WPPDFI_AJAX_Handler();
		$ajax_handler->handle_cache_clear();
	}

	/**
	 * Handle test AJAX request (for debugging)
	 */
	public function handle_test_request() {
		try {
			// Simple test response.
			wp_send_json_success(
				array(
					'message'     => 'AJAX is working correctly',
					'timestamp'   => current_time( 'mysql' ),
					'user_id'     => get_current_user_id(),
					'wp_version'  => get_bloginfo( 'version' ),
					'php_version' => PHP_VERSION,
				)
			);
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				wp_debug_log( '[WP Plugin Filters] Test AJAX error: ' . $e->getMessage() );
			}
			wp_send_json_error(
				array(
					'message' => 'Test AJAX failed: ' . $e->getMessage(),
					'code'    => 'test_error',
				),
				500
			);
		}
	}

	/**
	 * Render admin settings page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wppd-filters' ) );
		}

		$this->admin_settings->render_page();
	}

	/**
	 * Render network admin settings page
	 */
	public function render_network_admin_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wppd-filters' ) );
		}

		$this->admin_settings->render_network_page();
	}

	/**
	 * Cleanup cache (cron job)
	 */
	public function cleanup_cache() {
		$cache_manager = WPPDFI_Cache_Manager::get_instance();
		$cache_manager->cleanup_expired_cache();
	}

	/**
	 * Warm cache (cron job)
	 */
	public function warm_cache() {
		$cache_manager = WPPDFI_Cache_Manager::get_instance();
		$cache_manager->warm_popular_plugins_cache();
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}
}
