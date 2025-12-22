#!/usr/bin/env bash
# =============================================================================
# PHPUnit Test Runner - Docker-Based Testing (2025 Edition)
# =============================================================================
# Purpose: Run PHPUnit tests in Docker with optimal settings and PCOV coverage
# Features: Fast PCOV coverage, multiple Firebird versions, flexible modes
# Target: "First Citizen" quality status - 80%+ coverage target
#
# Usage: ./phpunit.sh [options] [-- <phpunit-args>]
#
# shellcheck disable=SC2034  # Unused variables are for configuration
# =============================================================================

set -euo pipefail

# Enable BuildKit for Docker builds
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1

# =============================================================================
# Constants and Colors
# =============================================================================

readonly SCRIPT_NAME="$(basename "$0")"
readonly SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
readonly PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Colors for output (check if terminal supports colors)
if [[ -t 1 ]] && command -v tput &>/dev/null && [[ $(tput colors) -ge 8 ]]; then
    readonly RED='\033[0;31m'
    readonly GREEN='\033[0;32m'
    readonly YELLOW='\033[1;33m'
    readonly BLUE='\033[0;34m'
    readonly CYAN='\033[0;36m'
    readonly NC='\033[0m'
    readonly BOLD='\033[1m'
else
    readonly RED=''
    readonly GREEN=''
    readonly YELLOW=''
    readonly BLUE=''
    readonly CYAN=''
    readonly NC=''
    readonly BOLD=''
fi

# =============================================================================
# Default Configuration
# =============================================================================

PHP_VERSION="${PHP_VERSION:-8.1}"
FIREBIRD_VERSION="3"
WITH_COVERAGE=false
COVERAGE_FORMAT="text"
TESTSUITE=""
FILTER=""
STOP_ON_FAILURE=false
REBUILD_CONTAINER=false
EXTRA_ARGS=""
VERBOSE=false

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
    echo -e "${RED}✗ $1${NC}" >&2
}

print_info() {
    echo -e "${CYAN}ℹ $1${NC}"
}

die() {
    print_error "$1"
    exit "${2:-1}"
}

# Cleanup function for proper exit handling
cleanup() {
    local exit_code=$?
    if [[ $exit_code -ne 0 ]]; then
        print_error "Script failed with exit code: $exit_code"
    fi
    # Stop containers on exit (unless in CI where we want to preserve logs)
    if [[ "${CI:-false}" != "true" ]]; then
        cd "$SCRIPT_DIR"
        docker compose down --remove-orphans 2>/dev/null || true
    fi
    exit $exit_code
}

trap cleanup EXIT

# =============================================================================
# Command Line Arguments
# =============================================================================

show_help() {
    cat << EOF
${BOLD}PHPUnit Test Runner - Docker-Based Testing${NC}

${BOLD}Usage:${NC} $SCRIPT_NAME [options] [-- <phpunit-args>]

${BOLD}Options:${NC}
  -p, --php <version>         PHP version (8.1|8.2|8.3|8.4|8.5, default: 8.1)
  -v, --version <version>     Firebird version (2.5|3|4|5|all, default: 3)
  -c, --coverage              Enable PCOV code coverage
  -f, --format <format>       Coverage format (text|html|clover|all, default: text)
  -s, --suite <suite>         Run specific testsuite (unit|integration|functional)
      --filter <pattern>      Filter tests by name
      --stop-on-failure       Stop on first failure
      --rebuild               Force rebuild of Docker containers
      --verbose               Show verbose output
  -h, --help                  Show this help message

${BOLD}Environment Variables:${NC}
  PHP_VERSION                 Override PHP version (default: 8.1)
  CI                          Set to 'true' to skip container cleanup

${BOLD}Examples:${NC}
  $SCRIPT_NAME                            # Run Firebird 3 tests (PHP 8.1)
  $SCRIPT_NAME -p 8.3                     # Run with PHP 8.3
  $SCRIPT_NAME -p 8.3 -c                  # PHP 8.3 with coverage
  $SCRIPT_NAME -v all                     # Run all Firebird versions
  $SCRIPT_NAME -s unit                    # Run only unit tests
  $SCRIPT_NAME -c -f html                 # Coverage as HTML report
  $SCRIPT_NAME -- --verbose               # Pass args to PHPUnit

${BOLD}Coverage Reports:${NC}
  HTML:   tests/var/coverage/html/index.html
  Clover: tests/var/coverage/clover.xml
  Text:   Printed to stdout

EOF
    exit 0
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -p|--php)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                PHP_VERSION="$2"
                shift 2
                ;;
            -v|--version)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                FIREBIRD_VERSION="$2"
                shift 2
                ;;
            -c|--coverage)
                WITH_COVERAGE=true
                shift
                ;;
            -f|--format)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                COVERAGE_FORMAT="$2"
                shift 2
                ;;
            -s|--suite)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                TESTSUITE="$2"
                shift 2
                ;;
            --filter)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                FILTER="$2"
                shift 2
                ;;
            --stop-on-failure)
                STOP_ON_FAILURE=true
                shift
                ;;
            --rebuild)
                REBUILD_CONTAINER=true
                shift
                ;;
            --verbose)
                VERBOSE=true
                shift
                ;;
            -h|--help)
                show_help
                ;;
            --)
                shift
                EXTRA_ARGS="$*"
                break
                ;;
            -*)
                die "Unknown option: $1"
                ;;
            *)
                die "Unknown argument: $1"
                ;;
        esac
    done
}

