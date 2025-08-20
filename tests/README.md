# WordPress Plugin Directory Filters - Test Suite

This comprehensive test suite ensures the reliability, security, and performance of the WordPress Plugin Directory Filters plugin. It includes multiple testing strategies and frameworks to provide thorough coverage of all plugin functionality.

## Test Structure

### Test Types

1. **Unit Tests** (`tests/unit/`) - Test individual classes and functions in isolation
2. **Integration Tests** (`tests/integration/`) - Test WordPress-specific functionality and hooks
3. **End-to-End Tests** (`tests/e2e/`) - Test complete user workflows
4. **Performance Tests** (`tests/performance/`) - Test caching, API calls, and resource usage
5. **Security Tests** (`tests/security/`) - Test authentication, authorization, and input validation
6. **Compatibility Tests** (`tests/compatibility/`) - Test across WordPress versions and environments
7. **JavaScript Tests** (`tests/js/`) - Test frontend admin functionality using Jest

### Test Framework Components

- **PHPUnit** - PHP testing framework with WordPress test suite integration
- **Jest** - JavaScript testing framework with DOM testing utilities
- **Mock API Handlers** - Simulate WordPress.org API responses
- **Test Data Fixtures** - Consistent test environments and data
- **Performance Benchmarks** - Automated performance threshold checking
- **Security Scanners** - Vulnerability detection and input validation testing

## Quick Start

### Prerequisites

- PHP 7.4+ with required extensions (json, mbstring, curl)
- Composer for PHP dependency management
- Node.js 16+ and npm for JavaScript testing
- MySQL/MariaDB for database testing
- WordPress test environment

### Installation

1. **Install dependencies:**
   ```bash
   # Install PHP dependencies
   composer install --dev
   
   # Install JavaScript dependencies
   npm install
   ```

2. **Setup test environment:**
   ```bash
   # Quick setup with defaults
   ./tests/bin/run-tests.sh --setup-only
   
   # Custom database configuration
   ./tests/bin/install-wp-tests.sh wp_test root password localhost latest
   ```

3. **Run all tests:**
   ```bash
   # Run complete test suite
   ./tests/bin/run-tests.sh
   
   # Run with coverage
   ./tests/bin/run-tests.sh --coverage
   ```

## Test Execution

### Command Line Interface

The test runner script provides comprehensive options:

```bash
# Run all tests
./tests/bin/run-tests.sh

# Run specific test suite
./tests/bin/run-tests.sh --suite unit
./tests/bin/run-tests.sh --suite integration
./tests/bin/run-tests.sh --suite performance

# Run with coverage
./tests/bin/run-tests.sh --coverage

# Run JavaScript tests only
./tests/bin/run-tests.sh --javascript

# Run specific test patterns
./tests/bin/run-tests.sh --filter test_api_handler
./tests/bin/run-tests.sh --group security

# Verbose output
./tests/bin/run-tests.sh --verbose
```

### Composer Scripts

Use Composer for common testing tasks:

```bash
# Basic test execution
composer test
composer test:unit
composer test:integration
composer test:performance

# Code quality checks
composer cs:check          # Check coding standards
composer cs:fix             # Fix coding standards
composer analyze            # Static analysis
composer security:check     # Security audit

# Comprehensive quality check
composer quality
```

### NPM Scripts

JavaScript testing and linting:

```bash
# Run JavaScript tests
npm test
npm run test:watch
npm run test:coverage

# Code quality
npm run lint
npm run lint:fix
```

## Test Configuration

### PHPUnit Configuration

The `phpunit.xml` file defines:
- Test suites organization
- Code coverage settings
- Performance thresholds
- WordPress environment variables

Key configuration options:
```xml
<const name="WP_PLUGIN_FILTERS_PERFORMANCE_THRESHOLD_MS" value="200"/>
<const name="WP_PLUGIN_FILTERS_MAX_MEMORY_MB" value="256"/>
<const name="WP_PLUGIN_FILTERS_MOCK_API" value="true"/>
```

### Jest Configuration

JavaScript testing configuration in `package.json`:
- JSDOM test environment
- WordPress globals simulation
- Coverage thresholds (80% minimum)
- Mock WordPress and jQuery APIs

### Database Configuration

Test database settings in `tests/wp-tests-config.php`:
- Separate test database (never use production!)
- Configurable connection parameters
- Test-specific WordPress constants

## Writing Tests

### PHP Unit Tests

Follow WordPress testing conventions:

```php
class Test_My_Class extends WP_Plugin_Filters_Test_Case {
    
    public function setUp(): void {
        parent::setUp();
        $this->set_current_user('admin');
    }
    
    public function test_my_functionality() {
        $instance = new My_Class();
        $result = $instance->my_method('test_input');
        
        $this->assertIsNotWPError($result);
        $this->assertEquals('expected_output', $result);
    }
}
```

### JavaScript Tests

Use Jest with WordPress mocks:

```javascript
describe('WPPluginFilters', () => {
    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = '<div id="test-container"></div>';
        
        // Reset mocks
        global.$.mockClear();
    });
    
    test('should initialize correctly', () => {
        const wpPluginFilters = window.WPPluginFilters;
        wpPluginFilters.init();
        
        expect(wpPluginFilters.$elements).toBeDefined();
    });
});
```

### Test Data Fixtures

Use helper functions for consistent test data:

```php
// Create mock plugin data
$plugin_data = wp_plugin_filters_create_mock_plugin([
    'slug' => 'test-plugin',
    'rating' => 4.5,
    'active_installs' => 100000
]);

// Mock API responses
$this->mock_api_response('query_plugins', [
    'plugins' => [$plugin_data],
    'info' => ['page' => 1, 'pages' => 1, 'results' => 1]
]);
```

