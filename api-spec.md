# WordPress Plugin Directory Filters - API Specification

## Overview
This document specifies the API integration patterns for the WordPress Plugin Directory Filters enhancement, including WordPress.org Plugin API integration, custom WordPress AJAX endpoints, and internal API interfaces designed to work seamlessly within the WordPress ecosystem.

## WordPress.org Plugin API Integration

### Base Configuration
```yaml
wordpress_org_api:
  base_url: "https://api.wordpress.org/plugins/info/1.2/"
  timeout: 30
  user_agent: "WordPress Plugin Directory Filters/1.0.0"
  rate_limits:
    requests_per_minute: 60
    burst_limit: 10
  retry_strategy:
    max_attempts: 3
    backoff_multiplier: 2
    initial_delay: 1000ms
```

### Plugin Search Endpoint Integration

#### Request Specification
```http
POST https://api.wordpress.org/plugins/info/1.2/
Content-Type: application/x-www-form-urlencoded

action=query_plugins&request[search]=ecommerce&request[page]=1&request[per_page]=24&request[fields][short_description]=1&request[fields][description]=0&request[fields][tested]=1&request[fields][requires]=1&request[fields][rating]=1&request[fields][ratings]=1&request[fields][downloaded]=1&request[fields][active_installs]=1&request[fields][last_updated]=1&request[fields][homepage]=1&request[fields][tags]=1
```

#### PHP Implementation
```php
<?php
class WP_Plugin_API_Client {
    private $base_url = 'https://api.wordpress.org/plugins/info/1.2/';
    private $timeout = 30;
    
    public function search_plugins($search_term, $page = 1, $per_page = 24, $additional_filters = []) {
        $request_args = [
            'search' => sanitize_text_field($search_term),
            'page' => absint($page),
            'per_page' => absint($per_page),
            'fields' => [
                'short_description' => true,
                'description' => false,
                'tested' => true,
                'requires' => true,
                'rating' => true,
                'ratings' => true,
                'downloaded' => true,
                'active_installs' => true,
                'last_updated' => true,
                'homepage' => true,
                'tags' => true,
                'screenshots' => false,
                'sections' => false
            ]
        ];
        
        // Apply additional filters for enhanced search
        if (!empty($additional_filters['tag'])) {
            $request_args['tag'] = sanitize_key($additional_filters['tag']);
        }
        
        if (!empty($additional_filters['author'])) {
            $request_args['author'] = sanitize_user($additional_filters['author']);
        }
        
        $body = [
            'action' => 'query_plugins',
            'request' => $request_args
        ];
        
        $response = wp_remote_post($this->base_url, [
            'timeout' => $this->timeout,
            'user-agent' => 'WordPress Plugin Directory Filters/1.0.0',
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ]);
        
        if (is_wp_error($response)) {
            WP_Plugin_Debug_Logger::log('API Request Failed', [
                'error' => $response->get_error_message(),
                'search_term' => $search_term
            ]);
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Invalid JSON response from WordPress.org API');
        }
        
        return $this->sanitize_api_response($data);
    }
    
    private function sanitize_api_response($data) {
        if (!isset($data['plugins']) || !is_array($data['plugins'])) {
            return new WP_Error('invalid_response', 'Invalid response format from WordPress.org API');
        }
        
        $sanitized_plugins = [];
        foreach ($data['plugins'] as $plugin) {
            $sanitized_plugins[] = [
                'slug' => sanitize_key($plugin['slug']),
                'name' => sanitize_text_field($plugin['name']),
                'version' => sanitize_text_field($plugin['version']),
                'author' => sanitize_text_field($plugin['author']),
                'rating' => floatval($plugin['rating']) / 20, // Convert 0-100 to 0-5 scale
                'num_ratings' => absint($plugin['num_ratings']),
                'active_installs' => absint($plugin['active_installs']),
                'last_updated' => sanitize_text_field($plugin['last_updated']),
                'tested' => sanitize_text_field($plugin['tested']),
                'requires' => sanitize_text_field($plugin['requires']),
                'short_description' => wp_kses_post($plugin['short_description']),
                'homepage' => esc_url_raw($plugin['homepage']),
                'download_link' => esc_url_raw($plugin['download_link']),
                'tags' => array_map('sanitize_key', (array) $plugin['tags'])
            ];
        }
        
        return [
            'plugins' => $sanitized_plugins,
            'info' => [
                'page' => absint($data['info']['page']),
                'pages' => absint($data['info']['pages']),
                'results' => absint($data['info']['results'])
            ]
        ];
    }
}
```

