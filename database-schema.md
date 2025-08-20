# WordPress Plugin Directory Filters - Database Schema and Data Models

## Overview

The WordPress Plugin Directory Filters enhancement utilizes WordPress native database APIs exclusively, avoiding custom tables while leveraging the WordPress options system, transients API, and multisite capabilities. This approach ensures compatibility with WordPress hosting environments and maintains consistency with WordPress data management patterns.

## WordPress Database Integration Strategy

### No Custom Tables Approach
```sql
-- This plugin does NOT create custom tables
-- All data storage uses WordPress native APIs:
-- 1. WordPress Options API (wp_options table)
-- 2. WordPress Transients API (wp_options table with TTL)
-- 3. WordPress Object Cache (Redis/Memcached when available)
-- 4. WordPress Multisite Meta (wp_sitemeta table)
```

**Rationale**: 
- Eliminates database schema migration complexity
- Automatically inherits WordPress database optimization
- Ensures compatibility with WordPress backup/restore procedures
- Reduces plugin activation/deactivation complexity
- Maintains WordPress multisite compatibility

## WordPress Options Schema

### Plugin Settings Storage
```sql
-- Plugin configuration stored in wp_options
INSERT INTO wp_options (option_name, option_value, autoload) VALUES 
(
    'wp_plugin_filters_settings',
    '{
        "version": "1.0.0",
        "usability_weights": {
            "user_rating": 40,
            "rating_count": 20,
            "installation_count": 25,
            "support_responsiveness": 15
        },
        "health_weights": {
            "update_frequency": 30,
            "wp_compatibility": 25,
            "support_response": 20,
            "time_since_update": 15,
            "reported_issues": 10
        },
        "cache_settings": {
            "plugin_metadata_ttl": 86400,
            "calculated_ratings_ttl": 21600,
            "search_results_ttl": 3600,
            "api_responses_ttl": 1800
        },
        "ui_settings": {
            "default_filters": [],
            "results_per_page": 24,
            "show_health_tooltips": true,
            "show_rating_breakdown": true
        }
    }',
    'yes'
);
```

### WordPress Options Schema Definition
```php
<?php
/**
 * Plugin Settings Schema
 * Stored in wp_options as 'wp_plugin_filters_settings'
 */
class WP_Plugin_Settings_Schema {
    const OPTION_NAME = 'wp_plugin_filters_settings';
    const AUTOLOAD = 'yes';
    
    /**
     * Default settings structure
     */
    public static function get_default_settings() {
        return [
            'version' => '1.0.0',
            'usability_weights' => [
                'user_rating' => 40,              // 40% weight for existing user ratings
                'rating_count' => 20,             // 20% weight for number of ratings
                'installation_count' => 25,       // 25% weight for popularity
                'support_responsiveness' => 15    // 15% weight for support quality
            ],
            'health_weights' => [
                'update_frequency' => 30,         // 30% weight for regular updates
                'wp_compatibility' => 25,         // 25% weight for WordPress compatibility
                'support_response' => 20,         // 20% weight for support responsiveness
                'time_since_update' => 15,        // 15% weight for recency
                'reported_issues' => 10           // 10% weight for known issues
            ],
            'cache_settings' => [
                'plugin_metadata_ttl' => 86400,   // 24 hours for stable plugin data
                'calculated_ratings_ttl' => 21600, // 6 hours for calculated ratings
                'search_results_ttl' => 3600,     // 1 hour for search results
                'api_responses_ttl' => 1800        // 30 minutes for API responses
            ],
            'ui_settings' => [
                'default_filters' => [],
                'results_per_page' => 24,
                'show_health_tooltips' => true,
                'show_rating_breakdown' => true,
                'animation_enabled' => true
            ],
            'advanced_settings' => [
                'api_timeout' => 30,
                'max_concurrent_requests' => 5,
                'enable_debug_logging' => false,
                'cache_warming_enabled' => false
            ]
        ];
    }
    
    /**
     * Settings validation schema
     */
    public static function get_validation_schema() {
        return [
            'version' => ['type' => 'string', 'required' => true],
            'usability_weights' => [
                'type' => 'array',
                'required' => true,
                'validation' => 'weights_sum_to_100',
                'fields' => [
                    'user_rating' => ['type' => 'integer', 'min' => 0, 'max' => 100],
                    'rating_count' => ['type' => 'integer', 'min' => 0, 'max' => 100],
                    'installation_count' => ['type' => 'integer', 'min' => 0, 'max' => 100],
                    'support_responsiveness' => ['type' => 'integer', 'min' => 0, 'max' => 100]
                ]
            ],
            'health_weights' => [
                'type' => 'array',
                'required' => true,
                'validation' => 'weights_sum_to_100',
                'fields' => [
                    'update_frequency' => ['type' => 'integer', 'min' => 0, 'max' => 100],
                    'wp_compatibility' => ['type' => 'integer', 'min' => 0, 'max' => 100],
                    'support_response' => ['type' => 'integer', 'min' => 0, 'max' => 100],
                    'time_since_update' => ['type' => 'integer', 'min' => 0, 'max' => 100],
                    'reported_issues' => ['type' => 'integer', 'min' => 0, 'max' => 100]
                ]
            ],
            'cache_settings' => [
                'type' => 'array',
                'required' => true,
                'fields' => [
                    'plugin_metadata_ttl' => ['type' => 'integer', 'min' => 3600, 'max' => 604800], // 1 hour to 1 week
                    'calculated_ratings_ttl' => ['type' => 'integer', 'min' => 1800, 'max' => 86400], // 30 min to 24 hours
                    'search_results_ttl' => ['type' => 'integer', 'min' => 300, 'max' => 7200], // 5 min to 2 hours
                    'api_responses_ttl' => ['type' => 'integer', 'min' => 300, 'max' => 3600] // 5 min to 1 hour
                ]
            ]
        ];
    }
}
```

