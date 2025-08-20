<?php
/**
 * Test Helper Functions
 *
 * @package WP_Plugin_Directory_Filters
 */

/**
 * Create mock plugin data for testing
 *
 * @param array $overrides Override default values
 * @return array Plugin data array
 */
function wp_plugin_filters_create_mock_plugin($overrides = []) {
    $defaults = [
        'slug' => 'test-plugin-' . wp_rand(1000, 9999),
        'name' => 'Test Plugin ' . wp_rand(1, 100),
        'version' => '1.' . wp_rand(0, 9) . '.' . wp_rand(0, 9),
        'author' => 'Test Author ' . wp_rand(1, 50),
        'rating' => wp_rand(10, 50) / 10, // 1.0 to 5.0
        'num_ratings' => wp_rand(5, 2000),
        'active_installs' => wp_rand(100, 5000000),
        'last_updated' => date('Y-m-d', strtotime('-' . wp_rand(1, 365) . ' days')),
        'added' => date('Y-m-d', strtotime('-' . wp_rand(365, 1825) . ' days')),
        'tested' => '6.' . wp_rand(1, 4),
        'requires' => '5.' . wp_rand(6, 8),
        'short_description' => 'Test plugin short description for ' . wp_rand(1, 1000),
        'description' => 'Full description for test plugin ' . wp_rand(1, 1000) . '. This is a longer description.',
        'homepage' => 'https://example.com/plugin-' . wp_rand(1, 1000),
        'download_link' => 'https://downloads.wordpress.org/plugin/test-plugin-' . wp_rand(1, 1000) . '.zip',
        'tags' => wp_plugin_filters_get_random_tags(),
        'support_threads' => wp_rand(0, 100),
        'support_threads_resolved' => 0, // Will be calculated
        'downloaded' => wp_rand(1000, 10000000),
        'ratings' => []
    ];
    
    // Calculate support threads resolved (0-100% of total threads)
    if ($defaults['support_threads'] > 0) {
        $resolution_rate = wp_rand(0, 100) / 100;
        $defaults['support_threads_resolved'] = intval($defaults['support_threads'] * $resolution_rate);
    }
    
    // Generate realistic ratings distribution
    $total_ratings = $defaults['num_ratings'];
    if ($total_ratings > 0) {
        $rating_avg = $defaults['rating'];
        $defaults['ratings'] = wp_plugin_filters_generate_rating_distribution($total_ratings, $rating_avg);
    }
    
    return array_merge($defaults, $overrides);
}

/**
 * Get random plugin tags
 *
 * @param int $count Number of tags to return
 * @return array Array of tag strings
 */
function wp_plugin_filters_get_random_tags($count = null) {
    $available_tags = [
        'admin', 'ajax', 'api', 'authentication', 'backup', 'block', 'blocks',
        'cache', 'caching', 'comments', 'contact', 'contact-form', 'content',
        'custom', 'custom-fields', 'custom-post-type', 'dashboard', 'database',
        'development', 'ecommerce', 'editor', 'email', 'form', 'forms',
        'gallery', 'google', 'images', 'import', 'integration', 'javascript',
        'jquery', 'login', 'media', 'menu', 'meta', 'multisite', 'optimization',
        'page', 'pages', 'performance', 'plugin', 'post', 'posts', 'security',
        'seo', 'settings', 'shortcode', 'sidebar', 'social', 'spam', 'themes',
        'users', 'widget', 'widgets', 'woocommerce', 'wordpress'
    ];
    
    if ($count === null) {
        $count = wp_rand(2, 6);
    }
    
    $selected_tags = array_rand(array_flip($available_tags), min($count, count($available_tags)));
    
    if (!is_array($selected_tags)) {
        $selected_tags = [$selected_tags];
    }
    
    return $selected_tags;
}

/**
 * Generate realistic rating distribution
 *
 * @param int   $total_ratings Total number of ratings
 * @param float $average_rating Average rating (1-5)
 * @return array Rating distribution array
 */
function wp_plugin_filters_generate_rating_distribution($total_ratings, $average_rating) {
    $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    
    // Simple algorithm to distribute ratings around the average
    $remaining = $total_ratings;
    
    // Start with the rating closest to average
    $primary_rating = round($average_rating);
    $primary_count = intval($total_ratings * 0.4); // 40% of ratings at primary level
    
    $distribution[$primary_rating] = min($primary_count, $remaining);
    $remaining -= $distribution[$primary_rating];
    
    // Distribute remaining ratings
    while ($remaining > 0) {
        $rating = wp_rand(1, 5);
        $count = wp_rand(1, min(10, $remaining));
        
        $distribution[$rating] += $count;
        $remaining -= $count;
    }
    
    return $distribution;
}