#### Response Format
```json
{
  "plugins": [
    {
      "slug": "woocommerce",
      "name": "WooCommerce",
      "version": "8.5.2",
      "author": "Automattic",
      "rating": 4.6,
      "num_ratings": 4023,
      "active_installs": 5000000,
      "last_updated": "2024-01-15 14:30:00",
      "tested": "6.4.2",
      "requires": "6.0",
      "short_description": "The most customizable eCommerce platform...",
      "homepage": "https://woocommerce.com/",
      "download_link": "https://downloads.wordpress.org/plugin/woocommerce.8.5.2.zip",
      "tags": ["ecommerce", "store", "sales", "sell", "shop"]
    }
  ],
  "info": {
    "page": 1,
    "pages": 42,
    "results": 1000
  }
}
```

### Plugin Details Endpoint Integration

#### Request Specification
```php
<?php
public function get_plugin_details($slug) {
    $cache_key = "wp_plugin_details_{$slug}";
    $cached_data = get_transient($cache_key);
    
    if ($cached_data !== false) {
        return $cached_data;
    }
    
    $body = [
        'action' => 'plugin_information',
        'request' => [
            'slug' => sanitize_key($slug),
            'fields' => [
                'description' => true,
                'installation' => false,
                'faq' => false,
                'screenshots' => false,
                'changelog' => false,
                'reviews' => false,
                'sections' => false,
                'tested' => true,
                'requires' => true,
                'rating' => true,
                'ratings' => true,
                'downloaded' => true,
                'active_installs' => true,
                'last_updated' => true,
                'added' => true,
                'homepage' => true,
                'tags' => true,
                'support_threads' => true,
                'support_threads_resolved' => true
            ]
        ]
    ];
    
    $response = wp_remote_post($this->base_url, [
        'timeout' => $this->timeout,
        'user-agent' => 'WordPress Plugin Directory Filters/1.0.0',
        'body' => $body
    ]);
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    $sanitized_data = $this->sanitize_plugin_details($data);
    
    // Cache for 6 hours
    set_transient($cache_key, $sanitized_data, 21600);
    
    return $sanitized_data;
}

private function sanitize_plugin_details($plugin) {
    return [
        'slug' => sanitize_key($plugin['slug']),
        'name' => sanitize_text_field($plugin['name']),
        'version' => sanitize_text_field($plugin['version']),
        'author' => sanitize_text_field($plugin['author']),
        'rating' => floatval($plugin['rating']) / 20,
        'num_ratings' => absint($plugin['num_ratings']),
        'active_installs' => absint($plugin['active_installs']),
        'last_updated' => sanitize_text_field($plugin['last_updated']),
        'added' => sanitize_text_field($plugin['added']),
        'tested' => sanitize_text_field($plugin['tested']),
        'requires' => sanitize_text_field($plugin['requires']),
        'description' => wp_kses_post($plugin['description']),
        'homepage' => esc_url_raw($plugin['homepage']),
        'download_link' => esc_url_raw($plugin['download_link']),
        'tags' => array_map('sanitize_key', (array) $plugin['tags']),
        'support_threads' => absint($plugin['support_threads']),
        'support_threads_resolved' => absint($plugin['support_threads_resolved'])
    ];
}
```

## WordPress AJAX Endpoints

### Plugin Filter Endpoint

