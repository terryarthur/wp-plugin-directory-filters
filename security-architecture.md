# WordPress Plugin Directory Filters - Security Architecture

## Security Overview

The WordPress Plugin Directory Filters enhancement implements a comprehensive security architecture that leverages WordPress security frameworks and follows WordPress security best practices. This defense-in-depth approach ensures protection against common web vulnerabilities while maintaining seamless integration with WordPress admin security patterns.

## WordPress Security Framework Integration

### WordPress Security APIs Utilized
```yaml
wordpress_security_apis:
  authentication:
    framework: "WordPress User Authentication System"
    session_management: "WordPress Session Handling"
    capability_system: "WordPress Role-Based Access Control"
  
  input_security:
    sanitization: "WordPress Sanitization Functions"
    validation: "WordPress Validation Callbacks"
    escaping: "WordPress Output Escaping Functions"
  
  csrf_protection:
    nonce_system: "WordPress Nonce Framework"
    form_protection: "wp_nonce_field() Integration"
    ajax_protection: "wp_verify_nonce() Validation"
  
  database_security:
    query_protection: "WordPress $wpdb Prepared Statements"
    injection_prevention: "$wpdb->prepare() Exclusive Usage"
    data_validation: "WordPress Type Casting Functions"
```

### WordPress Capability Integration
```php
<?php
/**
 * WordPress Capability Security Model
 * Defines required permissions for plugin functionality
 */
class WP_Plugin_Security_Capabilities {
    
    const REQUIRED_CAPABILITIES = [
        'plugin_installer_access' => 'install_plugins',
        'settings_management' => 'manage_options',
        'cache_management' => 'manage_options',
        'network_administration' => 'manage_network_options', // Multisite only
        'algorithm_configuration' => 'manage_options'
    ];
    
    /**
     * Check if current user has required capability
     */
    public static function can_access_plugin_installer() {
        return current_user_can('install_plugins');
    }
    
    public static function can_manage_settings() {
        return current_user_can('manage_options');
    }
    
    public static function can_manage_network_settings() {
        return is_multisite() && current_user_can('manage_network_options');
    }
    
    /**
     * Capability check with context validation
     */
    public static function verify_admin_access($required_capability) {
        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to access this feature.', 'wp-plugin-filters'), 401);
        }
        
        // Verify user has required capability
        if (!current_user_can($required_capability)) {
            wp_die(__('You do not have permission to access this feature.', 'wp-plugin-filters'), 403);
        }
        
        // Verify admin context
        if (!is_admin()) {
            wp_die(__('This feature is only available in WordPress admin.', 'wp-plugin-filters'), 403);
        }
        
        return true;
    }
    
    /**
     * Multi-site capability validation
     */
    public static function verify_multisite_access($network_admin = false) {
        if (!is_multisite()) {
            return self::can_manage_settings();
        }
        
        if ($network_admin) {
            return self::can_manage_network_settings();
        }
        
        // Check if network admin allows site-level management
        $network_settings = get_site_option('wp_plugin_filters_network_settings', []);
        if ($network_settings['network_override'] ?? false) {
            return self::can_manage_network_settings();
        }
        
        return self::can_manage_settings();
    }
}
```

## Input Security and Data Sanitization