## WordPress Transients Schema

### Plugin Metadata Cache
```sql
-- Plugin metadata cached using WordPress transients
-- Timeout entries manage TTL automatically
INSERT INTO wp_options (option_name, option_value, autoload) VALUES 
(
    '_transient_timeout_wp_plugin_meta_woocommerce',
    UNIX_TIMESTAMP() + 86400, -- 24 hour expiration
    'no'
),
(
    '_transient_wp_plugin_meta_woocommerce',
    '{
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
        "description": "The most customizable eCommerce platform...",
        "homepage": "https://woocommerce.com/",
        "download_link": "https://downloads.wordpress.org/plugin/woocommerce.8.5.2.zip",
        "tags": ["ecommerce", "store", "sales"],
        "support_threads": 150,
        "support_threads_resolved": 120,
        "cached_at": "2024-01-15 10:00:00",
        "cache_version": "1.0"
    }',
    'no'
);
```

### Calculated Ratings Cache
```sql
-- Calculated ratings cached separately with shorter TTL
INSERT INTO wp_options (option_name, option_value, autoload) VALUES 
(
    '_transient_timeout_wp_plugin_rating_woocommerce',
    UNIX_TIMESTAMP() + 21600, -- 6 hour expiration
    'no'
),
(
    '_transient_wp_plugin_rating_woocommerce',
    '{
        "plugin_slug": "woocommerce",
        "usability_rating": 4.7,
        "health_score": 92,
        "health_color": "green",
        "calculated_at": "2024-01-15 10:00:00",
        "algorithm_version": "1.0",
        "calculation_breakdown": {
            "usability": {
                "user_rating_component": 0.92,
                "rating_count_component": 1.0,
                "installation_component": 1.0,
                "support_component": 0.8,
                "weighted_score": 0.94,
                "final_score": 4.7
            },
            "health": {
                "update_frequency_score": 0.8,
                "compatibility_score": 1.0,
                "support_response_score": 0.8,
                "recency_score": 1.0,
                "issues_score": 0.9,
                "weighted_score": 92,
                "final_score": 92
            }
        },
        "cache_key": "wp_plugin_rating_woocommerce",
        "cache_version": "1.0"
    }',
    'no'
);
```