## Test Coverage

### Coverage Requirements

- **Unit Tests**: 80% line coverage minimum
- **Integration Tests**: All WordPress hooks and AJAX endpoints
- **End-to-End Tests**: Critical user journeys
- **Security Tests**: OWASP Top 10 coverage

### Coverage Reports

Generate and view coverage reports:

```bash
# Generate HTML coverage report
./tests/bin/run-tests.sh --coverage

# View coverage report
open tests/coverage/html/index.html

# JavaScript coverage
npm run test:coverage
open tests/coverage/js/lcov-report/index.html
```

## Performance Testing

### Performance Benchmarks

Automated performance thresholds:
- API requests: < 200ms
- Rating calculations: < 10ms per plugin
- Cache operations: < 50ms for 100 items
- Memory usage: < 256MB peak

### Running Performance Tests

```bash
# Run performance test suite
./tests/bin/run-tests.sh --performance

# With memory profiling
php -d memory_limit=512M ./tests/bin/run-tests.sh --performance --verbose
```

## Security Testing

### Security Test Coverage

- SQL injection prevention
- XSS (Cross-Site Scripting) prevention
- CSRF (Cross-Site Request Forgery) protection
- Authentication and authorization
- Input validation and sanitization
- Rate limiting and DoS prevention
- Information disclosure prevention

### Running Security Tests

```bash
# Run security test suite
./tests/bin/run-tests.sh --security

# Additional security checks
composer security:check
```

## Continuous Integration

### GitHub Actions

The `.github/workflows/tests.yml` file defines:
- Multi-PHP version testing (7.4, 8.0, 8.1, 8.2)
- Multi-WordPress version testing (5.8 through latest)
- JavaScript testing across Node.js versions
- Security scanning and static analysis
- Performance benchmarking
- Compatibility testing

### Local CI Simulation

Run CI-like tests locally:

```bash
# Run all tests like CI
./tests/bin/run-tests.sh --cleanup --coverage

# Test against specific WordPress version
./tests/bin/run-tests.sh --wp-version 6.0

# Test with specific database
./tests/bin/run-tests.sh --db-name wp_test_60 --wp-version 6.0
```

## Debugging Tests

### Debug Output

Enable verbose output and debugging:

```bash
# Verbose test execution
./tests/bin/run-tests.sh --verbose

# PHPUnit debug
vendor/bin/phpunit --debug --verbose

# WordPress debug mode
WP_DEBUG=true WP_DEBUG_LOG=true vendor/bin/phpunit
```

### Test Debugging Tools

- WordPress test framework debugging
- Mock API response inspection
- Database query logging
- Performance metrics logging

### Common Issues

1. **Database Connection Errors**
   - Verify MySQL/MariaDB is running
   - Check database credentials
   - Ensure test database exists

2. **API Mock Failures**
   - Verify mock API responses are properly formatted
   - Check for WordPress.org API structure changes
   - Ensure HTTP mocks are correctly configured

3. **JavaScript Test Failures**
   - Check that DOM mocks match WordPress admin structure
   - Verify jQuery and WordPress globals are mocked
   - Ensure test environment matches browser environment

## Best Practices

### Test Organization

1. **Logical grouping** - Organize tests by functionality
2. **Clear naming** - Use descriptive test method names
3. **Single responsibility** - One assertion per test when possible
4. **Setup/teardown** - Proper test isolation and cleanup

### Test Data Management

1. **Use factories** - Create test data with helper functions
2. **Isolation** - Each test should have independent data
3. **Cleanup** - Remove test data after execution
4. **Fixtures** - Use consistent test datasets

### Performance Considerations

1. **Mock external calls** - Don't hit real APIs in tests
2. **Database efficiency** - Use transactions when possible
3. **Parallel execution** - Design tests for parallel running
4. **Resource cleanup** - Prevent memory leaks in long test runs

## Contributing

### Adding New Tests

1. Choose appropriate test type (unit/integration/e2e)
2. Follow existing naming conventions
3. Include both positive and negative test cases
4. Add edge case testing
5. Ensure proper documentation

### Test Review Checklist

- [ ] Tests are isolated and independent
- [ ] All edge cases are covered
- [ ] Performance implications considered
- [ ] Security implications tested
- [ ] Documentation is updated
- [ ] CI/CD pipeline passes

## Troubleshooting

### Environment Issues

**WordPress test environment not found:**
```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
./tests/bin/install-wp-tests.sh wp_test root password localhost latest
```

**Database connection failed:**
```bash
# Check MySQL status
sudo service mysql status

# Test connection
mysql -u root -p -e "SHOW DATABASES;"
```

**Node.js dependencies missing:**
```bash
# Clean install
rm -rf node_modules package-lock.json
npm install
```

### Test Failures

**Mock API responses not working:**
- Verify the `pre_http_request` filter is properly applied
- Check that request URLs match the expected patterns
- Ensure response format matches WordPress.org API structure

**Permission errors:**
- Make sure test scripts are executable (`chmod +x`)
- Verify database user has CREATE/DROP privileges
- Check file system permissions for test directories

## Support

For test-related issues:

1. Check this documentation first
2. Review the test output and error messages
3. Verify your environment meets all prerequisites
4. Check the GitHub Actions runs for working examples
5. Open an issue with detailed error information

Remember: A comprehensive test suite is crucial for maintaining plugin quality and user trust. Invest time in writing good tests—it pays dividends in stability and maintainability.