### WordPress Sanitization Implementation
```php
<?php
/**
 * Comprehensive Input Sanitization using WordPress Functions
 */
class WP_Plugin_Input_Security {
    
    /**
     * Sanitize AJAX request data
     */
    public static function sanitize_ajax_request($request_data) {
        $sanitized = [];
        
        // Search and filter parameters
        $sanitized['search_term'] = sanitize_text_field($request_data['search_term'] ?? '');
        $sanitized['installation_range'] = self::sanitize_installation_range($request_data['installation_range'] ?? '');
        $sanitized['update_timeframe'] = self::sanitize_timeframe($request_data['update_timeframe'] ?? '');
        $sanitized['usability_rating'] = self::sanitize_rating_value($request_data['usability_rating'] ?? 0);
        $sanitized['health_score'] = self::sanitize_health_score($request_data['health_score'] ?? 0);
        
        // Sorting parameters
        $sanitized['sort_by'] = self::sanitize_sort_field($request_data['sort_by'] ?? 'relevance');
        $sanitized['sort_direction'] = self::sanitize_sort_direction($request_data['sort_direction'] ?? 'desc');
        
        // Pagination parameters
        $sanitized['page'] = max(1, absint($request_data['page'] ?? 1));
        $sanitized['per_page'] = max(1, min(48, absint($request_data['per_page'] ?? 24))); // Limit to 48 for performance
        
        // WordPress nonce (validated separately)
        $sanitized['nonce'] = sanitize_key($request_data['nonce'] ?? '');
        
        return $sanitized;
    }
    
    /**
     * Sanitize settings form data
     */
    public static function sanitize_settings_data($settings_data) {
        $sanitized = [];
        
        // Usability rating weights
        if (isset($settings_data['usability_weights']) && is_array($settings_data['usability_weights'])) {
            $sanitized['usability_weights'] = self::sanitize_algorithm_weights($settings_data['usability_weights']);
        }
        
        // Health score weights
        if (isset($settings_data['health_weights']) && is_array($settings_data['health_weights'])) {
            $sanitized['health_weights'] = self::sanitize_algorithm_weights($settings_data['health_weights']);
        }
        
        // Cache settings
        if (isset($settings_data['cache_settings']) && is_array($settings_data['cache_settings'])) {
            $sanitized['cache_settings'] = self::sanitize_cache_settings($settings_data['cache_settings']);
        }
        
        // UI settings
        if (isset($settings_data['ui_settings']) && is_array($settings_data['ui_settings'])) {
            $sanitized['ui_settings'] = self::sanitize_ui_settings($settings_data['ui_settings']);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize installation range filter
     */
    private static function sanitize_installation_range($range) {
        $valid_ranges = ['0-1k', '1k-10k', '10k-100k', '100k-1m', '1m-plus', 'all'];
        return in_array($range, $valid_ranges, true) ? $range : 'all';
    }
    
    /**
     * Sanitize timeframe filter
     */
    private static function sanitize_timeframe($timeframe) {
        $valid_timeframes = [
            'last_week', 'last_month', 'last_3months', 
            'last_6months', 'last_year', 'older', 'all'
        ];
        return in_array($timeframe, $valid_timeframes, true) ? $timeframe : 'all';
    }
    
    /**
     * Sanitize rating value
     */
    private static function sanitize_rating_value($rating) {
        $rating = floatval($rating);
        return max(0.0, min(5.0, $rating));
    }
    
    /**
     * Sanitize health score
     */
    private static function sanitize_health_score($score) {
        $score = intval($score);
        return max(0, min(100, $score));
    }
    
    /**
     * Sanitize sort field
     */
    private static function sanitize_sort_field($field) {
        $valid_fields = [
            'relevance', 'popularity', 'rating', 'updated', 
            'installations', 'usability_rating', 'health_score'
        ];
        return in_array($field, $valid_fields, true) ? $field : 'relevance';
    }
    
    /**
     * Sanitize sort direction
     */
    private static function sanitize_sort_direction($direction) {
        return in_array(strtolower($direction), ['asc', 'desc'], true) ? strtolower($direction) : 'desc';
    }
    
    /**
     * Sanitize algorithm weights with validation
     */
    private static function sanitize_algorithm_weights($weights) {
        $sanitized = [];
        $total_weight = 0;
        
        foreach ($weights as $component => $weight) {
            $sanitized_component = sanitize_key($component);
            $sanitized_weight = max(0, min(100, intval($weight)));
            $sanitized[$sanitized_component] = $sanitized_weight;
            $total_weight += $sanitized_weight;
        }
        
        // Validate weights sum to 100
        if (abs($total_weight - 100) > 1) { // Allow 1% tolerance
            // Reset to default weights if invalid
            return self::get_default_weights();
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize cache settings
     */
    private static function sanitize_cache_settings($cache_settings) {
        return [
            'plugin_metadata_ttl' => max(3600, min(604800, absint($cache_settings['plugin_metadata_ttl'] ?? 86400))), // 1 hour to 1 week
            'calculated_ratings_ttl' => max(1800, min(86400, absint($cache_settings['calculated_ratings_ttl'] ?? 21600))), // 30 min to 24 hours
            'search_results_ttl' => max(300, min(7200, absint($cache_settings['search_results_ttl'] ?? 3600))), // 5 min to 2 hours
            'api_responses_ttl' => max(300, min(3600, absint($cache_settings['api_responses_ttl'] ?? 1800))) // 5 min to 1 hour
        ];
    }
    
    /**
     * Sanitize UI settings
     */
    private static function sanitize_ui_settings($ui_settings) {
        return [
            'results_per_page' => max(12, min(48, absint($ui_settings['results_per_page'] ?? 24))),
            'show_health_tooltips' => !empty($ui_settings['show_health_tooltips']),
            'show_rating_breakdown' => !empty($ui_settings['show_rating_breakdown']),
            'animation_enabled' => !empty($ui_settings['animation_enabled'])
        ];
    }
    
    /**
     * Default algorithm weights for fallback
     */
    private static function get_default_weights() {
        return [
            'user_rating' => 40,
            'rating_count' => 20,
            'installation_count' => 25,
            'support_responsiveness' => 15
        ];
    }
}
```

