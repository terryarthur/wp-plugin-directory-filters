<?php
/**
 * Tests for WP_Plugin_Filters_Security_Handler class
 *
 * @package WP_Plugin_Directory_Filters
 */

class Test_WP_Plugin_Filters_Security_Handler extends WP_Plugin_Filters_Test_Case {

    /**
     * Security Handler instance
     *
     * @var WP_Plugin_Filters_Security_Handler
     */
    private $security_handler;

    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->security_handler = new WP_Plugin_Filters_Security_Handler();
    }

    /**
     * Test security handler instantiation
     */
    public function test_security_handler_instantiation() {
        $this->assertInstanceOf('WP_Plugin_Filters_Security_Handler', $this->security_handler);
    }

    /**
     * Test AJAX request validation with valid nonce and capability
     */
    public function test_validate_ajax_request_success() {
        $this->set_current_user('admin');
        
        // Simulate AJAX request
        $_POST['nonce'] = wp_create_nonce('test_action');
        add_filter('wp_doing_ajax', '__return_true');
        $_SERVER['HTTP_REFERER'] = admin_url('admin.php');

        $result = $this->security_handler->validate_ajax_request('test_action', 'install_plugins');

        $this->assertTrue($result);
        
        // Clean up
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST['nonce'], $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test AJAX request validation failure - not AJAX
     */
    public function test_validate_ajax_request_not_ajax() {
        $this->set_current_user('admin');
        
        $result = $this->security_handler->validate_ajax_request('test_action', 'install_plugins');

        $this->assertIsWPError($result);
        $this->assertEquals('not_ajax', $result->get_error_code());
    }

    /**
     * Test AJAX request validation failure - invalid nonce
     */
    public function test_validate_ajax_request_invalid_nonce() {
        $this->set_current_user('admin');
        
        // Simulate AJAX request with invalid nonce
        $_POST['nonce'] = 'invalid_nonce';
        add_filter('wp_doing_ajax', '__return_true');
        $_SERVER['HTTP_REFERER'] = admin_url('admin.php');

        $result = $this->security_handler->validate_ajax_request('test_action', 'install_plugins');

        $this->assertIsWPError($result);
        $this->assertEquals('nonce_verification_failed', $result->get_error_code());
        
        // Clean up
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST['nonce'], $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test AJAX request validation failure - insufficient permissions
     */
    public function test_validate_ajax_request_insufficient_permissions() {
        $this->set_current_user('subscriber');
        
        // Simulate AJAX request
        $_POST['nonce'] = wp_create_nonce('test_action');
        add_filter('wp_doing_ajax', '__return_true');
        $_SERVER['HTTP_REFERER'] = admin_url('admin.php');

        $result = $this->security_handler->validate_ajax_request('test_action', 'install_plugins');

        $this->assertIsWPError($result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
        
        // Clean up
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST['nonce'], $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test AJAX request validation failure - invalid referrer
     */
    public function test_validate_ajax_request_invalid_referrer() {
        $this->set_current_user('admin');
        
        // Simulate AJAX request with external referrer
        $_POST['nonce'] = wp_create_nonce('test_action');
        add_filter('wp_doing_ajax', '__return_true');
        $_SERVER['HTTP_REFERER'] = 'http://evil.com/attack.php';

        $result = $this->security_handler->validate_ajax_request('test_action', 'install_plugins');

        $this->assertIsWPError($result);
        $this->assertEquals('invalid_referrer', $result->get_error_code());
        
        // Clean up
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST['nonce'], $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test search parameter sanitization
     */
    public function test_sanitize_search_params() {
        $malicious_params = [
            'search_term' => '<script>alert("xss")</script>search term',
            'installation_range' => 'invalid_range',
            'update_timeframe' => 'invalid_timeframe',
            'usability_rating' => 'invalid_rating',
            'health_score' => 'invalid_score',
            'sort_by' => 'invalid_sort',
            'sort_direction' => 'invalid_direction',
            'page' => 'invalid_page',
            'per_page' => '999'
        ];

        $sanitized = $this->security_handler->sanitize_search_params($malicious_params);

        // Verify sanitization
        $this->assertStringNotContainsString('<script>', $sanitized['search_term']);
        $this->assertEquals('all', $sanitized['installation_range']); // Should default to 'all'
        $this->assertEquals('all', $sanitized['update_timeframe']); // Should default to 'all'
        $this->assertEquals(0, $sanitized['usability_rating']); // Should default to 0
        $this->assertEquals(0, $sanitized['health_score']); // Should default to 0
        $this->assertEquals('relevance', $sanitized['sort_by']); // Should default to 'relevance'
        $this->assertEquals('desc', $sanitized['sort_direction']); // Should default to 'desc'
        $this->assertEquals(1, $sanitized['page']); // Should default to 1
        $this->assertEquals(48, $sanitized['per_page']); // Should be limited to 48
    }

    /**
     * Test plugin slug validation
     */
    public function test_validate_plugin_slug() {
        // Valid slug
        $valid_slug = 'my-awesome-plugin';
        $result = $this->security_handler->validate_plugin_slug($valid_slug);
        $this->assertEquals($valid_slug, $result);

        // Invalid characters
        $invalid_slug = 'my_awesome@plugin!';
        $result = $this->security_handler->validate_plugin_slug($invalid_slug);
        $this->assertIsWPError($result);
        $this->assertEquals('invalid_slug_format', $result->get_error_code());

        // Empty slug
        $empty_slug = '';
        $result = $this->security_handler->validate_plugin_slug($empty_slug);
        $this->assertIsWPError($result);
        $this->assertEquals('invalid_slug', $result->get_error_code());

        // Too long slug
        $long_slug = str_repeat('a', 101);
        $result = $this->security_handler->validate_plugin_slug($long_slug);
        $this->assertIsWPError($result);
        $this->assertEquals('slug_too_long', $result->get_error_code());
    }

    /**
     * Test rate limiting functionality
     */
    public function test_rate_limiting() {
        $this->set_current_user('admin');

        // First request should succeed
        $result = $this->security_handler->check_rate_limit('test_action', 5, 60);
        $this->assertTrue($result);

        // Subsequent requests within limit should succeed
        for ($i = 0; $i < 4; $i++) {
            $result = $this->security_handler->check_rate_limit('test_action', 5, 60);
            $this->assertTrue($result, "Request {$i} should succeed within rate limit");
        }

        // Request exceeding limit should fail
        $result = $this->security_handler->check_rate_limit('test_action', 5, 60);
        $this->assertIsWPError($result);
        $this->assertEquals('rate_limit_exceeded', $result->get_error_code());
    }

    /**
     * Test rate limiting with different actions
     */
    public function test_rate_limiting_different_actions() {
        $this->set_current_user('admin');

        // Fill rate limit for action1
        for ($i = 0; $i < 5; $i++) {
            $this->security_handler->check_rate_limit('action1', 5, 60);
        }

        // action1 should be rate limited
        $result = $this->security_handler->check_rate_limit('action1', 5, 60);
        $this->assertIsWPError($result);

        // action2 should still work
        $result = $this->security_handler->check_rate_limit('action2', 5, 60);
        $this->assertTrue($result);
    }

    /**
     * Test HTML output escaping
     */
    public function test_escape_output() {
        // String escaping
        $malicious_string = '<script>alert("xss")</script>';
        $escaped = $this->security_handler->escape_output($malicious_string);
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $escaped);

        // Array escaping
        $malicious_array = [
            'safe' => 'Safe content',
            'malicious' => '<script>alert("xss")</script>',
            'nested' => [
                'also_malicious' => '<iframe src="evil.com"></iframe>'
            ]
        ];

        $escaped_array = $this->security_handler->escape_output($malicious_array);
        $this->assertEquals('Safe content', $escaped_array['safe']);
        $this->assertStringNotContainsString('<script>', $escaped_array['malicious']);
        $this->assertStringNotContainsString('<iframe>', $escaped_array['nested']['also_malicious']);

        // Object escaping
        $malicious_object = (object) [
            'safe' => 'Safe content',
            'malicious' => '<script>alert("xss")</script>'
        ];

        $escaped_object = $this->security_handler->escape_output($malicious_object);
        $this->assertEquals('Safe content', $escaped_object->safe);
        $this->assertStringNotContainsString('<script>', $escaped_object->malicious);
    }

    /**
     * Test URL validation
     */
    public function test_validate_url() {
        // Valid HTTP URL
        $valid_http = 'http://example.com/page';
        $result = $this->security_handler->validate_url($valid_http);
        $this->assertEquals($valid_http, $result);

        // Valid HTTPS URL
        $valid_https = 'https://example.com/page';
        $result = $this->security_handler->validate_url($valid_https);
        $this->assertEquals($valid_https, $result);

        // Invalid scheme
        $invalid_scheme = 'javascript:alert("xss")';
        $result = $this->security_handler->validate_url($invalid_scheme);
        $this->assertFalse($result);

        // FTP URL (not allowed)
        $ftp_url = 'ftp://example.com/file';
        $result = $this->security_handler->validate_url($ftp_url);
        $this->assertFalse($result);

        // Malformed URL
        $malformed = 'not-a-url';
        $result = $this->security_handler->validate_url($malformed);
        $this->assertFalse($result);
    }

    /**
     * Test installation range sanitization
     */
    public function test_sanitize_installation_range() {
        $valid_ranges = ['all', '0-1k', '1k-10k', '10k-100k', '100k-1m', '1m-plus'];
        
        foreach ($valid_ranges as $range) {
            $sanitized = $this->security_handler->sanitize_search_params(['installation_range' => $range]);
            $this->assertEquals($range, $sanitized['installation_range']);
        }

        // Invalid range should default to 'all'
        $sanitized = $this->security_handler->sanitize_search_params(['installation_range' => 'invalid']);
        $this->assertEquals('all', $sanitized['installation_range']);
    }

    /**
     * Test timeframe sanitization
     */
    public function test_sanitize_timeframe() {
        $valid_timeframes = ['all', 'last_week', 'last_month', 'last_3months', 'last_6months', 'last_year', 'older'];
        
        foreach ($valid_timeframes as $timeframe) {
            $sanitized = $this->security_handler->sanitize_search_params(['update_timeframe' => $timeframe]);
            $this->assertEquals($timeframe, $sanitized['update_timeframe']);
        }

        // Invalid timeframe should default to 'all'
        $sanitized = $this->security_handler->sanitize_search_params(['update_timeframe' => 'invalid']);
        $this->assertEquals('all', $sanitized['update_timeframe']);
    }

    /**
     * Test rating value sanitization
     */
    public function test_sanitize_rating() {
        // Valid ratings
        $test_cases = [
            ['input' => 4.5, 'expected' => 4.5],
            ['input' => 0, 'expected' => 0],
            ['input' => 5, 'expected' => 5],
            ['input' => -1, 'expected' => 0], // Should clamp to 0
            ['input' => 6, 'expected' => 5], // Should clamp to 5
            ['input' => 'invalid', 'expected' => 0], // Should convert to 0
        ];

        foreach ($test_cases as $case) {
            $sanitized = $this->security_handler->sanitize_search_params(['usability_rating' => $case['input']]);
            $this->assertEquals($case['expected'], $sanitized['usability_rating']);
        }
    }

    /**
     * Test health score sanitization
     */
    public function test_sanitize_health_score() {
        $test_cases = [
            ['input' => 50, 'expected' => 50],
            ['input' => 0, 'expected' => 0],
            ['input' => 100, 'expected' => 100],
            ['input' => -10, 'expected' => 0], // Should clamp to 0
            ['input' => 150, 'expected' => 100], // Should clamp to 100
            ['input' => 'invalid', 'expected' => 0], // Should convert to 0
        ];

        foreach ($test_cases as $case) {
            $sanitized = $this->security_handler->sanitize_search_params(['health_score' => $case['input']]);
            $this->assertEquals($case['expected'], $sanitized['health_score']);
        }
    }

    /**
     * Test sort field sanitization
     */
    public function test_sanitize_sort_field() {
        $valid_fields = ['relevance', 'popularity', 'rating', 'updated', 'installations', 'usability_rating', 'health_score'];
        
        foreach ($valid_fields as $field) {
            $sanitized = $this->security_handler->sanitize_search_params(['sort_by' => $field]);
            $this->assertEquals($field, $sanitized['sort_by']);
        }

        // Invalid field should default to 'relevance'
        $sanitized = $this->security_handler->sanitize_search_params(['sort_by' => 'invalid']);
        $this->assertEquals('relevance', $sanitized['sort_by']);
    }

    /**
     * Test sort direction sanitization
     */
    public function test_sanitize_sort_direction() {
        // Valid directions
        $sanitized = $this->security_handler->sanitize_search_params(['sort_direction' => 'asc']);
        $this->assertEquals('asc', $sanitized['sort_direction']);

        $sanitized = $this->security_handler->sanitize_search_params(['sort_direction' => 'desc']);
        $this->assertEquals('desc', $sanitized['sort_direction']);

        // Invalid direction should default to 'desc'
        $sanitized = $this->security_handler->sanitize_search_params(['sort_direction' => 'invalid']);
        $this->assertEquals('desc', $sanitized['sort_direction']);
    }

    /**
     * Test page number sanitization
     */
    public function test_sanitize_page_number() {
        $test_cases = [
            ['input' => 1, 'expected' => 1],
            ['input' => 50, 'expected' => 50],
            ['input' => 1000, 'expected' => 1000], // Maximum allowed
            ['input' => 1001, 'expected' => 1000], // Should clamp to max
            ['input' => 0, 'expected' => 1], // Should clamp to min
            ['input' => -5, 'expected' => 1], // Should clamp to min
            ['input' => 'invalid', 'expected' => 1], // Should convert to 1
        ];

        foreach ($test_cases as $case) {
            $sanitized = $this->security_handler->sanitize_search_params(['page' => $case['input']]);
            $this->assertEquals($case['expected'], $sanitized['page']);
        }
    }

    /**
     * Test per page sanitization
     */
    public function test_sanitize_per_page() {
        $test_cases = [
            ['input' => 24, 'expected' => 24],
            ['input' => 48, 'expected' => 48], // Maximum allowed
            ['input' => 50, 'expected' => 48], // Should clamp to max
            ['input' => 0, 'expected' => 1], // Should clamp to min
            ['input' => -5, 'expected' => 1], // Should clamp to min
            ['input' => 'invalid', 'expected' => 1], // Should convert to 1
        ];

        foreach ($test_cases as $case) {
            $sanitized = $this->security_handler->sanitize_search_params(['per_page' => $case['input']]);
            $this->assertEquals($case['expected'], $sanitized['per_page']);
        }
    }

    /**
     * Test search term length limitation
     */
    public function test_search_term_length_limitation() {
        // Normal search term
        $normal_term = 'wordpress plugin';
        $sanitized = $this->security_handler->sanitize_search_params(['search_term' => $normal_term]);
        $this->assertEquals($normal_term, $sanitized['search_term']);

        // Very long search term (should be truncated)
        $long_term = str_repeat('a', 250);
        $sanitized = $this->security_handler->sanitize_search_params(['search_term' => $long_term]);
        $this->assertEquals(200, strlen($sanitized['search_term']));
    }

    /**
     * Test token generation
     */
    public function test_generate_token() {
        $token1 = $this->security_handler->generate_token();
        $token2 = $this->security_handler->generate_token();

        // Tokens should be different
        $this->assertNotEquals($token1, $token2);

        // Token should be the right length (default 32)
        $this->assertEquals(32, strlen($token1));

        // Custom length
        $custom_token = $this->security_handler->generate_token(16);
        $this->assertEquals(16, strlen($custom_token));

        // Token should only contain valid characters
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token1);
    }

    /**
     * Test security event logging
     */
    public function test_security_event_logging() {
        // Enable debug logging for test
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        if (!defined('WP_DEBUG_LOG')) {
            define('WP_DEBUG_LOG', true);
        }

        // This test primarily ensures the method doesn't throw errors
        $this->security_handler->log_security_event(
            'test_event',
            'Test security event message',
            ['user_id' => get_current_user_id(), 'test_data' => 'value']
        );

        // If we get here without errors, the logging worked
        $this->assertTrue(true);
    }

    /**
     * Test client IP address extraction
     */
    public function test_get_client_ip() {
        // Test with various IP headers
        $test_cases = [
            'HTTP_CLIENT_IP' => '192.168.1.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
            'REMOTE_ADDR' => '198.51.100.1'
        ];

        foreach ($test_cases as $header => $ip) {
            // Set the header
            $_SERVER[$header] = $ip;
            
            // Check rate limiting to indirectly test IP extraction
            $result = $this->security_handler->check_rate_limit('ip_test', 1, 60);
            $this->assertTrue($result);
            
            // Clean up
            unset($_SERVER[$header]);
        }
    }

    /**
     * Test IP validation in client IP extraction
     */
    public function test_client_ip_validation() {
        // Set invalid IP
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'invalid.ip.address';
        
        // Should fall back to valid behavior (not throw errors)
        $result = $this->security_handler->check_rate_limit('ip_validation_test', 5, 60);
        $this->assertTrue($result);
        
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    /**
     * Test comma-separated IP handling
     */
    public function test_comma_separated_ip_handling() {
        // Simulate proxy with multiple IPs
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.1, 10.0.0.1';
        
        // Should use the first valid IP
        $result = $this->security_handler->check_rate_limit('multi_ip_test', 5, 60);
        $this->assertTrue($result);
        
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    /**
     * Test multisite network admin capability validation
     */
    public function test_multisite_capability_validation() {
        if (!is_multisite()) {
            $this->markTestSkipped('Multisite not enabled');
        }

        $this->set_current_user('super_admin');
        
        // Simulate AJAX request
        $_POST['nonce'] = wp_create_nonce('test_action');
        add_filter('wp_doing_ajax', '__return_true');
        $_SERVER['HTTP_REFERER'] = network_admin_url('admin.php');

        $result = $this->security_handler->validate_ajax_request('test_action', 'manage_network_options');

        $this->assertTrue($result);
        
        // Clean up
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST['nonce'], $_SERVER['HTTP_REFERER']);
    }
}