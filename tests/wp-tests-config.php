<?php
/**
 * WordPress Test Configuration
 *
 * This file configures the test database connection and other test-specific settings.
 * Copy this file to wp-tests-config.php and fill in the values.
 */

// Test database settings
define('DB_NAME', getenv('WP_TEST_DB_NAME') ?: 'wp_plugin_filters_test');
define('DB_USER', getenv('WP_TEST_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WP_TEST_DB_PASSWORD') ?: '');
define('DB_HOST', getenv('WP_TEST_DB_HOST') ?: 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// Test-specific constants
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'WP Plugin Filters Test');

// WordPress table prefix for tests
$table_prefix = 'wptests_';

// Force known bugs to be enabled
define('WP_TESTS_FORCE_KNOWN_BUGS', true);

// Test with multisite enabled
define('WP_TESTS_MULTISITE', true);

// WordPress debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Security keys for testing (use random keys in production)
define('AUTH_KEY',         'testing-key-1');
define('SECURE_AUTH_KEY',  'testing-key-2');
define('LOGGED_IN_KEY',    'testing-key-3');
define('NONCE_KEY',        'testing-key-4');
define('AUTH_SALT',        'testing-salt-1');
define('SECURE_AUTH_SALT', 'testing-salt-2');
define('LOGGED_IN_SALT',   'testing-salt-3');
define('NONCE_SALT',       'testing-salt-4');

// Plugin-specific test settings
define('WP_PLUGIN_FILTERS_TEST_API_KEY', 'test-api-key');
define('WP_PLUGIN_FILTERS_TEST_CACHE_DIR', sys_get_temp_dir() . '/wp-plugin-filters-test-cache/');

// Performance test thresholds
define('WP_PLUGIN_FILTERS_PERFORMANCE_THRESHOLD_MS', 200);
define('WP_PLUGIN_FILTERS_MAX_MEMORY_MB', 256);

// Test database connection verification
$test_db_connection = @new mysqli(DB_HOST, DB_USER, DB_PASSWORD);
if ($test_db_connection->connect_error) {
    die('Test database connection failed: ' . $test_db_connection->connect_error . "\n");
}

// Create test database if it doesn't exist
$test_db_connection->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8 COLLATE utf8_general_ci");
$test_db_connection->close();

// WordPress absolute path
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/wordpress/');
}