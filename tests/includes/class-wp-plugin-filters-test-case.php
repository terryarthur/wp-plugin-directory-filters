<?php
/**
 * Base Test Case for WP Plugin Directory Filters
 *
 * @package WP_Plugin_Directory_Filters
 */

/**
 * Base test case class with common functionality
 */
class WP_Plugin_Filters_Test_Case extends WP_UnitTestCase {
    
    /**
     * Plugin instance
     *
     * @var WP_Plugin_Directory_Filters
     */
    protected $plugin;
    
    /**
     * Test user IDs
     *
     * @var array
     */
    protected $test_users = [];
    
    /**
     * Original plugin settings
     *
     * @var array
     */
    protected $original_settings;
    
    /**
     * Test start time for performance measurements
     *
     * @var float
     */
    protected $test_start_time;
    
    /**
     * Test start memory for memory usage measurements
     *
     * @var int
     */
    protected $test_start_memory;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Record test start metrics
        $this->test_start_time = microtime(true);
        $this->test_start_memory = memory_get_usage();
        
        // Get plugin instance
        $this->plugin = WP_Plugin_Directory_Filters::get_instance();
        
        // Store original settings
        $this->original_settings = get_option('wp_plugin_filters_settings', []);
        
        // Set up test users
        $this->setup_test_users();
        
        // Set up test environment
        $this->setup_test_environment();
        
