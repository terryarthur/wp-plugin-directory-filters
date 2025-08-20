<?php
/**
 * Core plugin functionality
 *
 * @package WP_Plugin_Directory_Filters
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class using Singleton pattern
 */
class WP_Plugin_Directory_Filters {
    
    /**
     * Single instance of the class
     *
     * @var WP_Plugin_Directory_Filters
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
     * @var WP_Plugin_Filters_Admin_Settings
     */
    private $admin_settings;
    
    /**
     * Get single instance of the class
     *
     * @return WP_Plugin_Directory_Filters
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->version = WP_PLUGIN_FILTERS_VERSION;
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_admin_settings();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Plugin installer specific hooks
        add_action('load-plugin-install.php', array($this, 'enhance_plugin_installer'));
        
        // AJAX hooks for logged-in users
        add_action('wp_ajax_wp_plugin_filter', array($this, 'handle_filter_request'));
        add_action('wp_ajax_wp_plugin_sort', array($this, 'handle_sort_request'));
        add_action('wp_ajax_wp_plugin_rating', array($this, 'handle_rating_calculation'));
        add_action('wp_ajax_wp_plugin_clear_cache', array($this, 'handle_cache_clear'));
        
        // Multisite hooks
        if (is_multisite()) {
            add_action('network_admin_menu', array($this, 'add_network_admin_menu'));
        }
        
        // Cron hooks
        add_action('wp_plugin_filters_cleanup', array($this, 'cleanup_cache'));
        add_action('wp_plugin_filters_warm_cache', array($this, 'warm_cache'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-api-handler.php';
        require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-rating-calculator.php';
        require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-health-calculator.php';
        require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-cache-manager.php';
        require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once WP_PLUGIN_FILTERS_PLUGIN_DIR . 'includes/class-security-handler.php';
    }
    
    /**
     * Initialize admin settings once
     */
    private function init_admin_settings() {
        $this->admin_settings = new WP_Plugin_Filters_Admin_Settings();
        $this->admin_settings->init();
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Admin settings are already initialized in constructor
        // This method is kept for future admin initialization needs
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only load on plugin installer pages
        if ($hook_suffix !== 'plugin-install.php' && $hook_suffix !== 'settings_page_wp-plugin-filters') {
            return;
        }
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'wp-plugin-filters',
            WP_PLUGIN_FILTERS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            $this->version,
            true
        );
        
        // Localize script with WordPress admin data
        wp_localize_script('wp-plugin-filters', 'wpPluginFilters', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'filter_plugins' => wp_create_nonce('wp_plugin_filter_action'),
                'sort_plugins' => wp_create_nonce('wp_plugin_sort_action'),
                'calculate_rating' => wp_create_nonce('wp_plugin_rating_action'),
                'clear_cache' => wp_create_nonce('wp_plugin_clear_cache')
            ),
            'strings' => array(
                'loading' => __('Loading...', 'wp-plugin-filters'),
                'error' => __('An error occurred. Please try again.', 'wp-plugin-filters'),
                'noResults' => __('No plugins found matching your criteria.', 'wp-plugin-filters'),
                'rateLimit' => __('Too many requests. Please slow down.', 'wp-plugin-filters')
            )
        ));
        
        // Enqueue CSS
        wp_enqueue_style(
            'wp-plugin-filters-admin',
            WP_PLUGIN_FILTERS_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-admin', 'dashicons'),
            $this->version
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Plugin Directory Filters', 'wp-plugin-filters'),
            __('Plugin Filters', 'wp-plugin-filters'),
            'manage_options',
            'wp-plugin-filters',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Add network admin menu for multisite
     */
    public function add_network_admin_menu() {
        add_submenu_page(
            'settings.php',
            __('Plugin Directory Filters', 'wp-plugin-filters'),
            __('Plugin Filters', 'wp-plugin-filters'),
            'manage_network_options',
            'wp-plugin-filters-network',
            array($this, 'render_network_admin_page')
        );
    }
    
    /**
     * Enhance plugin installer page
     */
    public function enhance_plugin_installer() {
        // Add enhancement JavaScript and CSS to plugin installer page
        add_action('admin_footer', array($this, 'inject_enhancement_javascript'));
        add_action('admin_head', array($this, 'inject_enhancement_styles'));
    }
    
    /**
     * Inject JavaScript enhancements
     */
    public function inject_enhancement_javascript() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize plugin filters enhancement
            if (typeof WPPluginFilters !== 'undefined') {
                WPPluginFilters.init();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Inject CSS enhancements
     */
    public function inject_enhancement_styles() {
        ?>
        <style type="text/css">
        /* Ensure filter controls integrate seamlessly */
        .wp-plugin-filters-controls {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        }
        
        .wp-plugin-filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .wp-plugin-filters-field {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }
        
        .wp-plugin-filters-field label {
            font-weight: 600;
            margin-bottom: 4px;
            color: #1d2327;
        }
        
        .wp-plugin-filter-select,
        .wp-plugin-filter-input {
            min-width: 150px;
        }
        
        @media (max-width: 782px) {
            .wp-plugin-filters-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .wp-plugin-filters-field {
                min-width: auto;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Handle filter requests
     */
    public function handle_filter_request() {
        $ajax_handler = new WP_Plugin_Filters_AJAX_Handler();
        $ajax_handler->handle_filter_request();
    }
    
    /**
     * Handle sort requests
     */
    public function handle_sort_request() {
        $ajax_handler = new WP_Plugin_Filters_AJAX_Handler();
        $ajax_handler->handle_sort_request();
    }
    
    /**
     * Handle rating calculation
     */
    public function handle_rating_calculation() {
        $ajax_handler = new WP_Plugin_Filters_AJAX_Handler();
        $ajax_handler->handle_rating_calculation();
    }
    
    /**
     * Handle cache clearing
     */
    public function handle_cache_clear() {
        $ajax_handler = new WP_Plugin_Filters_AJAX_Handler();
        $ajax_handler->handle_cache_clear();
    }
    
    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-plugin-filters'));
        }
        
        $this->admin_settings->render_page();
    }
    
    /**
     * Render network admin settings page
     */
    public function render_network_admin_page() {
        if (!current_user_can('manage_network_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-plugin-filters'));
        }
        
        $this->admin_settings->render_network_page();
    }
    
    /**
     * Cleanup cache (cron job)
     */
    public function cleanup_cache() {
        $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
        $cache_manager->cleanup_expired_cache();
    }
    
    /**
     * Warm cache (cron job)
     */
    public function warm_cache() {
        $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
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