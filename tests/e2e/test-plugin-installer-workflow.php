<?php
/**
 * End-to-End Tests for Plugin Installer Workflow
 *
 * @package WP_Plugin_Directory_Filters
 */

class Test_Plugin_Installer_Workflow extends WP_Plugin_Filters_Test_Case {

    /**
     * Test complete plugin search and filter workflow
     */
    public function test_complete_plugin_search_workflow() {
        $this->set_current_user('admin');
        
        // Step 1: Load plugin installer page
        set_current_screen('plugin-install');
        global $pagenow;
        $pagenow = 'plugin-install.php';
        
        // Trigger page load hooks
        do_action('load-plugin-install.php');
        do_action('admin_enqueue_scripts', 'plugin-install.php');
        
        // Verify assets are loaded
        $this->assertTrue(wp_script_is('wp-plugin-filters', 'enqueued'));
        $this->assertTrue(wp_style_is('wp-plugin-filters-admin', 'enqueued'));
        
        // Step 2: Simulate AJAX search request
        $_POST = [
            'action' => 'wp_plugin_filter',
            'nonce' => wp_create_nonce('wp_plugin_filter_action'),
            'search_term' => 'ecommerce',
            'installation_range' => '100k-1m',
            'update_timeframe' => 'last_month',
            'usability_rating' => 4,
            'health_score' => 70,
            'sort_by' => 'installations',
            'sort_direction' => 'desc',
            'page' => 1,
            'per_page' => 24
        ];
        
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        add_filter('wp_doing_ajax', '__return_true');
        
        // Mock API response
        $this->mock_api_response('query_plugins', [
            'plugins' => [
                $this->create_test_plugin_data([
                    'slug' => 'woocommerce',
                    'name' => 'WooCommerce',
                    'author' => 'Automattic',
                    'active_installs' => 5000000,
                    'rating' => 4.6,
                    'last_updated' => date('Y-m-d', strtotime('-15 days')),
                    'tags' => ['ecommerce', 'shop']
                ]),
                $this->create_test_plugin_data([
                    'slug' => 'easy-digital-downloads',
                    'name' => 'Easy Digital Downloads',
                    'author' => 'Easy Digital Downloads',
                    'active_installs' => 200000,
                    'rating' => 4.4,
                    'last_updated' => date('Y-m-d', strtotime('-10 days')),
                    'tags' => ['ecommerce', 'digital']
                ])
            ],
            'info' => ['page' => 1, 'pages' => 1, 'results' => 2]
        ]);
        
        // Step 3: Execute AJAX request
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected - AJAX handlers call wp_die()
        }
        $output = ob_get_clean();
        
        remove_filter('wp_doing_ajax', '__return_true');
        
        // Step 4: Verify response
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('plugins', $response['data']);
        $this->assertCount(2, $response['data']['plugins']);
        
        // Step 5: Verify plugin data enhancement
        $plugins = $response['data']['plugins'];
        foreach ($plugins as $plugin) {
            $this->assertArrayHasKey('usability_rating', $plugin);
            $this->assertArrayHasKey('health_score', $plugin);
            $this->assertArrayHasKey('health_color', $plugin);
            $this->assertArrayHasKey('last_updated_human', $plugin);
        }
        
