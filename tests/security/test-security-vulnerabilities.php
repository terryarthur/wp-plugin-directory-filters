<?php
/**
 * Security Tests for WordPress Plugin Directory Filters
 * Tests for common security vulnerabilities and attack vectors
 *
 * @package WP_Plugin_Directory_Filters
 */

class Test_Security_Vulnerabilities extends WP_Plugin_Filters_Test_Case {

    /**
     * Test SQL injection prevention
     */
    public function test_sql_injection_prevention() {
        global $wpdb;
        
        $security_handler = new WP_Plugin_Filters_Security_Handler();
        
        // Test various SQL injection payloads
        $malicious_payloads = [
            "'; DROP TABLE {$wpdb->options}; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM {$wpdb->users} --",
            "'; INSERT INTO {$wpdb->options} (option_name, option_value) VALUES ('hacked', 'true'); --",
            "' OR 1=1; DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_%'; --",
            "\"; DROP DATABASE; --",
            "' OR '1'='1' /*",
            "admin'--",
            "' OR 1=1#",
            "1' ORDER BY 1--+",
            "' WAITFOR DELAY '00:00:10'--",
            "1'; EXEC xp_cmdshell('dir')--"
        ];

        foreach ($malicious_payloads as $payload) {
            // Test search term sanitization
            $sanitized_params = $security_handler->sanitize_search_params([
                'search_term' => $payload
            ]);
            
            $this->assertStringNotContainsString('DROP', $sanitized_params['search_term']);
            $this->assertStringNotContainsString('UNION', $sanitized_params['search_term']);
            $this->assertStringNotContainsString('DELETE', $sanitized_params['search_term']);
            $this->assertStringNotContainsString('INSERT', $sanitized_params['search_term']);
            $this->assertStringNotContainsString('--', $sanitized_params['search_term']);
            
            // Test plugin slug validation
            $validated_slug = $security_handler->validate_plugin_slug($payload);
            if (!is_wp_error($validated_slug)) {
                $this->assertStringNotContainsString('DROP', $validated_slug);
                $this->assertStringNotContainsString('UNION', $validated_slug);
                $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $validated_slug);
            }
        }
        
        // Verify database integrity after SQL injection attempts
        $options_count_before = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
        
        // Attempt malicious queries through plugin functions
        foreach ($malicious_payloads as $payload) {
            // This should be safe due to sanitization
            update_option('wp_plugin_filters_test_' . md5($payload), 'test_value');
        }
        
        $options_count_after = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
        
        // Should only have added legitimate options
        $this->assertEquals($options_count_before + count($malicious_payloads), $options_count_after);
        
