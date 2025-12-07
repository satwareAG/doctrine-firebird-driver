#!/bin/bash
# =============================================================================
# PHPUnit Test Runner - 2025 State-of-the-Art Edition
# =============================================================================
# Purpose: Run PHPUnit tests with optimal settings and PCOV coverage
# Features: Fast PCOV coverage, multiple Firebird versions, flexible modes
# Target: "First Citizen" quality status - 80%+ coverage target
# =============================================================================

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# Determine the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# =============================================================================
# Default Configuration
# =============================================================================

FIREBIRD_VERSION="3"
WITH_COVERAGE=false
COVERAGE_FORMAT="text"
TESTSUITE=""
FILTER=""
STOP_ON_FAILURE=false
REBUILD_CONTAINER=false
EXTRA_ARGS=""

# =============================================================================
# Command Line Arguments
# =============================================================================

show_help() {
    echo "Usage: $0 [options] [-- <phpunit-args>]"
    echo ""
    echo "Options:"
    echo "  -v, --version <2.5|3|4|5|all>  Firebird version (default: 3)"
    echo "  -c, --coverage                 Enable PCOV code coverage"
    echo "  -f, --format <text|html|clover|all>  Coverage format (default: text)"
    echo "  -s, --suite <unit|integration|functional>  Run specific testsuite"
    echo "  --filter <pattern>             Filter tests by name"
    echo "  --stop-on-failure              Stop on first failure"
    echo "  --rebuild                      Force rebuild of Docker containers"
    echo "  -h, --help                     Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                             # Run Firebird 3 tests"
    echo "  $0 -c                          # Run with coverage"
    echo "  $0 -v all                      # Run all Firebird versions"
    echo "  $0 -s unit                     # Run only unit tests"
    echo "  $0 -c -f html                  # Coverage as HTML report"
    echo "  $0 -- --verbose               # Pass args to PHPUnit"
    exit 0
}

while [[ "$#" -gt 0 ]]; do
    case $1 in
        -v|--version) FIREBIRD_VERSION="$2"; shift ;;
        -c|--coverage) WITH_COVERAGE=true ;;
        -f|--format) COVERAGE_FORMAT="$2"; shift ;;
        -s|--suite) TESTSUITE="$2"; shift ;;
        --filter) FILTER="$2"; shift ;;
        --stop-on-failure) STOP_ON_FAILURE=true ;;
        --rebuild) REBUILD_CONTAINER=true ;;
        -h|--help) show_help ;;
        --) shift; EXTRA_ARGS="$*"; break ;;
        *) echo "Unknown parameter: $1"; show_help ;;
    esac
    shift
done

# =============================================================================
# Helper Functions
# =============================================================================

print_header() {
    echo ""
    echo -e "${BOLD}${BLUE}═══════════════════════════════════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  $1${NC}"
    echo -e "${BOLD}${BLUE}═══════════════════════════════════════════════════════════════════${NC}"
}

print_step() {
    echo -e "${YELLOW}▶ $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${CYAN}ℹ $1${NC}"
}

get_phpunit_config() {
    local version=$1
    case $version in
        "2.5"|"25") echo "tests/phpunit-firebird25.xml" ;;
        "4") echo "tests/phpunit-firebird4.xml" ;;
        "5") echo "tests/phpunit-firebird5.xml" ;;
        *) echo "tests/phpunit.xml" ;;  # Default to Firebird 3
    esac
}

# Track timing
TOTAL_START=$(date +%s)

# =============================================================================
# Docker Environment Setup
# =============================================================================

print_header "Docker Test Environment Setup"

if [ "$REBUILD_CONTAINER" = true ]; then
    print_step "Rebuilding Docker containers (--rebuild specified)..."
    docker compose down --remove-orphans --volumes 2>/dev/null || true
    docker compose build --no-cache
else
    print_step "Starting Docker containers..."
    docker compose down --remove-orphans 2>/dev/null || true
fi

docker compose up -d

# Wait for Firebird to be ready
print_info "Waiting for Firebird containers to initialize..."
sleep 10

# Verify containers are running
print_step "Checking container health..."
if docker compose ps | grep -q "Up"; then
    print_success "Docker containers are running"
else
    print_error "Docker containers failed to start"
    docker compose logs
    exit 1
fi

# =============================================================================
# Install Dependencies
# =============================================================================

print_header "Installing Dependencies"
docker compose run --rm app composer update --prefer-stable
print_success "Dependencies installed"

# =============================================================================
# Build PHPUnit Command
# =============================================================================