### Output Escaping Implementation
```php
<?php
/**
 * WordPress Output Escaping Implementation
 */
class WP_Plugin_Output_Security {
    
    /**
     * Escape plugin data for display
     */
    public static function escape_plugin_data($plugin_data) {
        return [
            'slug' => sanitize_key($plugin_data['slug']),
            'name' => esc_html($plugin_data['name']),
            'version' => esc_html($plugin_data['version']),
            'author' => esc_html($plugin_data['author']),
            'rating' => floatval($plugin_data['rating']), // Numeric, no escaping needed
            'num_ratings' => absint($plugin_data['num_ratings']),
            'active_installs' => absint($plugin_data['active_installs']),
            'last_updated' => esc_html($plugin_data['last_updated']),
            'tested' => esc_html($plugin_data['tested']),
            'requires' => esc_html($plugin_data['requires']),
            'description' => wp_kses_post($plugin_data['description']), // Allow safe HTML
            'homepage' => esc_url($plugin_data['homepage']),
            'download_link' => esc_url($plugin_data['download_link']),
            'tags' => array_map('esc_html', $plugin_data['tags'] ?? [])
        ];
    }
    
    /**
     * Escape AJAX response data
     */
    public static function escape_ajax_response($response_data) {
        $escaped = [];
        
        if (isset($response_data['plugins']) && is_array($response_data['plugins'])) {
            $escaped['plugins'] = array_map([self::class, 'escape_plugin_data'], $response_data['plugins']);
        }
        
        if (isset($response_data['pagination'])) {
            $escaped['pagination'] = [
                'current_page' => absint($response_data['pagination']['current_page']),
                'total_pages' => absint($response_data['pagination']['total_pages']),
                'total_results' => absint($response_data['pagination']['total_results']),
                'per_page' => absint($response_data['pagination']['per_page'])
            ];
        }
        
        if (isset($response_data['filters_applied'])) {
            $escaped['filters_applied'] = self::escape_filter_data($response_data['filters_applied']);
        }
        
        return $escaped;
    }
    
    /**
     * Escape filter data
     */
    public static function escape_filter_data($filter_data) {
        return [
            'search_term' => esc_html($filter_data['search_term'] ?? ''),
            'installation_range' => sanitize_key($filter_data['installation_range'] ?? ''),
            'update_timeframe' => sanitize_key($filter_data['update_timeframe'] ?? ''),
            'usability_rating' => floatval($filter_data['usability_rating'] ?? 0),
            'health_score' => absint($filter_data['health_score'] ?? 0),
            'sort_by' => sanitize_key($filter_data['sort_by'] ?? ''),
            'sort_direction' => sanitize_key($filter_data['sort_direction'] ?? '')
        ];
    }
    
    /**
     * Generate safe JSON output
     */
    public static function safe_json_encode($data) {
        // Use WordPress JSON encoding for consistency and security
        return wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Escape data for HTML attributes
     */
    public static function escape_for_attributes($data) {
        if (is_array($data)) {
            return array_map('esc_attr', $data);
        }
        return esc_attr($data);
    }
    
    /**
     * Escape data for JavaScript output
     */
    public static function escape_for_javascript($data) {
        return wp_json_encode($data);
    }
}
```

## CSRF Protection and Nonce Implementation

### WordPress Nonce Security Framework
```php
<?php
/**
 * WordPress Nonce-based CSRF Protection
 */
class WP_Plugin_CSRF_Protection {
    
    // Nonce action names for different operations
    const NONCE_ACTIONS = [
        'filter_plugins' => 'wp_plugin_filter_action',
        'sort_plugins' => 'wp_plugin_sort_action',
        'calculate_ratings' => 'wp_plugin_rating_action',
        'clear_cache' => 'wp_plugin_clear_cache',
        'save_settings' => 'wp_plugin_save_settings',
        'network_settings' => 'wp_plugin_network_settings'
    ];
    
    /**
     * Generate nonces for JavaScript
     */
    public static function get_ajax_nonces() {
        $nonces = [];
        
        foreach (self::NONCE_ACTIONS as $operation => $action) {
            $nonces[$operation] = wp_create_nonce($action);
        }
        
        return $nonces;
    }
    
    /**
     * Verify nonce for AJAX requests
     */
    public static function verify_ajax_nonce($operation, $nonce_value) {
        if (!isset(self::NONCE_ACTIONS[$operation])) {
            return new WP_Error('invalid_operation', __('Invalid operation specified', 'wp-plugin-filters'));
        }
        
        $action = self::NONCE_ACTIONS[$operation];
        
        if (!wp_verify_nonce($nonce_value, $action)) {
            return new WP_Error('nonce_verification_failed', __('Security verification failed', 'wp-plugin-filters'));
        }
        
        return true;
    }
    
    /**
     * Verify form nonce with WordPress admin context
     */
    public static function verify_form_nonce($operation) {
        if (!isset($_POST['wp_plugin_nonce'])) {
            wp_die(__('Missing security token', 'wp-plugin-filters'), 400);
        }
        
        if (!isset(self::NONCE_ACTIONS[$operation])) {
            wp_die(__('Invalid operation', 'wp-plugin-filters'), 400);
        }
        
        $action = self::NONCE_ACTIONS[$operation];
        
        if (!wp_verify_nonce($_POST['wp_plugin_nonce'], $action)) {
            wp_die(__('Security verification failed', 'wp-plugin-filters'), 403);
        }
        
        return true;
    }
    
    /**
     * Generate nonce field for forms
     */
    public static function nonce_field($operation) {
        if (!isset(self::NONCE_ACTIONS[$operation])) {
            return '';
        }
        
        $action = self::NONCE_ACTIONS[$operation];
        return wp_nonce_field($action, 'wp_plugin_nonce', true, false);
    }
    
    /**
     * Security validation for AJAX endpoints
     */
    public static function validate_ajax_security($operation, $required_capability = 'install_plugins') {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'code' => 'not_logged_in',
                'message' => __('You must be logged in to perform this action', 'wp-plugin-filters')
            ], 401);
        }
        
        // Check user capabilities
        if (!current_user_can($required_capability)) {
            wp_send_json_error([
                'code' => 'insufficient_permissions', 
                'message' => __('You do not have permission to perform this action', 'wp-plugin-filters')
            ], 403);
        }
        
        // Verify nonce
        $nonce = sanitize_key($_POST['nonce'] ?? '');
        $verification = self::verify_ajax_nonce($operation, $nonce);
        
        if (is_wp_error($verification)) {
            wp_send_json_error([
                'code' => $verification->get_error_code(),
                'message' => $verification->get_error_message()
            ], 403);
        }
        
        // Check admin context
        if (!is_admin()) {
            wp_send_json_error([
                'code' => 'invalid_context',
                'message' => __('This action is only available in WordPress admin', 'wp-plugin-filters')
            ], 403);
        }
        
        return true;
    }
}
```