#### Endpoint Definition
```php
<?php
class WP_Plugin_AJAX_Handler {
    public function __construct() {
        add_action('wp_ajax_wp_plugin_filter', [$this, 'handle_filter_request']);
        add_action('wp_ajax_wp_plugin_sort', [$this, 'handle_sort_request']);
        add_action('wp_ajax_wp_plugin_clear_cache', [$this, 'handle_cache_clear']);
    }
    
    public function handle_filter_request() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'wp_plugin_filter_action')) {
            wp_send_json_error(['message' => __('Security check failed', 'wp-plugin-filters')]);
            return;
        }
        
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'wp-plugin-filters')]);
            return;
        }
        
        // Sanitize input parameters
        $filters = $this->sanitize_filter_params($_POST);
        
        // Generate cache key for this filter combination
        $cache_key = 'wp_plugin_filtered_' . md5(serialize($filters));
        $cached_results = get_transient($cache_key);
        
        if ($cached_results !== false) {
            wp_send_json_success($cached_results);
            return;
        }
        
        // Perform filtered search
        $results = $this->execute_filtered_search($filters);
        
        if (is_wp_error($results)) {
            wp_send_json_error([
                'message' => $results->get_error_message(),
                'code' => $results->get_error_code()
            ]);
            return;
        }
        
        // Cache results for 1 hour
        set_transient($cache_key, $results, 3600);
        
        wp_send_json_success($results);
    }
    
    private function sanitize_filter_params($post_data) {
        return [
            'search_term' => sanitize_text_field($post_data['search_term'] ?? ''),
            'installation_range' => sanitize_key($post_data['installation_range'] ?? ''),
            'update_timeframe' => sanitize_key($post_data['update_timeframe'] ?? ''),
            'usability_rating' => floatval($post_data['usability_rating'] ?? 0),
            'health_score' => absint($post_data['health_score'] ?? 0),
            'sort_by' => sanitize_key($post_data['sort_by'] ?? 'relevance'),
            'sort_direction' => in_array($post_data['sort_direction'] ?? 'desc', ['asc', 'desc']) ? $post_data['sort_direction'] : 'desc',
            'page' => absint($post_data['page'] ?? 1),
            'per_page' => min(48, absint($post_data['per_page'] ?? 24)) // Cap at 48 for performance
        ];
    }
}
```

#### AJAX Request Format
```javascript
// Frontend JavaScript AJAX request
jQuery.ajax({
    url: wpPluginFilters.ajaxUrl,
    method: 'POST',
    dataType: 'json',
    data: {
        action: 'wp_plugin_filter',
        nonce: wpPluginFilters.nonces.filter_plugins,
        search_term: 'ecommerce',
        installation_range: '100k-1m',
        update_timeframe: 'last_month',
        usability_rating: 4.0,
        health_score: 70,
        sort_by: 'installations',
        sort_direction: 'desc',
        page: 1,
        per_page: 24
    },
    beforeSend: function() {
        jQuery('.wp-filter-search .spinner').addClass('is-active');
    },
    success: function(response) {
        if (response.success) {
            WPPluginFilters.updatePluginGrid(response.data);
            WPPluginFilters.updatePagination(response.data.pagination);
            WPPluginFilters.updateURL(response.data.filters);
        } else {
            WPPluginFilters.showError(response.data.message);
        }
    },
    error: function(xhr, status, error) {
        WPPluginFilters.showError('Network error occurred. Please try again.');
    },
    complete: function() {
        jQuery('.wp-filter-search .spinner').removeClass('is-active');
    }
});
```

#### AJAX Response Format
```json
{
  "success": true,
  "data": {
    "plugins": [
      {
        "slug": "woocommerce",
        "name": "WooCommerce",
        "version": "8.5.2",
        "author": "Automattic",
        "rating": 4.6,
        "num_ratings": 4023,
        "active_installs": 5000000,
        "last_updated": "2024-01-15 14:30:00",
        "last_updated_human": "2 weeks ago",
        "tested": "6.4.2",
        "requires": "6.0",
        "short_description": "The most customizable eCommerce platform...",
        "homepage": "https://woocommerce.com/",
        "download_link": "https://downloads.wordpress.org/plugin/woocommerce.8.5.2.zip",
        "tags": ["ecommerce", "store", "sales"],
        "usability_rating": 4.7,
        "health_score": 92,
        "health_color": "green"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 15,
      "total_results": 350,
      "per_page": 24
    },
    "filters_applied": {
      "installation_range": "100k-1m",
      "update_timeframe": "last_month",
      "usability_rating": 4.0,
      "health_score": 70
    },
    "cache_info": {
      "cached": false,
      "cache_key": "wp_plugin_filtered_abc123",
      "expires_in": 3600
    }
  }
}
```

