<?php
/**
 * Plugin Uninstaller for WordPress Plugin Directory Filters
 *
 * @package WP_Plugin_Directory_Filters
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin uninstall
 */
class WP_Plugin_Filters_Uninstaller {
    
    /**
     * Uninstall the plugin
     *
     * This method is called when the plugin is uninstalled
     */
    public static function uninstall() {
        // Check if uninstall is called from WordPress
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            exit;
        }
        
        // Verify user permissions
        if (!current_user_can('delete_plugins')) {
            exit;
        }
        
        try {
            // Check for multisite and handle accordingly
            if (is_multisite()) {
                self::uninstall_multisite();
            } else {
                self::uninstall_single_site();
            }
            
            // Final cleanup
            self::final_cleanup();
            
            // Log successful uninstall
            self::log_uninstall();
            
        } catch (Exception $e) {
            // Log the error
            error_log('[WP Plugin Filters] Uninstall failed: ' . $e->getMessage());
            
            // Attempt emergency cleanup
            self::emergency_cleanup();
            
            // Log emergency cleanup
            error_log('[WP Plugin Filters] Emergency cleanup completed after failed uninstall');
        }
    }
    
    /**
     * Uninstall for single site
     */
    private static function uninstall_single_site() {
        // Remove all plugin options
        self::remove_options();
        
        // Remove all plugin transients
        self::remove_transients();
        
        // Clear scheduled cron events
        self::clear_cron_events();
        
        // Remove cache directories
        self::remove_cache_directories();
        
        // Clear object cache
        self::clear_object_cache();
    }
    
    /**
     * Uninstall for multisite network
     */
    private static function uninstall_multisite() {
        // Get all site IDs using WordPress function
        $site_ids = get_sites(array(
            'fields' => 'ids',
            'number' => 0 // Get all sites
        ));
        
        foreach ($site_ids as $site_id) {
            switch_to_blog($site_id);
            self::uninstall_single_site();
            restore_current_blog();
        }
        
        // Remove network-wide options
        self::remove_network_options();
    }
    
    /**
     * Remove all plugin options
     */
    private static function remove_options() {
        global $wpdb;
        
        // Remove plugin-specific options
        $options_to_remove = array(
            'wp_plugin_filters_settings',
            'wp_plugin_filters_version',
            'wp_plugin_filters_db_version',
            'wp_plugin_filters_activated',
            'wp_plugin_filters_first_activation',
            'wp_plugin_filters_api_status',
            'wp_plugin_filters_last_cleanup',
            'wp_plugin_filters_statistics'
        );
        
        foreach ($options_to_remove as $option) {
            delete_option($option);
        }
        
        // Remove any remaining plugin options using wildcard
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wp_plugin_filters_%'
            )
        );
        
        if ($result === false) {
            error_log('[WP Plugin Filters] Failed to delete plugin options from database');
        }
    }
    
    /**
     * Remove network options for multisite
     */
    private static function remove_network_options() {
        $network_options = array(
            'wp_plugin_filters_network_settings',
            'wp_plugin_filters_network_activated'
        );
        
        foreach ($network_options as $option) {
            delete_site_option($option);
        }
    }
    
    /**
     * Remove all plugin transients
     */
    private static function remove_transients() {
        global $wpdb;
        
        // Remove all plugin-related transients
        $result = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_plugin_%' 
             OR option_name LIKE '_transient_timeout_wp_plugin_%'"
        );
        
        if ($result === false) {
            error_log('[WP Plugin Filters] Failed to delete plugin transients from database');
        }
        
        // Remove specific transients
        $transients_to_remove = array(
            'wp_plugin_filters_activated',
            'wp_plugin_filters_deactivated',
            'wp_plugin_filters_activation_notice',
            'wp_plugin_filters_deactivation_notice'
        );
        
        foreach ($transients_to_remove as $transient) {
            delete_transient($transient);
        }
    }
    
    /**
     * Clear all cron events
     */
    private static function clear_cron_events() {
        $cron_hooks = array(
            'wp_plugin_filters_cleanup',
            'wp_plugin_filters_warm_cache',
            'wp_plugin_filters_collect_stats',
            'wp_plugin_filters_maintenance',
            'wp_plugin_filters_api_health_check',
            'wp_plugin_filters_rating_recalculation'
        );
        
        foreach ($cron_hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
    
    /**
     * Remove cache directories
     */
    private static function remove_cache_directories() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/wp-plugin-filters-cache';
        
        if (file_exists($cache_dir)) {
            self::remove_directory_recursive($cache_dir);
        }
    }
    
    /**
     * Recursively remove directory and its contents
     *
     * @param string $dir Directory path
     */
    private static function remove_directory_recursive($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        // Check if directory is readable
        if (!is_readable($dir)) {
            error_log('[WP Plugin Filters] Cannot read directory for deletion: ' . $dir);
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            try {
                if (is_dir($path)) {
                    self::remove_directory_recursive($path);
                } else {
                    if (is_writable($path)) {
                        unlink($path);
                    } else {
                        error_log('[WP Plugin Filters] Cannot delete file: ' . $path);
                    }
                }
            } catch (Exception $e) {
                error_log('[WP Plugin Filters] Error deleting: ' . $path . ' - ' . $e->getMessage());
            }
        }
        
        try {
            if (is_writable($dir)) {
                rmdir($dir);
            } else {
                error_log('[WP Plugin Filters] Cannot delete directory: ' . $dir);
            }
        } catch (Exception $e) {
            error_log('[WP Plugin Filters] Error deleting directory: ' . $dir . ' - ' . $e->getMessage());
        }
    }
    
    /**
     * Clear object cache
     */
    private static function clear_object_cache() {
        if (wp_using_ext_object_cache()) {
            $cache_groups = array(
                'plugin_metadata',
                'calculated_ratings',
                'search_results',
                'api_responses'
            );
            
            foreach ($cache_groups as $group) {
                wp_cache_flush_group($group);
            }
            
            // Full cache flush as final step
            wp_cache_flush();
        }
    }
    
    /**
     * Final cleanup operations
     */
    private static function final_cleanup() {
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Remove any remaining temporary files
        $temp_dir = sys_get_temp_dir();
        $temp_files = glob($temp_dir . '/wp-plugin-filters-*');
        
        foreach ($temp_files as $temp_file) {
            if (is_file($temp_file)) {
                unlink($temp_file);
            }
        }
    }
    
    /**
     * Log plugin uninstall
     */
    private static function log_uninstall() {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_data = array(
                'event' => 'plugin_uninstalled',
                'version' => get_option('wp_plugin_filters_version', 'unknown'),
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'multisite' => is_multisite(),
                'uninstall_summary' => self::get_uninstall_summary()
            );
            
            error_log('[WP Plugin Filters] Uninstall: ' . wp_json_encode($log_data));
        }
    }
    
    /**
     * Get uninstall summary
     *
     * @return array Uninstall summary
     */
    private static function get_uninstall_summary() {
        global $wpdb;
        
        // Count remaining plugin data before final cleanup
        $remaining_options = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wp_plugin_filters_%'
            )
        );
        
        $remaining_transients = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_plugin_%'"
        );
        
        return array(
            'remaining_options' => intval($remaining_options),
            'remaining_transients' => intval($remaining_transients),
            'cache_cleared' => wp_using_ext_object_cache(),
            'cron_events_cleared' => true,
            'uninstall_completed' => true
        );
    }
    
    /**
     * Verify uninstall completion
     *
     * @return bool Uninstall successful
     */
    public static function verify_uninstall() {
        global $wpdb;
        
        // Check if any plugin data remains
        $remaining_options = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wp_plugin_filters_%'
            )
        );
        
        $remaining_transients = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_plugin_%'"
        );
        
        // Check if cron events were cleared
        $remaining_crons = wp_next_scheduled('wp_plugin_filters_cleanup') ? 1 : 0;
        
        return ($remaining_options == 0 && $remaining_transients == 0 && $remaining_crons == 0);
    }
    
    /**
     * Emergency cleanup - force remove all plugin data
     */
    public static function emergency_cleanup() {
        global $wpdb;
        
        // Force delete all plugin-related database entries
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '%wp_plugin_filters%' 
             OR option_name LIKE '%wp_plugin_%' 
             OR option_name LIKE '_transient_wp_plugin_%' 
             OR option_name LIKE '_transient_timeout_wp_plugin_%'"
        );
        
        // Clear all cron events (just in case)
        wp_clear_scheduled_hook('wp_plugin_filters_cleanup');
        wp_clear_scheduled_hook('wp_plugin_filters_warm_cache');
        wp_clear_scheduled_hook('wp_plugin_filters_collect_stats');
        
        // Force clear cache
        if (wp_using_ext_object_cache()) {
            wp_cache_flush();
        }
        
        error_log('[WP Plugin Filters] Emergency cleanup completed');
    }
    
    /**
     * Check if user confirmed data deletion
     *
     * @return bool User confirmed deletion
     */
    private static function user_confirmed_deletion() {
        // In WordPress, plugin uninstall already requires user confirmation
        // This is just an additional check if needed
        return true;
    }
    
    /**
     * Create uninstall backup (optional)
     */
    private static function create_uninstall_backup() {
        $settings = get_option('wp_plugin_filters_settings');
        
        if ($settings) {
            $backup_data = array(
                'settings' => $settings,
                'version' => get_option('wp_plugin_filters_version'),
                'timestamp' => current_time('mysql'),
                'wordpress_version' => get_bloginfo('version')
            );
            
            // Store backup as a temporary option (will be auto-removed)
            set_transient('wp_plugin_filters_uninstall_backup', $backup_data, WEEK_IN_SECONDS);
        }
    }
}