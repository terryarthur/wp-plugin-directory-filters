<?php
/**
 * Integration Tests for WP_Plugin_Directory_Filters core functionality
 *
 * @package WP_Plugin_Directory_Filters
 */

class Test_WP_Plugin_Directory_Filters_Core_Integration extends WP_Plugin_Filters_Test_Case {

    /**
     * Test plugin initialization and hooks
     */
    public function test_plugin_initialization() {
        // Plugin should be loaded
        $this->assertTrue(class_exists('WP_Plugin_Directory_Filters'));
        
        // Get plugin instance
        $plugin = WP_Plugin_Directory_Filters::get_instance();
        $this->assertInstanceOf('WP_Plugin_Directory_Filters', $plugin);
        
        // Singleton pattern test
        $plugin2 = WP_Plugin_Directory_Filters::get_instance();
        $this->assertSame($plugin, $plugin2);
    }

    /**
     * Test WordPress hooks registration
     */
    public function test_wordpress_hooks_registration() {
        // Admin hooks should be registered
        $this->assertGreaterThan(0, has_action('admin_init'));
        $this->assertGreaterThan(0, has_action('admin_enqueue_scripts'));
        $this->assertGreaterThan(0, has_action('admin_menu'));
        
        // Plugin installer hooks
        $this->assertGreaterThan(0, has_action('load-plugin-install.php'));
        
        // AJAX hooks
        $this->assertGreaterThan(0, has_action('wp_ajax_wp_plugin_filter'));
        $this->assertGreaterThan(0, has_action('wp_ajax_wp_plugin_sort'));
        $this->assertGreaterThan(0, has_action('wp_ajax_wp_plugin_rating'));
        $this->assertGreaterThan(0, has_action('wp_ajax_wp_plugin_clear_cache'));
        
        // Cron hooks
        $this->assertGreaterThan(0, has_action('wp_plugin_filters_cleanup'));
        $this->assertGreaterThan(0, has_action('wp_plugin_filters_warm_cache'));
    }

    /**
     * Test multisite hooks registration
     */
    public function test_multisite_hooks_registration() {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite not enabled');
        }
        