### Plugin Rating Calculation Endpoint

#### Endpoint Implementation
```php
<?php
public function handle_rating_calculation() {
    if (!wp_verify_nonce($_POST['nonce'], 'wp_plugin_rating_action')) {
        wp_send_json_error(['message' => __('Security check failed', 'wp-plugin-filters')]);
    }
    
    $plugin_slug = sanitize_key($_POST['plugin_slug']);
    $force_recalculate = isset($_POST['force_recalculate']) && $_POST['force_recalculate'] === 'true';
    
    $cache_key = "wp_plugin_rating_{$plugin_slug}";
    
    if (!$force_recalculate) {
        $cached_rating = get_transient($cache_key);
        if ($cached_rating !== false) {
            wp_send_json_success($cached_rating);
            return;
        }
    }
    
    // Get plugin details for rating calculation
    $api_client = new WP_Plugin_API_Client();
    $plugin_details = $api_client->get_plugin_details($plugin_slug);
    
    if (is_wp_error($plugin_details)) {
        wp_send_json_error([
            'message' => $plugin_details->get_error_message(),
            'code' => $plugin_details->get_error_code()
        ]);
        return;
    }
    
    // Calculate ratings using algorithms
    $rating_calculator = new WP_Plugin_Rating_Calculator();
    $health_calculator = new WP_Plugin_Health_Calculator();
    
    $usability_rating = $rating_calculator->calculate_usability_rating($plugin_details);
    $health_score = $health_calculator->calculate_health_score($plugin_details);
    
    $result = [
        'plugin_slug' => $plugin_slug,
        'usability_rating' => round($usability_rating, 1),
        'health_score' => intval($health_score),
        'health_color' => $this->get_health_color($health_score),
        'calculated_at' => current_time('mysql'),
        'calculation_breakdown' => [
            'usability' => $rating_calculator->get_calculation_breakdown(),
            'health' => $health_calculator->get_calculation_breakdown()
        ]
    ];
    
    // Cache for 6 hours
    set_transient($cache_key, $result, 21600);
    
    wp_send_json_success($result);
}

private function get_health_color($score) {
    if ($score >= 86) return 'green';
    if ($score >= 71) return 'light-green';
    if ($score >= 41) return 'orange';
    return 'red';
}
```

### Cache Management Endpoint

#### Implementation
```php
<?php
public function handle_cache_clear() {
    if (!wp_verify_nonce($_POST['nonce'], 'wp_plugin_clear_cache')) {
        wp_send_json_error(['message' => __('Security check failed', 'wp-plugin-filters')]);
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'wp-plugin-filters')]);
    }
    
    $cache_type = sanitize_key($_POST['cache_type'] ?? 'all');
    $cleared_count = 0;
    
    global $wpdb;
    
    switch ($cache_type) {
        case 'plugin_metadata':
            $cleared_count = $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_wp_plugin_meta_%' 
                 OR option_name LIKE '_transient_timeout_wp_plugin_meta_%'"
            );
            break;
            
        case 'plugin_ratings':
            $cleared_count = $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_wp_plugin_rating_%' 
                 OR option_name LIKE '_transient_timeout_wp_plugin_rating_%'"
            );
            break;
            
        case 'search_results':
            $cleared_count = $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_wp_plugin_filtered_%' 
                 OR option_name LIKE '_transient_timeout_wp_plugin_filtered_%'"
            );
            break;
            
        case 'all':
        default:
            $cleared_count = $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_wp_plugin_%' 
                 OR option_name LIKE '_transient_timeout_wp_plugin_%'"
            );
            break;
    }
    
    // Clear object cache if available
    if (wp_using_ext_object_cache()) {
        wp_cache_flush_group('plugin_metadata');
        wp_cache_flush_group('plugin_ratings');
    }
    
    wp_send_json_success([
        'message' => sprintf(__('Cleared %d cache entries', 'wp-plugin-filters'), $cleared_count),
        'cache_type' => $cache_type,
        'cleared_count' => $cleared_count,
        'timestamp' => current_time('mysql')
    ]);
}
```