### AJAX Security Implementation
```php
<?php
/**
 * Secure AJAX Endpoint Implementation
 */
class WP_Plugin_Secure_AJAX {
    
    /**
     * Secure plugin filter AJAX handler
     */
    public function handle_filter_request() {
        // Comprehensive security validation
        WP_Plugin_CSRF_Protection::validate_ajax_security('filter_plugins', 'install_plugins');
        
        // Rate limiting check
        $rate_limit_check = WP_Plugin_Rate_Limiter::check_rate_limit();
        if (is_wp_error($rate_limit_check)) {
            wp_send_json_error([
                'code' => $rate_limit_check->get_error_code(),
                'message' => $rate_limit_check->get_error_message()
            ], 429);
        }
        
        // Sanitize input data
        $request_data = WP_Plugin_Input_Security::sanitize_ajax_request($_POST);
        
        // Validate input data
        $validation_result = self::validate_filter_request($request_data);
        if (is_wp_error($validation_result)) {
            wp_send_json_error([
                'code' => $validation_result->get_error_code(),
                'message' => $validation_result->get_error_message()
            ], 400);
        }
        
        // Process request with sanitized data
        $results = $this->execute_secure_filter_search($request_data);
        
        if (is_wp_error($results)) {
            wp_send_json_error([
                'code' => $results->get_error_code(),
                'message' => $results->get_error_message()
            ], 500);
        }
        
        // Escape output data
        $escaped_results = WP_Plugin_Output_Security::escape_ajax_response($results);
        
        wp_send_json_success($escaped_results);
    }
    
    /**
     * Validate filter request parameters
     */
    private static function validate_filter_request($request_data) {
        // Search term length validation
        if (strlen($request_data['search_term']) > 200) {
            return new WP_Error('search_term_too_long', __('Search term is too long', 'wp-plugin-filters'));
        }
        
        // Results per page limit
        if ($request_data['per_page'] > 48) {
            return new WP_Error('per_page_limit', __('Results per page exceeds maximum', 'wp-plugin-filters'));
        }
        
        // Page number validation
        if ($request_data['page'] > 1000) {
            return new WP_Error('page_limit', __('Page number exceeds maximum', 'wp-plugin-filters'));
        }
        
        return true;
    }
    
    /**
     * Execute filter search with security measures
     */
    private function execute_secure_filter_search($request_data) {
        try {
            // Performance monitoring
            $start_time = microtime(true);
            
            // Execute search with timeout protection
            $timeout = apply_filters('wp_plugin_filters_search_timeout', 30);
            
            $search_result = $this->perform_filtered_search($request_data, $timeout);
            
            // Log performance metrics
            $execution_time = microtime(true) - $start_time;
            if ($execution_time > 10) { // Log slow queries
                WP_Plugin_Security_Logger::log_slow_query($request_data, $execution_time);
            }
            
            return $search_result;
            
        } catch (Exception $e) {
            WP_Plugin_Security_Logger::log_exception($e, $request_data);
            return new WP_Error('search_execution_error', __('An error occurred during search', 'wp-plugin-filters'));
        }
    }
}
```

## Database Security