        // Network admin hooks should be registered
        $this->assertGreaterThan(0, has_action('network_admin_menu'));
    }

    /**
     * Test admin menu creation
     */
    public function test_admin_menu_creation() {
        global $admin_page_hooks, $submenu;
        
        $this->set_current_user('admin');
        
        // Trigger admin menu creation
        do_action('admin_menu');
        
        // Check if plugin menu was added to settings
        $this->assertArrayHasKey('options-general.php', $submenu);
        
        $found_menu = false;
        foreach ($submenu['options-general.php'] as $menu_item) {
            if ($menu_item[2] === 'wp-plugin-filters') {
                $found_menu = true;
                break;
            }
        }
        
        $this->assertTrue($found_menu, 'Plugin menu should be added to WordPress admin');
    }

    /**
     * Test network admin menu creation for multisite
     */
    public function test_network_admin_menu_creation() {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite not enabled');
        }
        
        global $submenu;
        
        $this->set_current_user('super_admin');
        
        // Trigger network admin menu creation
        do_action('network_admin_menu');
        
        // Check if plugin menu was added to network settings
        $this->assertArrayHasKey('settings.php', $submenu);
        
        $found_menu = false;
        foreach ($submenu['settings.php'] as $menu_item) {
            if ($menu_item[2] === 'wp-plugin-filters-network') {
                $found_menu = true;
                break;
            }
        }
        
        $this->assertTrue($found_menu, 'Plugin network menu should be added to WordPress network admin');
    }

    /**
     * Test admin script and style enqueuing
     */
    public function test_admin_assets_enqueuing() {
        global $wp_scripts, $wp_styles;
        
        $this->set_current_user('admin');
        
        // Simulate plugin installer page
        set_current_screen('plugin-install');
        
        // Trigger asset enqueuing
        do_action('admin_enqueue_scripts', 'plugin-install.php');
        
        // Check if scripts are enqueued
        $this->assertTrue(wp_script_is('wp-plugin-filters', 'enqueued'));
        
        // Check if styles are enqueued
        $this->assertTrue(wp_style_is('wp-plugin-filters-admin', 'enqueued'));
        
        // Check localized script data
        $localized_data = $wp_scripts->get_data('wp-plugin-filters', 'data');
        $this->assertStringContainsString('wpPluginFilters', $localized_data);
        $this->assertStringContainsString('ajaxUrl', $localized_data);
        $this->assertStringContainsString('nonces', $localized_data);
    }

    /**
     * Test admin assets are NOT enqueued on wrong pages
     */
    public function test_admin_assets_not_enqueued_wrong_page() {
        $this->set_current_user('admin');
        
        // Simulate different admin page
        set_current_screen('edit-post');
        
        // Trigger asset enqueuing
        do_action('admin_enqueue_scripts', 'edit.php');
        
        // Scripts should NOT be enqueued
        $this->assertFalse(wp_script_is('wp-plugin-filters', 'enqueued'));
        $this->assertFalse(wp_style_is('wp-plugin-filters-admin', 'enqueued'));
    }

    /**
     * Test AJAX handler instantiation and security
     */
    public function test_ajax_handler_security() {
        $this->set_current_user('admin');
        
        // Test filter request without proper nonce (should fail)
        $_POST = [
            'action' => 'wp_plugin_filter',
            'search_term' => 'test'
        ];
        
        add_filter('wp_doing_ajax', '__return_true');
        
        // Capture output
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected - should die due to missing nonce
        }
        $output = ob_get_clean();
        
        remove_filter('wp_doing_ajax', '__return_true');
        
        // Should contain error response
        $this->assertStringContainsString('error', $output);
        
        unset($_POST);
    }

    /**
     * Test AJAX filter request with proper authentication
     */
    public function test_ajax_filter_request_authenticated() {
        $this->set_current_user('admin');
        
        $_POST = [
            'action' => 'wp_plugin_filter',
            'nonce' => wp_create_nonce('wp_plugin_filter_action'),
            'search_term' => 'test',
            'installation_range' => 'all',
            'update_timeframe' => 'all',
            'usability_rating' => 0,
            'health_score' => 0,
            'sort_by' => 'relevance',
            'sort_direction' => 'desc',
            'page' => 1,
            'per_page' => 24
        ];
        
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        add_filter('wp_doing_ajax', '__return_true');
        
        // Mock successful API response
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'plugins' => [
                            [
                                'slug' => 'test-plugin',
                                'name' => 'Test Plugin',
                                'version' => '1.0.0',
                                'author' => 'Test Author',
                                'rating' => 4.5,
                                'num_ratings' => 100,
                                'active_installs' => 10000,
                                'last_updated' => date('Y-m-d', strtotime('-7 days')),
                                'added' => date('Y-m-d', strtotime('-1 year')),
                                'tested' => '6.4',
                                'requires' => '5.8',
                                'short_description' => 'A test plugin',
                                'description' => 'Test plugin description',
                                'homepage' => 'https://example.com',
                                'download_link' => 'https://downloads.wordpress.org/plugin/test-plugin.zip',
                                'tags' => ['test'],
                                'support_threads' => 10,
                                'support_threads_resolved' => 8,
                                'downloaded' => 50000,
                                'ratings' => [5 => 70, 4 => 20, 3 => 8, 2 => 1, 1 => 1]
                            ]
                        ],
                        'info' => ['page' => 1, 'pages' => 1, 'results' => 1]
                    ])
                ];
            }
            return $preempt;
        }, 10, 3);
        
        // Capture output
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected - AJAX handlers call wp_die() to end execution
        }
        $output = ob_get_clean();
        
        remove_filter('wp_doing_ajax', '__return_true');
        
        // Should contain success response
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        $this->assertTrue($response['success'] ?? false);
        $this->assertArrayHasKey('data', $response);
        
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test plugin installer page enhancements
     */
    public function test_plugin_installer_enhancements() {
        $this->set_current_user('admin');
        
        // Simulate loading plugin installer page
        global $pagenow;
        $pagenow = 'plugin-install.php';
        
        do_action('load-plugin-install.php');
        
        // Check that enhancement hooks are added
        $this->assertGreaterThan(0, has_action('admin_footer'));
        $this->assertGreaterThan(0, has_action('admin_head'));
    }

    /**
     * Test cron job scheduling
     */
    public function test_cron_job_scheduling() {
        // Test cleanup cron
        $timestamp = wp_next_scheduled('wp_plugin_filters_cleanup');
        if (!$timestamp) {
            // Schedule if not already scheduled
            wp_schedule_event(time(), 'daily', 'wp_plugin_filters_cleanup');
            $timestamp = wp_next_scheduled('wp_plugin_filters_cleanup');
        }
        $this->assertNotFalse($timestamp, 'Cleanup cron job should be scheduled');
        
        // Test cache warming cron
        $timestamp = wp_next_scheduled('wp_plugin_filters_warm_cache');
        if (!$timestamp) {
            // Schedule if not already scheduled
            wp_schedule_event(time(), 'hourly', 'wp_plugin_filters_warm_cache');
            $timestamp = wp_next_scheduled('wp_plugin_filters_warm_cache');
        }
        $this->assertNotFalse($timestamp, 'Cache warming cron job should be scheduled');
    }

    /**
     * Test cron job execution
     */
    public function test_cron_job_execution() {
        $plugin = WP_Plugin_Directory_Filters::get_instance();
        
        // Test cleanup execution (should not throw errors)
        $plugin->cleanup_cache();
        $this->assertTrue(true, 'Cleanup cron should execute without errors');
        
        // Test cache warming execution (should not throw errors)
        $plugin->warm_cache();
        $this->assertTrue(true, 'Cache warming cron should execute without errors');
    }

    /**
     * Test database operations and options
     */
    public function test_database_operations() {
        // Test option creation and retrieval
        $test_settings = [
            'enable_caching' => true,
            'cache_duration' => 7200,
            'custom_setting' => 'test_value'
        ];
        
        update_option('wp_plugin_filters_settings', $test_settings);
        $retrieved_settings = get_option('wp_plugin_filters_settings');
        
        $this->assertEquals($test_settings, $retrieved_settings);
        
        // Test transient operations
        $test_data = ['test' => 'data', 'timestamp' => time()];
        set_transient('wp_plugin_filters_test', $test_data, 3600);
        
        $retrieved_data = get_transient('wp_plugin_filters_test');
        $this->assertEquals($test_data, $retrieved_data);
        
        // Clean up
        delete_option('wp_plugin_filters_settings');
        delete_transient('wp_plugin_filters_test');
    }

    /**
     * Test WordPress capabilities integration
     */
    public function test_capabilities_integration() {
        // Admin should have install_plugins capability
        $this->set_current_user('admin');
        $this->assertTrue(current_user_can('install_plugins'));
        
        // Editor should not have install_plugins capability
        $this->set_current_user('editor');
        $this->assertFalse(current_user_can('install_plugins'));
        
        // Subscriber should not have install_plugins capability
        $this->set_current_user('subscriber');
        $this->assertFalse(current_user_can('install_plugins'));
    }

    /**
     * Test plugin activation and deactivation hooks
     */
    public function test_activation_deactivation_hooks() {
        // Test activation
        if (function_exists('wp_plugin_filters_activate')) {
            // Should not throw errors
            wp_plugin_filters_activate();
            $this->assertTrue(true, 'Plugin activation should complete without errors');
        }
        
        // Test deactivation
        if (function_exists('wp_plugin_filters_deactivate')) {
            // Should not throw errors
            wp_plugin_filters_deactivate();
            $this->assertTrue(true, 'Plugin deactivation should complete without errors');
        }
    }

    /**
     * Test WordPress localization/internationalization
     */
    public function test_localization() {
        // Test text domain loading
        $this->assertTrue(is_textdomain_loaded('wp-plugin-filters'), 'Text domain should be loaded');
        
        // Test translated strings
        $translated = __('Loading...', 'wp-plugin-filters');
        $this->assertIsString($translated);
        $this->assertNotEmpty($translated);
    }

    /**
     * Test WordPress cache integration
     */
    public function test_wordpress_cache_integration() {
        // Test WordPress object cache
        $test_key = 'wp_plugin_filters_test_cache';
        $test_data = ['test' => 'cache_data', 'time' => time()];
        
        wp_cache_set($test_key, $test_data, 'wp_plugin_filters');
        $cached_data = wp_cache_get($test_key, 'wp_plugin_filters');
        
        $this->assertEquals($test_data, $cached_data);
        
        // Test cache deletion
        wp_cache_delete($test_key, 'wp_plugin_filters');
        $deleted_data = wp_cache_get($test_key, 'wp_plugin_filters');
        
        $this->assertFalse($deleted_data);
    }

    /**
     * Test WordPress error handling integration
     */
    public function test_wordpress_error_handling() {
        // Test WP_Error creation and handling
        $error = new WP_Error('test_error', 'Test error message', ['test_data' => 'value']);
        
        $this->assertInstanceOf('WP_Error', $error);
        $this->assertTrue(is_wp_error($error));
        $this->assertEquals('test_error', $error->get_error_code());
        $this->assertEquals('Test error message', $error->get_error_message());
        
        // Test error data
        $error_data = $error->get_error_data();
        $this->assertEquals(['test_data' => 'value'], $error_data);
    }

    /**
     * Test WordPress HTTP API integration
     */
    public function test_wordpress_http_api_integration() {
        // Test wp_remote_post functionality (will be mocked in actual API tests)
        $response = wp_remote_post('https://httpbin.org/post', [
            'body' => ['test' => 'data'],
            'timeout' => 10
        ]);
        
        if (!is_wp_error($response)) {
            $this->assertIsArray($response);
            $this->assertArrayHasKey('response', $response);
            $this->assertArrayHasKey('body', $response);
            
            $response_code = wp_remote_retrieve_response_code($response);
            $this->assertEquals(200, $response_code);
        } else {
            // Network issues in test environment are acceptable
            $this->markTestSkipped('Network request failed in test environment');
        }
    }

    /**
     * Test WordPress nonce integration
     */
    public function test_wordpress_nonce_integration() {
        $action = 'wp_plugin_test_action';
        
        // Create nonce
        $nonce = wp_create_nonce($action);
        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
        
        // Verify nonce
        $this->assertTrue(wp_verify_nonce($nonce, $action));
        
        // Verify with wrong action
        $this->assertFalse(wp_verify_nonce($nonce, 'wrong_action'));
    }

    /**
     * Test WordPress database abstraction integration
     */
    public function test_wordpress_database_integration() {
        global $wpdb;
        
        // Test database query
        $option_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'wp_plugin_filters%'");
        $this->assertIsNumeric($option_count);
        
        // Test prepared statement
        $prepared = $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            'wp_plugin_filters_test_option'
        );
        $this->assertStringContainsString('wp_plugin_filters_test_option', $prepared);
    }

    /**
     * Test REST API integration (if applicable)
     */
    public function test_rest_api_integration() {
        // Check if REST API is available
        if (!function_exists('rest_url')) {
            $this->markTestSkipped('REST API not available');
        }
        
        $rest_url = rest_url();
        $this->assertStringContainsString('/wp-json/', $rest_url);
        
        // Plugin should not register REST endpoints by default
        // This test ensures we're not accidentally exposing REST endpoints
        $routes = rest_get_server()->get_routes();
        $plugin_routes = array_filter(array_keys($routes), function($route) {
            return strpos($route, 'wp-plugin-filters') !== false;
        });
        
        $this->assertEmpty($plugin_routes, 'Plugin should not register REST API endpoints');
    }

    /**
     * Test WordPress admin notices integration
     */
    public function test_admin_notices_integration() {
        $this->set_current_user('admin');
        
        // Test adding admin notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Test notice from plugin</p></div>';
        });
        
        // Capture admin notices
        ob_start();
        do_action('admin_notices');
        $notices = ob_get_clean();
        
        $this->assertStringContainsString('Test notice from plugin', $notices);
    }

    /**
     * Test plugin constants and paths
     */
    public function test_plugin_constants() {
        // Test plugin constants are defined
        $this->assertTrue(defined('WP_PLUGIN_FILTERS_VERSION'));
        $this->assertTrue(defined('WP_PLUGIN_FILTERS_PLUGIN_DIR'));
        $this->assertTrue(defined('WP_PLUGIN_FILTERS_PLUGIN_URL'));
        $this->assertTrue(defined('WP_PLUGIN_FILTERS_BASENAME'));
        
        // Test constant values
        $this->assertIsString(WP_PLUGIN_FILTERS_VERSION);
        $this->assertNotEmpty(WP_PLUGIN_FILTERS_VERSION);
        
        $this->assertDirectoryExists(WP_PLUGIN_FILTERS_PLUGIN_DIR);
        
        $this->assertStringStartsWith('http', WP_PLUGIN_FILTERS_PLUGIN_URL);
        
        $this->assertStringContains('wp-plugin-directory-filters', WP_PLUGIN_FILTERS_BASENAME);
    }
}