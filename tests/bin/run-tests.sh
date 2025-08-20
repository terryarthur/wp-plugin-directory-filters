#!/usr/bin/env bash

# WordPress Plugin Directory Filters Test Runner
# Comprehensive test execution script for local development and CI/CD

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default configuration
DEFAULT_WP_VERSION="latest"
DEFAULT_PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
DEFAULT_DB_NAME="wp_plugin_filters_test"
DEFAULT_DB_USER="root"
DEFAULT_DB_PASS=""
DEFAULT_DB_HOST="localhost"

# Parse command line arguments
PHPUNIT_ARGS=""
COVERAGE=false
VERBOSE=false
SUITE=""
SETUP_ONLY=false
CLEANUP=false
PARALLEL=false
PERFORMANCE=false
SECURITY=false
JAVASCRIPT=false
ALL_TESTS=true

usage() {
    cat << EOF
WordPress Plugin Directory Filters Test Runner

USAGE:
    $0 [OPTIONS]

OPTIONS:
    -h, --help              Show this help message
    -s, --suite SUITE       Run specific test suite (unit|integration|e2e|performance|security|compatibility)
    -c, --coverage          Generate code coverage report
    -v, --verbose           Enable verbose output
    -p, --parallel          Run tests in parallel (where supported)
    --setup-only            Only setup test environment, don't run tests
    --cleanup               Clean up test environment after running
    --wp-version VERSION    WordPress version to test against (default: $DEFAULT_WP_VERSION)
    --php-version VERSION   PHP version (for information only, default: $DEFAULT_PHP_VERSION)
    --db-name NAME          Test database name (default: $DEFAULT_DB_NAME)
    --db-user USER          Test database user (default: $DEFAULT_DB_USER)
    --db-pass PASS          Test database password (default: empty)
    --db-host HOST          Test database host (default: $DEFAULT_DB_HOST)
    --performance           Run performance tests only
    --security              Run security tests only
    --javascript            Run JavaScript tests only
    --filter FILTER         PHPUnit filter pattern
    --group GROUP           PHPUnit group

EXAMPLES:
    $0                      Run all tests
    $0 -s unit -c           Run unit tests with coverage
    $0 --performance -v     Run performance tests with verbose output
    $0 --setup-only         Setup test environment only
    $0 --javascript         Run JavaScript tests only
    $0 --filter test_api    Run tests matching 'test_api'
    $0 --group security     Run tests in 'security' group

EOF
}

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}✓${NC} $1"
}

warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1" >&2
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            usage
            exit 0
            ;;
        -s|--suite)
            SUITE="$2"
            ALL_TESTS=false
            shift 2
            ;;
        -c|--coverage)
            COVERAGE=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -p|--parallel)
            PARALLEL=true
            shift
            ;;
        --setup-only)
            SETUP_ONLY=true
            shift
            ;;
        --cleanup)
            CLEANUP=true
            shift
            ;;
        --wp-version)
            WP_VERSION="$2"
            shift 2
            ;;
        --php-version)
            PHP_VERSION="$2"
            shift 2
            ;;
        --db-name)
            DB_NAME="$2"
            shift 2
            ;;
        --db-user)
            DB_USER="$2"
            shift 2
            ;;
        --db-pass)
            DB_PASS="$2"
            shift 2
            ;;
        --db-host)
            DB_HOST="$2"
            shift 2
            ;;
        --performance)
            PERFORMANCE=true
            ALL_TESTS=false
            shift
            ;;
        --security)
            SECURITY=true
            ALL_TESTS=false
            shift
            ;;
        --javascript)
            JAVASCRIPT=true
            ALL_TESTS=false
            shift
            ;;
        --filter)
            PHPUNIT_ARGS="$PHPUNIT_ARGS --filter $2"
            shift 2
            ;;
        --group)
            PHPUNIT_ARGS="$PHPUNIT_ARGS --group $2"
            shift 2
            ;;
        *)
            error "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Set defaults