/**
 * Create mock API search response
 *
 * @param array $plugins Array of plugin data
 * @param array $info Search info (page, pages, results)
 * @return array API response structure
 */
function wp_plugin_filters_create_mock_search_response($plugins = [], $info = []) {
    $default_info = [
        'page' => 1,
        'pages' => 1,
        'results' => count($plugins)
    ];
    
    return [
        'plugins' => $plugins,
        'info' => array_merge($default_info, $info)
    ];
}

/**
 * Create mock WordPress.org API responses
 *
 * @param string $action API action (query_plugins, plugin_information)
 * @param array  $params Request parameters
 * @return array Mock response
 */
function wp_plugin_filters_create_mock_api_response($action, $params = []) {
    switch ($action) {
        case 'query_plugins':
            return wp_plugin_filters_create_mock_query_plugins_response($params);
            
        case 'plugin_information':
            return wp_plugin_filters_create_mock_plugin_information_response($params);
            
        default:
            return new WP_Error('unknown_action', 'Unknown API action');
    }
}

/**
 * Create mock query_plugins response
 *
 * @param array $params Request parameters
 * @return array Mock response
 */
function wp_plugin_filters_create_mock_query_plugins_response($params = []) {
    $search_term = $params['search'] ?? '';
    $page = $params['page'] ?? 1;
    $per_page = $params['per_page'] ?? 24;
    $tag = $params['tag'] ?? '';
    $author = $params['author'] ?? '';
    
    // Generate plugins based on search criteria
    $plugins = [];
    $plugin_count = wp_rand(5, 48);
    
    for ($i = 0; $i < $plugin_count; $i++) {
        $plugin_overrides = [];
        
        // Match search term in name or description
        if (!empty($search_term)) {
            $plugin_overrides['name'] = ucwords($search_term) . ' Plugin ' . ($i + 1);
            $plugin_overrides['short_description'] = "Plugin for {$search_term} functionality";
        }
        
        // Match tag
        if (!empty($tag)) {
            $plugin_overrides['tags'] = array_merge([$tag], wp_plugin_filters_get_random_tags(wp_rand(1, 3)));
        }
        
        // Match author
        if (!empty($author)) {
            $plugin_overrides['author'] = $author;
        }
        
        $plugins[] = wp_plugin_filters_create_mock_plugin($plugin_overrides);
    }
    
    // Calculate pagination
    $total_results = count($plugins) * wp_rand(1, 10); // Simulate more results
    $total_pages = ceil($total_results / $per_page);
    
    return wp_plugin_filters_create_mock_search_response($plugins, [
        'page' => $page,
        'pages' => $total_pages,
        'results' => $total_results
    ]);
}

/**
 * Create mock plugin_information response
 *
 * @param array $params Request parameters
 * @return array Mock response
 */
function wp_plugin_filters_create_mock_plugin_information_response($params = []) {
    $slug = $params['slug'] ?? 'test-plugin';
    
    $plugin = wp_plugin_filters_create_mock_plugin([
        'slug' => $slug,
        'name' => ucwords(str_replace('-', ' ', $slug)),
        'description' => "Full description for {$slug}. This plugin provides comprehensive functionality for WordPress websites. It includes advanced features and is regularly updated.",
        'installation' => "1. Upload the plugin files to the `/wp-content/plugins/{$slug}` directory\n2. Activate the plugin through the 'Plugins' screen in WordPress\n3. Configure the plugin settings",
        'faq' => [
            'How do I use this plugin?' => 'Simply activate the plugin and configure the settings in the admin area.',
            'Is this plugin compatible with my theme?' => 'Yes, this plugin is designed to work with any properly coded WordPress theme.'
        ],
        'changelog' => [
            '1.0.0' => 'Initial release',
            '1.0.1' => 'Bug fixes and improvements'
        ],
        'screenshots' => [
            'https://ps.w.org/' . $slug . '/assets/screenshot-1.png',
            'https://ps.w.org/' . $slug . '/assets/screenshot-2.png'
        ]
    ]);
    
    return $plugin;
}

/**
 * Create test user with specific role
 *
 * @param string $role User role
 * @param array  $user_data Additional user data
 * @return int User ID
 */