### WordPress Database Security Implementation
```php
<?php
/**
 * Database Security using WordPress $wpdb
 */
class WP_Plugin_Database_Security {
    
    /**
     * Secure database queries using WordPress $wpdb
     */
    public static function get_cached_plugins_secure($plugin_slugs) {
        global $wpdb;
        
        // Sanitize input slugs
        $sanitized_slugs = array_map('sanitize_key', $plugin_slugs);
        $sanitized_slugs = array_filter($sanitized_slugs); // Remove empty values
        
        if (empty($sanitized_slugs)) {
            return [];
        }
        
        // Create placeholders for prepared statement
        $placeholders = array_fill(0, count($sanitized_slugs), '%s');
        $placeholder_string = implode(',', $placeholders);
        
        // Prepare secure query
        $query = $wpdb->prepare(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name IN ($placeholder_string)
             AND option_name LIKE '_transient_wp_plugin_meta_%'
             ORDER BY option_name",
            $sanitized_slugs
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Validate and sanitize results
        $validated_results = [];
        foreach ($results as $result) {
            if (self::validate_cache_result($result)) {
                $validated_results[] = $result;
            }
        }
        
        return $validated_results;
    }
    
    /**
     * Secure cache cleanup with prepared statements
     */
    public static function cleanup_expired_cache_secure() {
        global $wpdb;
        
        // Use prepared statement for cache cleanup
        $current_time = current_time('timestamp');
        
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
                 WHERE a.option_name LIKE '_transient_wp_plugin_%'
                 AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
                 AND b.option_name LIKE '_transient_timeout_wp_plugin_%'
                 AND CAST(b.option_value AS UNSIGNED) < %d",
                $current_time
            )
        );
        
        if ($deleted_count === false) {
            WP_Plugin_Security_Logger::log_database_error($wpdb->last_error, 'cache_cleanup');
            return new WP_Error('database_error', __('Database cleanup failed', 'wp-plugin-filters'));
        }
        
        return $deleted_count;
    }
    
    /**
     * Validate cached data integrity
     */
    private static function validate_cache_result($cache_result) {
        if (!is_array($cache_result) || !isset($cache_result['option_name'], $cache_result['option_value'])) {
            return false;
        }
        
        // Validate option name format
        if (!preg_match('/^_transient_wp_plugin_(meta|rating|search|api)_[\w\d]+$/', $cache_result['option_name'])) {
            return false;
        }
        
        // Validate JSON structure
        $decoded_data = json_decode($cache_result['option_value'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Secure settings retrieval with validation
     */
    public static function get_settings_secure($option_name = 'wp_plugin_filters_settings') {
        $option_name = sanitize_key($option_name);
        
        $settings = get_option($option_name, []);
        
        // Validate settings structure
        if (!is_array($settings)) {
            WP_Plugin_Security_Logger::log_invalid_settings($option_name, $settings);
            return self::get_default_settings();
        }
        
        return self::validate_settings_structure($settings);
    }
    
    /**
     * Secure settings update with validation
     */
    public static function update_settings_secure($settings, $option_name = 'wp_plugin_filters_settings') {
        $option_name = sanitize_key($option_name);
        
        // Validate settings before saving
        $validated_settings = self::validate_settings_structure($settings);
        
        if (is_wp_error($validated_settings)) {
            return $validated_settings;
        }
        
        // Use WordPress options API for secure storage
        $result = update_option($option_name, $validated_settings);
        
        if (!$result) {
            WP_Plugin_Security_Logger::log_settings_update_failure($option_name, $validated_settings);
            return new WP_Error('settings_update_failed', __('Failed to save settings', 'wp-plugin-filters'));
        }
        
        return true;
    }
    
    /**
     * Validate settings structure and data integrity
     */
    private static function validate_settings_structure($settings) {
        $schema = WP_Plugin_Settings_Schema::get_validation_schema();
        $validated = [];
        
        foreach ($schema as $field => $rules) {
            if ($rules['required'] && !isset($settings[$field])) {
                return new WP_Error('missing_required_field', sprintf(__('Required field %s is missing', 'wp-plugin-filters'), $field));
            }
            
            if (isset($settings[$field])) {
                $validation_result = self::validate_field($settings[$field], $rules);
                if (is_wp_error($validation_result)) {
                    return $validation_result;
                }
                $validated[$field] = $validation_result;
            }
        }
        
        return $validated;
    }
    
    /**
     * Validate individual field according to rules
     */
    private static function validate_field($value, $rules) {
        // Type validation
        if (isset($rules['type'])) {
            if ($rules['type'] === 'array' && !is_array($value)) {
                return new WP_Error('invalid_type', __('Expected array type', 'wp-plugin-filters'));
            }
            if ($rules['type'] === 'integer' && !is_int($value)) {
                $value = intval($value);
            }
            if ($rules['type'] === 'string' && !is_string($value)) {
                return new WP_Error('invalid_type', __('Expected string type', 'wp-plugin-filters'));
            }
        }
        
        // Range validation for integers
        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            return new WP_Error('value_too_small', sprintf(__('Value must be at least %d', 'wp-plugin-filters'), $rules['min']));
        }
        
        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            return new WP_Error('value_too_large', sprintf(__('Value must be at most %d', 'wp-plugin-filters'), $rules['max']));
        }
        
        // Custom validation functions
        if (isset($rules['validation']) && method_exists(__CLASS__, $rules['validation'])) {
            $validation_method = $rules['validation'];
            $validation_result = self::$validation_method($value);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }
        }
        
        return $value;
    }
    
    /**
     * Validate algorithm weights sum to 100
     */
    private static function weights_sum_to_100($weights) {
        if (!is_array($weights)) {
            return new WP_Error('invalid_weights', __('Weights must be an array', 'wp-plugin-filters'));
        }
        
        $total = array_sum(array_values($weights));
        if (abs($total - 100) > 1) { // Allow 1% tolerance
            return new WP_Error('invalid_weights_sum', __('Algorithm weights must sum to 100%', 'wp-plugin-filters'));
        }
        
        return $weights;
    }
    
    /**
     * Get default settings as fallback
     */
    private static function get_default_settings() {
        return WP_Plugin_Settings_Schema::get_default_settings();
    }
}
```

