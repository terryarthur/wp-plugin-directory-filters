<?php
/**
 * Performance Tests for WordPress Plugin Directory Filters
 *
 * @package WP_Plugin_Directory_Filters
 */

class Test_Performance_Benchmarks extends WP_Plugin_Filters_Test_Case {

    /**
     * Performance threshold in milliseconds
     */
    const PERFORMANCE_THRESHOLD_MS = 200;
    
    /**
     * Memory threshold in MB
     */
    const MEMORY_THRESHOLD_MB = 32;
    
    /**
     * Large dataset size for stress testing
     */
    const LARGE_DATASET_SIZE = 1000;

    /**
     * Test API handler performance with various dataset sizes
     */
    public function test_api_handler_performance() {
        $api_handler = new WP_Plugin_Filters_API_Handler();
        
        // Test small dataset
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        // Mock small response
        $this->mock_api_response('query_plugins', [
            'plugins' => array_fill(0, 24, $this->create_test_plugin_data()),
            'info' => ['page' => 1, 'pages' => 1, 'results' => 24]
        ]);
        
        $result = $api_handler->search_plugins('test', 1, 24);
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        $memory_usage = (memory_get_usage() - $start_memory) / 1024 / 1024;
        
        $this->assertIsNotWPError($result);
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS, $execution_time, 
            "Small dataset API search took {$execution_time}ms, expected < " . self::PERFORMANCE_THRESHOLD_MS . "ms");
        $this->assertLessThan(self::MEMORY_THRESHOLD_MB, $memory_usage,
            "Small dataset used {$memory_usage}MB, expected < " . self::MEMORY_THRESHOLD_MB . "MB");
        
        // Test large dataset
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        // Mock large response
        $this->mock_api_response('query_plugins', [
            'plugins' => array_fill(0, 48, $this->create_test_plugin_data()),
            'info' => ['page' => 1, 'pages' => 20, 'results' => 960]
        ]);
        
        $result = $api_handler->search_plugins('popular', 1, 48);
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        $memory_usage = (memory_get_usage() - $start_memory) / 1024 / 1024;
        