        // Step 6: Verify filters were applied
        $this->assertArrayHasKey('filters_applied', $response['data']);
        $filters_applied = $response['data']['filters_applied'];
        $this->assertEquals('ecommerce', $filters_applied['search_term']);
        $this->assertEquals('100k-1m', $filters_applied['installation_range']);
        $this->assertEquals('installations', $filters_applied['sort_by']);
        
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test plugin rating calculation workflow
     */
    public function test_plugin_rating_calculation_workflow() {
        $this->set_current_user('admin');
        
        // Step 1: Request plugin rating calculation
        $_POST = [
            'action' => 'wp_plugin_rating',
            'nonce' => wp_create_nonce('wp_plugin_rating_action'),
            'plugin_slug' => 'woocommerce'
        ];
        
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        add_filter('wp_doing_ajax', '__return_true');
        
        // Mock plugin details API response
        $plugin_data = $this->create_test_plugin_data([
            'slug' => 'woocommerce',
            'name' => 'WooCommerce',
            'rating' => 4.6,
            'num_ratings' => 2000,
            'active_installs' => 5000000,
            'support_threads' => 100,
            'support_threads_resolved' => 85
        ]);
        
        $this->mock_api_response('plugin_information', $plugin_data);
        
        // Step 2: Execute rating calculation
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_rating');
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        remove_filter('wp_doing_ajax', '__return_true');
        
        // Step 3: Verify response
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        
        $data = $response['data'];
        $this->assertEquals('woocommerce', $data['plugin_slug']);
        $this->assertIsFloat($data['usability_rating']);
        $this->assertIsInt($data['health_score']);
        $this->assertArrayHasKey('health_color', $data);
        $this->assertArrayHasKey('health_description', $data);
        $this->assertArrayHasKey('calculation_breakdown', $data);
        
        // Step 4: Verify calculation breakdown
        $breakdown = $data['calculation_breakdown'];
        $this->assertArrayHasKey('usability', $breakdown);
        $this->assertArrayHasKey('health', $breakdown);
        
        // Step 5: Verify caching
        $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
        $cached_rating = $cache_manager->get('woocommerce_ratings', 'calculated_ratings');
        $this->assertIsArray($cached_rating);
        $this->assertEquals($data['usability_rating'], $cached_rating['usability_rating']);
        
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test admin settings workflow
     */
    public function test_admin_settings_workflow() {
        $this->set_current_user('admin');
        
        // Step 1: Load admin settings page
        set_current_screen('settings_page_wp-plugin-filters');
        
        // Step 2: Create admin settings instance
        $admin_settings = new WP_Plugin_Filters_Admin_Settings();
        $admin_settings->init();
        
        // Step 3: Simulate settings save
        $_POST = [
            'wp_plugin_filters_settings' => [
                'enable_caching' => '1',
                'cache_duration' => '7200',
                'api_timeout' => '45',
                'rate_limit_per_minute' => '30',
                'usability_weights' => [
                    'user_rating' => '50',
                    'rating_count' => '15',
                    'installation_count' => '20',
                    'support_responsiveness' => '15'
                ]
            ],
            'submit' => 'Save Settings',
            '_wpnonce' => wp_create_nonce('wp_plugin_filters_settings')
        ];
        
        // Step 4: Process settings save
        do_action('admin_init');
        
        // Step 5: Verify settings were saved
        $saved_settings = get_option('wp_plugin_filters_settings');
        $this->assertIsArray($saved_settings);
        $this->assertTrue($saved_settings['enable_caching']);
        $this->assertEquals(7200, $saved_settings['cache_duration']);
        $this->assertEquals(45, $saved_settings['api_timeout']);
        $this->assertEquals(30, $saved_settings['rate_limit_per_minute']);
        
        // Step 6: Verify weight validation
        $weights = $saved_settings['usability_weights'];
        $this->assertEquals(100, array_sum($weights)); // Should sum to 100
        
        unset($_POST);
    }

    /**
     * Test cache management workflow
     */
    public function test_cache_management_workflow() {
        $this->set_current_user('admin');
        
        // Step 1: Populate cache with test data
        $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
        $test_plugins = [
            $this->create_test_plugin_data(['slug' => 'plugin1']),
            $this->create_test_plugin_data(['slug' => 'plugin2'])
        ];
        
        $cache_manager->set('test_search_123', $test_plugins, 'search_results');
        $cache_manager->set('plugin1_ratings', ['usability_rating' => 4.5], 'calculated_ratings');
        
        // Step 2: Verify cache is populated
        $cached_search = $cache_manager->get('test_search_123', 'search_results');
        $this->assertIsArray($cached_search);
        $this->assertCount(2, $cached_search);
        
        $cached_rating = $cache_manager->get('plugin1_ratings', 'calculated_ratings');
        $this->assertIsArray($cached_rating);
        $this->assertEquals(4.5, $cached_rating['usability_rating']);
        
        // Step 3: Test cache clearing via AJAX
        $_POST = [
            'action' => 'wp_plugin_clear_cache',
            'nonce' => wp_create_nonce('wp_plugin_clear_cache'),
            'cache_type' => 'all'
        ];
        
        $_SERVER['HTTP_REFERER'] = admin_url('options-general.php?page=wp-plugin-filters');
        add_filter('wp_doing_ajax', '__return_true');
        
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_clear_cache');
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        remove_filter('wp_doing_ajax', '__return_true');
        
        // Step 4: Verify cache clear response
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('cleared_count', $response['data']);
        $this->assertGreaterThan(0, $response['data']['cleared_count']);
        
        // Step 5: Verify cache is actually cleared
        $cleared_search = $cache_manager->get('test_search_123', 'search_results');
        $this->assertNull($cleared_search);
        
        $cleared_rating = $cache_manager->get('plugin1_ratings', 'calculated_ratings');
        $this->assertNull($cleared_rating);
        
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test error handling workflow
     */
    public function test_error_handling_workflow() {
        $this->set_current_user('admin');
        
        // Test 1: Invalid nonce
        $_POST = [
            'action' => 'wp_plugin_filter',
            'nonce' => 'invalid_nonce',
            'search_term' => 'test'
        ];
        
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        add_filter('wp_doing_ajax', '__return_true');
        
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertEquals('nonce_verification_failed', $response['data']['code']);
        
        // Test 2: Insufficient permissions
        $this->set_current_user('subscriber');
        
        $_POST['nonce'] = wp_create_nonce('wp_plugin_filter_action');
        
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('insufficient_permissions', $response['data']['code']);
        
        // Test 3: API failure
        $this->set_current_user('admin');
        
        // Mock API failure
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                return new WP_Error('http_request_failed', 'Connection timeout');
            }
            return $preempt;
        }, 10, 3);
        
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('message', $response['data']);
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test multisite workflow
     */
    public function test_multisite_workflow() {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite not enabled');
        }
        
