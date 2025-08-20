<?php
/**
 * Tests for WP_Plugin_Filters_API_Handler class
 *
 * @package WP_Plugin_Directory_Filters
 */

class Test_WP_Plugin_Filters_API_Handler extends WP_Plugin_Filters_Test_Case {

    /**
     * API Handler instance
     *
     * @var WP_Plugin_Filters_API_Handler
     */
    private $api_handler;

    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->api_handler = new WP_Plugin_Filters_API_Handler();
        $this->set_current_user('admin');
    }

    /**
     * Test API handler instantiation
     */
    public function test_api_handler_instantiation() {
        $this->assertInstanceOf('WP_Plugin_Filters_API_Handler', $this->api_handler);
    }

    /**
     * Test successful plugin search
     */
    public function test_search_plugins_success() {
        // Mock successful API response
        $mock_response = [
            'plugins' => [
                $this->create_test_plugin_data(['slug' => 'test-plugin-1']),
                $this->create_test_plugin_data(['slug' => 'test-plugin-2']),
            ],
            'info' => [
                'page' => 1,
                'pages' => 1,
                'results' => 2
            ]
        ];

        $this->mock_api_response('query_plugins', $mock_response);

        $result = $this->api_handler->search_plugins('test', 1, 24);

        $this->assertIsNotWPError($result);
        $this->assertArrayHasKey('plugins', $result);
        $this->assertArrayHasKey('info', $result);
        $this->assertCount(2, $result['plugins']);
        
        // Validate plugin data structure
        foreach ($result['plugins'] as $plugin) {
            $this->assertValidPluginData($plugin);
        }
    }

    /**
     * Test plugin search with filters
     */
    public function test_search_plugins_with_filters() {
        $filters = [
            'tag' => 'ecommerce',
            'author' => 'automattic'
        ];

        $mock_response = [
            'plugins' => [
                $this->create_test_plugin_data([
                    'slug' => 'woocommerce',
                    'author' => 'Automattic',
                    'tags' => ['ecommerce', 'shop']
                ])
            ],
            'info' => ['page' => 1, 'pages' => 1, 'results' => 1]
        ];

        $this->mock_api_response('query_plugins', $mock_response);

        $result = $this->api_handler->search_plugins('shop', 1, 24, $filters);

        $this->assertIsNotWPError($result);
        $this->assertArrayHasKey('plugins', $result);
        $this->assertEquals('woocommerce', $result['plugins'][0]['slug']);
    }

    /**
     * Test plugin search with empty results
     */
    public function test_search_plugins_empty_results() {
        $mock_response = [
            'plugins' => [],
            'info' => ['page' => 1, 'pages' => 0, 'results' => 0]
        ];

        $this->mock_api_response('query_plugins', $mock_response);

        $result = $this->api_handler->search_plugins('nonexistentplugin12345');

        $this->assertIsNotWPError($result);
        $this->assertArrayHasKey('plugins', $result);
        $this->assertEmpty($result['plugins']);
        $this->assertEquals(0, $result['info']['results']);
    }

    /**
     * Test API connection failure
     */
    public function test_search_plugins_api_failure() {
        // Mock API failure
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                return new WP_Error('http_request_failed', 'Connection timeout');
            }
            return $preempt;
        }, 10, 3);

        $result = $this->api_handler->search_plugins('test');

        $this->assertIsWPError($result);
        $this->assertEquals('api_error', $result->get_error_code());
    }

    /**
     * Test plugin search with invalid JSON response
     */
    public function test_search_plugins_invalid_json() {
        // Mock invalid JSON response
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => 'invalid json{'
                ];
            }
            return $preempt;
        }, 10, 3);

        $result = $this->api_handler->search_plugins('test');

        $this->assertIsWPError($result);
        $this->assertEquals('json_decode_error', $result->get_error_code());
    }

    /**
     * Test plugin details retrieval
     */
    public function test_get_plugin_details_success() {
        $plugin_data = $this->create_test_plugin_data(['slug' => 'woocommerce']);
        $this->mock_api_response('plugin_information', $plugin_data);

        $result = $this->api_handler->get_plugin_details('woocommerce');

        $this->assertIsNotWPError($result);
        $this->assertValidPluginData($result);
        $this->assertEquals('woocommerce', $result['slug']);
    }

    /**
     * Test plugin details for non-existent plugin
     */
    public function test_get_plugin_details_not_found() {
        // Mock 404 response
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                return [
                    'response' => ['code' => 404],
                    'body' => json_encode(['error' => 'Plugin not found'])
                ];
            }
            return $preempt;
        }, 10, 3);

        $result = $this->api_handler->get_plugin_details('nonexistent-plugin');

        $this->assertIsWPError($result);
    }

    /**
     * Test rate limiting functionality
     */
    public function test_rate_limiting() {
        // Simulate rapid requests
        for ($i = 0; $i < 65; $i++) {
            $result = $this->api_handler->search_plugins('test' . $i);
            
            if ($i < 60) {
                // Should succeed within rate limit
                $this->assertIsNotWPError($result, "Request {$i} should not be rate limited");
            } else {
                // Should be rate limited after 60 requests
                $this->assertIsWPError($result, "Request {$i} should be rate limited");
                $this->assertEquals('rate_limit_exceeded', $result->get_error_code());
                break;
            }
        }
    }

    /**
     * Test cache functionality for search results
     */
    public function test_search_caching() {
        $mock_response = [
            'plugins' => [$this->create_test_plugin_data()],
            'info' => ['page' => 1, 'pages' => 1, 'results' => 1]
        ];

        $this->mock_api_response('query_plugins', $mock_response);

        // First request - should hit API
        $result1 = $this->api_handler->search_plugins('test');
        $this->assertIsNotWPError($result1);

        // Second request - should hit cache
        $result2 = $this->api_handler->search_plugins('test');
        $this->assertIsNotWPError($result2);

        // Results should be identical
        $this->assertEquals($result1, $result2);
        
        // Verify cache key exists
        $cache_key = 'wp_plugin_search_' . md5(serialize([
            'search' => 'test',
            'page' => 1,
            'per_page' => 24,
            'filters' => []
        ]));
        
        $cached_result = get_transient($cache_key);
        $this->assertNotFalse($cached_result);
    }

    /**
     * Test cache functionality for plugin details
     */
    public function test_plugin_details_caching() {
        $plugin_data = $this->create_test_plugin_data(['slug' => 'test-plugin']);
        $this->mock_api_response('plugin_information', $plugin_data);

        // First request
        $result1 = $this->api_handler->get_plugin_details('test-plugin');
        $this->assertIsNotWPError($result1);

        // Second request (should be cached)
        $result2 = $this->api_handler->get_plugin_details('test-plugin');
        $this->assertIsNotWPError($result2);

        $this->assertEquals($result1, $result2);

        // Verify cache
        $cache_key = 'wp_plugin_details_test-plugin';
        $cached_result = get_transient($cache_key);
        $this->assertNotFalse($cached_result);
    }

    /**
     * Test input sanitization
     */
    public function test_input_sanitization() {
        // Test with malicious input
        $malicious_search = '<script>alert("xss")</script>';
        $result = $this->api_handler->search_plugins($malicious_search);

        // Should not contain script tags in the actual request
        $this->assertIsNotWPError($result);
        
        // Test with SQL injection attempt
        $sql_injection = "'; DROP TABLE wp_options; --";
        $result = $this->api_handler->search_plugins($sql_injection);
        $this->assertIsNotWPError($result);
    }

    /**
     * Test pagination parameters
     */
    public function test_pagination_parameters() {
        $mock_response = [
            'plugins' => array_fill(0, 48, $this->create_test_plugin_data()),
            'info' => ['page' => 2, 'pages' => 5, 'results' => 240]
        ];

        $this->mock_api_response('query_plugins', $mock_response);

        $result = $this->api_handler->search_plugins('popular', 2, 48);

        $this->assertIsNotWPError($result);
        $this->assertEquals(2, $result['info']['page']);
        $this->assertEquals(5, $result['info']['pages']);
        $this->assertEquals(240, $result['info']['results']);
        $this->assertCount(48, $result['plugins']);
    }

    /**
     * Test maximum per_page limit
     */
    public function test_per_page_limit() {
        // Test that per_page is limited to 48
        $mock_response = [
            'plugins' => array_fill(0, 48, $this->create_test_plugin_data()),
            'info' => ['page' => 1, 'pages' => 1, 'results' => 48]
        ];

        $this->mock_api_response('query_plugins', $mock_response);

        $result = $this->api_handler->search_plugins('test', 1, 100); // Request 100, should get 48

        $this->assertIsNotWPError($result);
        $this->assertLessThanOrEqual(48, count($result['plugins']));
    }

    /**
     * Test popular plugins cache warming
     */
    public function test_popular_plugins_cache_warming() {
        $popular_slugs = $this->api_handler->get_popular_plugin_slugs();

        $this->assertIsArray($popular_slugs);
        $this->assertNotEmpty($popular_slugs);
        $this->assertContains('woocommerce', $popular_slugs);
        $this->assertContains('yoast-seo', $popular_slugs);
        $this->assertContains('elementor', $popular_slugs);
    }

    /**
     * Test API cache clearing
     */
    public function test_api_cache_clearing() {
        // Set up some test cache entries
        set_transient('wp_plugin_search_test123', 'test_data', 3600);
        set_transient('wp_plugin_details_testplugin', 'test_data', 3600);

        // Clear search cache only
        $cleared = $this->api_handler->clear_api_cache('search');
        $this->assertGreaterThan(0, $cleared);

        // Verify search cache is cleared but details cache remains
        $this->assertFalse(get_transient('wp_plugin_search_test123'));
        $this->assertNotFalse(get_transient('wp_plugin_details_testplugin'));

        // Clear all cache
        set_transient('wp_plugin_search_test456', 'test_data', 3600);
        $cleared = $this->api_handler->clear_api_cache('all');
        $this->assertGreaterThan(0, $cleared);

        // Verify all caches are cleared
        $this->assertFalse(get_transient('wp_plugin_search_test456'));
        $this->assertFalse(get_transient('wp_plugin_details_testplugin'));
    }

    /**
     * Test API timeout handling
     */
    public function test_api_timeout_handling() {
        // Mock timeout response
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                return new WP_Error('http_request_failed', 'Operation timed out');
            }
            return $preempt;
        }, 10, 3);

        $result = $this->api_handler->search_plugins('test');

        $this->assertIsWPError($result);
        $this->assertEquals('api_error', $result->get_error_code());
    }

    /**
     * Test HTTP status code handling
     */
    public function test_http_status_code_handling() {
        // Mock 500 server error
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                return [
                    'response' => ['code' => 500],
                    'body' => 'Internal Server Error'
                ];
            }
            return $preempt;
        }, 10, 3);

        $result = $this->api_handler->search_plugins('test');

        $this->assertIsWPError($result);
        $this->assertEquals('api_http_error', $result->get_error_code());
    }

    /**
     * Test data sanitization in API responses
     */
    public function test_api_response_sanitization() {
        $malicious_plugin_data = [
            'slug' => 'test-plugin',
            'name' => '<script>alert("xss")</script>Test Plugin',
            'short_description' => '<iframe src="http://evil.com"></iframe>Description',
            'description' => '<script>document.location="http://evil.com"</script>',
            'homepage' => 'javascript:alert("xss")',
            'download_link' => 'ftp://evil.com/malware.zip',
            'tags' => ['<script>', 'valid-tag'],
        ];

        $this->mock_api_response('plugin_information', $malicious_plugin_data);

        $result = $this->api_handler->get_plugin_details('test-plugin');

        $this->assertIsNotWPError($result);
        
        // Verify sanitization
        $this->assertStringNotContainsString('<script>', $result['name']);
        $this->assertStringNotContainsString('<iframe>', $result['short_description']);
        $this->assertStringNotContainsString('<script>', $result['description']);
        $this->assertEmpty($result['homepage']); // Invalid URL should be empty
        $this->assertEmpty($result['download_link']); // Invalid URL should be empty
        $this->assertNotContains('<script>', $result['tags']);
    }

    /**
     * Test user agent string
     */
    public function test_user_agent_string() {
        // This test verifies the user agent is properly set
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.wordpress.org') !== false) {
                $this->assertArrayHasKey('user-agent', $args);
                $this->assertStringContainsString('WordPress Plugin Directory Filters', $args['user-agent']);
                
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['plugins' => [], 'info' => ['page' => 1, 'pages' => 0, 'results' => 0]])
                ];
            }
            return $preempt;
        }, 10, 3);

        $this->api_handler->search_plugins('test');
    }
}