## Rate Limiting and DoS Protection

### Request Rate Limiting Implementation
```php
<?php
/**
 * Rate Limiting using WordPress Transients
 */
class WP_Plugin_Rate_Limiter {
    
    const RATE_LIMITS = [
        'per_minute' => 60,      // 60 requests per minute
        'per_hour' => 1000,      // 1000 requests per hour
        'burst_limit' => 10,     // 10 requests in 10 seconds
        'concurrent_limit' => 3  // 3 concurrent requests
    ];
    
    /**
     * Check if request is within rate limits
     */
    public static function check_rate_limit($user_id = null) {
        $user_id = $user_id ?? get_current_user_id();
        $ip_address = self::get_client_ip();
        
        // Check per-user limits (if logged in)
        if ($user_id) {
            $user_check = self::check_user_rate_limit($user_id);
            if (is_wp_error($user_check)) {
                return $user_check;
            }
        }
        
        // Check IP-based limits (for additional protection)
        $ip_check = self::check_ip_rate_limit($ip_address);
        if (is_wp_error($ip_check)) {
            return $ip_check;
        }
        
        // Record successful request
        self::record_request($user_id, $ip_address);
        
        return true;
    }
    
    /**
     * Check per-user rate limits
     */
    private static function check_user_rate_limit($user_id) {
        $rate_keys = [
            'minute' => "wp_plugin_rate_user_{$user_id}_minute",
            'hour' => "wp_plugin_rate_user_{$user_id}_hour",
            'burst' => "wp_plugin_rate_user_{$user_id}_burst"
        ];
        
        // Check minute limit
        $minute_count = get_transient($rate_keys['minute']) ?: 0;
        if ($minute_count >= self::RATE_LIMITS['per_minute']) {
            WP_Plugin_Security_Logger::log_rate_limit_exceeded($user_id, 'minute', $minute_count);
            return new WP_Error('rate_limit_minute', __('Too many requests per minute. Please slow down.', 'wp-plugin-filters'));
        }
        
        // Check hour limit
        $hour_count = get_transient($rate_keys['hour']) ?: 0;
        if ($hour_count >= self::RATE_LIMITS['per_hour']) {
            WP_Plugin_Security_Logger::log_rate_limit_exceeded($user_id, 'hour', $hour_count);
            return new WP_Error('rate_limit_hour', __('Hourly request limit exceeded. Please try again later.', 'wp-plugin-filters'));
        }
        
        // Check burst limit
        $burst_count = get_transient($rate_keys['burst']) ?: 0;
        if ($burst_count >= self::RATE_LIMITS['burst_limit']) {
            WP_Plugin_Security_Logger::log_rate_limit_exceeded($user_id, 'burst', $burst_count);
            return new WP_Error('rate_limit_burst', __('Too many rapid requests. Please wait a moment.', 'wp-plugin-filters'));
        }
        
        return true;
    }
    
    /**
     * Check IP-based rate limits
     */
    private static function check_ip_rate_limit($ip_address) {
        $ip_hash = md5($ip_address); // Hash IP for privacy
        $rate_keys = [
            'minute' => "wp_plugin_rate_ip_{$ip_hash}_minute",
            'hour' => "wp_plugin_rate_ip_{$ip_hash}_hour"
        ];
        
        $minute_count = get_transient($rate_keys['minute']) ?: 0;
        if ($minute_count >= self::RATE_LIMITS['per_minute'] * 2) { // Higher limit for IP
            return new WP_Error('rate_limit_ip', __('IP rate limit exceeded', 'wp-plugin-filters'));
        }
        
        return true;
    }
    
    /**
     * Record successful request for rate limiting
     */
    private static function record_request($user_id, $ip_address) {
        if ($user_id) {
            // Increment user counters
            self::increment_counter("wp_plugin_rate_user_{$user_id}_minute", 60);
            self::increment_counter("wp_plugin_rate_user_{$user_id}_hour", 3600);
            self::increment_counter("wp_plugin_rate_user_{$user_id}_burst", 10);
        }
        
        // Increment IP counters
        $ip_hash = md5($ip_address);
        self::increment_counter("wp_plugin_rate_ip_{$ip_hash}_minute", 60);
        self::increment_counter("wp_plugin_rate_ip_{$ip_hash}_hour", 3600);
    }
    
    /**
     * Increment rate limiting counter
     */
    private static function increment_counter($key, $ttl) {
        $current_count = get_transient($key) ?: 0;
        set_transient($key, $current_count + 1, $ttl);
    }
    
    /**
     * Get client IP address securely
     */
    private static function get_client_ip() {
        // Check for various headers (in order of reliability)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Standard forwarded header
            'REMOTE_ADDR'                // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1'; // Fallback for local/invalid IPs
    }
    
    /**
     * Check for concurrent request limits
     */
    public static function check_concurrent_requests($user_id) {
        $concurrent_key = "wp_plugin_concurrent_user_{$user_id}";
        $concurrent_count = get_transient($concurrent_key) ?: 0;
        
        if ($concurrent_count >= self::RATE_LIMITS['concurrent_limit']) {
            return new WP_Error('concurrent_limit', __('Too many concurrent requests', 'wp-plugin-filters'));
        }
        
        // Increment concurrent counter (will be decremented when request completes)
        set_transient($concurrent_key, $concurrent_count + 1, 30); // 30 second timeout
        
        return true;
    }
    
    /**
     * Decrement concurrent request counter
     */
    public static function release_concurrent_limit($user_id) {
        $concurrent_key = "wp_plugin_concurrent_user_{$user_id}";
        $concurrent_count = get_transient($concurrent_key) ?: 0;
        
        if ($concurrent_count > 0) {
            set_transient($concurrent_key, $concurrent_count - 1, 30);
        }
    }
}
```