build_phpunit_command() {
    local config=$1
    local cmd=""
    
    # Base command with PCOV if coverage enabled
    if [ "$WITH_COVERAGE" = true ]; then
        cmd="php -d pcov.enabled=1 -d pcov.directory=/app/src vendor/bin/phpunit"
        
        # Add coverage options based on format
        case $COVERAGE_FORMAT in
            "html")
                cmd="$cmd --coverage-html=tests/var/coverage/html"
                ;;
            "clover")
                cmd="$cmd --coverage-clover=tests/var/coverage/clover.xml"
                ;;
            "all")
                cmd="$cmd --coverage-text --coverage-html=tests/var/coverage/html --coverage-clover=tests/var/coverage/clover.xml"
                ;;
            *)
                cmd="$cmd --coverage-text"
                ;;
        esac
    else
        cmd="vendor/bin/phpunit --no-coverage"
    fi
    
    # Add config file
    cmd="$cmd -c $config"
    
    # Add testsuite filter
    if [ -n "$TESTSUITE" ]; then
        case $TESTSUITE in
            "unit") cmd="$cmd --testsuite Unit" ;;
            "integration") cmd="$cmd --testsuite Integration" ;;
            "functional") cmd="$cmd --testsuite Functional" ;;
        esac
    fi
    
    # Add test name filter
    if [ -n "$FILTER" ]; then
        cmd="$cmd --filter '$FILTER'"
    fi
    
    # Add stop on failure
    if [ "$STOP_ON_FAILURE" = true ]; then
        cmd="$cmd --stop-on-failure"
    fi
    
    # Add extra arguments
    if [ -n "$EXTRA_ARGS" ]; then
        cmd="$cmd $EXTRA_ARGS"
    fi
    
    echo "$cmd"
}

run_tests_for_version() {
    local version=$1
    local config=$(get_phpunit_config "$version")
    local cmd=$(build_phpunit_command "$config")
    
    print_step "Running tests for Firebird $version..."
    print_info "Config: $config"
    print_info "Command: $cmd"
    
    if docker compose run --rm app bash -c "$cmd"; then
        print_success "Firebird $version: PASSED"
        return 0
    else
        print_error "Firebird $version: FAILED"
        return 1
    fi
}

# =============================================================================
# Run Tests
# =============================================================================

print_header "Running PHPUnit Tests"

# Create output directories
docker compose run --rm app mkdir -p tests/var/coverage tests/var/logs tests/var/reports

FAILED=false

if [ "$FIREBIRD_VERSION" = "all" ]; then
    # Run tests against all Firebird versions
    print_info "Running tests against all Firebird versions..."
    
    # Run with coverage only on Firebird 3 (primary)
    ORIGINAL_COVERAGE=$WITH_COVERAGE
    
    # Firebird 3 (with coverage if enabled)
    print_header "Firebird 3 (Primary)"
    run_tests_for_version "3" || FAILED=true
    
    # Other versions (no coverage for speed)
    WITH_COVERAGE=false
    
    print_header "Firebird 2.5"
    run_tests_for_version "2.5" || FAILED=true
    
    print_header "Firebird 4"
    run_tests_for_version "4" || FAILED=true
    
    print_header "Firebird 5"
    run_tests_for_version "5" || FAILED=true
    
    WITH_COVERAGE=$ORIGINAL_COVERAGE
else
    # Run tests for single version
    run_tests_for_version "$FIREBIRD_VERSION" || FAILED=true
fi

# =============================================================================
# Cleanup
# =============================================================================

print_header "Cleanup"
print_step "Stopping Docker containers..."
docker compose down

# =============================================================================
# Summary
# =============================================================================

TOTAL_END=$(date +%s)
TOTAL_DURATION=$((TOTAL_END - TOTAL_START))

echo ""
if [ "$FAILED" = true ]; then
    echo -e "${BOLD}${RED}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${RED}║                     SOME TESTS FAILED!                            ║${NC}"
    echo -e "${BOLD}${RED}╚═══════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BOLD}Total Duration:${NC} ${TOTAL_DURATION}s"
    exit 1
else
    echo -e "${BOLD}${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BOLD}${GREEN}║                    ALL TESTS PASSED!                              ║${NC}"
    echo -e "${BOLD}${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BOLD}Total Duration:${NC} ${TOTAL_DURATION}s"
fi

echo ""

# Show coverage report location if generated
if [ "$WITH_COVERAGE" = true ]; then
    echo -e "${BOLD}Coverage Reports:${NC}"
    case $COVERAGE_FORMAT in
        "html"|"all")
            echo -e "  HTML:   tests/var/coverage/html/index.html"
            ;;
    esac
    case $COVERAGE_FORMAT in
        "clover"|"all")
            echo -e "  Clover: tests/var/coverage/clover.xml"
            ;;
    esac
    echo ""
fi

echo -e "${GREEN}All tests executed${NC}"