WP_VERSION=${WP_VERSION:-$DEFAULT_WP_VERSION}
PHP_VERSION=${PHP_VERSION:-$DEFAULT_PHP_VERSION}
DB_NAME=${DB_NAME:-$DEFAULT_DB_NAME}
DB_USER=${DB_USER:-$DEFAULT_DB_USER}
DB_PASS=${DB_PASS:-$DEFAULT_DB_PASS}
DB_HOST=${DB_HOST:-$DEFAULT_DB_HOST}

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

log "WordPress Plugin Directory Filters Test Runner"
log "Project Directory: $PROJECT_DIR"
log "PHP Version: $PHP_VERSION"
log "WordPress Version: $WP_VERSION"
log "Database: $DB_USER@$DB_HOST/$DB_NAME"

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check PHP
    if ! command -v php &> /dev/null; then
        error "PHP is not installed or not in PATH"
        exit 1
    fi
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        error "Composer is not installed or not in PATH"
        exit 1
    fi
    
    # Check MySQL/MariaDB
    if ! command -v mysql &> /dev/null; then
        warning "MySQL client not found - database tests may fail"
    fi
    
    # Check Node.js for JavaScript tests
    if [[ $JAVASCRIPT == true || $ALL_TESTS == true ]]; then
        if ! command -v node &> /dev/null; then
            error "Node.js is required for JavaScript tests"
            exit 1
        fi
        
        if ! command -v npm &> /dev/null; then
            error "npm is required for JavaScript tests"
            exit 1
        fi
    fi
    
    success "Prerequisites check passed"
}

# Setup test environment
setup_environment() {
    log "Setting up test environment..."
    
    cd "$PROJECT_DIR"
    
    # Install Composer dependencies
    if [[ ! -d "vendor" ]] || [[ "composer.lock" -nt "vendor" ]]; then
        log "Installing Composer dependencies..."
        composer install --dev --no-interaction --prefer-dist
    fi
    
    # Install Node.js dependencies for JavaScript tests
    if [[ $JAVASCRIPT == true || $ALL_TESTS == true ]]; then
        if [[ ! -d "node_modules" ]] || [[ "package-lock.json" -nt "node_modules" ]]; then
            log "Installing Node.js dependencies..."
            npm ci
        fi
    fi
    
    # Setup WordPress test environment
    log "Setting up WordPress test environment..."
    bash "$SCRIPT_DIR/install-wp-tests.sh" "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION"
    
    success "Test environment setup completed"
}

# Run PHPUnit tests
run_phpunit_tests() {
    local suite_arg=""
    local coverage_arg=""
    local verbose_arg=""
    
    if [[ -n "$1" ]]; then
        suite_arg="--testsuite=\"$1\""
    fi
    
    if [[ $COVERAGE == true ]]; then
        coverage_arg="--coverage-html tests/coverage/html --coverage-clover tests/coverage/clover.xml"
    fi
    
    if [[ $VERBOSE == true ]]; then
        verbose_arg="--verbose"
    fi
    
    log "Running PHPUnit tests..."
    
    cd "$PROJECT_DIR"
    
    local cmd="vendor/bin/phpunit --configuration phpunit.xml $suite_arg $coverage_arg $verbose_arg $PHPUNIT_ARGS"
    
    if [[ $VERBOSE == true ]]; then
        log "Executing: $cmd"
    fi
    
    eval $cmd
}

# Run JavaScript tests
run_javascript_tests() {
    log "Running JavaScript tests..."
    
    cd "$PROJECT_DIR"
    
    # Lint JavaScript
    log "Running ESLint..."
    npm run lint
    
    # Run Jest tests
    log "Running Jest tests..."
    if [[ $COVERAGE == true ]]; then
        npm run test:coverage
    else
        npm test
    fi
    
    success "JavaScript tests completed"
}

# Run performance tests
run_performance_tests() {
    log "Running performance tests..."
    
    # Set memory limit for performance tests
    export WP_PLUGIN_FILTERS_PERFORMANCE_THRESHOLD_MS=200
    export WP_PLUGIN_FILTERS_MAX_MEMORY_MB=256
    
    run_phpunit_tests "Performance Tests"
    
    success "Performance tests completed"
}

# Run security tests
run_security_tests() {
    log "Running security tests..."
    
    run_phpunit_tests "Security Tests"
    
    # Additional security checks
    if command -v composer &> /dev/null; then
        log "Checking for security vulnerabilities..."
        composer audit || warning "Security audit found issues"
    fi
    
    success "Security tests completed"
}