## Internal API Interfaces

### Rating Calculator API

#### Interface Definition
```php
<?php
interface WP_Plugin_Rating_Calculator_Interface {
    /**
     * Calculate usability rating for a plugin
     * 
     * @param array $plugin_data Plugin metadata from WordPress.org API
     * @return float Usability rating from 1.0 to 5.0
     */
    public function calculate_usability_rating(array $plugin_data): float;
    
    /**
     * Get breakdown of calculation components
     * 
     * @return array Detailed breakdown of rating calculation
     */
    public function get_calculation_breakdown(): array;
    
    /**
     * Update algorithm weights
     * 
     * @param array $weights New weight configuration
     * @return bool Success status
     */
    public function update_weights(array $weights): bool;
}

class WP_Plugin_Rating_Calculator implements WP_Plugin_Rating_Calculator_Interface {
    private $weights;
    private $breakdown = [];
    
    public function __construct() {
        $settings = get_option('wp_plugin_filters_settings', []);
        $this->weights = $settings['usability_weights'] ?? [
            'user_rating' => 40,
            'rating_count' => 20,
            'installation_count' => 25,
            'support_responsiveness' => 15
        ];
    }
    
    public function calculate_usability_rating(array $plugin_data): float {
        $components = [
            'user_rating' => $this->calculate_user_rating_component($plugin_data),
            'rating_count' => $this->calculate_rating_count_component($plugin_data),
            'installation_count' => $this->calculate_installation_component($plugin_data),
            'support_responsiveness' => $this->calculate_support_component($plugin_data)
        ];
        
        $weighted_score = 0;
        $total_weight = 0;
        
        foreach ($components as $component => $score) {
            if ($score !== null) {
                $weight = $this->weights[$component] / 100;
                $weighted_score += $score * $weight;
                $total_weight += $weight;
            }
        }
        
        // Normalize if some components are missing
        $final_score = $total_weight > 0 ? $weighted_score / $total_weight : 0;
        
        $this->breakdown = [
            'components' => $components,
            'weights' => $this->weights,
            'weighted_score' => $weighted_score,
            'total_weight' => $total_weight,
            'final_score' => $final_score
        ];
        
        return max(1.0, min(5.0, $final_score * 5)); // Scale to 1-5 range
    }
    
    private function calculate_user_rating_component(array $plugin_data): ?float {
        if (!isset($plugin_data['rating']) || $plugin_data['rating'] <= 0) {
            return null;
        }
        
        return floatval($plugin_data['rating']) / 5.0; // Normalize to 0-1 scale
    }
    
    private function calculate_rating_count_component(array $plugin_data): ?float {
        if (!isset($plugin_data['num_ratings']) || $plugin_data['num_ratings'] <= 0) {
            return null;
        }
        
        $rating_count = intval($plugin_data['num_ratings']);
        
        // Logarithmic scale for rating count significance
        if ($rating_count >= 1000) return 1.0;
        if ($rating_count >= 100) return 0.8;
        if ($rating_count >= 20) return 0.6;
        if ($rating_count >= 5) return 0.4;
        return 0.2;
    }
    
    private function calculate_installation_component(array $plugin_data): ?float {
        if (!isset($plugin_data['active_installs']) || $plugin_data['active_installs'] <= 0) {
            return null;
        }
        
        $installs = intval($plugin_data['active_installs']);
        
        // Logarithmic scale for installation count
        if ($installs >= 1000000) return 1.0;
        if ($installs >= 100000) return 0.8;
        if ($installs >= 10000) return 0.6;
        if ($installs >= 1000) return 0.4;
        return 0.2;
    }
    
    private function calculate_support_component(array $plugin_data): ?float {
        if (!isset($plugin_data['support_threads']) || !isset($plugin_data['support_threads_resolved'])) {
            return null;
        }
        
        $total_threads = intval($plugin_data['support_threads']);
        $resolved_threads = intval($plugin_data['support_threads_resolved']);
        
        if ($total_threads === 0) {
            return 0.5; // Neutral score for no support data
        }
        
        return floatval($resolved_threads) / floatval($total_threads);
    }
    
    public function get_calculation_breakdown(): array {
        return $this->breakdown;
    }
    
    public function update_weights(array $weights): bool {
        // Validate weights sum to 100
        $total_weight = array_sum($weights);
        if (abs($total_weight - 100) > 0.01) {
            return false;
        }
        
        $this->weights = $weights;
        
        // Update stored settings
        $settings = get_option('wp_plugin_filters_settings', []);
        $settings['usability_weights'] = $weights;
        return update_option('wp_plugin_filters_settings', $settings);
    }
}
```