        // Clear any existing caches
        $this->clear_all_caches();
    }

    /**
     * Clean up after test
     */
    public function tearDown(): void {
        // Restore original settings
        update_option('wp_plugin_filters_settings', $this->original_settings);
        
        // Clean up test data
        $this->cleanup_test_data();
        
        // Clear caches
        $this->clear_all_caches();
        
        // Remove test users
        $this->cleanup_test_users();
        
        // Log performance metrics
        $this->log_performance_metrics();
        
        parent::tearDown();
    }

    /**
     * Set up test users with different capabilities
     */
    protected function setup_test_users() {
        // Administrator
        $this->test_users['admin'] = $this->factory()->user->create([
            'role' => 'administrator',
            'user_login' => 'test_admin_' . uniqid(),
            'user_email' => 'admin_' . uniqid() . '@example.com',
        ]);
        
        // Editor
        $this->test_users['editor'] = $this->factory()->user->create([
            'role' => 'editor',
            'user_login' => 'test_editor_' . uniqid(),
            'user_email' => 'editor_' . uniqid() . '@example.com',
        ]);
        
        // Subscriber (no install_plugins capability)
        $this->test_users['subscriber'] = $this->factory()->user->create([
            'role' => 'subscriber',
            'user_login' => 'test_subscriber_' . uniqid(),
            'user_email' => 'subscriber_' . uniqid() . '@example.com',
        ]);
        
        // Super admin for multisite tests
        if (is_multisite()) {
            $this->test_users['super_admin'] = $this->factory()->user->create([
                'role' => 'administrator',
                'user_login' => 'test_super_admin_' . uniqid(),
                'user_email' => 'super_admin_' . uniqid() . '@example.com',
            ]);
            grant_super_admin($this->test_users['super_admin']);
        }
    }

    /**
     * Set up test environment with default settings
     */
    protected function setup_test_environment() {
        // Set test-specific plugin settings
        $test_settings = [
            'enable_caching' => true,
            'cache_duration' => 3600,
            'api_timeout' => 30,
            'rate_limit_per_minute' => 60,
            'usability_weights' => [
                'user_rating' => 40,
                'rating_count' => 20,
                'installation_count' => 25,
                'support_responsiveness' => 15
            ],
            'enable_debug_logging' => true,
            'mock_api_responses' => true
        ];
        
        update_option('wp_plugin_filters_settings', $test_settings);
        
        // Set up test transients
        set_transient('wp_plugin_filters_test_flag', true, 3600);
        
        // Create test directories
        $test_dirs = [
            WP_PLUGIN_FILTERS_TEST_DATA_DIR,
            WP_PLUGIN_FILTERS_TEST_FIXTURES_DIR,
            WP_PLUGIN_FILTERS_TEST_LOGS_DIR,
        ];
        
        foreach ($test_dirs as $dir) {
            if (!is_dir($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }

    /**
     * Clean up test data
     */
    protected function cleanup_test_data() {
        // Remove test transients
        delete_transient('wp_plugin_filters_test_flag');
        
        // Clean up test cache entries
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wp_plugin_test_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wp_plugin_test_%'");
        
        // Clean up test files
        $this->cleanup_test_files();
    }

    /**
     * Clean up test files
     */
    protected function cleanup_test_files() {
        $test_dirs = [
            WP_PLUGIN_FILTERS_TEST_LOGS_DIR,
        ];
        
        foreach ($test_dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*.log');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Clean up test users
     */
    protected function cleanup_test_users() {
        foreach ($this->test_users as $user_id) {
            if (is_multisite() && is_super_admin($user_id)) {
                revoke_super_admin($user_id);
            }
            wp_delete_user($user_id, true);
        }
        $this->test_users = [];
    }

    /**
     * Clear all plugin caches
     */
    protected function clear_all_caches() {
        // WordPress object cache
        wp_cache_flush();
        
        // Plugin-specific caches
        if (class_exists('WP_Plugin_Filters_Cache_Manager')) {
            $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
            $cache_manager->clear_all_cache();
        }
        
        // Transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wp_plugin_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wp_plugin_%'");
    }

    /**
     * Log performance metrics
     */
    protected function log_performance_metrics() {
        $execution_time = (microtime(true) - $this->test_start_time) * 1000; // ms
        $memory_usage = (memory_get_usage() - $this->test_start_memory) / 1024 / 1024; // MB
        $peak_memory = memory_get_peak_usage() / 1024 / 1024; // MB
        
        $metrics = [
            'test_name' => get_class($this) . '::' . $this->getName(),
            'execution_time_ms' => round($execution_time, 2),
            'memory_usage_mb' => round($memory_usage, 2),
            'peak_memory_mb' => round($peak_memory, 2),
            'timestamp' => current_time('mysql'),
        ];
        
        // Log to file if debug logging is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Plugin Filters Test Metrics] ' . wp_json_encode($metrics));
        }
        
        // Assert performance thresholds
        if (defined('WP_PLUGIN_FILTERS_PERFORMANCE_THRESHOLD_MS')) {
            $this->assertLessThan(
                WP_PLUGIN_FILTERS_PERFORMANCE_THRESHOLD_MS,
                $execution_time,
                "Test execution time ({$execution_time}ms) exceeded threshold (" . WP_PLUGIN_FILTERS_PERFORMANCE_THRESHOLD_MS . "ms)"
            );
        }
        
        if (defined('WP_PLUGIN_FILTERS_MAX_MEMORY_MB')) {
            $this->assertLessThan(
                WP_PLUGIN_FILTERS_MAX_MEMORY_MB,
                $peak_memory,
                "Peak memory usage ({$peak_memory}MB) exceeded threshold (" . WP_PLUGIN_FILTERS_MAX_MEMORY_MB . "MB)"
            );
        }
    }

    /**
     * Create test plugin data
     *
     * @param array $overrides Data overrides
     * @return array Plugin data
     */
    protected function create_test_plugin_data($overrides = []) {
        $defaults = [
            'slug' => 'test-plugin-' . uniqid(),
            'name' => 'Test Plugin ' . uniqid(),
            'version' => '1.0.0',
            'author' => 'Test Author',
            'rating' => 4.5,
            'num_ratings' => 100,
            'active_installs' => 50000,
            'last_updated' => date('Y-m-d', strtotime('-7 days')),
            'added' => date('Y-m-d', strtotime('-1 year')),
            'tested' => '6.4',
            'requires' => '5.8',
            'short_description' => 'A test plugin for unit testing',
            'description' => 'This is a test plugin used for unit testing purposes.',
            'homepage' => 'https://example.com/test-plugin',
            'download_link' => 'https://downloads.wordpress.org/plugin/test-plugin.zip',
            'tags' => ['test', 'development'],
            'support_threads' => 20,
            'support_threads_resolved' => 18,
            'downloaded' => 100000,
            'ratings' => [5 => 70, 4 => 20, 3 => 8, 2 => 1, 1 => 1]
        ];
        
        return array_merge($defaults, $overrides);
    }

    /**
     * Set current user for test
     *
     * @param string $role User role (admin, editor, subscriber, super_admin)
     */
    protected function set_current_user($role = 'admin') {
        if (!isset($this->test_users[$role])) {
            $this->fail("Test user role '{$role}' not found");
        }
        
        wp_set_current_user($this->test_users[$role]);
    }

    /**
     * Assert that a value is a valid WordPress error
     *
     * @param mixed  $actual   Value to check
     * @param string $message  Custom assertion message
     */
    protected function assertIsWPError($actual, $message = '') {
        $this->assertInstanceOf('WP_Error', $actual, $message);
    }

    /**
     * Assert that a value is not a WordPress error
     *
     * @param mixed  $actual   Value to check
     * @param string $message  Custom assertion message
     */
    protected function assertIsNotWPError($actual, $message = '') {
        $this->assertFalse(is_wp_error($actual), $message . (is_wp_error($actual) ? ' Error: ' . $actual->get_error_message() : ''));
    }

    /**
     * Assert that a plugin data array has required fields
     *
     * @param array $plugin_data Plugin data to validate
     */
    protected function assertValidPluginData($plugin_data) {
        $required_fields = [
            'slug', 'name', 'version', 'author', 'rating', 'num_ratings',
            'active_installs', 'last_updated', 'tested', 'requires',
            'short_description', 'homepage', 'download_link'
        ];
        
        foreach ($required_fields as $field) {
            $this->assertArrayHasKey($field, $plugin_data, "Plugin data missing required field: {$field}");
        }
        
        // Validate data types
        $this->assertIsString($plugin_data['slug']);
        $this->assertIsString($plugin_data['name']);
        $this->assertIsString($plugin_data['version']);
        $this->assertIsFloat($plugin_data['rating']) || $this->assertIsInt($plugin_data['rating']);
        $this->assertIsInt($plugin_data['num_ratings']);
        $this->assertIsInt($plugin_data['active_installs']);
        
        // Validate rating range
        $this->assertGreaterThanOrEqual(0, $plugin_data['rating']);
        $this->assertLessThanOrEqual(5, $plugin_data['rating']);
        
        // Validate URLs
        if (!empty($plugin_data['homepage'])) {
            $this->assertMatchesRegularExpression('/^https?:\/\//', $plugin_data['homepage']);
        }
        if (!empty($plugin_data['download_link'])) {
            $this->assertMatchesRegularExpression('/^https?:\/\//', $plugin_data['download_link']);
        }
    }

    /**
     * Assert that caching is working correctly
     *
     * @param string $cache_key Cache key to check
     * @param string $cache_group Cache group
     */
    protected function assertCacheWorks($cache_key, $cache_group = 'default') {
        $test_data = 'test_cache_data_' . uniqid();
        
        // Set cache
        wp_cache_set($cache_key, $test_data, $cache_group);
        
        // Get cache
        $cached_data = wp_cache_get($cache_key, $cache_group);
        
        $this->assertEquals($test_data, $cached_data, 'Cache set/get failed');
        
        // Delete cache
        wp_cache_delete($cache_key, $cache_group);
        
        // Verify deletion
        $deleted_data = wp_cache_get($cache_key, $cache_group);
        $this->assertFalse($deleted_data, 'Cache deletion failed');
    }

    /**
     * Mock WordPress.org API response
     *
     * @param string $action API action
     * @param array  $response_data Response data
     */
    protected function mock_api_response($action, $response_data) {
        add_filter('pre_http_request', function($preempt, $args, $url) use ($action, $response_data) {
            if (strpos($url, 'api.wordpress.org/plugins/info') !== false) {
                $body = wp_parse_args($args['body']);
                if (($body['action'] ?? '') === $action) {
                    return [
                        'response' => ['code' => 200],
                        'body' => json_encode($response_data)
                    ];
                }
            }
            return $preempt;
        }, 10, 3);
    }

    /**
     * Create a test HTTP response
     *
     * @param array $data Response data
     * @param int   $status_code HTTP status code
     * @return array HTTP response array
     */
    protected function create_http_response($data, $status_code = 200) {
        return [
            'response' => ['code' => $status_code],
            'body' => is_array($data) ? json_encode($data) : $data
        ];
    }

    /**
     * Simulate multisite environment
     */
    protected function simulate_multisite() {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite not enabled');
        }
        
        // Create a test site
        $site_id = $this->factory()->blog->create([
            'domain' => 'test.example.org',
            'path' => '/testsite/',
        ]);
        
        switch_to_blog($site_id);
        
        return $site_id;
    }

    /**
     * Restore from multisite simulation
     */
    protected function restore_from_multisite() {
        if (is_multisite()) {
            restore_current_blog();
        }
    }
}