# =============================================================================
# Docker Functions
# =============================================================================

get_phpunit_config() {
    local version="$1"
    case "$version" in
        "2.5"|"25") echo "tests/phpunit-firebird25.xml" ;;
        "4")        echo "tests/phpunit-firebird4.xml" ;;
        "5")        echo "tests/phpunit-firebird5.xml" ;;
        *)          echo "tests/phpunit.xml" ;;  # Default to Firebird 3
    esac
}

wait_for_containers() {
    local timeout="${1:-60}"
    local start_time
    start_time=$(date +%s)
    
    print_info "Waiting for containers to be healthy (timeout: ${timeout}s)..."
    
    while true; do
        local elapsed
        elapsed=$(($(date +%s) - start_time))
        
        if [[ $elapsed -ge $timeout ]]; then
            print_error "Timeout waiting for containers"
            docker compose ps
            docker compose logs --tail=50
            return 1
        fi
        
        # Check if at least firebird3 is ready (primary test target)
        if docker compose ps --format json 2>/dev/null | grep -q '"Status":"running"' || \
           docker compose ps 2>/dev/null | grep -q "Up"; then
            break
        fi
        
        sleep 2
    done
    
    # Give Firebird a bit more time to fully initialize
    sleep 5
    return 0
}

build_phpunit_command() {
    local config="$1"
    local cmd=""
    
    # Base command with PCOV if coverage enabled
    if [[ "$WITH_COVERAGE" == "true" ]]; then
        cmd="php -d pcov.enabled=1 -d pcov.directory=/app/src vendor/bin/phpunit"
        
        # Add coverage options based on format
        case "$COVERAGE_FORMAT" in
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
    if [[ -n "$TESTSUITE" ]]; then
        case "$TESTSUITE" in
            "unit")        cmd="$cmd --testsuite Unit" ;;
            "integration") cmd="$cmd --testsuite Integration" ;;
            "functional")  cmd="$cmd --testsuite Functional" ;;
        esac
    fi
    
    # Add test name filter
    if [[ -n "$FILTER" ]]; then
        cmd="$cmd --filter '$FILTER'"
    fi
    
    # Add stop on failure
    if [[ "$STOP_ON_FAILURE" == "true" ]]; then
        cmd="$cmd --stop-on-failure"
    fi
    
    # Add extra arguments
    if [[ -n "$EXTRA_ARGS" ]]; then
        cmd="$cmd $EXTRA_ARGS"
    fi
    
    echo "$cmd"
}

run_tests_for_version() {
    local version="$1"
    local config
    config=$(get_phpunit_config "$version")
    local cmd
    cmd=$(build_phpunit_command "$config")
    
    print_step "Running tests for Firebird $version..."
    print_info "Config: $config"
    [[ "$VERBOSE" == "true" ]] && print_info "Command: $cmd"
    
    if docker compose run --rm app bash -c "$cmd"; then
        print_success "Firebird $version: PASSED"
        return 0
    else
        print_error "Firebird $version: FAILED"
        return 1
    fi
}