# Run specific test suite
run_test_suite() {
    case $1 in
        unit)
            run_phpunit_tests "Unit Tests"
            ;;
        integration)
            run_phpunit_tests "Integration Tests"
            ;;
        e2e)
            run_phpunit_tests "End-to-End Tests"
            ;;
        performance)
            run_performance_tests
            ;;
        security)
            run_security_tests
            ;;
        compatibility)
            run_phpunit_tests "Compatibility Tests"
            ;;
        *)
            error "Unknown test suite: $1"
            error "Available suites: unit, integration, e2e, performance, security, compatibility"
            exit 1
            ;;
    esac
}

# Generate test report
generate_report() {
    log "Generating test report..."
    
    local report_dir="$PROJECT_DIR/tests/reports"
    mkdir -p "$report_dir"
    
    local report_file="$report_dir/test-report-$(date +%Y%m%d-%H%M%S).html"
    
    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>WordPress Plugin Directory Filters - Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #0073aa; color: white; padding: 20px; margin-bottom: 20px; }
        .section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .warning { background: #fff3cd; border-color: #ffeaa7; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .info { background: #d1ecf1; border-color: #bee5eb; }
    </style>
</head>
<body>
    <div class="header">
        <h1>WordPress Plugin Directory Filters - Test Report</h1>
        <p>Generated on: $(date)</p>
        <p>WordPress Version: $WP_VERSION</p>
        <p>PHP Version: $PHP_VERSION</p>
    </div>
    
    <div class="section info">
        <h2>Test Configuration</h2>
        <ul>
            <li>Database: $DB_USER@$DB_HOST/$DB_NAME</li>
            <li>Coverage: $([ $COVERAGE == true ] && echo "Enabled" || echo "Disabled")</li>
            <li>Verbose: $([ $VERBOSE == true ] && echo "Enabled" || echo "Disabled")</li>
        </ul>
    </div>
    
    <div class="section">
        <h2>Test Results</h2>
        <p>Test execution completed. Check individual test outputs for detailed results.</p>
    </div>
</body>
</html>
EOF
    
    success "Test report generated: $report_file"
}

# Cleanup function
cleanup_environment() {
    if [[ $CLEANUP == true ]]; then
        log "Cleaning up test environment..."
        
        # Remove test database
        if command -v mysql &> /dev/null; then
            mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null || true
        fi
        
        # Clean up temporary files
        rm -rf /tmp/wordpress-tests-lib 2>/dev/null || true
        rm -rf /tmp/wordpress 2>/dev/null || true
        
        success "Cleanup completed"
    fi
}

# Trap cleanup on exit
trap cleanup_environment EXIT

# Main execution
main() {
    check_prerequisites
    setup_environment
    
    if [[ $SETUP_ONLY == true ]]; then
        success "Test environment setup completed. Exiting as requested."
        exit 0
    fi
    
    local start_time=$(date +%s)
    
    # Run tests based on options
    if [[ $JAVASCRIPT == true ]]; then
        run_javascript_tests
    elif [[ $PERFORMANCE == true ]]; then
        run_performance_tests
    elif [[ $SECURITY == true ]]; then
        run_security_tests
    elif [[ -n "$SUITE" ]]; then
        run_test_suite "$SUITE"
    elif [[ $ALL_TESTS == true ]]; then
        log "Running all test suites..."
        
        # Run JavaScript tests first
        run_javascript_tests
        
        # Run PHP test suites
        run_phpunit_tests "Unit Tests"
        run_phpunit_tests "Integration Tests"
        run_phpunit_tests "End-to-End Tests"
        run_performance_tests
        run_security_tests
        run_phpunit_tests "Compatibility Tests"
        
        success "All test suites completed"
    fi
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    log "Test execution completed in ${duration} seconds"
    
    # Generate coverage report if enabled
    if [[ $COVERAGE == true ]]; then
        log "Coverage report available at: tests/coverage/html/index.html"
    fi
    
    generate_report
    success "All tests completed successfully!"
}

# Run main function
main "$@"