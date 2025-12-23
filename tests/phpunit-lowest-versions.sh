#!/usr/bin/env bash
# =============================================================================
# PHPUnit Multi-PHP Version Test Runner - Docker-Based (2025 Edition)
# =============================================================================
# Purpose: Test doctrine-firebird-driver against multiple PHP versions
# Features: Docker-based testing, lowest/highest dependency bounds, parallel execution
# Target: Ensure compatibility across PHP 8.1, 8.2, 8.3, 8.4, 8.5
#
# Usage: ./phpunit-lowest-versions.sh [options]
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

# Supported PHP versions (update as new versions release)
PHP_VERSIONS=("8.1" "8.2" "8.3" "8.4")
DEPENDENCY_MODE="prefer-stable"  # prefer-lowest, prefer-stable
RUN_SINGLE_VERSION=""
VERBOSE=false
SKIP_BUILD=false

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
${BOLD}PHPUnit Multi-PHP Version Test Runner - Docker-Based${NC}

${BOLD}Usage:${NC} $SCRIPT_NAME [options]

${BOLD}Options:${NC}
  -p, --php <version>       Run tests for specific PHP version only
                            (8.1|8.2|8.3|8.4|8.5)
  -l, --prefer-lowest       Test with lowest dependency versions
  -s, --prefer-stable       Test with highest stable versions (default)
      --skip-build          Skip Docker image rebuild
      --verbose             Show verbose output
  -h, --help                Show this help message

${BOLD}Environment Variables:${NC}
  CI                        Set to 'true' to skip container cleanup

${BOLD}Examples:${NC}
  $SCRIPT_NAME                    # Test all PHP versions with stable deps
  $SCRIPT_NAME -l                 # Test all PHP versions with lowest deps
  $SCRIPT_NAME -p 8.3             # Test only PHP 8.3
  $SCRIPT_NAME -p 8.3 -l          # Test PHP 8.3 with lowest deps

${BOLD}PHP Versions Tested:${NC}
  • PHP 8.1 (Minimum supported)
  • PHP 8.2
  • PHP 8.3 (Active LTS)
  • PHP 8.4 (Latest stable)
  • PHP 8.5 (Development, optional)

${BOLD}Dependency Modes:${NC}
  --prefer-stable:  Install highest compatible versions (default)
  --prefer-lowest:  Install lowest compatible versions (catch BC breaks)

EOF
    exit 0
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -p|--php)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                RUN_SINGLE_VERSION="$2"
                shift 2
                ;;
            -l|--prefer-lowest)
                DEPENDENCY_MODE="prefer-lowest"
                shift
                ;;
            -s|--prefer-stable)
                DEPENDENCY_MODE="prefer-stable"
                shift
                ;;
            --skip-build)
                SKIP_BUILD=true
                shift
                ;;
            --verbose)
                VERBOSE=true
                shift
                ;;
            -h|--help)
                show_help
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

build_image() {
    local php_version="$1"
    
    if [[ "$SKIP_BUILD" == "true" ]]; then
        print_info "Skipping image build (--skip-build specified)"
        return 0
    fi
    
    print_step "Building Docker image for PHP $php_version..."
    
    if docker compose build --build-arg PHP_VERSION="$php_version" app 2>/dev/null; then
        print_success "Image built for PHP $php_version"
        return 0
    else
        print_error "Failed to build image for PHP $php_version"
        return 1
    fi
}

run_composer_install() {
    local php_version="$1"
    local dep_mode="$2"
    
    print_step "Installing dependencies (--$dep_mode)..."
    
    local composer_flags=""
    if [[ "$dep_mode" == "prefer-lowest" ]]; then
        composer_flags="--prefer-lowest --prefer-stable"
    else
        composer_flags="--prefer-stable"
    fi
    
    if docker compose run --rm app composer update $composer_flags --no-interaction --no-progress; then
        print_success "Dependencies installed for PHP $php_version ($dep_mode)"
        return 0
    else
        print_error "Failed to install dependencies for PHP $php_version"
        return 1
    fi
}

run_phpunit() {
    local php_version="$1"
    
    print_step "Running PHPUnit tests..."
    
    if docker compose run --rm app vendor/bin/phpunit -c tests/phpunit.xml --no-coverage; then
        print_success "Tests passed for PHP $php_version"
        return 0
    else
        print_error "Tests failed for PHP $php_version"
        return 1
    fi
}