## Security Logging and Monitoring

### Security Event Logging
```php
<?php
/**
 * Security Event Logging System
 */
class WP_Plugin_Security_Logger {
    
    const LOG_LEVELS = [
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    
    /**
     * Log security event
     */
    public static function log_security_event($level, $event_type, $message, $context = []) {
        if (!self::should_log($level)) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'event_type' => $event_type,
            'message' => $message,
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip_hash(),
            'user_agent' => self::get_sanitized_user_agent(),
            'request_uri' => sanitize_text_field($_SERVER['REQUEST_URI'] ?? ''),
            'context' => $context
        ];
        
        // Write to WordPress debug log if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Plugin Filters Security] ' . wp_json_encode($log_entry));
        }
        
        // Store in database for critical events
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            self::store_security_log($log_entry);
        }
        
        // Send alerts for critical events
        if ($level === 'CRITICAL') {
            self::send_security_alert($log_entry);
        }
    }
    
    /**
     * Log rate limit exceeded
     */
    public static function log_rate_limit_exceeded($user_id, $limit_type, $count) {
        self::log_security_event('WARNING', 'rate_limit_exceeded', 
            "Rate limit exceeded: {$limit_type} limit reached ({$count} requests)", 
            ['user_id' => $user_id, 'limit_type' => $limit_type, 'count' => $count]
        );
    }
    
    /**
     * Log authentication failures
     */
    public static function log_auth_failure($attempted_action, $user_id = null) {
        self::log_security_event('WARNING', 'auth_failure',
            "Authentication failure for action: {$attempted_action}",
            ['attempted_action' => $attempted_action, 'user_id' => $user_id]
        );
    }
    
    /**
     * Log invalid nonce attempts
     */
    public static function log_invalid_nonce($operation, $provided_nonce) {
        self::log_security_event('WARNING', 'invalid_nonce',
            "Invalid nonce provided for operation: {$operation}",
            ['operation' => $operation, 'nonce_hash' => md5($provided_nonce)]
        );
    }
    
    /**
     * Log suspicious activity
     */
    public static function log_suspicious_activity($activity_type, $details) {
        self::log_security_event('ERROR', 'suspicious_activity',
            "Suspicious activity detected: {$activity_type}",
            ['activity_type' => $activity_type, 'details' => $details]
        );
    }
    
    /**
     * Log database errors
     */
    public static function log_database_error($error_message, $operation) {
        self::log_security_event('ERROR', 'database_error',
            "Database error during {$operation}: {$error_message}",
            ['operation' => $operation, 'error' => $error_message]
        );
    }
    
    /**
     * Log slow queries for potential DoS detection
     */
    public static function log_slow_query($request_data, $execution_time) {
        self::log_security_event('INFO', 'slow_query',
            "Slow query detected: {$execution_time}s",
            ['execution_time' => $execution_time, 'request_params' => $request_data]
        );
    }
    
    /**
     * Log API exceptions
     */
    public static function log_exception(Exception $exception, $context = []) {
        self::log_security_event('ERROR', 'exception',
            "Exception occurred: {$exception->getMessage()}",
            [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'context' => $context
            ]
        );
    }
    
    /**
     * Determine if logging should occur based on level
     */
    private static function should_log($level) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return in_array($level, ['ERROR', 'CRITICAL']);
        }
        
        return true; // Log everything in debug mode
    }
    
    /**
     * Store security log in database
     */
    private static function store_security_log($log_entry) {
        global $wpdb;
        
        // Store in options table with expiration (30 days)
        $option_name = 'wp_plugin_security_log_' . time() . '_' . wp_rand(1000, 9999);
        add_option($option_name, $log_entry, '', 'no');
        
        // Schedule cleanup
        wp_schedule_single_event(time() + (30 * DAY_IN_SECONDS), 'wp_plugin_cleanup_security_log', [$option_name]);
    }
    
    /**
     * Send security alert for critical events
     */
    private static function send_security_alert($log_entry) {
        $admin_email = get_option('admin_email');
        
        if (empty($admin_email)) {
            return;
        }
        
        $subject = sprintf(__('[%s] Security Alert: %s', 'wp-plugin-filters'), 
            get_bloginfo('name'), 
            $log_entry['event_type']
        );
        
        $message = sprintf(__("Security Alert Details:\n\nEvent: %s\nMessage: %s\nTimestamp: %s\nUser ID: %s\nIP Hash: %s\n\nContext: %s", 'wp-plugin-filters'),
            $log_entry['event_type'],
            $log_entry['message'],
            $log_entry['timestamp'],
            $log_entry['user_id'] ?: 'N/A',
            $log_entry['ip_address'],
            wp_json_encode($log_entry['context'])
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get hashed client IP for privacy
     */
    private static function get_client_ip_hash() {
        $ip = WP_Plugin_Rate_Limiter::get_client_ip();
        return md5($ip . wp_salt('secure_auth'));
    }
    
    /**
     * Get sanitized user agent
     */
    private static function get_sanitized_user_agent() {
        return sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
    }
}

// Register cleanup hook
add_action('wp_plugin_cleanup_security_log', function($option_name) {
    delete_option($option_name);
});
```