### Search Results Cache
```sql
-- Search results cached with hash-based keys
INSERT INTO wp_options (option_name, option_value, autoload) VALUES 
(
    '_transient_timeout_wp_plugin_search_a1b2c3d4e5',
    UNIX_TIMESTAMP() + 3600, -- 1 hour expiration
    'no'
),
(
    '_transient_wp_plugin_search_a1b2c3d4e5',
    '{
        "search_term": "ecommerce",
        "filters": {
            "installation_range": "100k-1m",
            "update_timeframe": "last_month",
            "usability_rating": 4.0,
            "health_score": 70
        },
        "sort": {
            "field": "installations",
            "direction": "desc"
        },
        "pagination": {
            "page": 1,
            "per_page": 24,
            "total_results": 150,
            "total_pages": 7
        },
        "results": [
            {
                "slug": "woocommerce",
                "basic_data": "...",
                "enhanced_data": "..."
            }
        ],
        "cached_at": "2024-01-15 10:00:00",
        "cache_hash": "a1b2c3d4e5",
        "api_response_count": 3
    }',
    'no'
);
```

## WordPress Transients Implementation

### Cache Key Generation Strategy
```php
<?php
class WP_Plugin_Cache_Keys {
    /**
     * Generate cache key for plugin metadata
     */
    public static function plugin_metadata($slug) {
        return "wp_plugin_meta_" . sanitize_key($slug);
    }
    
    /**
     * Generate cache key for calculated ratings
     */
    public static function plugin_rating($slug) {
        return "wp_plugin_rating_" . sanitize_key($slug);
    }
    
    /**
     * Generate cache key for search results
     */
    public static function search_results($search_params) {
        $normalized_params = self::normalize_search_params($search_params);
        $hash = md5(serialize($normalized_params));
        return "wp_plugin_search_" . $hash;
    }
    
    /**
     * Generate cache key for API responses
     */
    public static function api_response($endpoint, $params) {
        $hash = md5($endpoint . serialize($params));
        return "wp_plugin_api_" . $hash;
    }
    
    /**
     * Normalize search parameters for consistent cache keys
     */
    private static function normalize_search_params($params) {
        $normalized = [
            'search_term' => strtolower(trim($params['search_term'] ?? '')),
            'installation_range' => $params['installation_range'] ?? '',
            'update_timeframe' => $params['update_timeframe'] ?? '',
            'usability_rating' => floatval($params['usability_rating'] ?? 0),
            'health_score' => intval($params['health_score'] ?? 0),
            'sort_by' => $params['sort_by'] ?? 'relevance',
            'sort_direction' => $params['sort_direction'] ?? 'desc',
            'per_page' => intval($params['per_page'] ?? 24)
        ];
        
        // Remove empty values for consistent hashing
        return array_filter($normalized, function($value) {
            return $value !== '' && $value !== 0 && $value !== 0.0;
        });
    }
}
```

### WordPress Transients Manager
```php
<?php
class WP_Plugin_Transients_Manager {
    /**
     * Set transient with WordPress transients API
     */
    public static function set($key, $data, $expiration) {
        // Add metadata to cached data
        $cache_data = [
            'data' => $data,
            'cached_at' => current_time('mysql'),
            'cache_key' => $key,
            'cache_version' => '1.0',
            'expires_at' => current_time('mysql', true) + $expiration
        ];
        
        return set_transient($key, $cache_data, $expiration);
    }
    
    /**
     * Get transient with WordPress transients API
     */
    public static function get($key) {
        $cache_data = get_transient($key);
        
        if ($cache_data === false) {
            return null;
        }
        
        // Validate cache structure
        if (!is_array($cache_data) || !isset($cache_data['data'])) {
            // Invalid cache structure, delete it
            delete_transient($key);
            return null;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * Delete transient with WordPress transients API
     */
    public static function delete($key) {
        return delete_transient($key);
    }
    
    /**
     * Get cache metadata
     */
    public static function get_cache_info($key) {
        $cache_data = get_transient($key);
        
        if ($cache_data === false || !isset($cache_data['cached_at'])) {
            return null;
        }
        
        $timeout = get_option("_transient_timeout_{$key}");
        
        return [
            'cached_at' => $cache_data['cached_at'],
            'cache_key' => $cache_data['cache_key'] ?? $key,
            'cache_version' => $cache_data['cache_version'] ?? '1.0',
            'expires_at' => $cache_data['expires_at'] ?? null,
            'timeout' => $timeout,
            'time_remaining' => $timeout ? max(0, $timeout - time()) : 0
        ];
    }
    
    /**
     * Clear all plugin-related transients
     */
    public static function clear_all_plugin_cache() {
        global $wpdb;
        
        // Delete all plugin-related transients
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_plugin_%' 
             OR option_name LIKE '_transient_timeout_wp_plugin_%'"
        );
        
        // Clear object cache groups if available
        if (wp_using_ext_object_cache()) {
            wp_cache_flush_group('plugin_metadata');
            wp_cache_flush_group('plugin_ratings');
            wp_cache_flush_group('plugin_searches');
        }
        
        return $deleted;
    }
    
    /**
     * Clear specific cache type
     */
    public static function clear_cache_type($type) {
        global $wpdb;
        
        $patterns = [
            'metadata' => 'wp_plugin_meta_%',
            'ratings' => 'wp_plugin_rating_%', 
            'searches' => 'wp_plugin_search_%',
            'api' => 'wp_plugin_api_%'
        ];
        
        if (!isset($patterns[$type])) {
            return false;
        }
        
        $pattern = $patterns[$type];
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 OR option_name LIKE %s",
                "_transient_{$pattern}",
                "_transient_timeout_{$pattern}"
            )
        );
    }
}
```

