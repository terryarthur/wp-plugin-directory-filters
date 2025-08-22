<?php
/**
 * Plugin Activator for WordPress Plugin Directory Filters
 *
 * @package WP_Plugin_Directory_Filters
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin activation
 */
class WP_Plugin_Filters_Activator {
    
    /**
     * Activate the plugin
     *
     * This method is called when the plugin is activated
     */
    public static function activate() {
        // Check WordPress version compatibility
        if (!self::check_wordpress_version()) {
            deactivate_plugins(WP_PLUGIN_FILTERS_BASENAME);
            wp_die(
                sprintf(
                    __('Plugin Directory Filters requires WordPress %s or higher. Please update WordPress.', 'wp-plugin-directory-filters'),
                    '5.8'
                ),
                __('Plugin Activation Error', 'wp-plugin-directory-filters'),
                array('back_link' => true)
            );
        }
        
        // Check PHP version compatibility
        if (!self::check_php_version()) {
            deactivate_plugins(WP_PLUGIN_FILTERS_BASENAME);
            wp_die(
                sprintf(
                    __('Plugin Directory Filters requires PHP %s or higher. Your current version is %s.', 'wp-plugin-directory-filters'),
                    '7.4',
                    PHP_VERSION
                ),
                __('Plugin Activation Error', 'wp-plugin-directory-filters'),
                array('back_link' => true)
            );
        }
        
        // Check required functions
        if (!self::check_required_functions()) {
            deactivate_plugins(WP_PLUGIN_FILTERS_BASENAME);
            wp_die(
                __('Plugin Directory Filters requires functions that are not available in your hosting environment.', 'wp-plugin-directory-filters'),
                __('Plugin Activation Error', 'wp-plugin-directory-filters'),
                array('back_link' => true)
            );
        }
        
        // Create default settings
        self::create_default_settings();
        
        // Schedule cron events
        self::schedule_cron_events();
        
        // Create cache directories if needed
        self::setup_cache_directories();
        
        // Set plugin version
        self::set_plugin_version();
        
        // Log activation
        self::log_activation();
        
        // Clear any existing cache
        self::clear_activation_cache();
        
        // Set activation flag for first-time setup
        set_transient('wp_plugin_filters_activated', true, 60);
    }
    
    /**
     * Check WordPress version compatibility
     *
     * @return bool Compatible version
     */
    private static function check_wordpress_version() {
        global $wp_version;
        return version_compare($wp_version, '5.8', '>=');
    }
    
    /**
     * Check PHP version compatibility
     *
     * @return bool Compatible version
     */
    private static function check_php_version() {
        return version_compare(PHP_VERSION, '7.4', '>=');
    }
    
    /**
     * Check required functions are available
     *
     * @return bool Required functions available
     */
    private static function check_required_functions() {
        $required_functions = array(
            'curl_init',
            'json_decode',
            'json_encode',
            'serialize',
            'unserialize',
            'md5',
            'time',
            'strtotime'
        );
        
        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create default plugin settings
     */
    private static function create_default_settings() {
        $default_settings = array(
            'usability_weights' => array(
                'user_rating' => 40,
                'rating_count' => 20,
                'installation_count' => 25,
                'support_responsiveness' => 15
            ),
            'health_weights' => array(
                'update_frequency' => 30,
                'wp_compatibility' => 25,
                'support_response' => 20,
                'time_since_update' => 15,
                'reported_issues' => 10
            ),
            'cache_durations' => array(
                'plugin_metadata' => 86400,    // 24 hours
                'calculated_ratings' => 21600, // 6 hours
                'search_results' => 3600,      // 1 hour
                'api_responses' => 1800        // 30 minutes
            ),
            'first_activation' => true,
            'activated_at' => current_time('mysql')
        );
        
        // Only add default settings if they don't exist
        if (!get_option('wp_plugin_filters_settings')) {
            add_option('wp_plugin_filters_settings', $default_settings);
        }
    }
    
    /**
     * Schedule WordPress cron events
     */
    private static function schedule_cron_events() {
        // Schedule daily cache cleanup
        if (!wp_next_scheduled('wp_plugin_filters_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_plugin_filters_cleanup');
        }
        
        // Schedule hourly cache warming for popular plugins
        if (!wp_next_scheduled('wp_plugin_filters_warm_cache')) {
            wp_schedule_event(time() + 300, 'hourly', 'wp_plugin_filters_warm_cache'); // Start after 5 minutes
        }
        
        // Schedule weekly statistics collection
        if (!wp_next_scheduled('wp_plugin_filters_collect_stats')) {
            wp_schedule_event(time() + 600, 'weekly', 'wp_plugin_filters_collect_stats'); // Start after 10 minutes
        }
    }
    
    /**
     * Setup cache directories if using file-based caching
     */
    private static function setup_cache_directories() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/wp-plugin-directory-filters-cache';
        
        // Create cache directory if it doesn't exist
        if (!file_exists($cache_dir)) {
            if (wp_mkdir_p($cache_dir)) {
                // Create .htaccess to protect cache directory
                $htaccess_content = "# WordPress Plugin Directory Filters Cache Protection\n";
                $htaccess_content .= "Order deny,allow\n";
                $htaccess_content .= "Deny from all\n";
                $htaccess_content .= "<Files ~ \"\\.(php|html|htm)$\">\n";
                $htaccess_content .= "    Deny from all\n";
                $htaccess_content .= "</Files>\n";
                
                file_put_contents($cache_dir . '/.htaccess', $htaccess_content);
                
                // Create index.php to prevent directory listing
                file_put_contents($cache_dir . '/index.php', '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * Set plugin version in database
     */
    private static function set_plugin_version() {
        update_option('wp_plugin_filters_version', WP_PLUGIN_FILTERS_VERSION);
        update_option('wp_plugin_filters_db_version', '1.0.0');
    }
    
    /**
     * Log plugin activation
     */
    private static function log_activation() {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_data = array(
                'event' => 'plugin_activated',
                'version' => WP_PLUGIN_FILTERS_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'multisite' => is_multisite(),
                'active_theme' => get_option('stylesheet'),
                'active_plugins' => get_option('active_plugins')
            );
            
            error_log('[WP Plugin Filters] Activation: ' . wp_json_encode($log_data));
        }
    }
    
    /**
     * Clear any existing cache during activation
     */
    private static function clear_activation_cache() {
        // Clear WordPress object cache if available
        if (wp_using_ext_object_cache()) {
            wp_cache_flush();
        }
        
        // Clear relevant transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_plugin_%' 
             OR option_name LIKE '_transient_timeout_wp_plugin_%'"
        );
    }
    
    /**
     * Check if this is a network activation
     *
     * @return bool Network activation
     */
    private static function is_network_activation() {
        return is_multisite() && isset($_GET['networkwide']) && $_GET['networkwide'] == '1';
    }
    
    /**
     * Activate for multisite network
     */
    public static function activate_multisite() {
        if (!self::is_network_activation()) {
            return;
        }
        
        global $wpdb;
        
        // Get all blog IDs
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
        
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            self::activate();
            restore_current_blog();
        }
    }
    
    /**
     * Test API connectivity during activation
     *
     * @return bool API is accessible
     */
    private static function test_api_connectivity() {
        $test_url = 'https://api.wordpress.org/plugins/info/1.2/';
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'user-agent' => 'WordPress Plugin Directory Filters/' . WP_PLUGIN_FILTERS_VERSION
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }
    
    /**
     * Create activation notice for admin
     */
    private static function create_activation_notice() {
        $notice = array(
            'type' => 'success',
            'message' => sprintf(
                __('WordPress Plugin Directory Filters has been activated! Visit the %s to configure filtering algorithms.', 'wp-plugin-directory-filters'),
                sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('options-general.php?page=wp-plugin-directory-filters'),
                    __('settings page', 'wp-plugin-directory-filters')
                )
            ),
            'dismissible' => true
        );
        
        set_transient('wp_plugin_filters_activation_notice', $notice, 300); // 5 minutes
    }
    