### Health Score Calculator API

#### Interface Definition
```php
<?php
interface WP_Plugin_Health_Calculator_Interface {
    /**
     * Calculate health score for a plugin
     * 
     * @param array $plugin_data Plugin metadata from WordPress.org API
     * @return int Health score from 0 to 100
     */
    public function calculate_health_score(array $plugin_data): int;
    
    /**
     * Get breakdown of health score calculation
     * 
     * @return array Detailed breakdown of health score components
     */
    public function get_calculation_breakdown(): array;
}

class WP_Plugin_Health_Calculator implements WP_Plugin_Health_Calculator_Interface {
    private $weights;
    private $breakdown = [];
    
    public function __construct() {
        $settings = get_option('wp_plugin_filters_settings', []);
        $this->weights = $settings['health_weights'] ?? [
            'update_frequency' => 30,
            'wp_compatibility' => 25,
            'support_response' => 20,
            'time_since_update' => 15,
            'reported_issues' => 10
        ];
    }
    
    public function calculate_health_score(array $plugin_data): int {
        $components = [
            'update_frequency' => $this->calculate_update_frequency_score($plugin_data),
            'wp_compatibility' => $this->calculate_compatibility_score($plugin_data),
            'support_response' => $this->calculate_support_response_score($plugin_data),
            'time_since_update' => $this->calculate_recency_score($plugin_data),
            'reported_issues' => $this->calculate_issues_score($plugin_data)
        ];
        
        $weighted_score = 0;
        $total_weight = 0;
        
        foreach ($components as $component => $score) {
            if ($score !== null) {
                $weight = $this->weights[$component] / 100;
                $weighted_score += $score * $weight;
                $total_weight += $weight;
            }
        }
        
        $final_score = $total_weight > 0 ? ($weighted_score / $total_weight) * 100 : 0;
        
        $this->breakdown = [
            'components' => $components,
            'weights' => $this->weights,
            'weighted_score' => $weighted_score,
            'total_weight' => $total_weight,
            'final_score' => $final_score
        ];
        
        return max(0, min(100, intval(round($final_score))));
    }
    
    private function calculate_update_frequency_score(array $plugin_data): ?float {
        // This would require historical update data
        // For now, use a simplified approach based on version number
        if (!isset($plugin_data['version'])) {
            return null;
        }
        
        $version = $plugin_data['version'];
        $version_parts = explode('.', $version);
        
        // Heuristic: more version segments suggest more active development
        if (count($version_parts) >= 3) return 0.8;
        if (count($version_parts) === 2) return 0.6;
        return 0.4;
    }
    
    private function calculate_compatibility_score(array $plugin_data): ?float {
        if (!isset($plugin_data['tested'])) {
            return null;
        }
        
        $tested_version = $plugin_data['tested'];
        $current_wp_version = get_bloginfo('version');
        
        // Simple version comparison
        if (version_compare($tested_version, $current_wp_version, '>=')) {
            return 1.0;
        } elseif (version_compare($tested_version, $current_wp_version, '>') >= -0.1) {
            return 0.8;
        } else {
            return 0.4;
        }
    }
    
    private function calculate_support_response_score(array $plugin_data): ?float {
        if (!isset($plugin_data['support_threads']) || !isset($plugin_data['support_threads_resolved'])) {
            return null;
        }
        
        $total = intval($plugin_data['support_threads']);
        $resolved = intval($plugin_data['support_threads_resolved']);
        
        if ($total === 0) return 0.5;
        
        return floatval($resolved) / floatval($total);
    }
    
    private function calculate_recency_score(array $plugin_data): ?float {
        if (!isset($plugin_data['last_updated'])) {
            return null;
        }
        
        $last_updated = strtotime($plugin_data['last_updated']);
        $now = current_time('timestamp');
        $days_since_update = ($now - $last_updated) / DAY_IN_SECONDS;
        
        // Score based on recency
        if ($days_since_update <= 30) return 1.0;      // Within a month
        if ($days_since_update <= 90) return 0.8;      // Within 3 months
        if ($days_since_update <= 180) return 0.6;     // Within 6 months
        if ($days_since_update <= 365) return 0.4;     // Within a year
        return 0.2;                                     // Over a year
    }
    
    private function calculate_issues_score(array $plugin_data): ?float {
        // This would require integration with WordPress.org support forums
        // For now, use a placeholder based on rating distribution
        if (!isset($plugin_data['ratings']) || !is_array($plugin_data['ratings'])) {
            return 0.5;
        }
        
        $ratings = $plugin_data['ratings'];
        $total_ratings = array_sum($ratings);
        
        if ($total_ratings === 0) return 0.5;
        
        // Low star ratings might indicate issues
        $low_star_ratings = ($ratings[1] ?? 0) + ($ratings[2] ?? 0);
        $issue_ratio = $low_star_ratings / $total_ratings;
        
        return 1.0 - min(1.0, $issue_ratio * 2); // Scale issue impact
    }
    
    public function get_calculation_breakdown(): array {
        return $this->breakdown;
    }
}
```