## WordPress Object Cache Integration

### Object Cache Strategy
```php
<?php
class WP_Plugin_Object_Cache {
    /**
     * Check if external object cache is available
     */
    public static function is_available() {
        return wp_using_ext_object_cache();
    }
    
    /**
     * Set object cache with group support
     */
    public static function set($key, $data, $group = 'plugin_metadata', $expiration = 3600) {
        if (!self::is_available()) {
            return false;
        }
        
        $cache_data = [
            'data' => $data,
            'cached_at' => current_time('mysql'),
            'cache_group' => $group,
            'cache_key' => $key
        ];
        
        return wp_cache_set($key, $cache_data, $group, $expiration);
    }
    
    /**
     * Get from object cache
     */
    public static function get($key, $group = 'plugin_metadata') {
        if (!self::is_available()) {
            return false;
        }
        
        $cache_data = wp_cache_get($key, $group);
        
        if ($cache_data === false || !is_array($cache_data) || !isset($cache_data['data'])) {
            return false;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * Delete from object cache
     */
    public static function delete($key, $group = 'plugin_metadata') {
        if (!self::is_available()) {
            return false;
        }
        
        return wp_cache_delete($key, $group);
    }
    
    /**
     * Flush cache group
     */
    public static function flush_group($group) {
        if (!self::is_available()) {
            return false;
        }
        
        return wp_cache_flush_group($group);
    }
}
```

### Hybrid Caching Implementation
```php
<?php
class WP_Plugin_Hybrid_Cache {
    /**
     * Get data with fallback strategy: Object Cache -> Transients -> API
     */
    public static function get_plugin_data($slug) {
        // Try object cache first (fastest)
        if (WP_Plugin_Object_Cache::is_available()) {
            $data = WP_Plugin_Object_Cache::get($slug, 'plugin_metadata');
            if ($data !== false) {
                return $data;
            }
        }
        
        // Fall back to transients (database)
        $data = WP_Plugin_Transients_Manager::get("wp_plugin_meta_{$slug}");
        if ($data !== null) {
            // Populate object cache for future requests
            if (WP_Plugin_Object_Cache::is_available()) {
                WP_Plugin_Object_Cache::set($slug, $data, 'plugin_metadata', 3600);
            }
            return $data;
        }
        
        return null; // Cache miss, need to fetch from API
    }
    
    /**
     * Store data in both cache layers
     */
    public static function set_plugin_data($slug, $data, $ttl = 86400) {
        // Store in transients (persistent)
        WP_Plugin_Transients_Manager::set("wp_plugin_meta_{$slug}", $data, $ttl);
        
        // Store in object cache (faster access)
        if (WP_Plugin_Object_Cache::is_available()) {
            $object_cache_ttl = min($ttl, 3600); // Max 1 hour in object cache
            WP_Plugin_Object_Cache::set($slug, $data, 'plugin_metadata', $object_cache_ttl);
        }
        
        return true;
    }
}
```

## WordPress Multisite Schema

