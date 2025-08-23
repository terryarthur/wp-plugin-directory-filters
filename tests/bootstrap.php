<?php
/**
 * WordPress Plugin Directory Filters Test Bootstrap
 *
 * @package WP_Plugin_Directory_Filters
 */

// Composer autoloader
if (file_exists(dirname(__FILE__) . '/../vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/../vendor/autoload.php';
}

// Define test environment constants
define('WP_PLUGIN_FILTERS_TESTING', true);
define('WP_PLUGIN_FILTERS_TEST_DATA_DIR', __DIR__ . '/data/');
define('WP_PLUGIN_FILTERS_TEST_FIXTURES_DIR', __DIR__ . '/fixtures/');
define('WP_PLUGIN_FILTERS_TEST_LOGS_DIR', __DIR__ . '/logs/');

// Create test directories if they don't exist
$test_dirs = [
    WP_PLUGIN_FILTERS_TEST_DATA_DIR,
    WP_PLUGIN_FILTERS_TEST_FIXTURES_DIR,
    WP_PLUGIN_FILTERS_TEST_LOGS_DIR,
    __DIR__ . '/coverage/',
];

foreach ($test_dirs as $dir) {
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
}

// WordPress test environment setup
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php\n";
    echo "Please set the WP_TESTS_DIR environment variable to point to WordPress test suite.\n";
    echo "Example: export WP_TESTS_DIR=/tmp/wordpress-tests-lib\n";
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Plugin constants
    define('WP_PLUGIN_FILTERS_VERSION', '1.0.0');
    define('WP_PLUGIN_FILTERS_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
    define('WP_PLUGIN_FILTERS_PLUGIN_URL', 'http://example.org/wp-content/plugins/wp-plugin-directory-filters/');
    define('WP_PLUGIN_FILTERS_BASENAME', 'wp-plugin-directory-filters/wp-plugin-directory-filters.php');
    
    // Load plugin
    require dirname(dirname(__FILE__)) . '/wp-plugin-directory-filters.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

/**
 * Set up test database and WordPress environment
 */
function _setup_test_environment() {
    // Set up test user roles and capabilities
    $admin_user = wp_create_user('test_admin', 'password', 'admin@example.org');
    $user = get_user_by('id', $admin_user);
    $user->add_role('administrator');
    
    $editor_user = wp_create_user('test_editor', 'password', 'editor@example.org');
    $user = get_user_by('id', $editor_user);
    $user->add_role('editor');
    
    // Set up test options
    update_option('wp_plugin_filters_settings', [
        'enable_caching' => true,
        'cache_duration' => 3600,
        'api_timeout' => 30,
        'rate_limit_per_minute' => 60,
        'usability_weights' => [
            'user_rating' => 40,
            'rating_count' => 20,
            'installation_count' => 25,
            'support_responsiveness' => 15
        ]
    ]);
    
    // Create test transients and cache data
    set_transient('wp_plugin_test_cache', 'test_data', 3600);
}

tests_add_filter('wp_loaded', '_setup_test_environment');

/**
 * Mock WordPress.org API responses for testing
 */
function _setup_api_mocks() {
    if (!defined('WP_PLUGIN_FILTERS_MOCK_API') || !WP_PLUGIN_FILTERS_MOCK_API) {
        return;
    }
    
    // Mock wp_remote_post for API calls
    add_filter('pre_http_request', function($preempt, $args, $url) {
        if (strpos($url, 'api.wordpress.org/plugins/info') !== false) {
            return _get_mock_api_response($args);
        }
        return $preempt;
    }, 10, 3);
}

tests_add_filter('init', '_setup_api_mocks');

/**
 * Get mock API response based on request
 */
function _get_mock_api_response($args) {
    $body = wp_parse_args($args['body']);
    $action = $body['action'] ?? '';
    $request = $body['request'] ?? [];
    
    switch ($action) {
        case 'query_plugins':
            return _get_mock_search_response($request);
        case 'plugin_information':
            return _get_mock_plugin_details($request);
        default:
            return new WP_Error('mock_api_error', 'Unknown action');
    }
}

/**
 * Get mock search response
 */
function _get_mock_search_response($request) {
    $search_term = $request['search'] ?? '';
    $page = $request['page'] ?? 1;
    $per_page = $request['per_page'] ?? 24;
    
    $mock_plugins = _get_mock_plugins_data();
    
    // Filter by search term if provided
    if (!empty($search_term)) {
        $mock_plugins = array_filter($mock_plugins, function($plugin) use ($search_term) {
            return stripos($plugin['name'], $search_term) !== false || 
                   stripos($plugin['short_description'], $search_term) !== false;
        });
    }
    
    $total_results = count($mock_plugins);
    $total_pages = ceil($total_results / $per_page);
    $offset = ($page - 1) * $per_page;
    $plugins = array_slice($mock_plugins, $offset, $per_page);
    
    return [
        'response' => ['code' => 200],
        'body' => json_encode([
            'plugins' => $plugins,
            'info' => [
                'page' => $page,
                'pages' => $total_pages,
                'results' => $total_results
            ]
        ])
    ];
}

/**
 * Get mock plugin details
 */
function _get_mock_plugin_details($request) {
    $slug = $request['slug'] ?? '';
    $mock_plugins = _get_mock_plugins_data();
    
    $plugin = null;
    foreach ($mock_plugins as $mock_plugin) {
        if ($mock_plugin['slug'] === $slug) {
            $plugin = $mock_plugin;
            break;
        }
    }
    
    if (!$plugin) {
        return [
            'response' => ['code' => 404],
            'body' => json_encode(['error' => 'Plugin not found'])
        ];
    }
    
    return [
        'response' => ['code' => 200],
        'body' => json_encode($plugin)
    ];
}

/**
 * Get mock plugins data for testing
 */
function _get_mock_plugins_data() {
    return [
        [
            'slug' => 'test-plugin-1',
            'name' => 'Test Plugin One',
            'version' => '1.0.0',
            'author' => 'Test Author',
            'rating' => 4.5,
            'num_ratings' => 150,
            'active_installs' => 50000,
            'last_updated' => gmdate('Y-m-d', strtotime('-7 days')),
            'added' => gmdate('Y-m-d', strtotime('-1 year')),
            'tested' => '6.4',
            'requires' => '5.8',
            'short_description' => 'A test plugin for unit testing',
            'description' => 'This is a test plugin used for unit testing purposes.',
            'homepage' => 'https://example.com/test-plugin-1',
            'download_link' => 'https://downloads.wordpress.org/plugin/test-plugin-1.zip',
            'tags' => ['test', 'development'],
            'support_threads' => 20,
            'support_threads_resolved' => 18,
            'downloaded' => 100000,
            'ratings' => [5 => 100, 4 => 30, 3 => 15, 2 => 3, 1 => 2]
        ],
        [
            'slug' => 'test-plugin-2',
            'name' => 'Test Plugin Two',
            'version' => '2.1.0',
            'author' => 'Another Author',
            'rating' => 3.8,
            'num_ratings' => 75,
            'active_installs' => 1000000,
            'last_updated' => gmdate('Y-m-d', strtotime('-30 days')),
            'added' => gmdate('Y-m-d', strtotime('-2 years')),
            'tested' => '6.4',
            'requires' => '5.6',
            'short_description' => 'Another test plugin for comprehensive testing',
            'description' => 'This is another test plugin for comprehensive testing.',
            'homepage' => 'https://example.com/test-plugin-2',
            'download_link' => 'https://downloads.wordpress.org/plugin/test-plugin-2.zip',
            'tags' => ['test', 'popular'],
            'support_threads' => 100,
            'support_threads_resolved' => 85,
            'downloaded' => 5000000,
            'ratings' => [5 => 45, 4 => 20, 3 => 8, 2 => 1, 1 => 1]
        ],
        [
            'slug' => 'test-plugin-3',
            'name' => 'Test Plugin Three',
            'version' => '0.5.0',
            'author' => 'Beta Developer',
            'rating' => 2.1,
            'num_ratings' => 10,
            'active_installs' => 500,
            'last_updated' => gmdate('Y-m-d', strtotime('-180 days')),
            'added' => gmdate('Y-m-d', strtotime('-6 months')),
            'tested' => '6.2',
            'requires' => '5.8',
            'short_description' => 'A beta test plugin with lower ratings',
            'description' => 'This is a beta test plugin with lower ratings for testing edge cases.',
            'homepage' => 'https://example.com/test-plugin-3',
            'download_link' => 'https://downloads.wordpress.org/plugin/test-plugin-3.zip',
            'tags' => ['beta', 'experimental'],
            'support_threads' => 5,
            'support_threads_resolved' => 2,
            'downloaded' => 1500,
            'ratings' => [5 => 1, 4 => 1, 3 => 2, 2 => 3, 1 => 3]
        ]
    ];
}

/**
 * Clean up test environment after tests
 */
function _cleanup_test_environment() {
    // Clean up test transients
    delete_transient('wp_plugin_test_cache');
    
    // Clean up test options
    delete_option('wp_plugin_filters_settings');
    
    // Clean up test files
    $test_files = glob(WP_PLUGIN_FILTERS_TEST_LOGS_DIR . '*');
    foreach ($test_files as $file) {
        if (is_file($file)) {
            wp_delete_file($file);
        }
    }
}

register_shutdown_function('_cleanup_test_environment');

// Load WordPress test framework
require_once $_tests_dir . '/includes/bootstrap.php';

// Load test helper functions
require_once __DIR__ . '/includes/test-helpers.php';
require_once __DIR__ . '/includes/mock-handlers.php';
require_once __DIR__ . '/includes/assertion-helpers.php';

// Load base test classes
require_once __DIR__ . '/includes/class-wp-plugin-filters-test-case.php';
require_once __DIR__ . '/includes/class-wp-plugin-filters-ajax-test-case.php';
require_once __DIR__ . '/includes/class-wp-plugin-filters-api-test-case.php';