# =============================================================================
# Main Execution
# =============================================================================

main() {
    parse_args "$@"
    
    # Change to script directory for docker compose context
    cd "$SCRIPT_DIR"
    
    # Track timing
    local total_start
    total_start=$(date +%s)
    
    # Export PHP_VERSION for docker compose build args
    export PHP_VERSION
    
    print_header "Docker Test Environment Setup (PHP $PHP_VERSION)"
    
    # Setup Docker environment
    if [[ "$REBUILD_CONTAINER" == "true" ]]; then
        print_step "Rebuilding Docker containers (--rebuild specified)..."
        docker compose down --remove-orphans --volumes 2>/dev/null || true
        docker compose build --no-cache --build-arg PHP_VERSION="$PHP_VERSION"
    else
        print_step "Starting Docker containers with PHP $PHP_VERSION..."
        docker compose down --remove-orphans 2>/dev/null || true
        docker compose build --build-arg PHP_VERSION="$PHP_VERSION"
    fi
    
    docker compose up -d
    
    # Wait for containers
    wait_for_containers 60 || die "Failed to start containers"
    
    # Verify containers are running
    print_step "Checking container health..."
    if docker compose ps 2>/dev/null | grep -q "Up"; then
        print_success "Docker containers are running"
    else
        print_error "Docker containers failed to start"
        docker compose logs
        die "Container startup failed"
    fi
    
    # Install dependencies
    print_header "Installing Dependencies"
    docker compose run --rm app composer update --prefer-stable || die "Composer update failed"
    print_success "Dependencies installed"
    
    # Create output directories
    docker compose run --rm app mkdir -p tests/var/coverage tests/var/logs tests/var/reports
    
    # Run tests
    print_header "Running PHPUnit Tests"
    
    local failed=false
    
    if [[ "$FIREBIRD_VERSION" == "all" ]]; then
        # Run tests against all Firebird versions
        print_info "Running tests against all Firebird versions..."
        
        # Run with coverage only on Firebird 3 (primary)
        local original_coverage="$WITH_COVERAGE"
        
        # Firebird 3 (with coverage if enabled)
        print_header "Firebird 3 (Primary)"
        run_tests_for_version "3" || failed=true
        
        # Other versions (no coverage for speed)
        WITH_COVERAGE=false
        
        print_header "Firebird 2.5"
        run_tests_for_version "2.5" || failed=true
        
        print_header "Firebird 4"
        run_tests_for_version "4" || failed=true
        
        print_header "Firebird 5"
        run_tests_for_version "5" || failed=true
        
        WITH_COVERAGE="$original_coverage"
    else
        # Run tests for single version
        run_tests_for_version "$FIREBIRD_VERSION" || failed=true
    fi
    
    # Calculate duration
    local total_end
    total_end=$(date +%s)
    local total_duration=$((total_end - total_start))
    
    # Summary
    echo ""
    if [[ "$failed" == "true" ]]; then
        echo -e "${BOLD}${RED}╔═══════════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${BOLD}${RED}║                     SOME TESTS FAILED!                            ║${NC}"
        echo -e "${BOLD}${RED}╚═══════════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        echo -e "${BOLD}Total Duration:${NC} ${total_duration}s"
        return 1
    else
        echo -e "${BOLD}${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${BOLD}${GREEN}║                    ALL TESTS PASSED!                              ║${NC}"
        echo -e "${BOLD}${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        echo -e "${BOLD}Total Duration:${NC} ${total_duration}s"
    fi
    
    echo ""
    
    # Show coverage report location if generated
    if [[ "$WITH_COVERAGE" == "true" ]]; then
        echo -e "${BOLD}Coverage Reports:${NC}"
        case "$COVERAGE_FORMAT" in
            "html"|"all")
                echo -e "  HTML:   tests/var/coverage/html/index.html"
                ;;&
            "clover"|"all")
                echo -e "  Clover: tests/var/coverage/clover.xml"
                ;;
        esac
        echo ""
    fi
    
    echo -e "${GREEN}All tests executed${NC}"
    return 0
}

# Run main function with all arguments
main "$@"