### Network-Wide Settings
```sql
-- Network settings stored in wp_sitemeta for multisite installations
INSERT INTO wp_sitemeta (meta_id, site_id, meta_key, meta_value) VALUES 
(
    NULL,
    1,
    'wp_plugin_filters_network_settings',
    '{
        "network_override": false,
        "allowed_sites": [],
        "global_cache_settings": {
            "enable_network_cache": true,
            "shared_cache_duration": 86400
        },
        "global_algorithm_settings": {
            "enforce_network_weights": false,
            "default_usability_weights": {},
            "default_health_weights": {}
        },
        "network_admin_settings": {
            "allow_site_customization": true,
            "enable_site_analytics": false
        }
    }'
);
```

### Multisite Data Management
```php
<?php
class WP_Plugin_Multisite_Data {
    /**
     * Get network-wide settings
     */
    public static function get_network_settings() {
        if (!is_multisite()) {
            return [];
        }
        
        return get_site_option('wp_plugin_filters_network_settings', []);
    }
    
    /**
     * Update network-wide settings
     */
    public static function update_network_settings($settings) {
        if (!is_multisite()) {
            return false;
        }
        
        return update_site_option('wp_plugin_filters_network_settings', $settings);
    }
    
    /**
     * Get effective settings (network override or site-specific)
     */
    public static function get_effective_settings() {
        if (!is_multisite()) {
            return get_option('wp_plugin_filters_settings', []);
        }
        
        $network_settings = self::get_network_settings();
        
        if ($network_settings['network_override'] ?? false) {
            // Network admin overrides site settings
            return array_merge(
                get_option('wp_plugin_filters_settings', []),
                $network_settings
            );
        }
        
        // Use site-specific settings
        return get_option('wp_plugin_filters_settings', []);
    }
    
    /**
     * Clear cache across network
     */
    public static function clear_network_cache() {
        if (!is_multisite()) {
            return WP_Plugin_Transients_Manager::clear_all_plugin_cache();
        }
        
        $sites = get_sites(['number' => 0]);
        $total_cleared = 0;
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            $total_cleared += WP_Plugin_Transients_Manager::clear_all_plugin_cache();
            restore_current_blog();
        }
        
        return $total_cleared;
    }
}
```

## Data Models and Structures

### Plugin Metadata Model
```php
<?php
/**
 * Plugin Metadata Data Model
 * Represents cached plugin data from WordPress.org API
 */
class WP_Plugin_Metadata_Model {
    public $slug;                    // string: WordPress.org plugin slug
    public $name;                    // string: Plugin display name
    public $version;                 // string: Current plugin version
    public $author;                  // string: Plugin author name
    public $rating;                  // float: Average user rating (0-5)
    public $num_ratings;             // int: Number of user ratings
    public $active_installs;         // int: Active installation count
    public $last_updated;            // string: Last update timestamp (MySQL format)
    public $tested;                  // string: WordPress tested up to version
    public $requires;                // string: Minimum WordPress version
    public $description;             // string: Plugin description/excerpt
    public $homepage;                // string: Plugin homepage URL
    public $download_link;           // string: Plugin download URL
    public $tags;                    // array: Plugin tags
    public $support_threads;         // int: Total support threads
    public $support_threads_resolved; // int: Resolved support threads
    
    // Cache metadata
    public $cached_at;               // string: When data was cached
    public $cache_version;           // string: Cache format version
    public $api_source;              // string: Source of the data
    
    /**
     * Create from WordPress.org API response
     */
    public static function from_api_response($api_data) {
        $model = new self();
        
        // Map API fields to model properties with sanitization
        $model->slug = sanitize_key($api_data['slug'] ?? '');
        $model->name = sanitize_text_field($api_data['name'] ?? '');
        $model->version = sanitize_text_field($api_data['version'] ?? '');
        $model->author = sanitize_text_field($api_data['author'] ?? '');
        $model->rating = floatval($api_data['rating'] ?? 0) / 20; // Convert 0-100 to 0-5
        $model->num_ratings = absint($api_data['num_ratings'] ?? 0);
        $model->active_installs = absint($api_data['active_installs'] ?? 0);
        $model->last_updated = sanitize_text_field($api_data['last_updated'] ?? '');
        $model->tested = sanitize_text_field($api_data['tested'] ?? '');
        $model->requires = sanitize_text_field($api_data['requires'] ?? '');
        $model->description = wp_kses_post($api_data['short_description'] ?? '');
        $model->homepage = esc_url_raw($api_data['homepage'] ?? '');
        $model->download_link = esc_url_raw($api_data['download_link'] ?? '');
        $model->tags = array_map('sanitize_key', (array)($api_data['tags'] ?? []));
        $model->support_threads = absint($api_data['support_threads'] ?? 0);
        $model->support_threads_resolved = absint($api_data['support_threads_resolved'] ?? 0);
        
        // Add cache metadata
        $model->cached_at = current_time('mysql');
        $model->cache_version = '1.0';
        $model->api_source = 'wordpress_org';
        
        return $model;
    }
    
    /**
     * Convert to array for storage
     */
    public function to_array() {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'version' => $this->version,
            'author' => $this->author,
            'rating' => $this->rating,
            'num_ratings' => $this->num_ratings,
            'active_installs' => $this->active_installs,
            'last_updated' => $this->last_updated,
            'tested' => $this->tested,
            'requires' => $this->requires,
            'description' => $this->description,
            'homepage' => $this->homepage,
            'download_link' => $this->download_link,
            'tags' => $this->tags,
            'support_threads' => $this->support_threads,
            'support_threads_resolved' => $this->support_threads_resolved,
            'cached_at' => $this->cached_at,
            'cache_version' => $this->cache_version,
            'api_source' => $this->api_source
        ];
    }
    
    /**
     * Create from cached array data
     */
    public static function from_cache_array($cache_data) {
        $model = new self();
        
        foreach ($cache_data as $key => $value) {
            if (property_exists($model, $key)) {
                $model->$key = $value;
            }
        }
        
        return $model;
    }
    
    /**
     * Validate data integrity
     */
    public function is_valid() {
        return !empty($this->slug) && 
               !empty($this->name) && 
               !empty($this->version) &&
               is_numeric($this->rating) &&
               is_numeric($this->active_installs);
    }
}
```