function wp_plugin_filters_create_test_user($role = 'subscriber', $user_data = []) {
    $defaults = [
        'user_login' => 'test_' . $role . '_' . wp_rand(1000, 9999),
        'user_email' => 'test_' . $role . '_' . wp_rand(1000, 9999) . '@example.com',
        'user_pass' => 'test_password_' . wp_rand(1000, 9999),
        'first_name' => 'Test',
        'last_name' => ucfirst($role),
        'role' => $role
    ];
    
    $user_data = array_merge($defaults, $user_data);
    
    $user_id = wp_insert_user($user_data);
    
    if (is_wp_error($user_id)) {
        throw new Exception('Failed to create test user: ' . $user_id->get_error_message());
    }
    
    return $user_id;
}

/**
 * Create test settings configuration
 *
 * @param array $overrides Override default settings
 * @return array Settings array
 */
function wp_plugin_filters_create_test_settings($overrides = []) {
    $defaults = [
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
        'enable_debug_logging' => false,
        'max_cache_size' => 100,
        'cache_cleanup_interval' => 'daily'
    ];
    
    return array_merge($defaults, $overrides);
}

/**
 * Simulate plugin search with filters
 *
 * @param array $plugins Array of plugin data
 * @param array $filters Filter parameters
 * @return array Filtered plugins
 */
function wp_plugin_filters_apply_test_filters($plugins, $filters = []) {
    $filtered = $plugins;
    
    // Search term filter
    if (!empty($filters['search_term'])) {
        $search_term = strtolower($filters['search_term']);
        $filtered = array_filter($filtered, function($plugin) use ($search_term) {
            return strpos(strtolower($plugin['name']), $search_term) !== false ||
                   strpos(strtolower($plugin['short_description']), $search_term) !== false;
        });
    }
    
    // Installation range filter
    if (!empty($filters['installation_range']) && $filters['installation_range'] !== 'all') {
        $filtered = array_filter($filtered, function($plugin) use ($filters) {
            return wp_plugin_filters_matches_installation_range($plugin['active_installs'], $filters['installation_range']);
        });
    }
    
    // Update timeframe filter
    if (!empty($filters['update_timeframe']) && $filters['update_timeframe'] !== 'all') {
        $filtered = array_filter($filtered, function($plugin) use ($filters) {
            return wp_plugin_filters_matches_update_timeframe($plugin['last_updated'], $filters['update_timeframe']);
        });
    }
    
    // Rating filter
    if (!empty($filters['usability_rating']) && $filters['usability_rating'] > 0) {
        $filtered = array_filter($filtered, function($plugin) use ($filters) {
            return $plugin['rating'] >= $filters['usability_rating'];
        });
    }
    
    return array_values($filtered);
}

/**
 * Check if installation count matches range
 *
 * @param int    $installs Installation count
 * @param string $range    Range identifier
 * @return bool
 */
function wp_plugin_filters_matches_installation_range($installs, $range) {
    switch ($range) {
        case '0-1k':
            return $installs < 1000;
        case '1k-10k':
            return $installs >= 1000 && $installs < 10000;
        case '10k-100k':
            return $installs >= 10000 && $installs < 100000;
        case '100k-1m':
            return $installs >= 100000 && $installs < 1000000;
        case '1m-plus':
            return $installs >= 1000000;
        default:
            return true;
    }
}

/**
 * Check if update date matches timeframe
 *
 * @param string $last_updated Last updated date
 * @param string $timeframe    Timeframe identifier
 * @return bool
 */
function wp_plugin_filters_matches_update_timeframe($last_updated, $timeframe) {
    $last_updated_timestamp = strtotime($last_updated);
    $now = current_time('timestamp');
    $days_ago = ($now - $last_updated_timestamp) / DAY_IN_SECONDS;
    
    switch ($timeframe) {
        case 'last_week':
            return $days_ago <= 7;
        case 'last_month':
            return $days_ago <= 30;
        case 'last_3months':
            return $days_ago <= 90;
        case 'last_6months':
            return $days_ago <= 180;
        case 'last_year':
            return $days_ago <= 365;
        case 'older':
            return $days_ago > 365;
        default:
            return true;
    }
}

/**
 * Sort plugins by specified criteria
 *
 * @param array  $plugins   Array of plugin data
 * @param string $sort_by   Sort criteria
 * @param string $direction Sort direction (asc/desc)
 * @return array Sorted plugins
 */