wait_for_containers() {
    local timeout="${1:-60}"
    local start_time
    start_time=$(date +%s)
    
    print_info "Waiting for Firebird containers..."
    
    while true; do
        local elapsed
        elapsed=$(($(date +%s) - start_time))
        
        if [[ $elapsed -ge $timeout ]]; then
            print_error "Timeout waiting for containers"
            return 1
        fi
        
        if docker compose ps 2>/dev/null | grep -q "Up"; then
            break
        fi
        
        sleep 2
    done
    
    # Give Firebird time to fully initialize
    sleep 5
    return 0
}

# =============================================================================
# Test Functions
# =============================================================================

test_php_version() {
    local php_version="$1"
    local dep_mode="$2"
    
    print_header "Testing PHP $php_version ($dep_mode dependencies)"
    
    local version_start
    version_start=$(date +%s)
    
    # Export PHP_VERSION for docker compose
    export PHP_VERSION="$php_version"
    
    # Build image
    if ! build_image "$php_version"; then
        return 1
    fi
    
    # Start Firebird containers
    docker compose up -d firebird3 firebird4 firebird5 firebird25
    
    # Wait for containers
    if ! wait_for_containers 60; then
        return 1
    fi
    
    # Install dependencies
    if ! run_composer_install "$php_version" "$dep_mode"; then
        return 1
    fi
    
    # Run tests
    if ! run_phpunit "$php_version"; then
        local version_end
        version_end=$(date +%s)
        print_error "PHP $php_version: FAILED (Duration: $((version_end - version_start))s)"
        return 1
    fi
    
    local version_end
    version_end=$(date +%s)
    print_success "PHP $php_version: PASSED (Duration: $((version_end - version_start))s)"
    return 0
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
    
    print_header "Multi-PHP Version Test Suite"
    
    # Determine which versions to test
    local versions_to_test=()
    if [[ -n "$RUN_SINGLE_VERSION" ]]; then
        versions_to_test=("$RUN_SINGLE_VERSION")
        print_info "Testing single version: PHP $RUN_SINGLE_VERSION"
    else
        versions_to_test=("${PHP_VERSIONS[@]}")
        print_info "Testing versions: ${versions_to_test[*]}"
    fi
    
    print_info "Dependency mode: $DEPENDENCY_MODE"
    echo ""
    
    # Track results
    local passed=()
    local failed=()
    
    # Stop any existing containers
    docker compose down --remove-orphans 2>/dev/null || true
    
    # Test each version
    for php_version in "${versions_to_test[@]}"; do
        if test_php_version "$php_version" "$DEPENDENCY_MODE"; then
            passed+=("$php_version")
        else
            failed+=("$php_version")
        fi
        
        # Clean up between versions
        docker compose down --remove-orphans 2>/dev/null || true
        echo ""
    done
    
    # Calculate duration
    local total_end
    total_end=$(date +%s)
    local total_duration=$((total_end - total_start))
    
    # Summary
    print_header "Test Results Summary"
    
    echo -e "${BOLD}Dependency Mode:${NC} $DEPENDENCY_MODE"
    echo ""
    
    if [[ ${#passed[@]} -gt 0 ]]; then
        echo -e "${GREEN}${BOLD}Passed (${#passed[@]}):${NC}"
        for version in "${passed[@]}"; do
            echo -e "  ${GREEN}✓ PHP $version${NC}"
        done
        echo ""
    fi
    
    if [[ ${#failed[@]} -gt 0 ]]; then
        echo -e "${RED}${BOLD}Failed (${#failed[@]}):${NC}"
        for version in "${failed[@]}"; do
            echo -e "  ${RED}✗ PHP $version${NC}"
        done
        echo ""
    fi
    
    echo -e "${BOLD}Total Duration:${NC} ${total_duration}s"
    echo ""
    
    # Final status
    if [[ ${#failed[@]} -gt 0 ]]; then
        echo -e "${BOLD}${RED}╔═══════════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${BOLD}${RED}║              SOME PHP VERSIONS FAILED!                            ║${NC}"
        echo -e "${BOLD}${RED}╚═══════════════════════════════════════════════════════════════════╝${NC}"
        return 1
    else
        echo -e "${BOLD}${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${BOLD}${GREEN}║              ALL PHP VERSIONS PASSED!                             ║${NC}"
        echo -e "${BOLD}${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
    fi
    
    return 0
}

# Run main function with all arguments
main "$@"