### Plugin Rating Model
```php
<?php
/**
 * Plugin Rating Data Model
 * Represents calculated usability rating and health score
 */
class WP_Plugin_Rating_Model {
    public $plugin_slug;             // string: Plugin slug
    public $usability_rating;        // float: Calculated usability rating (1.0-5.0)
    public $health_score;            // int: Calculated health score (0-100)
    public $health_color;            // string: Color coding for health score
    public $calculated_at;           // string: When ratings were calculated
    public $algorithm_version;       // string: Algorithm version used
    public $calculation_breakdown;   // array: Detailed calculation data
    public $cache_key;               // string: Cache key used
    public $cache_version;           // string: Cache format version
    
    /**
     * Create new rating model
     */
    public static function create($plugin_slug, $usability_rating, $health_score, $breakdown = []) {
        $model = new self();
        
        $model->plugin_slug = sanitize_key($plugin_slug);
        $model->usability_rating = round(floatval($usability_rating), 1);
        $model->health_score = max(0, min(100, intval($health_score)));
        $model->health_color = self::determine_health_color($model->health_score);
        $model->calculated_at = current_time('mysql');
        $model->algorithm_version = '1.0';
        $model->calculation_breakdown = $breakdown;
        $model->cache_key = "wp_plugin_rating_{$plugin_slug}";
        $model->cache_version = '1.0';
        
        return $model;
    }
    
    /**
     * Determine health score color
     */
    private static function determine_health_color($score) {
        if ($score >= 86) return 'green';
        if ($score >= 71) return 'light-green';
        if ($score >= 41) return 'orange';
        return 'red';
    }
    
    /**
     * Convert to array for storage
     */
    public function to_array() {
        return [
            'plugin_slug' => $this->plugin_slug,
            'usability_rating' => $this->usability_rating,
            'health_score' => $this->health_score,
            'health_color' => $this->health_color,
            'calculated_at' => $this->calculated_at,
            'algorithm_version' => $this->algorithm_version,
            'calculation_breakdown' => $this->calculation_breakdown,
            'cache_key' => $this->cache_key,
            'cache_version' => $this->cache_version
        ];
    }
    
    /**
     * Create from cached array data
     */
    public static function from_cache_array($cache_data) {
        $model = new self();
        
        foreach ($cache_data as $key => $value) {
            if (property_exists($model, $key)) {
                $model->$key = $value;
            }
        }
        
        return $model;
    }
    
    /**
     * Check if rating needs recalculation
     */
    public function needs_recalculation($max_age_seconds = 21600) {
        $calculated_timestamp = strtotime($this->calculated_at);
        $current_timestamp = current_time('timestamp');
        
        return ($current_timestamp - $calculated_timestamp) > $max_age_seconds;
    }
}
```

