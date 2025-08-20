<?php
/**
 * WordPress Version Compatibility Tests
 *
 * @package WP_Plugin_Directory_Filters
 */

class Test_WordPress_Versions extends WP_Plugin_Filters_Test_Case {

    /**
     * Test minimum WordPress version requirements
     */
    public function test_minimum_wordpress_version() {
        global $wp_version;
        
        // Plugin requires WordPress 5.8+
        $required_version = '5.8';
        
        $this->assertTrue(
            version_compare($wp_version, $required_version, '>='),
            "WordPress version {$wp_version} does not meet minimum requirement of {$required_version}"
        );
    }

    /**
     * Test WordPress function availability
     */
    public function test_wordpress_function_availability() {
        $required_functions = [
            // Core WordPress functions used by the plugin
            'add_action',
            'add_filter',
            'wp_enqueue_script',
            'wp_enqueue_style',
            'wp_localize_script',
            'wp_create_nonce',
            'wp_verify_nonce',
            'current_user_can',
            'wp_remote_post',
            'wp_remote_retrieve_response_code',
            'wp_remote_retrieve_body',
            'sanitize_text_field',
            'sanitize_key',
            'esc_html',
            'esc_url_raw',
            'wp_kses_post',
            'get_transient',
            'set_transient',
            'delete_transient',
            'wp_cache_get',
            'wp_cache_set',
            'wp_cache_delete',
            'admin_url',
            'network_admin_url',
            'is_multisite',
            'wp_doing_ajax',
            'wp_send_json_success',
            'wp_send_json_error',
            'wp_schedule_event',
            'wp_next_scheduled',
            'wp_clear_scheduled_hook',
            'get_current_user_id',
            'wp_get_current_user',
            'get_option',
            'update_option',
            'delete_option',
            'get_site_option',
            'update_site_option',
            'delete_site_option'
        ];

        foreach ($required_functions as $function) {
            $this->assertTrue(
                function_exists($function),
                "Required WordPress function '{$function}' is not available"
            );
        }
    }

    /**
     * Test WordPress class availability
     */
    public function test_wordpress_class_availability() {
        $required_classes = [
            'WP_Error',
            'WP_User',
            'WP_Query',
            'WP_Http',
            'WP_Object_Cache'
        ];

        foreach ($required_classes as $class) {
            $this->assertTrue(
                class_exists($class),
                "Required WordPress class '{$class}' is not available"
            );
        }
    }

    /**
     * Test WordPress constant availability
     */
    public function test_wordpress_constant_availability() {
        $required_constants = [
            'ABSPATH',
            'WP_CONTENT_DIR',
            'WP_PLUGIN_DIR',
            'WPINC',
            'DB_NAME',
            'DB_USER',
            'DB_PASSWORD',
            'DB_HOST'
        ];

        foreach ($required_constants as $constant) {
            $this->assertTrue(
                defined($constant),
                "Required WordPress constant '{$constant}' is not defined"
            );
        }
    }

    /**
     * Test hook system compatibility
     */
    public function test_hook_system_compatibility() {
        // Test action hooks
        $test_action_fired = false;
        add_action('test_plugin_action', function() use (&$test_action_fired) {
            $test_action_fired = true;
        });
        
        do_action('test_plugin_action');
        $this->assertTrue($test_action_fired, 'Action hook system not working');

        // Test filter hooks
        add_filter('test_plugin_filter', function($value) {
            return $value . '_filtered';
        });
        
        $result = apply_filters('test_plugin_filter', 'test');
        $this->assertEquals('test_filtered', $result, 'Filter hook system not working');

        // Test hook priorities
        $order = [];
        add_action('test_priority_action', function() use (&$order) {
            $order[] = 'high';
        }, 5);
        
        add_action('test_priority_action', function() use (&$order) {
            $order[] = 'low';
        }, 15);
        
        add_action('test_priority_action', function() use (&$order) {
            $order[] = 'default';
        });
        
        do_action('test_priority_action');
        $this->assertEquals(['high', 'default', 'low'], $order, 'Hook priorities not working correctly');
    }

