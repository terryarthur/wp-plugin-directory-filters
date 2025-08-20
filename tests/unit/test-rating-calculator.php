<?php
/**
 * Tests for WP_Plugin_Filters_Rating_Calculator class
 *
 * @package WP_Plugin_Directory_Filters
 */

class Test_WP_Plugin_Filters_Rating_Calculator extends WP_Plugin_Filters_Test_Case {

    /**
     * Rating Calculator instance
     *
     * @var WP_Plugin_Filters_Rating_Calculator
     */
    private $calculator;

    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->calculator = new WP_Plugin_Filters_Rating_Calculator();
    }

    /**
     * Test calculator instantiation
     */
    public function test_calculator_instantiation() {
        $this->assertInstanceOf('WP_Plugin_Filters_Rating_Calculator', $this->calculator);
    }

    /**
     * Test default weights are loaded correctly
     */
    public function test_default_weights() {
        $weights = $this->calculator->get_weights();
        
        $this->assertIsArray($weights);
        $this->assertArrayHasKey('user_rating', $weights);
        $this->assertArrayHasKey('rating_count', $weights);
        $this->assertArrayHasKey('installation_count', $weights);
        $this->assertArrayHasKey('support_responsiveness', $weights);
        
        // Weights should sum to 100
        $total = array_sum($weights);
        $this->assertEquals(100, $total, 'Default weights should sum to 100');
    }

    /**
     * Test usability rating calculation with complete data
     */
    public function test_calculate_usability_rating_complete_data() {
        $plugin_data = [
            'rating' => 4.5,
            'num_ratings' => 150,
            'active_installs' => 50000,
            'support_threads' => 20,
            'support_threads_resolved' => 18
        ];

        $rating = $this->calculator->calculate_usability_rating($plugin_data);

        $this->assertIsFloat($rating);
        $this->assertGreaterThanOrEqual(1.0, $rating);
        $this->assertLessThanOrEqual(5.0, $rating);
        $this->assertGreaterThan(3.0, $rating); // Should be good rating with this data
    }

    /**
     * Test usability rating calculation with missing data
     */
    public function test_calculate_usability_rating_missing_data() {
        $plugin_data = [
            'rating' => 3.0,
            'num_ratings' => 50,
            // Missing active_installs and support data
        ];

        $rating = $this->calculator->calculate_usability_rating($plugin_data);

        $this->assertIsFloat($rating);
        $this->assertGreaterThanOrEqual(1.0, $rating);
        $this->assertLessThanOrEqual(5.0, $rating);
    }

    /**
     * Test usability rating calculation with no data
     */
    public function test_calculate_usability_rating_no_data() {
        $plugin_data = [];

        $rating = $this->calculator->calculate_usability_rating($plugin_data);

        $this->assertIsFloat($rating);
        $this->assertEquals(1.0, $rating); // Should return minimum rating
    }

    /**
     * Test usability rating calculation with excellent data
     */
    public function test_calculate_usability_rating_excellent_data() {
        $plugin_data = [
            'rating' => 5.0,
            'num_ratings' => 2000,
            'active_installs' => 5000000,
            'support_threads' => 100,
            'support_threads_resolved' => 98
        ];

        $rating = $this->calculator->calculate_usability_rating($plugin_data);

        $this->assertIsFloat($rating);
        $this->assertGreaterThan(4.5, $rating); // Should be very high rating
        $this->assertLessThanOrEqual(5.0, $rating);
    }

    /**
     * Test usability rating calculation with poor data
     */
    public function test_calculate_usability_rating_poor_data() {
        $plugin_data = [
            'rating' => 1.5,
            'num_ratings' => 5,
            'active_installs' => 50,
            'support_threads' => 10,
            'support_threads_resolved' => 2
        ];

        $rating = $this->calculator->calculate_usability_rating($plugin_data);

        $this->assertIsFloat($rating);
        $this->assertGreaterThanOrEqual(1.0, $rating);
        $this->assertLessThan(3.0, $rating); // Should be low rating
    }

    /**
     * Test individual component calculations
     */
    public function test_user_rating_component() {
        // Test with various rating values
        $test_cases = [
            ['rating' => 5.0, 'expected_min' => 0.9, 'expected_max' => 1.0],
            ['rating' => 4.0, 'expected_min' => 0.7, 'expected_max' => 0.9],
            ['rating' => 3.0, 'expected_min' => 0.5, 'expected_max' => 0.7],
            ['rating' => 2.0, 'expected_min' => 0.3, 'expected_max' => 0.5],
            ['rating' => 1.0, 'expected_min' => 0.1, 'expected_max' => 0.3],
            ['rating' => 0, 'expected' => null], // No rating data
        ];

        foreach ($test_cases as $case) {
            $plugin_data = isset($case['rating']) ? ['rating' => $case['rating']] : [];
            $this->calculator->calculate_usability_rating($plugin_data);
            $breakdown = $this->calculator->get_calculation_breakdown();
            
            $component_score = $breakdown['components']['user_rating'];
            
            if (isset($case['expected'])) {
                $this->assertEquals($case['expected'], $component_score);
            } else {
                $this->assertGreaterThanOrEqual($case['expected_min'], $component_score);
                $this->assertLessThanOrEqual($case['expected_max'], $component_score);
            }
        }
    }

    /**
     * Test rating count component calculation
     */
    public function test_rating_count_component() {
        $test_cases = [
            ['num_ratings' => 0, 'expected' => null],
            ['num_ratings' => 5, 'expected' => 0.4],
            ['num_ratings' => 20, 'expected' => 0.6],
            ['num_ratings' => 100, 'expected' => 0.8],
            ['num_ratings' => 1000, 'expected' => 1.0],
            ['num_ratings' => 5000, 'expected' => 1.0],
        ];

        foreach ($test_cases as $case) {
            $plugin_data = $case['num_ratings'] > 0 ? ['num_ratings' => $case['num_ratings']] : [];
            $this->calculator->calculate_usability_rating($plugin_data);
            $breakdown = $this->calculator->get_calculation_breakdown();
            
            $component_score = $breakdown['components']['rating_count'];
            $this->assertEquals($case['expected'], $component_score, "Failed for {$case['num_ratings']} ratings");
        }
    }

    /**
     * Test installation count component calculation
     */
    public function test_installation_count_component() {
        $test_cases = [
            ['active_installs' => 0, 'expected' => null],
            ['active_installs' => 50, 'expected' => 0.2],
            ['active_installs' => 500, 'expected' => 0.3],
            ['active_installs' => 5000, 'expected' => 0.5],
            ['active_installs' => 50000, 'expected' => 0.7],
            ['active_installs' => 500000, 'expected' => 0.9],
            ['active_installs' => 5000000, 'expected' => 1.0],
        ];

        foreach ($test_cases as $case) {
            $plugin_data = $case['active_installs'] > 0 ? ['active_installs' => $case['active_installs']] : [];
            $this->calculator->calculate_usability_rating($plugin_data);
            $breakdown = $this->calculator->get_calculation_breakdown();
            
            $component_score = $breakdown['components']['installation_count'];
            $this->assertEquals($case['expected'], $component_score, "Failed for {$case['active_installs']} installs");
        }
    }

    /**
     * Test support responsiveness component calculation
     */
    public function test_support_responsiveness_component() {
        $test_cases = [
            ['support_threads' => 0, 'support_threads_resolved' => 0, 'expected' => 0.5],
            ['support_threads' => 10, 'support_threads_resolved' => 2, 'expected' => 0.3], // 20% resolution
            ['support_threads' => 10, 'support_threads_resolved' => 5, 'expected' => 0.6], // 50% resolution
            ['support_threads' => 10, 'support_threads_resolved' => 8, 'expected' => 0.9], // 80% resolution
            ['support_threads' => 10, 'support_threads_resolved' => 10, 'expected' => 1.0], // 100% resolution
        ];

        foreach ($test_cases as $case) {
            $plugin_data = [
                'support_threads' => $case['support_threads'],
                'support_threads_resolved' => $case['support_threads_resolved']
            ];
            
            $this->calculator->calculate_usability_rating($plugin_data);
            $breakdown = $this->calculator->get_calculation_breakdown();
            
            $component_score = $breakdown['components']['support_responsiveness'];
            $this->assertEquals($case['expected'], $component_score, 
                "Failed for {$case['support_threads_resolved']}/{$case['support_threads']} resolution rate");
        }
    }

    /**
     * Test calculation breakdown functionality
     */
    public function test_calculation_breakdown() {
        $plugin_data = [
            'rating' => 4.0,
            'num_ratings' => 100,
            'active_installs' => 10000,
            'support_threads' => 20,
            'support_threads_resolved' => 16
        ];

        $rating = $this->calculator->calculate_usability_rating($plugin_data);
        $breakdown = $this->calculator->get_calculation_breakdown();

        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey('components', $breakdown);
        $this->assertArrayHasKey('weights', $breakdown);
        $this->assertArrayHasKey('weighted_score', $breakdown);
        $this->assertArrayHasKey('total_weight', $breakdown);
        $this->assertArrayHasKey('normalized_score', $breakdown);
        $this->assertArrayHasKey('final_score', $breakdown);

        // Verify components
        $this->assertArrayHasKey('user_rating', $breakdown['components']);
        $this->assertArrayHasKey('rating_count', $breakdown['components']);
        $this->assertArrayHasKey('installation_count', $breakdown['components']);
        $this->assertArrayHasKey('support_responsiveness', $breakdown['components']);

        // Verify final score matches return value
        $this->assertEquals($rating, $breakdown['final_score']);
    }

    /**
     * Test weight updates
     */
    public function test_update_weights() {
        $new_weights = [
            'user_rating' => 50,
            'rating_count' => 15,
            'installation_count' => 20,
            'support_responsiveness' => 15
        ];

        $result = $this->calculator->update_weights($new_weights);
        $this->assertTrue($result);

        $updated_weights = $this->calculator->get_weights();
        $this->assertEquals($new_weights, $updated_weights);

        // Verify weights are persisted
        $stored_settings = get_option('wp_plugin_filters_settings', []);
        $this->assertEquals($new_weights, $stored_settings['usability_weights']);
    }

    /**
     * Test weight validation
     */
    public function test_weight_validation() {
        // Test weights that don't sum to 100
        $invalid_weights = [
            'user_rating' => 50,
            'rating_count' => 30,
            'installation_count' => 30,
            'support_responsiveness' => 10
        ]; // Sums to 120

        $result = $this->calculator->update_weights($invalid_weights);
        $this->assertIsWPError($result);
        $this->assertEquals('invalid_total', $result->get_error_code());

        // Test missing component
        $missing_component = [
            'user_rating' => 50,
            'rating_count' => 30,
            'installation_count' => 20
            // Missing support_responsiveness
        ];

        $result = $this->calculator->update_weights($missing_component);
        $this->assertIsWPError($result);
        $this->assertEquals('missing_component', $result->get_error_code());

        // Test invalid weight values
        $invalid_values = [
            'user_rating' => -10,
            'rating_count' => 30,
            'installation_count' => 40,
            'support_responsiveness' => 40
        ];

        $result = $this->calculator->update_weights($invalid_values);
        $this->assertIsWPError($result);
        $this->assertEquals('invalid_range', $result->get_error_code());
    }

    /**
     * Test reset weights to default
     */
    public function test_reset_weights_to_default() {
        // Change weights first
        $custom_weights = [
            'user_rating' => 50,
            'rating_count' => 10,
            'installation_count' => 30,
            'support_responsiveness' => 10
        ];

        $this->calculator->update_weights($custom_weights);
        $this->assertEquals($custom_weights, $this->calculator->get_weights());

        // Reset to default
        $result = $this->calculator->reset_weights_to_default();
        $this->assertTrue($result);

        $reset_weights = $this->calculator->get_weights();
        $default_weights = [
            'user_rating' => 40,
            'rating_count' => 20,
            'installation_count' => 25,
            'support_responsiveness' => 15
        ];

        $this->assertEquals($default_weights, $reset_weights);
    }

    /**
     * Test algorithm explanation
     */
    public function test_algorithm_explanation() {
        $explanation = $this->calculator->get_algorithm_explanation();

        $this->assertIsArray($explanation);
        $this->assertArrayHasKey('title', $explanation);
        $this->assertArrayHasKey('description', $explanation);
        $this->assertArrayHasKey('components', $explanation);
        $this->assertArrayHasKey('scale', $explanation);

        // Check components explanation
        $components = $explanation['components'];
        $this->assertArrayHasKey('user_rating', $components);
        $this->assertArrayHasKey('rating_count', $components);
        $this->assertArrayHasKey('installation_count', $components);
        $this->assertArrayHasKey('support_responsiveness', $components);

        foreach ($components as $component) {
            $this->assertArrayHasKey('label', $component);
            $this->assertArrayHasKey('description', $component);
            $this->assertArrayHasKey('weight', $component);
        }
    }

    /**
     * Test batch rating calculation
     */
    public function test_calculate_batch_ratings() {
        $plugins_data = [
            [
                'slug' => 'plugin-1',
                'rating' => 4.5,
                'num_ratings' => 100,
                'active_installs' => 50000,
                'support_threads' => 20,
                'support_threads_resolved' => 18
            ],
            [
                'slug' => 'plugin-2',
                'rating' => 3.0,
                'num_ratings' => 50,
                'active_installs' => 1000,
                'support_threads' => 10,
                'support_threads_resolved' => 5
            ],
            [
                'slug' => 'plugin-3',
                'rating' => 5.0,
                'num_ratings' => 500,
                'active_installs' => 1000000,
                'support_threads' => 50,
                'support_threads_resolved' => 48
            ]
        ];

        $ratings = $this->calculator->calculate_batch_ratings($plugins_data);

        $this->assertIsArray($ratings);
        $this->assertCount(3, $ratings);
        $this->assertArrayHasKey('plugin-1', $ratings);
        $this->assertArrayHasKey('plugin-2', $ratings);
        $this->assertArrayHasKey('plugin-3', $ratings);

        // Verify rating order (plugin-3 should have highest rating)
        $this->assertGreaterThan($ratings['plugin-1'], $ratings['plugin-3']);
        $this->assertGreaterThan($ratings['plugin-2'], $ratings['plugin-1']);
    }

    /**
     * Test edge cases and boundary conditions
     */
    public function test_edge_cases() {
        // Test with extreme values
        $extreme_plugin_data = [
            'rating' => 5.0,
            'num_ratings' => PHP_INT_MAX,
            'active_installs' => PHP_INT_MAX,
            'support_threads' => PHP_INT_MAX,
            'support_threads_resolved' => PHP_INT_MAX
        ];

        $rating = $this->calculator->calculate_usability_rating($extreme_plugin_data);
        $this->assertLessThanOrEqual(5.0, $rating);
        $this->assertGreaterThanOrEqual(1.0, $rating);

        // Test with negative values (should be handled gracefully)
        $negative_plugin_data = [
            'rating' => -1.0,
            'num_ratings' => -50,
            'active_installs' => -1000,
            'support_threads' => -10,
            'support_threads_resolved' => -5
        ];

        $rating = $this->calculator->calculate_usability_rating($negative_plugin_data);
        $this->assertLessThanOrEqual(5.0, $rating);
        $this->assertGreaterThanOrEqual(1.0, $rating);
    }

    /**
     * Test weight configuration from WordPress options
     */
    public function test_weight_configuration_from_options() {
        // Set custom weights in options
        $custom_weights = [
            'user_rating' => 30,
            'rating_count' => 25,
            'installation_count' => 30,
            'support_responsiveness' => 15
        ];

        update_option('wp_plugin_filters_settings', [
            'usability_weights' => $custom_weights
        ]);

        // Create new calculator instance to load from options
        $new_calculator = new WP_Plugin_Filters_Rating_Calculator();
        $loaded_weights = $new_calculator->get_weights();

        $this->assertEquals($custom_weights, $loaded_weights);
    }

    /**
     * Test rating consistency
     */
    public function test_rating_consistency() {
        $plugin_data = [
            'rating' => 4.0,
            'num_ratings' => 100,
            'active_installs' => 10000,
            'support_threads' => 20,
            'support_threads_resolved' => 16
        ];

        // Calculate rating multiple times
        $ratings = [];
        for ($i = 0; $i < 10; $i++) {
            $ratings[] = $this->calculator->calculate_usability_rating($plugin_data);
        }

        // All ratings should be identical
        $unique_ratings = array_unique($ratings);
        $this->assertCount(1, $unique_ratings, 'Rating calculation should be consistent');
    }
}