## Error Handling and Response Patterns

### WordPress Error Integration
```php
<?php
class WP_Plugin_API_Error_Handler {
    public static function handle_wp_error(WP_Error $error, $context = []) {
        $error_data = [
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'context' => $context,
            'timestamp' => current_time('mysql')
        ];
        
        // Log error if WordPress debugging is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[WP Plugin Filters API Error] %s: %s | Context: %s',
                $error_data['code'],
                $error_data['message'],
                wp_json_encode($context)
            ));
        }
        
        // Return standardized error response
        return [
            'success' => false,
            'data' => [
                'code' => $error_data['code'],
                'message' => self::get_user_friendly_message($error_data['code']),
                'debug_message' => $error_data['message'],
                'retry_allowed' => self::is_retryable_error($error_data['code'])
            ]
        ];
    }
    
    private static function get_user_friendly_message($error_code) {
        $messages = [
            'api_error' => __('Unable to connect to WordPress.org. Please try again later.', 'wp-plugin-filters'),
            'json_decode_error' => __('Invalid response from server. Please try again.', 'wp-plugin-filters'),
            'invalid_response' => __('Unexpected response format. Please contact support.', 'wp-plugin-filters'),
            'rate_limit_exceeded' => __('Too many requests. Please wait a moment and try again.', 'wp-plugin-filters'),
            'timeout_error' => __('Request timed out. Please try again.', 'wp-plugin-filters')
        ];
        
        return $messages[$error_code] ?? __('An unknown error occurred. Please try again.', 'wp-plugin-filters');
    }
    
    private static function is_retryable_error($error_code) {
        $retryable_codes = ['api_error', 'timeout_error', 'rate_limit_exceeded'];
        return in_array($error_code, $retryable_codes);
    }
}
```