    /**
     * Check for potential conflicts with other plugins
     *
     * @return array Potential conflicts
     */
    private static function check_plugin_conflicts() {
        $potential_conflicts = array();
        $active_plugins = get_option('active_plugins', array());
        
        // List of plugins that might conflict
        $conflict_plugins = array(
            'plugin-installer-speedup/plugin-installer-speedup.php' => __('Plugin Installer Speedup', 'wp-plugin-directory-filters'),
            'advanced-plugin-search/advanced-plugin-search.php' => __('Advanced Plugin Search', 'wp-plugin-directory-filters'),
            'plugin-organizer/plugin-organizer.php' => __('Plugin Organizer', 'wp-plugin-directory-filters')
        );
        
        foreach ($conflict_plugins as $plugin_file => $plugin_name) {
            if (in_array($plugin_file, $active_plugins)) {
                $potential_conflicts[] = $plugin_name;
            }
        }
        
        return $potential_conflicts;
    }
    
    /**
     * Performance check during activation
     *
     * @return array Performance metrics
     */
    private static function performance_check() {
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        
        // Simulate some plugin operations
        for ($i = 0; $i < 100; $i++) {
            $test_data = array('test' => $i, 'data' => str_repeat('x', 1000));
            $serialized = serialize($test_data);
            $unserialized = unserialize($serialized);
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        
        return array(
            'execution_time' => ($end_time - $start_time) * 1000, // milliseconds
            'memory_usage' => $end_memory - $start_memory,
            'peak_memory' => memory_get_peak_usage(true)
        );
    }
    
    /**
     * Verify activation success
     *
     * @return bool Activation successful
     */
    public static function verify_activation() {
        // Check if settings were created
        $settings = get_option('wp_plugin_filters_settings');
        if (!$settings) {
            return false;
        }
        
        // Check if version was set
        $version = get_option('wp_plugin_filters_version');
        if (!$version) {
            return false;
        }
        
        // Check if cron events were scheduled
        if (!wp_next_scheduled('wp_plugin_filters_cleanup')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Rollback activation if something goes wrong
     */
    public static function rollback_activation() {
        // Remove settings
        delete_option('wp_plugin_filters_settings');
        delete_option('wp_plugin_filters_version');
        delete_option('wp_plugin_filters_db_version');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('wp_plugin_filters_cleanup');
        wp_clear_scheduled_hook('wp_plugin_filters_warm_cache');
        wp_clear_scheduled_hook('wp_plugin_filters_collect_stats');
        
        // Clear transients
        delete_transient('wp_plugin_filters_activated');
        delete_transient('wp_plugin_filters_activation_notice');
        
        // Log rollback
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Plugin Filters] Activation rolled back due to errors');
        }
    }
}