    /**
     * Test AJAX system compatibility
     */
    public function test_ajax_system_compatibility() {
        $this->set_current_user('admin');
        
        // Test wp_doing_ajax() function
        $this->assertFalse(wp_doing_ajax(), 'wp_doing_ajax should return false outside AJAX');
        
        add_filter('wp_doing_ajax', '__return_true');
        $this->assertTrue(wp_doing_ajax(), 'wp_doing_ajax filter not working');
        remove_filter('wp_doing_ajax', '__return_true');
        
        // Test AJAX action registration
        $ajax_fired = false;
        add_action('wp_ajax_test_compatibility', function() use (&$ajax_fired) {
            $ajax_fired = true;
            wp_die();
        });
        
        // Simulate AJAX request
        $_POST['action'] = 'test_compatibility';
        add_filter('wp_doing_ajax', '__return_true');
        
        try {
            do_action('wp_ajax_test_compatibility');
        } catch (WPDieException $e) {
            // Expected
        }
        
        $this->assertTrue($ajax_fired, 'AJAX action system not working');
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST['action']);
    }

    /**
     * Test database compatibility
     */
    public function test_database_compatibility() {
        global $wpdb;
        
        // Test basic database operations
        $this->assertInstanceOf('wpdb', $wpdb, 'WordPress database object not available');
        
        // Test options table
        $test_option = 'wp_plugin_filters_compat_test_' . time();
        $test_value = ['test' => 'data', 'timestamp' => time()];
        
        update_option($test_option, $test_value);
        $retrieved = get_option($test_option);
        
        $this->assertEquals($test_value, $retrieved, 'Options table not working correctly');
        
        delete_option($test_option);
        $deleted = get_option($test_option, 'default');
        
        $this->assertEquals('default', $deleted, 'Option deletion not working');
        
        // Test transients
        $transient_key = 'wp_plugin_filters_trans_test_' . time();
        $transient_value = 'test_transient_data';
        
        set_transient($transient_key, $transient_value, 3600);
        $retrieved_transient = get_transient($transient_key);
        
        $this->assertEquals($transient_value, $retrieved_transient, 'Transients not working correctly');
        
        delete_transient($transient_key);
        $deleted_transient = get_transient($transient_key);
        
        $this->assertFalse($deleted_transient, 'Transient deletion not working');
        
        // Test prepared statements
        $prepared = $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $test_option);
        $this->assertStringContainsString($test_option, $prepared, 'Database prepare function not working');
    }

    /**
     * Test caching system compatibility
     */
    public function test_caching_system_compatibility() {
        // Test object cache
        $cache_key = 'wp_plugin_filters_cache_test';
        $cache_value = ['test' => 'cache_data', 'time' => time()];
        $cache_group = 'test_group';
        
        wp_cache_set($cache_key, $cache_value, $cache_group);
        $cached = wp_cache_get($cache_key, $cache_group);
        
        $this->assertEquals($cache_value, $cached, 'Object cache not working correctly');
        
        wp_cache_delete($cache_key, $cache_group);
        $deleted_cache = wp_cache_get($cache_key, $cache_group);
        
        $this->assertFalse($deleted_cache, 'Cache deletion not working');
        
        // Test cache flush
        wp_cache_set($cache_key, $cache_value, $cache_group);
        wp_cache_flush();
        $flushed_cache = wp_cache_get($cache_key, $cache_group);
        
        $this->assertFalse($flushed_cache, 'Cache flush not working');
    }

    /**
     * Test HTTP API compatibility
     */
    public function test_http_api_compatibility() {
        // Test wp_remote_post function availability and basic functionality
        $this->assertTrue(function_exists('wp_remote_post'), 'wp_remote_post function not available');
        $this->assertTrue(function_exists('wp_remote_get'), 'wp_remote_get function not available');
        $this->assertTrue(function_exists('wp_remote_retrieve_response_code'), 'wp_remote_retrieve_response_code function not available');
        $this->assertTrue(function_exists('wp_remote_retrieve_body'), 'wp_remote_retrieve_body function not available');
        
        // Test basic HTTP request structure
        $mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode(['test' => 'data'])
        ];
        
        // Mock HTTP request
        add_filter('pre_http_request', function($preempt, $args, $url) use ($mock_response) {
            if (strpos($url, 'httpbin.org') !== false) {
                return $mock_response;
            }
            return $preempt;
        }, 10, 3);
        
        $response = wp_remote_post('https://httpbin.org/post', [
            'body' => ['test' => 'data'],
            'timeout' => 10
        ]);
        
        $this->assertIsArray($response, 'HTTP response should be an array');
        $this->assertArrayHasKey('response', $response, 'HTTP response missing response key');
        $this->assertArrayHasKey('body', $response, 'HTTP response missing body key');
        
        $response_code = wp_remote_retrieve_response_code($response);
        $this->assertEquals(200, $response_code, 'HTTP response code retrieval not working');
        
        $body = wp_remote_retrieve_body($response);
        $this->assertIsString($body, 'HTTP response body retrieval not working');
    }

    /**
     * Test user and capability system compatibility
     */
    public function test_user_capability_system_compatibility() {
        // Test user functions
        $this->assertTrue(function_exists('get_current_user_id'), 'get_current_user_id function not available');
        $this->assertTrue(function_exists('wp_get_current_user'), 'wp_get_current_user function not available');
        $this->assertTrue(function_exists('current_user_can'), 'current_user_can function not available');
        
        // Test capability checking
        $this->set_current_user('admin');
        $this->assertTrue(current_user_can('install_plugins'), 'Admin should have install_plugins capability');
        $this->assertTrue(current_user_can('manage_options'), 'Admin should have manage_options capability');
        
        $this->set_current_user('subscriber');
        $this->assertFalse(current_user_can('install_plugins'), 'Subscriber should not have install_plugins capability');
        $this->assertFalse(current_user_can('manage_options'), 'Subscriber should not have manage_options capability');
        
        // Test user object
        $this->set_current_user('admin');
        $user = wp_get_current_user();
        $this->assertInstanceOf('WP_User', $user, 'wp_get_current_user should return WP_User object');
        $this->assertGreaterThan(0, $user->ID, 'User should have valid ID');
    }

    /**
     * Test multisite compatibility
     */
    public function test_multisite_compatibility() {
        // Test multisite function availability
        $this->assertTrue(function_exists('is_multisite'), 'is_multisite function not available');
        $this->assertTrue(function_exists('get_site_option'), 'get_site_option function not available');
        $this->assertTrue(function_exists('update_site_option'), 'update_site_option function not available');
        $this->assertTrue(function_exists('delete_site_option'), 'delete_site_option function not available');
        
        if (is_multisite()) {
            // Test multisite-specific functionality
            $this->assertTrue(function_exists('switch_to_blog'), 'switch_to_blog function not available');
            $this->assertTrue(function_exists('restore_current_blog'), 'restore_current_blog function not available');
            $this->assertTrue(function_exists('get_current_blog_id'), 'get_current_blog_id function not available');
            $this->assertTrue(function_exists('is_super_admin'), 'is_super_admin function not available');
            
            // Test site options
            $test_site_option = 'wp_plugin_filters_site_test_' . time();
            $test_site_value = ['multisite' => true, 'timestamp' => time()];
            
            update_site_option($test_site_option, $test_site_value);
            $retrieved_site_option = get_site_option($test_site_option);
            
            $this->assertEquals($test_site_value, $retrieved_site_option, 'Site options not working correctly');
            
            delete_site_option($test_site_option);
            $deleted_site_option = get_site_option($test_site_option, 'default');
            
            $this->assertEquals('default', $deleted_site_option, 'Site option deletion not working');
            
            // Test super admin capability
            if (isset($this->test_users['super_admin'])) {
                $this->assertTrue(is_super_admin($this->test_users['super_admin']), 'Super admin detection not working');
            }
        } else {
            $this->markTestSkipped('Multisite not enabled, skipping multisite-specific tests');
        }
    }

    /**
     * Test JavaScript/jQuery compatibility
     */
    public function test_javascript_compatibility() {
        // Test that WordPress includes jQuery
        global $wp_scripts;
        
        $this->assertInstanceOf('WP_Scripts', $wp_scripts, 'WP_Scripts not available');
        
        // Test jQuery registration
        wp_enqueue_script('jquery');
        $this->assertTrue(wp_script_is('jquery', 'enqueued'), 'jQuery not available');
        
        // Test wp-util dependency
        wp_enqueue_script('wp-util');
        $this->assertTrue(wp_script_is('wp-util', 'enqueued'), 'wp-util not available');
        
        // Test script localization
        wp_localize_script('jquery', 'testLocalization', ['test' => 'data']);
        $localized_data = $wp_scripts->get_data('jquery', 'data');
        $this->assertStringContainsString('testLocalization', $localized_data, 'Script localization not working');
    }

    /**
     * Test CSS/styling compatibility
     */
    public function test_css_compatibility() {
        global $wp_styles;
        
        $this->assertInstanceOf('WP_Styles', $wp_styles, 'WP_Styles not available');
        
        // Test style enqueuing
        wp_enqueue_style('wp-admin');
        $this->assertTrue(wp_style_is('wp-admin', 'enqueued'), 'wp-admin styles not available');
        
        wp_enqueue_style('dashicons');
        $this->assertTrue(wp_style_is('dashicons', 'enqueued'), 'Dashicons not available');
    }

    /**
     * Test cron system compatibility
     */
    public function test_cron_system_compatibility() {
        // Test cron functions
        $this->assertTrue(function_exists('wp_schedule_event'), 'wp_schedule_event function not available');
        $this->assertTrue(function_exists('wp_next_scheduled'), 'wp_next_scheduled function not available');
        $this->assertTrue(function_exists('wp_clear_scheduled_hook'), 'wp_clear_scheduled_hook function not available');
        $this->assertTrue(function_exists('wp_get_schedules'), 'wp_get_schedules function not available');
        
        // Test cron scheduling
        $hook = 'wp_plugin_filters_compat_test_' . time();
        $args = ['test' => 'data'];
        
        wp_schedule_event(time() + 3600, 'hourly', $hook, $args);
        $next_scheduled = wp_next_scheduled($hook, $args);
        
        $this->assertNotFalse($next_scheduled, 'Cron event scheduling not working');
        $this->assertGreaterThan(time(), $next_scheduled, 'Scheduled time should be in the future');
        
        wp_clear_scheduled_hook($hook, $args);
        $cleared_scheduled = wp_next_scheduled($hook, $args);
        
        $this->assertFalse($cleared_scheduled, 'Cron event clearing not working');
        
        // Test cron schedules
        $schedules = wp_get_schedules();
        $this->assertIsArray($schedules, 'wp_get_schedules should return array');
        $this->assertArrayHasKey('hourly', $schedules, 'Hourly schedule not available');
        $this->assertArrayHasKey('daily', $schedules, 'Daily schedule not available');
    }

    /**
     * Test plugin activation/deactivation system compatibility
     */
    public function test_plugin_lifecycle_compatibility() {
        // Test hook registration functions
        $this->assertTrue(function_exists('register_activation_hook'), 'register_activation_hook function not available');
        $this->assertTrue(function_exists('register_deactivation_hook'), 'register_deactivation_hook function not available');
        $this->assertTrue(function_exists('register_uninstall_hook'), 'register_uninstall_hook function not available');
        
        // Test plugin data functions
        $this->assertTrue(function_exists('get_plugin_data'), 'get_plugin_data function not available');
        $this->assertTrue(function_exists('is_plugin_active'), 'is_plugin_active function not available');
        
        // Test plugin constants
        $this->assertTrue(defined('WP_PLUGIN_DIR'), 'WP_PLUGIN_DIR not defined');
        $this->assertTrue(defined('WPMU_PLUGIN_DIR'), 'WPMU_PLUGIN_DIR not defined');
        
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Test plugin data retrieval
        $plugin_file = WP_PLUGIN_FILTERS_PLUGIN_DIR . 'wp-plugin-directory-filters.php';
        if (file_exists($plugin_file)) {
            $plugin_data = get_plugin_data($plugin_file);
            $this->assertIsArray($plugin_data, 'get_plugin_data should return array');
            $this->assertArrayHasKey('Name', $plugin_data, 'Plugin data missing Name');
            $this->assertArrayHasKey('Version', $plugin_data, 'Plugin data missing Version');
        }
    }

    /**
     * Test internationalization (i18n) compatibility
     */
    public function test_i18n_compatibility() {
        // Test i18n functions
        $this->assertTrue(function_exists('__'), '__ function not available');
        $this->assertTrue(function_exists('_e'), '_e function not available');
        $this->assertTrue(function_exists('_n'), '_n function not available');
        $this->assertTrue(function_exists('_x'), '_x function not available');
        $this->assertTrue(function_exists('load_plugin_textdomain'), 'load_plugin_textdomain function not available');
        $this->assertTrue(function_exists('is_textdomain_loaded'), 'is_textdomain_loaded function not available');
        
        // Test text domain loading
        $textdomain = 'wp-plugin-filters';
        load_plugin_textdomain($textdomain, false, dirname(WP_PLUGIN_FILTERS_BASENAME) . '/languages');
        
        // Test basic translation functions
        $translated = __('Loading...', $textdomain);
        $this->assertIsString($translated, 'Translation function should return string');
        
        // Test plural translations
        $singular = 'One plugin';
        $plural = '%d plugins';
        $count = 5;
        
        $plural_translation = _n($singular, $plural, $count, $textdomain);
        $this->assertIsString($plural_translation, 'Plural translation should return string');
    }

    /**
     * Test WordPress version-specific features
     */
    public function test_version_specific_features() {
        global $wp_version;
        
        // Test features available in WordPress 5.8+
        if (version_compare($wp_version, '5.8', '>=')) {
            $this->assertTrue(function_exists('wp_is_block_theme'), 'wp_is_block_theme should be available in WP 5.8+');
        }
        
        // Test features available in WordPress 6.0+
        if (version_compare($wp_version, '6.0', '>=')) {
            $this->assertTrue(function_exists('wp_get_global_settings'), 'wp_get_global_settings should be available in WP 6.0+');
        }
        
        // Test features available in WordPress 6.1+
        if (version_compare($wp_version, '6.1', '>=')) {
            $this->assertTrue(function_exists('wp_is_development_mode'), 'wp_is_development_mode should be available in WP 6.1+');
        }
        
        // Test that deprecated functions still work (for backward compatibility)
        if (function_exists('get_bloginfo')) {
            $charset = get_bloginfo('charset');
            $this->assertIsString($charset, 'get_bloginfo should still work for backward compatibility');
        }
    }

    /**
     * Test PHP version compatibility
     */
    public function test_php_version_compatibility() {
        $required_php_version = '7.4';
        $current_php_version = PHP_VERSION;
        
        $this->assertTrue(
            version_compare($current_php_version, $required_php_version, '>='),
            "PHP version {$current_php_version} does not meet minimum requirement of {$required_php_version}"
        );
        
        // Test required PHP features
        $required_extensions = [
            'json',
            'mbstring',
            'curl'
        ];
        
        foreach ($required_extensions as $extension) {
            $this->assertTrue(
                extension_loaded($extension),
                "Required PHP extension '{$extension}' is not loaded"
            );
        }
        
        // Test required PHP functions
        $required_php_functions = [
            'json_encode',
            'json_decode',
            'curl_init',
            'mb_strlen',
            'hash',
            'serialize',
            'unserialize'
        ];
        
        foreach ($required_php_functions as $function) {
            $this->assertTrue(
                function_exists($function),
                "Required PHP function '{$function}' is not available"
            );
        }
    }
}