## Cache Cleanup and Maintenance

### WordPress Cron Integration
```php
<?php
/**
 * Cache maintenance using WordPress cron
 */
class WP_Plugin_Cache_Maintenance {
    
    /**
     * Register cron events
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('wp_plugin_filters_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_plugin_filters_cache_cleanup');
        }
        
        if (!wp_next_scheduled('wp_plugin_filters_cache_warming')) {
            wp_schedule_event(time(), 'hourly', 'wp_plugin_filters_cache_warming');
        }
    }
    
    /**
     * Daily cache cleanup
     */
    public static function daily_cache_cleanup() {
        global $wpdb;
        
        // Remove expired transients (WordPress doesn't always clean these up)
        $expired_transients = $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_wp_plugin_%'
             AND a.option_name = CONCAT('_transient_', SUBSTRING(b.option_name, 20))
             AND b.option_name LIKE '_transient_timeout_wp_plugin_%'
             AND b.option_value < UNIX_TIMESTAMP()"
        );
        
        // Log cleanup results
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WP Plugin Filters: Cleaned up {$expired_transients} expired cache entries");
        }
        
        return $expired_transients;
    }
    
    /**
     * Cache warming for popular plugins
     */
    public static function warm_popular_plugins_cache() {
        // This would be implemented to pre-cache popular plugins
        // Based on installation count or site-specific usage patterns
        
        $popular_plugins = ['woocommerce', 'yoast-seo', 'elementor', 'contact-form-7'];
        
        foreach ($popular_plugins as $slug) {
            $cache_key = "wp_plugin_meta_{$slug}";
            $cached_data = get_transient($cache_key);
            
            if ($cached_data === false) {
                // Cache miss - trigger background refresh
                wp_schedule_single_event(time() + 30, 'wp_plugin_filters_refresh_plugin', [$slug]);
            }
        }
    }
    
    /**
     * Get cache statistics
     */
    public static function get_cache_stats() {
        global $wpdb;
        
        $stats = [];
        
        // Count different cache types
        $cache_types = [
            'metadata' => 'wp_plugin_meta_%',
            'ratings' => 'wp_plugin_rating_%',
            'searches' => 'wp_plugin_search_%',
            'api' => 'wp_plugin_api_%'
        ];
        
        foreach ($cache_types as $type => $pattern) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                    "_transient_{$pattern}"
                )
            );
            
            $stats[$type] = intval($count);
        }
        
        // Calculate total size (approximate)
        $total_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_wp_plugin_%'"
        );
        
        $stats['total_entries'] = array_sum($stats);
        $stats['total_size_bytes'] = intval($total_size);
        $stats['total_size_human'] = size_format($total_size);
        
        return $stats;
    }
}

// Register cron hooks
add_action('wp_plugin_filters_cache_cleanup', ['WP_Plugin_Cache_Maintenance', 'daily_cache_cleanup']);
add_action('wp_plugin_filters_cache_warming', ['WP_Plugin_Cache_Maintenance', 'warm_popular_plugins_cache']);
```

## Summary

This database schema leverages WordPress native storage APIs exclusively, providing:

1. **WordPress Options Integration**: All persistent settings stored using WordPress Options API
2. **WordPress Transients Caching**: Efficient caching with automatic TTL management
3. **Object Cache Support**: Redis/Memcached integration when available
4. **Multisite Compatibility**: Network-wide and site-specific settings support
5. **No Custom Tables**: Eliminates database migration complexity
6. **Automatic Cleanup**: WordPress cron integration for cache maintenance
7. **Performance Optimization**: Multi-tier caching strategy
8. **Data Integrity**: Comprehensive validation and error handling

The schema ensures scalability while maintaining compatibility with WordPress hosting environments and providing efficient data access patterns optimized for the WordPress ecosystem.