        // Step 1: Test network admin menu
        $this->set_current_user('super_admin');
        
        global $submenu;
        do_action('network_admin_menu');
        
        $this->assertArrayHasKey('settings.php', $submenu);
        $found_menu = false;
        foreach ($submenu['settings.php'] as $menu_item) {
            if ($menu_item[2] === 'wp-plugin-filters-network') {
                $found_menu = true;
                break;
            }
        }
        $this->assertTrue($found_menu);
        
        // Step 2: Test network settings save
        $network_settings = [
            'network_enable_caching' => true,
            'network_cache_duration' => 3600,
            'network_rate_limit' => 60
        ];
        
        update_site_option('wp_plugin_filters_network_settings', $network_settings);
        $saved_network_settings = get_site_option('wp_plugin_filters_network_settings');
        
        $this->assertEquals($network_settings, $saved_network_settings);
        
        // Step 3: Test individual site functionality
        $site_id = $this->simulate_multisite();
        
        // Plugin should work on individual sites
        $plugin = WP_Plugin_Directory_Filters::get_instance();
        $this->assertInstanceOf('WP_Plugin_Directory_Filters', $plugin);
        
        $this->restore_from_multisite();
    }

    /**
     * Test plugin activation/deactivation workflow
     */
    public function test_plugin_lifecycle_workflow() {
        // Test activation
        if (function_exists('wp_plugin_filters_activate')) {
            // Clear any existing data first
            delete_option('wp_plugin_filters_settings');
            wp_clear_scheduled_hook('wp_plugin_filters_cleanup');
            wp_clear_scheduled_hook('wp_plugin_filters_warm_cache');
            
            // Run activation
            wp_plugin_filters_activate();
            
            // Verify default settings created
            $settings = get_option('wp_plugin_filters_settings');
            $this->assertIsArray($settings);
            $this->assertArrayHasKey('enable_caching', $settings);
            
            // Verify cron jobs scheduled
            $this->assertNotFalse(wp_next_scheduled('wp_plugin_filters_cleanup'));
            $this->assertNotFalse(wp_next_scheduled('wp_plugin_filters_warm_cache'));
        }
        
        // Test deactivation
        if (function_exists('wp_plugin_filters_deactivate')) {
            wp_plugin_filters_deactivate();
            
            // Verify cron jobs cleared
            $this->assertFalse(wp_next_scheduled('wp_plugin_filters_cleanup'));
            $this->assertFalse(wp_next_scheduled('wp_plugin_filters_warm_cache'));
        }
    }

    /**
     * Test complete user journey - search to install
     */
    public function test_complete_user_journey() {
        $this->set_current_user('admin');
        
        // Step 1: User visits plugin installer
        set_current_screen('plugin-install');
        global $pagenow;
        $pagenow = 'plugin-install.php';
        
        do_action('load-plugin-install.php');
        
        // Step 2: User searches for plugins
        $search_response = $this->simulate_ajax_request('wp_plugin_filter', [
            'search_term' => 'contact form',
            'installation_range' => 'all',
            'sort_by' => 'popularity'
        ]);
        
        $this->assertTrue($search_response['success']);
        $plugins = $search_response['data']['plugins'];
        $this->assertNotEmpty($plugins);
        
        // Step 3: User clicks on a plugin for rating calculation
        $plugin_slug = $plugins[0]['slug'];
        $rating_response = $this->simulate_ajax_request('wp_plugin_rating', [
            'plugin_slug' => $plugin_slug
        ]);
        
        $this->assertTrue($rating_response['success']);
        $this->assertArrayHasKey('usability_rating', $rating_response['data']);
        $this->assertArrayHasKey('health_score', $rating_response['data']);
        
        // Step 4: User applies filters
        $filtered_response = $this->simulate_ajax_request('wp_plugin_filter', [
            'search_term' => 'contact form',
            'installation_range' => '100k-1m',
            'usability_rating' => 4,
            'health_score' => 70,
            'sort_by' => 'usability_rating',
            'sort_direction' => 'desc'
        ]);
        
        $this->assertTrue($filtered_response['success']);
        $filtered_plugins = $filtered_response['data']['plugins'];
        
        // Verify filtering worked
        foreach ($filtered_plugins as $plugin) {
            $this->assertGreaterThanOrEqual(100000, $plugin['active_installs']);
            $this->assertLessThan(1000000, $plugin['active_installs']);
            if (isset($plugin['usability_rating'])) {
                $this->assertGreaterThanOrEqual(4.0, $plugin['usability_rating']);
            }
            if (isset($plugin['health_score'])) {
                $this->assertGreaterThanOrEqual(70, $plugin['health_score']);
            }
        }
        
        // Step 5: User clears filters
        $cleared_response = $this->simulate_ajax_request('wp_plugin_filter', [
            'search_term' => 'contact form',
            'installation_range' => 'all',
            'usability_rating' => 0,
            'health_score' => 0,
            'sort_by' => 'relevance'
        ]);
        
        $this->assertTrue($cleared_response['success']);
        $this->assertGreaterThanOrEqual(count($filtered_plugins), count($cleared_response['data']['plugins']));
    }

    /**
     * Helper method to simulate AJAX requests
     */
    private function simulate_ajax_request($action, $data = []) {
        $default_data = [
            'action' => $action,
            'nonce' => wp_create_nonce(str_replace('wp_plugin_', '', $action) . '_action'),
            'page' => 1,
            'per_page' => 24
        ];
        
        $_POST = array_merge($default_data, $data);
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        
        add_filter('wp_doing_ajax', '__return_true');
        
        // Mock API responses based on action
        if ($action === 'wp_plugin_filter') {
            $this->mock_search_api_response($data);
        } elseif ($action === 'wp_plugin_rating') {
            $this->mock_plugin_details_api_response($data['plugin_slug'] ?? 'test-plugin');
        }
        
        ob_start();
        try {
            do_action('wp_ajax_' . $action);
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST, $_SERVER['HTTP_REFERER']);
        
        return json_decode($output, true);
    }

    /**
     * Mock search API response
     */
    private function mock_search_api_response($data) {
        $search_term = $data['search_term'] ?? '';
        $installation_range = $data['installation_range'] ?? 'all';
        
        $plugins = [];
        
        if (strpos($search_term, 'contact form') !== false) {
            $plugins[] = $this->create_test_plugin_data([
                'slug' => 'contact-form-7',
                'name' => 'Contact Form 7',
                'active_installs' => 5000000,
                'rating' => 4.1,
                'tags' => ['contact', 'form']
            ]);
            
            $plugins[] = $this->create_test_plugin_data([
                'slug' => 'wpforms-lite',
                'name' => 'WPForms Lite',
                'active_installs' => 1000000,
                'rating' => 4.6,
                'tags' => ['contact', 'form', 'builder']
            ]);
        }
        
        // Apply installation range filter
        if ($installation_range !== 'all') {
            $plugins = array_filter($plugins, function($plugin) use ($installation_range) {
                $installs = $plugin['active_installs'];
                switch ($installation_range) {
                    case '100k-1m':
                        return $installs >= 100000 && $installs < 1000000;
                    case '1m-plus':
                        return $installs >= 1000000;
                    default:
                        return true;
                }
            });
        }
        
        $this->mock_api_response('query_plugins', [
            'plugins' => array_values($plugins),
            'info' => ['page' => 1, 'pages' => 1, 'results' => count($plugins)]
        ]);
    }

    /**
     * Mock plugin details API response
     */
    private function mock_plugin_details_api_response($slug) {
        $plugin_data = $this->create_test_plugin_data([
            'slug' => $slug,
            'rating' => 4.5,
            'num_ratings' => 500,
            'active_installs' => 100000,
            'support_threads' => 50,
            'support_threads_resolved' => 40
        ]);
        
        $this->mock_api_response('plugin_information', $plugin_data);
    }
}