### Rate Limiting Implementation
```php
<?php
class WP_Plugin_Rate_Limiter {
    private $requests_per_minute = 60;
    private $burst_limit = 10;
    
    public function is_request_allowed($user_id = null) {
        $user_id = $user_id ?? get_current_user_id();
        $rate_key = "wp_plugin_rate_limit_{$user_id}";
        $burst_key = "wp_plugin_burst_limit_{$user_id}";
        
        // Check burst limit (short-term)
        $burst_count = get_transient($burst_key) ?: 0;
        if ($burst_count >= $this->burst_limit) {
            return new WP_Error('rate_limit_exceeded', 'Burst limit exceeded');
        }
        
        // Check per-minute limit
        $minute_count = get_transient($rate_key) ?: 0;
        if ($minute_count >= $this->requests_per_minute) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded');
        }
        
        // Increment counters
        set_transient($burst_key, $burst_count + 1, 10); // 10 second window
        set_transient($rate_key, $minute_count + 1, 60); // 1 minute window
        
        return true;
    }
}
```

## API Security Specifications

### WordPress Nonce Implementation
All AJAX endpoints implement WordPress nonce verification:

```php
<?php
public function verify_request_security($action, $nonce_key = 'nonce') {
    // Verify nonce
    if (!isset($_POST[$nonce_key]) || !wp_verify_nonce($_POST[$nonce_key], $action)) {
        wp_send_json_error([
            'message' => __('Security verification failed', 'wp-plugin-filters'),
            'code' => 'nonce_verification_failed'
        ]);
        wp_die();
    }
    
    // Verify user capabilities
    if (!current_user_can('install_plugins')) {
        wp_send_json_error([
            'message' => __('Insufficient permissions', 'wp-plugin-filters'),
            'code' => 'insufficient_permissions'
        ]);
        wp_die();
    }
    
    // Additional security checks can be added here
    return true;
}
```

### Input Sanitization Standards
All API inputs use WordPress sanitization functions:

```php
<?php
class WP_Plugin_Input_Sanitizer {
    public static function sanitize_filter_params(array $params) {
        return [
            'search_term' => sanitize_text_field($params['search_term'] ?? ''),
            'installation_range' => self::sanitize_installation_range($params['installation_range'] ?? ''),
            'update_timeframe' => self::sanitize_timeframe($params['update_timeframe'] ?? ''),
            'usability_rating' => self::sanitize_rating($params['usability_rating'] ?? 0),
            'health_score' => self::sanitize_health_score($params['health_score'] ?? 0),
            'sort_by' => self::sanitize_sort_field($params['sort_by'] ?? 'relevance'),
            'sort_direction' => self::sanitize_sort_direction($params['sort_direction'] ?? 'desc'),
            'page' => absint($params['page'] ?? 1),
            'per_page' => min(48, absint($params['per_page'] ?? 24))
        ];
    }
    
    private static function sanitize_installation_range($range) {
        $valid_ranges = ['0-1k', '1k-10k', '10k-100k', '100k-1m', '1m-plus'];
        return in_array($range, $valid_ranges) ? $range : '';
    }
    
    private static function sanitize_timeframe($timeframe) {
        $valid_timeframes = ['last_week', 'last_month', 'last_3months', 'last_6months', 'last_year', 'older'];
        return in_array($timeframe, $valid_timeframes) ? $timeframe : '';
    }
    
    private static function sanitize_rating($rating) {
        $rating = floatval($rating);
        return max(0, min(5, $rating));
    }
    
    private static function sanitize_health_score($score) {
        $score = intval($score);
        return max(0, min(100, $score));
    }
    
    private static function sanitize_sort_field($field) {
        $valid_fields = ['relevance', 'popularity', 'rating', 'updated', 'installations', 'usability_rating', 'health_score'];
        return in_array($field, $valid_fields) ? $field : 'relevance';
    }
    
    private static function sanitize_sort_direction($direction) {
        return in_array($direction, ['asc', 'desc']) ? $direction : 'desc';
    }
}
```

This API specification provides comprehensive integration patterns for the WordPress Plugin Directory Filters, ensuring seamless operation within the WordPress ecosystem while maintaining security, performance, and compatibility standards.