## Content Security Policy Integration

### CSP Headers for WordPress Admin
```php
<?php
/**
 * Content Security Policy Implementation
 */
class WP_Plugin_CSP_Security {
    
    /**
     * Add CSP headers for plugin pages
     */
    public static function add_csp_headers() {
        if (!self::is_plugin_admin_page()) {
            return;
        }
        
        $csp_directives = self::get_csp_directives();
        $csp_header = self::build_csp_header($csp_directives);
        
        header("Content-Security-Policy: {$csp_header}");
    }
    
    /**
     * Check if current page is plugin admin page
     */
    private static function is_plugin_admin_page() {
        $current_screen = get_current_screen();
        return $current_screen && in_array($current_screen->id, [
            'plugin-install',
            'settings_page_wp-plugin-filters'
        ]);
    }
    
    /**
     * Get CSP directives for plugin functionality
     */
    private static function get_csp_directives() {
        return [
            'default-src' => ["'self'"],
            'script-src' => [
                "'self'",
                "'unsafe-inline'", // WordPress admin requires inline scripts
                site_url(),
                admin_url()
            ],
            'style-src' => [
                "'self'",
                "'unsafe-inline'", // WordPress admin requires inline styles
                site_url(),
                admin_url()
            ],
            'img-src' => [
                "'self'",
                'data:', // For base64 images
                'https://ps.w.org', // WordPress.org plugin screenshots
                'https://s.w.org'   // WordPress.org assets
            ],
            'connect-src' => [
                "'self'",
                'https://api.wordpress.org', // WordPress.org API
                admin_url('admin-ajax.php')
            ],
            'font-src' => [
                "'self'",
                site_url()
            ],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => [
                "'self'",
                admin_url()
            ]
        ];
    }
    
    /**
     * Build CSP header string
     */
    private static function build_csp_header($directives) {
        $csp_parts = [];
        
        foreach ($directives as $directive => $sources) {
            $sources_string = implode(' ', $sources);
            $csp_parts[] = "{$directive} {$sources_string}";
        }
        
        return implode('; ', $csp_parts);
    }
}

// Add CSP headers for admin pages
add_action('admin_init', ['WP_Plugin_CSP_Security', 'add_csp_headers']);
```

## Security Summary and Recommendations

### Security Measures Implemented

1. **Authentication & Authorization**
   - WordPress capability system integration
   - Multi-site permission handling
   - Admin context validation

2. **Input Security**
   - Comprehensive sanitization using WordPress functions
   - Data validation with schema enforcement
   - Type casting and range validation

3. **Output Security**
   - WordPress escaping functions for all output
   - JSON encoding with WordPress standards
   - HTML sanitization with wp_kses

4. **CSRF Protection**
   - WordPress nonce system implementation
   - Operation-specific nonce actions
   - Form and AJAX protection

5. **Database Security**
   - WordPress $wpdb prepared statements
   - Input sanitization before queries
   - Data integrity validation

6. **Rate Limiting**
   - User and IP-based rate limiting
   - Burst and sustained request protection
   - Concurrent request limiting

7. **Security Monitoring**
   - Comprehensive event logging
   - Suspicious activity detection
   - Alert system for critical events

### Security Best Practices Followed

- **Principle of Least Privilege**: Users only get minimum required permissions
- **Defense in Depth**: Multiple security layers protect against various attack vectors
- **Input Validation**: All input validated and sanitized before processing
- **Output Encoding**: All output properly escaped based on context
- **Error Handling**: Secure error messages that don't leak information
- **Logging and Monitoring**: Comprehensive security event tracking

### Security Recommendations

1. **Regular Security Updates**: Keep WordPress core and dependencies updated
2. **Security Monitoring**: Monitor security logs for suspicious activities
3. **Rate Limiting Tuning**: Adjust rate limits based on site traffic patterns
4. **CSP Implementation**: Deploy Content Security Policy headers
5. **Security Audits**: Regular security testing and code reviews
6. **Backup Strategy**: Regular backups of WordPress database and files

This security architecture ensures the WordPress Plugin Directory Filters enhancement maintains the highest security standards while providing excellent functionality and user experience.