        // Clean up test options
        foreach ($malicious_payloads as $payload) {
            delete_option('wp_plugin_filters_test_' . md5($payload));
        }
    }

    /**
     * Test Cross-Site Scripting (XSS) prevention
     */
    public function test_xss_prevention() {
        $security_handler = new WP_Plugin_Filters_Security_Handler();
        
        $xss_payloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')">',
            '<body onload=alert("XSS")>',
            '<input onfocus=alert("XSS") autofocus>',
            '<select onfocus=alert("XSS") autofocus>',
            '<textarea onfocus=alert("XSS") autofocus>',
            '<keygen onfocus=alert("XSS") autofocus>',
            '<video><source onerror="alert(\'XSS\')">',
            '<audio src=x onerror=alert("XSS")>',
            '"><script>alert("XSS")</script>',
            '\';alert("XSS");//',
            '<scr<script>ipt>alert("XSS")</script>',
            '<<SCRIPT>alert("XSS")<</SCRIPT>',
            '<SCRIPT SRC=http://evil.com/xss.js></SCRIPT>',
            '<IMG """><SCRIPT>alert("XSS")</SCRIPT>">',
            '<SCRIPT>String.fromCharCode(97,108,101,114,116,40,39,88,83,83,39,41)</SCRIPT>',
            '\'><img src=x onerror=alert("XSS")>',
            '"><svg/onload=alert("XSS")>',
            '<details open ontoggle=alert("XSS")>',
            '<marquee onstart=alert("XSS")>',
            '<object data="data:text/html,<script>alert(\'XSS\')</script>">',
            'data:text/html,<script>alert("XSS")</script>'
        ];

        foreach ($xss_payloads as $payload) {
            // Test search parameter sanitization
            $sanitized_params = $security_handler->sanitize_search_params([
                'search_term' => $payload
            ]);
            
            $this->assertStringNotContainsString('<script>', strtolower($sanitized_params['search_term']));
            $this->assertStringNotContainsString('javascript:', strtolower($sanitized_params['search_term']));
            $this->assertStringNotContainsString('onerror=', strtolower($sanitized_params['search_term']));
            $this->assertStringNotContainsString('onload=', strtolower($sanitized_params['search_term']));
            
            // Test output escaping
            $escaped_output = $security_handler->escape_output($payload);
            $this->assertStringNotContainsString('<script>', $escaped_output);
            $this->assertStringNotContainsString('<img src=x onerror=', $escaped_output);
            $this->assertStringNotContainsString('<svg onload=', $escaped_output);
            
            // Verify dangerous characters are escaped
            if (strpos($payload, '<') !== false) {
                $this->assertStringContainsString('&lt;', $escaped_output);
            }
            if (strpos($payload, '>') !== false) {
                $this->assertStringContainsString('&gt;', $escaped_output);
            }
            if (strpos($payload, '"') !== false) {
                $this->assertStringContainsString('&quot;', $escaped_output);
            }
        }
        
        // Test plugin data sanitization from API
        $api_handler = new WP_Plugin_Filters_API_Handler();
        
        $malicious_plugin_data = [
            'slug' => 'test-plugin',
            'name' => '<script>alert("XSS in name")</script>Test Plugin',
            'short_description' => '<img src=x onerror=alert("XSS in description")>Plugin description',
            'description' => '<iframe src="javascript:alert(\'XSS in full description\')">Full description</iframe>',
            'author' => '<svg onload=alert("XSS in author")>Author Name</svg>',
            'homepage' => 'javascript:alert("XSS in homepage")',
            'download_link' => 'javascript:alert("XSS in download")',
            'tags' => ['<script>alert("XSS in tags")</script>', 'legitimate-tag']
        ];
        
        $this->mock_api_response('plugin_information', $malicious_plugin_data);
        
        $result = $api_handler->get_plugin_details('test-plugin');
        
        $this->assertIsNotWPError($result);
        $this->assertStringNotContainsString('<script>', $result['name']);
        $this->assertStringNotContainsString('<img src=x onerror=', $result['short_description']);
        $this->assertStringNotContainsString('<iframe', $result['description']);
        $this->assertStringNotContainsString('<svg onload=', $result['author']);
        $this->assertNotContains('<script>alert("XSS in tags")</script>', $result['tags']);
        
        // URLs should be validated and rejected if malicious
        $this->assertEmpty($result['homepage']); // Should be empty due to invalid URL
        $this->assertEmpty($result['download_link']); // Should be empty due to invalid URL
    }

    /**
     * Test Cross-Site Request Forgery (CSRF) prevention
     */
    public function test_csrf_prevention() {
        $this->set_current_user('admin');
        
        // Test AJAX requests without nonce (should fail)
        $_POST = [
            'action' => 'wp_plugin_filter',
            'search_term' => 'test'
            // Missing nonce
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
        $this->assertFalse($response['success']);
        $this->assertEquals('nonce_verification_failed', $response['data']['code']);
        
        // Test with invalid nonce (should fail)
        $_POST['nonce'] = 'invalid_nonce_12345';
        
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('nonce_verification_failed', $response['data']['code']);
        
        // Test with correct nonce (should succeed)
        $_POST['nonce'] = wp_create_nonce('wp_plugin_filter_action');
        
        // Mock API response
        $this->mock_api_response('query_plugins', [
            'plugins' => [$this->create_test_plugin_data()],
            'info' => ['page' => 1, 'pages' => 1, 'results' => 1]
        ]);
        
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test authentication and authorization
     */
    public function test_authentication_authorization() {
        // Test without authentication (should fail)
        wp_set_current_user(0);
        
        $security_handler = new WP_Plugin_Filters_Security_Handler();
        
        $_POST['nonce'] = wp_create_nonce('wp_plugin_filter_action');
        add_filter('wp_doing_ajax', '__return_true');
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        
        $this->assertIsWPError($result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
        
        // Test with subscriber role (should fail for install_plugins)
        $this->set_current_user('subscriber');
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        
        $this->assertIsWPError($result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
        
        // Test with editor role (should fail for install_plugins)
        $this->set_current_user('editor');
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        
        $this->assertIsWPError($result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
        
        // Test with admin role (should succeed)
        $this->set_current_user('admin');
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        
        $this->assertTrue($result);
        
        // Test privilege escalation attempt
        remove_all_filters('user_has_cap');
        
        // Temporarily remove admin capability
        $user = wp_get_current_user();
        $user->remove_cap('install_plugins');
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        
        $this->assertIsWPError($result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
        
        // Restore capability
        $user->add_cap('install_plugins');
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test rate limiting and DoS prevention
     */
    public function test_rate_limiting_dos_prevention() {
        $this->set_current_user('admin');
        $security_handler = new WP_Plugin_Filters_Security_Handler();
        
        // Test rate limiting
        $action = 'dos_test';
        $limit = 5;
        $window = 60;
        
        // First 5 requests should succeed
        for ($i = 0; $i < $limit; $i++) {
            $result = $security_handler->check_rate_limit($action, $limit, $window);
            $this->assertTrue($result, "Request {$i} should succeed within rate limit");
        }
        
        // 6th request should be rate limited
        $result = $security_handler->check_rate_limit($action, $limit, $window);
        $this->assertIsWPError($result);
        $this->assertEquals('rate_limit_exceeded', $result->get_error_code());
        
        // Test AJAX rate limiting
        $ajax_handler = new WP_Plugin_Filters_AJAX_Handler();
        
        $_POST = [
            'action' => 'wp_plugin_filter',
            'nonce' => wp_create_nonce('wp_plugin_filter_action'),
            'search_term' => 'rate_limit_test'
        ];
        
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        add_filter('wp_doing_ajax', '__return_true');
        
        // Mock API response
        $this->mock_api_response('query_plugins', [
            'plugins' => [$this->create_test_plugin_data()],
            'info' => ['page' => 1, 'pages' => 1, 'results' => 1]
        ]);
        
        // Make requests up to the limit
        $successful_requests = 0;
        $rate_limited_requests = 0;
        
        for ($i = 0; $i < 35; $i++) { // Attempt more than the typical limit
            ob_start();
            try {
                do_action('wp_ajax_wp_plugin_filter');
            } catch (WPDieException $e) {
                // Expected
            }
            $output = ob_get_clean();
            
            $response = json_decode($output, true);
            
            if ($response['success']) {
                $successful_requests++;
            } else if (isset($response['data']['code']) && $response['data']['code'] === 'rate_limit_exceeded') {
                $rate_limited_requests++;
            }
        }
        
        $this->assertGreaterThan(0, $rate_limited_requests, 'Some requests should be rate limited');
        $this->assertLessThan(35, $successful_requests, 'Not all requests should succeed due to rate limiting');
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test input validation and sanitization
     */
    public function test_input_validation_sanitization() {
        $security_handler = new WP_Plugin_Filters_Security_Handler();
        
        // Test extremely long input (potential buffer overflow)
        $very_long_string = str_repeat('A', 10000);
        
        $sanitized_params = $security_handler->sanitize_search_params([
            'search_term' => $very_long_string
        ]);
        
        $this->assertLessThanOrEqual(200, strlen($sanitized_params['search_term']), 
            'Search term should be truncated to prevent buffer overflow');
        
        // Test null byte injection
        $null_byte_payload = "test\0malicious";
        
        $sanitized_params = $security_handler->sanitize_search_params([
            'search_term' => $null_byte_payload
        ]);
        
        $this->assertStringNotContainsString("\0", $sanitized_params['search_term']);
        
        // Test directory traversal in plugin slug
        $traversal_payloads = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '....//....//....//etc//passwd',
            'test/../../../sensitive-file',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd'
        ];
        
        foreach ($traversal_payloads as $payload) {
            $result = $security_handler->validate_plugin_slug($payload);
            
            if (!is_wp_error($result)) {
                $this->assertStringNotContainsString('..', $result);
                $this->assertStringNotContainsString('/', $result);
                $this->assertStringNotContainsString('\\', $result);
                $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
            } else {
                // Should reject invalid slug formats
                $this->assertInstanceOf('WP_Error', $result);
            }
        }
        
        // Test numeric boundary conditions
        $boundary_tests = [
            ['field' => 'page', 'value' => -1, 'expected_min' => 1],
            ['field' => 'page', 'value' => 999999, 'expected_max' => 1000],
            ['field' => 'per_page', 'value' => -1, 'expected_min' => 1],
            ['field' => 'per_page', 'value' => 999999, 'expected_max' => 48],
            ['field' => 'usability_rating', 'value' => -10, 'expected_min' => 0],
            ['field' => 'usability_rating', 'value' => 100, 'expected_max' => 5],
            ['field' => 'health_score', 'value' => -50, 'expected_min' => 0],
            ['field' => 'health_score', 'value' => 999, 'expected_max' => 100]
        ];
        
        foreach ($boundary_tests as $test) {
            $sanitized_params = $security_handler->sanitize_search_params([
                $test['field'] => $test['value']
            ]);
            
            if (isset($test['expected_min'])) {
                $this->assertGreaterThanOrEqual($test['expected_min'], $sanitized_params[$test['field']]);
            }
            if (isset($test['expected_max'])) {
                $this->assertLessThanOrEqual($test['expected_max'], $sanitized_params[$test['field']]);
            }
        }
    }

    /**
     * Test information disclosure prevention
     */
    public function test_information_disclosure_prevention() {
        // Test that errors don't leak sensitive information
        $api_handler = new WP_Plugin_Filters_API_Handler();
        
        // Mock various error conditions
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                return new WP_Error('http_request_failed', 'cURL error 28: Connection timed out after 30001 milliseconds');
            }
            return $preempt;
        }, 10, 3);
        
        $result = $api_handler->search_plugins('test');
        
        $this->assertIsWPError($result);
        
        // Error message should be generic and not leak internal details
        $error_message = $result->get_error_message();
        $this->assertStringNotContainsString('cURL', $error_message);
        $this->assertStringNotContainsString('mysql', strtolower($error_message));
        $this->assertStringNotContainsString('database', strtolower($error_message));
        $this->assertStringNotContainsString('/var/www', $error_message);
        $this->assertStringNotContainsString('C:\\', $error_message);
        
        // Test that debug information is not exposed
        $this->assertStringNotContainsString('Warning:', $error_message);
        $this->assertStringNotContainsString('Notice:', $error_message);
        $this->assertStringNotContainsString('Fatal error:', $error_message);
        
        // Test AJAX error responses don't leak information
        $this->set_current_user('admin');
        
        $_POST = [
            'action' => 'wp_plugin_filter',
            'nonce' => wp_create_nonce('wp_plugin_filter_action'),
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
        
        $this->assertFalse($response['success']);
        
        // Error response should not contain sensitive information
        $error_data = json_encode($response);
        $this->assertStringNotContainsString('cURL', $error_data);
        $this->assertStringNotContainsString('/var/www', $error_data);
        $this->assertStringNotContainsString('mysql', strtolower($error_data));
        $this->assertStringNotContainsString('password', strtolower($error_data));
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test secure token generation
     */
    public function test_secure_token_generation() {
        $security_handler = new WP_Plugin_Filters_Security_Handler();
        
        // Generate multiple tokens
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = $security_handler->generate_token();
        }
        
        // Test uniqueness
        $unique_tokens = array_unique($tokens);
        $this->assertCount(100, $unique_tokens, 'All tokens should be unique');
        
        // Test length
        foreach ($tokens as $token) {
            $this->assertEquals(32, strlen($token), 'Token should be 32 characters long');
            $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token, 'Token should be hexadecimal');
        }
        
        // Test custom length
        $custom_token = $security_handler->generate_token(64);
        $this->assertEquals(64, strlen($custom_token));
        
        // Test entropy (basic check)
        $token = $security_handler->generate_token();
        $char_counts = array_count_values(str_split($token));
        $max_char_frequency = max($char_counts) / strlen($token);
        
        // No character should appear more than 50% of the time (indicating poor entropy)
        $this->assertLessThan(0.5, $max_char_frequency, 'Token should have good entropy');
    }

    /**
     * Test referrer validation
     */
    public function test_referrer_validation() {
        $this->set_current_user('admin');
        $security_handler = new WP_Plugin_Filters_Security_Handler();
        
        // Test valid referrer
        $_POST['nonce'] = wp_create_nonce('wp_plugin_filter_action');
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        add_filter('wp_doing_ajax', '__return_true');
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        $this->assertTrue($result);
        
        // Test invalid external referrer
        $_SERVER['HTTP_REFERER'] = 'http://evil.com/attack.php';
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        $this->assertIsWPError($result);
        $this->assertEquals('invalid_referrer', $result->get_error_code());
        
        // Test missing referrer
        unset($_SERVER['HTTP_REFERER']);
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        $this->assertIsWPError($result);
        $this->assertEquals('invalid_referrer', $result->get_error_code());
        
        // Test referrer from different subdomain (should fail)
        $_SERVER['HTTP_REFERER'] = 'http://malicious.example.com/wp-admin/plugin-install.php';
        
        $result = $security_handler->validate_ajax_request('wp_plugin_filter_action', 'install_plugins');
        $this->assertIsWPError($result);
        $this->assertEquals('invalid_referrer', $result->get_error_code());
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST, $_SERVER['HTTP_REFERER']);
    }

    /**
     * Test session security
     */
    public function test_session_security() {
        // Test that plugin doesn't create insecure sessions
        $this->assertFalse(session_id(), 'Plugin should not start PHP sessions');
        
        // Test nonce expiration
        $old_nonce = wp_create_nonce('test_action');
        
        // Simulate time passage (WordPress nonces expire after 24 hours)
        // We can't actually travel in time, so we test the nonce validation logic
        $this->assertTrue(wp_verify_nonce($old_nonce, 'test_action'));
        $this->assertFalse(wp_verify_nonce($old_nonce, 'wrong_action'));
        $this->assertFalse(wp_verify_nonce('invalid_nonce', 'test_action'));
        
        // Test that nonces are properly scoped to actions
        $nonce1 = wp_create_nonce('action1');
        $nonce2 = wp_create_nonce('action2');
        
        $this->assertNotEquals($nonce1, $nonce2);
        $this->assertTrue(wp_verify_nonce($nonce1, 'action1'));
        $this->assertFalse(wp_verify_nonce($nonce1, 'action2'));
        $this->assertTrue(wp_verify_nonce($nonce2, 'action2'));
        $this->assertFalse(wp_verify_nonce($nonce2, 'action1'));
    }

    /**
     * Test file upload security (if applicable)
     */
    public function test_file_upload_security() {
        // This plugin doesn't handle file uploads, but we test that it doesn't
        // accidentally create upload vulnerabilities
        
        // Test that plugin doesn't handle $_FILES
        $_FILES = [
            'malicious_file' => [
                'name' => 'shell.php',
                'type' => 'application/x-php',
                'tmp_name' => '/tmp/phpABC123',
                'error' => 0,
                'size' => 1024
            ]
        ];
        
        $this->set_current_user('admin');
        
        $_POST = [
            'action' => 'wp_plugin_filter',
            'nonce' => wp_create_nonce('wp_plugin_filter_action'),
            'search_term' => 'test'
        ];
        
        $_SERVER['HTTP_REFERER'] = admin_url('plugin-install.php');
        add_filter('wp_doing_ajax', '__return_true');
        
        // Mock API response
        $this->mock_api_response('query_plugins', [
            'plugins' => [$this->create_test_plugin_data()],
            'info' => ['page' => 1, 'pages' => 1, 'results' => 1]
        ]);
        
        ob_start();
        try {
            do_action('wp_ajax_wp_plugin_filter');
        } catch (WPDieException $e) {
            // Expected
        }
        $output = ob_get_clean();
        
        // Plugin should work normally and ignore $_FILES
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        
        // Verify no files were processed or moved
        $this->assertFileDoesNotExist('/tmp/phpABC123_processed');
        $this->assertFileDoesNotExist(WP_PLUGIN_FILTERS_PLUGIN_DIR . 'shell.php');
        
        remove_filter('wp_doing_ajax', '__return_true');
        unset($_POST, $_SERVER['HTTP_REFERER'], $_FILES);
    }

    /**
     * Test data exposure through WordPress REST API
     */
    public function test_rest_api_data_exposure() {
        if (!function_exists('rest_url')) {
            $this->markTestSkipped('REST API not available');
        }
        
        // Verify plugin doesn't accidentally expose data through REST API
        $routes = rest_get_server()->get_routes();
        
        $plugin_routes = array_filter(array_keys($routes), function($route) {
            return strpos($route, 'wp-plugin-filters') !== false || 
                   strpos($route, 'plugin-filters') !== false;
        });
        
        $this->assertEmpty($plugin_routes, 'Plugin should not register REST API endpoints');
        
        // Test that plugin data is not accessible through standard WordPress endpoints
        $request = new WP_REST_Request('GET', '/wp/v2/options');
        $response = rest_get_server()->dispatch($request);
        
        if ($response->get_status() === 200) {
            $options = $response->get_data();
            
            foreach ($options as $option) {
                if (isset($option['key'])) {
                    $this->assertStringNotContainsString('wp_plugin_filters', $option['key']);
                }
            }
        }
    }

    /**
     * Test cache poisoning prevention
     */
    public function test_cache_poisoning_prevention() {
        $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
        
        // Test that malicious data cannot be injected into cache
        $malicious_data = [
            'evil_script' => '<script>alert("cache poisoning")</script>',
            'sql_injection' => "'; DROP TABLE wp_options; --",
            'php_injection' => '<?php system("rm -rf /"); ?>',
            'serialized_object' => serialize(new stdClass())
        ];
        
        $cache_manager->set('malicious_test', $malicious_data, 'test_group');
        $retrieved = $cache_manager->get('malicious_test', 'test_group');
        
        // Data should be stored and retrieved as-is (no code execution)
        $this->assertEquals($malicious_data, $retrieved);
        
        // But when used in output, it should be escaped
        $security_handler = new WP_Plugin_Filters_Security_Handler();
        $escaped = $security_handler->escape_output($retrieved);
        
        $this->assertStringNotContainsString('<script>', $escaped['evil_script']);
        $this->assertStringNotContainsString('<?php', $escaped['php_injection']);
        $this->assertStringContainsString('&lt;script&gt;', $escaped['evil_script']);
        
        // Test cache key manipulation
        $malicious_keys = [
            '../../../etc/passwd',
            '<?php echo "injected"; ?>',
            '<script>alert("xss")</script>',
            serialize(['malicious' => 'object'])
        ];
        
        foreach ($malicious_keys as $key) {
            $cache_manager->set($key, 'test_data', 'test_group');
            $retrieved = $cache_manager->get($key, 'test_group');
            
            // Should work normally without code execution
            $this->assertEquals('test_data', $retrieved);
        }
        
        // Clean up
        foreach ($malicious_keys as $key) {
            $cache_manager->delete($key, 'test_group');
        }
        $cache_manager->delete('malicious_test', 'test_group');
    }
}