function wp_plugin_filters_sort_test_plugins($plugins, $sort_by = 'relevance', $direction = 'desc') {
    if ($sort_by === 'relevance') {
        return $plugins; // Keep original order
    }
    
    usort($plugins, function($a, $b) use ($sort_by, $direction) {
        $comparison = 0;
        
        switch ($sort_by) {
            case 'installations':
                $comparison = ($a['active_installs'] ?? 0) <=> ($b['active_installs'] ?? 0);
                break;
            case 'rating':
                $comparison = ($a['rating'] ?? 0) <=> ($b['rating'] ?? 0);
                break;
            case 'updated':
                $a_time = strtotime($a['last_updated'] ?? '1970-01-01');
                $b_time = strtotime($b['last_updated'] ?? '1970-01-01');
                $comparison = $a_time <=> $b_time;
                break;
            case 'name':
                $comparison = strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                break;
            default:
                $comparison = 0;
                break;
        }
        
        return $direction === 'desc' ? -$comparison : $comparison;
    });
    
    return $plugins;
}

/**
 * Generate performance test data
 *
 * @param int $count Number of plugins to generate
 * @return array Array of plugin data
 */
function wp_plugin_filters_generate_performance_test_data($count = 1000) {
    $plugins = [];
    
    for ($i = 0; $i < $count; $i++) {
        $plugins[] = wp_plugin_filters_create_mock_plugin([
            'slug' => 'perf-test-plugin-' . $i,
            'name' => 'Performance Test Plugin ' . $i,
            'active_installs' => wp_rand(100, 5000000),
            'rating' => wp_rand(10, 50) / 10,
            'last_updated' => date('Y-m-d', strtotime('-' . wp_rand(1, 365) . ' days'))
        ]);
    }
    
    return $plugins;
}

/**
 * Create HTTP response mock
 *
 * @param array $data        Response data
 * @param int   $status_code HTTP status code
 * @param array $headers     Response headers
 * @return array HTTP response array
 */
function wp_plugin_filters_create_http_response($data, $status_code = 200, $headers = []) {
    $default_headers = [
        'content-type' => 'application/json',
        'cache-control' => 'no-cache'
    ];
    
    return [
        'response' => [
            'code' => $status_code,
            'message' => wp_remote_retrieve_response_message_from_code($status_code)
        ],
        'headers' => array_merge($default_headers, $headers),
        'body' => is_array($data) ? wp_json_encode($data) : $data,
        'cookies' => []
    ];
}

/**
 * Get response message from HTTP status code
 *
 * @param int $code HTTP status code
 * @return string Status message
 */
function wp_remote_retrieve_response_message_from_code($code) {
    $messages = [
        200 => 'OK',
        201 => 'Created',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable'
    ];
    
    return $messages[$code] ?? 'Unknown';
}

/**
 * Clean up test data
 *
 * @param array $cleanup_data Data to clean up
 */
function wp_plugin_filters_cleanup_test_data($cleanup_data = []) {
    // Clean up test users
    if (isset($cleanup_data['users'])) {
        foreach ($cleanup_data['users'] as $user_id) {
            wp_delete_user($user_id, true);
        }
    }
    
    // Clean up test options
    if (isset($cleanup_data['options'])) {
        foreach ($cleanup_data['options'] as $option_name) {
            delete_option($option_name);
        }
    }
    
    // Clean up test transients
    if (isset($cleanup_data['transients'])) {
        foreach ($cleanup_data['transients'] as $transient_name) {
            delete_transient($transient_name);
        }
    }
    
    // Clean up test files
    if (isset($cleanup_data['files'])) {
        foreach ($cleanup_data['files'] as $file_path) {
            if (file_exists($file_path) && is_writable($file_path)) {
                unlink($file_path);
            }
        }
    }
}

/**
 * Assert plugin data structure is valid
 *
 * @param array  $plugin_data Plugin data to validate
 * @param string $message     Assertion message
 */
function wp_plugin_filters_assert_valid_plugin_data($plugin_data, $message = '') {
    $required_fields = [
        'slug', 'name', 'version', 'author', 'rating', 'num_ratings',
        'active_installs', 'last_updated', 'tested', 'requires',
        'short_description', 'homepage', 'download_link'
    ];
    
    foreach ($required_fields as $field) {
        if (!isset($plugin_data[$field])) {
            throw new Exception($message . " Missing required field: {$field}");
        }
    }
    
    // Validate data types
    if (!is_string($plugin_data['slug'])) {
        throw new Exception($message . " Invalid slug data type");
    }
    
    if (!is_numeric($plugin_data['rating']) || $plugin_data['rating'] < 0 || $plugin_data['rating'] > 5) {
        throw new Exception($message . " Invalid rating value");
    }
    
    if (!is_int($plugin_data['active_installs']) || $plugin_data['active_installs'] < 0) {
        throw new Exception($message . " Invalid active installs value");
    }
}