        $this->assertIsNotWPError($result);
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS * 2, $execution_time,
            "Large dataset API search took {$execution_time}ms, expected < " . (self::PERFORMANCE_THRESHOLD_MS * 2) . "ms");
        $this->assertLessThan(self::MEMORY_THRESHOLD_MB * 2, $memory_usage,
            "Large dataset used {$memory_usage}MB, expected < " . (self::MEMORY_THRESHOLD_MB * 2) . "MB");
    }

    /**
     * Test rating calculator performance with various dataset sizes
     */
    public function test_rating_calculator_performance() {
        $calculator = new WP_Plugin_Filters_Rating_Calculator();
        
        // Test single plugin calculation
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        $plugin_data = $this->create_test_plugin_data();
        $rating = $calculator->calculate_usability_rating($plugin_data);
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        $memory_usage = (memory_get_usage() - $start_memory) / 1024 / 1024;
        
        $this->assertIsFloat($rating);
        $this->assertLessThan(10, $execution_time, 
            "Single rating calculation took {$execution_time}ms, expected < 10ms");
        $this->assertLessThan(1, $memory_usage,
            "Single rating calculation used {$memory_usage}MB, expected < 1MB");
        
        // Test batch calculation performance
        $plugins_data = array_fill(0, 100, $this->create_test_plugin_data());
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        $ratings = $calculator->calculate_batch_ratings($plugins_data);
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        $memory_usage = (memory_get_usage() - $start_memory) / 1024 / 1024;
        
        $this->assertCount(100, $ratings);
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS, $execution_time,
            "Batch rating calculation (100 plugins) took {$execution_time}ms, expected < " . self::PERFORMANCE_THRESHOLD_MS . "ms");
        $this->assertLessThan(self::MEMORY_THRESHOLD_MB, $memory_usage,
            "Batch calculation used {$memory_usage}MB, expected < " . self::MEMORY_THRESHOLD_MB . "MB");
    }

    /**
     * Test cache manager performance
     */
    public function test_cache_manager_performance() {
        $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
        
        // Test cache write performance
        $test_data = array_fill(0, 100, $this->create_test_plugin_data());
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        for ($i = 0; $i < 100; $i++) {
            $cache_manager->set("test_key_{$i}", $test_data[$i], 'performance_test');
        }
        
        $write_time = (microtime(true) - $start_time) * 1000;
        $write_memory = (memory_get_usage() - $start_memory) / 1024 / 1024;
        
        $this->assertLessThan(100, $write_time,
            "Cache write (100 items) took {$write_time}ms, expected < 100ms");
        $this->assertLessThan(self::MEMORY_THRESHOLD_MB, $write_memory,
            "Cache write used {$write_memory}MB, expected < " . self::MEMORY_THRESHOLD_MB . "MB");
        
        // Test cache read performance
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        $retrieved_count = 0;
        for ($i = 0; $i < 100; $i++) {
            $cached_data = $cache_manager->get("test_key_{$i}", 'performance_test');
            if ($cached_data !== null) {
                $retrieved_count++;
            }
        }
        
        $read_time = (microtime(true) - $start_time) * 1000;
        $read_memory = (memory_get_usage() - $start_memory) / 1024 / 1024;
        
        $this->assertEquals(100, $retrieved_count, 'All cached items should be retrievable');
        $this->assertLessThan(50, $read_time,
            "Cache read (100 items) took {$read_time}ms, expected < 50ms");
        $this->assertLessThan(self::MEMORY_THRESHOLD_MB, $read_memory,
            "Cache read used {$read_memory}MB, expected < " . self::MEMORY_THRESHOLD_MB . "MB");
        
        // Test cache clear performance
        $start_time = microtime(true);
        
        $cleared = $cache_manager->clear_all_cache('performance_test');
        
        $clear_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertGreaterThan(0, $cleared);
        $this->assertLessThan(100, $clear_time,
            "Cache clear took {$clear_time}ms, expected < 100ms");
    }

    /**
     * Test AJAX handler performance under load
     */
    public function test_ajax_handler_performance() {
        $ajax_handler = new WP_Plugin_Filters_AJAX_Handler();
        $this->set_current_user('admin');
        
        // Mock successful API responses
        $this->mock_api_response('query_plugins', [
            'plugins' => array_fill(0, 24, $this->create_test_plugin_data()),
            'info' => ['page' => 1, 'pages' => 1, 'results' => 24]
        ]);
        
        // Simulate multiple concurrent requests
        $request_data = [
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
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            // Modify search term to avoid cache hits
            $test_request = $request_data;
            $test_request['search_term'] = 'test_' . $i;
            
            $responses[] = $this->execute_filtered_search_reflection($ajax_handler, $test_request);
        }
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        $memory_usage = (memory_get_usage() - $start_memory) / 1024 / 1024;
        
        // Verify all requests succeeded
        foreach ($responses as $response) {
            $this->assertIsArray($response);
            $this->assertArrayHasKey('plugins', $response);
        }
        
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS * 10, $execution_time,
            "10 AJAX requests took {$execution_time}ms, expected < " . (self::PERFORMANCE_THRESHOLD_MS * 10) . "ms");
        $this->assertLessThan(self::MEMORY_THRESHOLD_MB * 2, $memory_usage,
            "10 AJAX requests used {$memory_usage}MB, expected < " . (self::MEMORY_THRESHOLD_MB * 2) . "MB");
    }

    /**
     * Test database query performance
     */
    public function test_database_performance() {
        global $wpdb;
        
        // Test option queries performance
        $start_time = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            update_option("wp_plugin_filters_test_{$i}", ['test' => 'data']);
        }
        
        $write_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS, $write_time,
            "100 option writes took {$write_time}ms, expected < " . self::PERFORMANCE_THRESHOLD_MS . "ms");
        
        // Test option read performance
        $start_time = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            $option = get_option("wp_plugin_filters_test_{$i}");
            $this->assertIsArray($option);
        }
        
        $read_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertLessThan(50, $read_time,
            "100 option reads took {$read_time}ms, expected < 50ms");
        
        // Test transient performance
        $start_time = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            set_transient("wp_plugin_filters_perf_{$i}", ['test' => 'data'], 3600);
        }
        
        $transient_write_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertLessThan(self::PERFORMANCE_THRESHOLD_MS, $transient_write_time,
            "100 transient writes took {$transient_write_time}ms, expected < " . self::PERFORMANCE_THRESHOLD_MS . "ms");
        
        // Clean up
        for ($i = 0; $i < 100; $i++) {
            delete_option("wp_plugin_filters_test_{$i}");
            delete_transient("wp_plugin_filters_perf_{$i}");
        }
    }

    /**
     * Test memory usage with large datasets
     */
    public function test_memory_usage_large_datasets() {
        $initial_memory = memory_get_usage();
        
        // Create large plugin dataset
        $large_dataset = [];
        for ($i = 0; $i < self::LARGE_DATASET_SIZE; $i++) {
            $large_dataset[] = $this->create_test_plugin_data([
                'slug' => "plugin-{$i}",
                'name' => "Plugin {$i}",
                'short_description' => str_repeat("Description for plugin {$i}. ", 10)
            ]);
        }
        
        $dataset_memory = (memory_get_usage() - $initial_memory) / 1024 / 1024;
        
        // Test rating calculator with large dataset
        $calculator = new WP_Plugin_Filters_Rating_Calculator();
        $ratings = $calculator->calculate_batch_ratings($large_dataset);
        
        $calculation_memory = (memory_get_usage() - $initial_memory) / 1024 / 1024;
        
        $this->assertCount(self::LARGE_DATASET_SIZE, $ratings);
        $this->assertLessThan(128, $calculation_memory,
            "Large dataset processing used {$calculation_memory}MB, expected < 128MB");
        
        // Test cache manager with large dataset
        $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
        
        for ($i = 0; $i < 100; $i++) {
            $cache_manager->set("large_dataset_{$i}", array_slice($large_dataset, $i * 10, 10), 'large_test');
        }
        
        $cache_memory = (memory_get_usage() - $initial_memory) / 1024 / 1024;
        
        $this->assertLessThan(256, $cache_memory,
            "Large dataset caching used {$cache_memory}MB, expected < 256MB");
        
        // Clean up
        $cache_manager->clear_all_cache('large_test');
        unset($large_dataset, $ratings);
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Test concurrent request handling performance
     */
    public function test_concurrent_request_performance() {
        $this->set_current_user('admin');
        
        // Mock successful responses
        $this->mock_api_response('query_plugins', [
            'plugins' => array_fill(0, 24, $this->create_test_plugin_data()),
            'info' => ['page' => 1, 'pages' => 1, 'results' => 24]
        ]);
        
        $ajax_handler = new WP_Plugin_Filters_AJAX_Handler();
        
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        // Simulate 20 concurrent requests with different parameters
        $responses = [];
        for ($i = 0; $i < 20; $i++) {
            $request_data = [
                'search_term' => "concurrent_test_{$i}",
                'installation_range' => ($i % 2 === 0) ? '10k-100k' : '100k-1m',
                'sort_by' => ($i % 3 === 0) ? 'rating' : 'installations',
                'page' => 1,
                'per_page' => 24
            ];
            
            $responses[] = $this->execute_filtered_search_reflection($ajax_handler, $request_data);
        }
        
        $execution_time = (microtime(true) - $start_time) * 1000;
        $memory_usage = (memory_get_usage() - $start_memory) / 1024 / 1024;
        
        // Verify all requests completed successfully
        $this->assertCount(20, $responses);
        foreach ($responses as $response) {
            $this->assertIsArray($response);
            $this->assertArrayHasKey('plugins', $response);
        }
        
        $this->assertLessThan(2000, $execution_time,
            "20 concurrent requests took {$execution_time}ms, expected < 2000ms");
        $this->assertLessThan(64, $memory_usage,
            "20 concurrent requests used {$memory_usage}MB, expected < 64MB");
    }

    /**
     * Test caching effectiveness for performance
     */
    public function test_caching_performance_effectiveness() {
        $api_handler = new WP_Plugin_Filters_API_Handler();
        
        // Mock API response
        $this->mock_api_response('query_plugins', [
            'plugins' => array_fill(0, 24, $this->create_test_plugin_data()),
            'info' => ['page' => 1, 'pages' => 1, 'results' => 24]
        ]);
        
        // First request (cache miss)
        $start_time = microtime(true);
        $result1 = $api_handler->search_plugins('cache_test');
        $cache_miss_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertIsNotWPError($result1);
        
        // Second request (cache hit)
        $start_time = microtime(true);
        $result2 = $api_handler->search_plugins('cache_test');
        $cache_hit_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertIsNotWPError($result2);
        $this->assertEquals($result1, $result2);
        
        // Cache hit should be significantly faster
        $this->assertLessThan($cache_miss_time / 2, $cache_hit_time,
            "Cache hit ({$cache_hit_time}ms) should be faster than cache miss ({$cache_miss_time}ms)");
        $this->assertLessThan(10, $cache_hit_time,
            "Cache hit took {$cache_hit_time}ms, expected < 10ms");
    }

    /**
     * Test plugin filtering performance with various filter combinations
     */
    public function test_filtering_performance() {
        $plugins = array_fill(0, 1000, $this->create_test_plugin_data());
        
        // Vary plugin data for realistic filtering
        foreach ($plugins as $i => &$plugin) {
            $plugin['slug'] = "plugin-{$i}";
            $plugin['active_installs'] = rand(100, 5000000);
            $plugin['rating'] = rand(1, 5) + (rand(0, 9) / 10);
            $plugin['last_updated'] = date('Y-m-d', strtotime('-' . rand(1, 365) . ' days'));
        }
        
        $ajax_handler = new WP_Plugin_Filters_AJAX_Handler();
        
        // Test installation range filtering
        $start_time = microtime(true);
        $filtered = $this->apply_installation_filter_reflection($ajax_handler, $plugins, '100k-1m');
        $filter_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertLessThan(50, $filter_time,
            "Installation filtering (1000 plugins) took {$filter_time}ms, expected < 50ms");
        
        // Verify filtering accuracy
        foreach ($filtered as $plugin) {
            $this->assertGreaterThanOrEqual(100000, $plugin['active_installs']);
            $this->assertLessThan(1000000, $plugin['active_installs']);
        }
        
        // Test date filtering
        $start_time = microtime(true);
        $date_filtered = $this->apply_date_filter_reflection($ajax_handler, $plugins, 'last_month');
        $date_filter_time = (microtime(true) - $start_time) * 1000;
        
        $this->assertLessThan(50, $date_filter_time,
            "Date filtering (1000 plugins) took {$date_filter_time}ms, expected < 50ms");
    }

    /**
     * Reflection helper to access private methods for testing
     */
    private function execute_filtered_search_reflection($ajax_handler, $request_data) {
        $reflection = new ReflectionClass($ajax_handler);
        $method = $reflection->getMethod('execute_filtered_search');
        $method->setAccessible(true);
        return $method->invokeArgs($ajax_handler, [$request_data]);
    }

    /**
     * Reflection helper for installation filtering
     */
    private function apply_installation_filter_reflection($ajax_handler, $plugins, $range) {
        $reflection = new ReflectionClass($ajax_handler);
        $method = $reflection->getMethod('plugin_matches_installation_range');
        $method->setAccessible(true);
        
        return array_filter($plugins, function($plugin) use ($ajax_handler, $method, $range) {
            return $method->invokeArgs($ajax_handler, [$plugin, $range]);
        });
    }

    /**
     * Reflection helper for date filtering
     */
    private function apply_date_filter_reflection($ajax_handler, $plugins, $timeframe) {
        $reflection = new ReflectionClass($ajax_handler);
        $method = $reflection->getMethod('plugin_matches_update_timeframe');
        $method->setAccessible(true);
        
        return array_filter($plugins, function($plugin) use ($ajax_handler, $method, $timeframe) {
            return $method->invokeArgs($ajax_handler, [$plugin, $timeframe]);
        });
    }

    /**
     * Test memory leak detection
     */
    public function test_memory_leak_detection() {
        $initial_memory = memory_get_usage();
        
        // Perform repeated operations that should not accumulate memory
        for ($i = 0; $i < 100; $i++) {
            $calculator = new WP_Plugin_Filters_Rating_Calculator();
            $plugin_data = $this->create_test_plugin_data();
            $rating = $calculator->calculate_usability_rating($plugin_data);
            
            unset($calculator, $plugin_data, $rating);
            
            // Force garbage collection every 10 iterations
            if ($i % 10 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        $final_memory = memory_get_usage();
        $memory_growth = ($final_memory - $initial_memory) / 1024 / 1024;
        
        $this->assertLessThan(5, $memory_growth,
            "Memory grew by {$memory_growth}MB after 100 operations, expected < 5MB (possible memory leak)");
    }

    /**
     * Test query performance optimization
     */
    public function test_query_performance_optimization() {
        global $wpdb;
        
        // Track query count
        $queries_before = $wpdb->num_queries;
        
        // Perform typical plugin operations
        $cache_manager = WP_Plugin_Filters_Cache_Manager::get_instance();
        $api_handler = new WP_Plugin_Filters_API_Handler();
        $calculator = new WP_Plugin_Filters_Rating_Calculator();
        
        // Cache some data
        $test_data = $this->create_test_plugin_data();
        $cache_manager->set('perf_test_1', $test_data, 'test_group');
        $cached = $cache_manager->get('perf_test_1', 'test_group');
        
        // Calculate rating
        $rating = $calculator->calculate_usability_rating($test_data);
        
        // Get settings
        $settings = get_option('wp_plugin_filters_settings', []);
        
        $queries_after = $wpdb->num_queries;
        $query_count = $queries_after - $queries_before;
        
        $this->assertLessThan(10, $query_count,
            "Plugin operations triggered {$query_count} queries, expected < 10");
    }
}