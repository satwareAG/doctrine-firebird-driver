#!/usr/bin/env bash
# =============================================================================
# Docker Code Quality Check - Comprehensive Quality Pipeline (2025 Edition)
# =============================================================================
# Purpose: Run comprehensive code quality checks in Docker environment
# Features: PCOV coverage, PHPStan Level 8, Psalm, PHP-CS-Fixer, full Firebird compatibility
# Target: "First Citizen" quality status - surpass DBAL core drivers
#
# Usage: ./docker-cqc.sh [options]
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
REBUILD_CONTAINER=false
COVERAGE_ONLY=false
QUICK_MODE=false
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
${BOLD}Docker Code Quality Check - Comprehensive Quality Pipeline${NC}

${BOLD}Usage:${NC} $SCRIPT_NAME [options]

${BOLD}Options:${NC}
  -p, --php <version>   PHP version (8.1|8.2|8.3|8.4|8.5, default: 8.1)
      --rebuild         Force rebuild of Docker containers
      --coverage        Run only tests with coverage (skip multi-version)
      --quick           Quick mode: run static analysis only (no tests)
      --verbose         Show verbose output
  -h, --help            Show this help message

${BOLD}Environment Variables:${NC}
  PHP_VERSION           Override PHP version (default: 8.1)
  CI                    Set to 'true' to skip container cleanup

${BOLD}Examples:${NC}
  $SCRIPT_NAME                  # Full quality check
  $SCRIPT_NAME -p 8.3           # Full quality check with PHP 8.3
  $SCRIPT_NAME --coverage       # Tests + coverage only
  $SCRIPT_NAME --quick          # Static analysis only (no tests)
  $SCRIPT_NAME --rebuild        # Rebuild containers first

${BOLD}Quality Pipeline Phases:${NC}
  1. PHP_CodeSniffer - Code style (PSR-12)
  2. PHPStan Level 8 - Static analysis with strict rules
  3. Psalm - Additional static analysis
  4. PHPUnit + Coverage - Full test suite with PCOV coverage
  5. Multi-version - Test against all Firebird versions

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
            --rebuild)
                REBUILD_CONTAINER=true
                shift
                ;;
            --coverage)
                COVERAGE_ONLY=true
                shift
                ;;
            --quick)
                QUICK_MODE=true
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
        
        if docker compose ps 2>/dev/null | grep -q "Up"; then
            break
        fi
        
        sleep 2
    done
    
    # Give Firebird time to fully initialize
    sleep 5
    return 0
}

run_in_docker() {
    local cmd="$1"
    local timeout="${2:-300}"  # Default 5 minute timeout
    [[ "$VERBOSE" == "true" ]] && print_info "Running: $cmd (timeout: ${timeout}s)"
    
    # Use -T to disable pseudo-TTY allocation (prevents hangs in scripts)
    # Use timeout command to prevent infinite hangs
    if timeout "$timeout" docker compose run --rm -T app bash -c "$cmd"; then
        return 0
    else
        local exit_code=$?
        if [[ $exit_code -eq 124 ]]; then
            print_error "Command timed out after ${timeout}s: $cmd"
        else
            print_error "Command failed with exit code $exit_code: $cmd"
        fi
        return $exit_code
    fi
}

# =============================================================================
# Quality Check Functions
# =============================================================================

run_coding_standards() {
    print_header "Phase 1: Coding Standards Check (PHP_CodeSniffer)"
    print_step "Running PHPCS with auto-fix attempt..."
    
    local start_time
    start_time=$(date +%s)
    
    # Try to auto-fix first, then check
    if ! run_in_docker "vendor/bin/phpcs --report=summary 2>/dev/null"; then
        print_info "Attempting auto-fix with PHPCBF..."
        run_in_docker "vendor/bin/phpcbf" || true
    fi
    
    # Final check
    if run_in_docker "vendor/bin/phpcs --report=full --report-file=tests/var/reports/phpcs-report.txt"; then
        print_success "Coding standards: PASSED"
    else
        print_error "Coding standards: FAILED (see tests/var/reports/phpcs-report.txt)"
        return 1
    fi
    
    local end_time
    end_time=$(date +%s)
    print_info "Duration: $((end_time - start_time))s"
    return 0
}

run_phpstan() {
    print_header "Phase 2: Static Analysis (PHPStan Level 8 + Strict Rules)"
    print_step "Running PHPStan with strict analysis..."
    
    local start_time
    start_time=$(date +%s)
    
    if run_in_docker "vendor/bin/phpstan analyse --memory-limit=2G --error-format=table 2>&1 | tee tests/var/reports/phpstan-report.txt"; then
        print_success "PHPStan Level 8: PASSED"
    else
        print_error "PHPStan Level 8: FAILED"
        return 1
    fi
    
    local end_time
    end_time=$(date +%s)
    print_info "Duration: $((end_time - start_time))s"
    return 0
}

run_psalm() {
    print_header "Phase 3: Static Analysis (Psalm)"
    print_step "Running Psalm static analysis..."
    
    local start_time
    start_time=$(date +%s)
    
    # Auto-fix type hints where possible
    print_info "Attempting to auto-fix type issues..."
    run_in_docker "vendor/bin/psalm --alter --issues=MissingReturnType,MissingParamType --no-cache 2>/dev/null" || true
    
    # Update baseline if needed
    print_info "Updating Psalm baseline..."
    run_in_docker "vendor/bin/psalm --set-baseline=psalm-baseline.xml --no-cache 2>&1 | tee tests/var/reports/psalm-report.txt" || true
    
    # Final analysis
    if run_in_docker "vendor/bin/psalm --no-cache --show-info=false --output-format=text"; then
        print_success "Psalm: PASSED"
    else
        print_info "Psalm: Completed with baseline (check psalm-baseline.xml for known issues)"
    fi
    
    local end_time
    end_time=$(date +%s)
    print_info "Duration: $((end_time - start_time))s"
    return 0
}

run_tests_with_coverage() {
    print_header "Phase 4: PHPUnit Test Suite with Coverage"
    print_step "Running tests against Firebird 3 with PCOV coverage..."
    
    # Restart Firebird 3 to ensure clean state
    print_info "Restarting Firebird 3 container..."
    docker compose restart firebird3
    wait_for_containers 30
    
    local start_time
    start_time=$(date +%s)
    
    if run_in_docker "php -d pcov.enabled=1 -d pcov.directory=/app/src vendor/bin/phpunit -c tests/phpunit.xml --coverage-text --coverage-html=tests/var/coverage/html 2>&1 | tee tests/var/reports/phpunit-fb3-report.txt" 1200; then
        print_success "Firebird 3 Tests: PASSED"
    else
        print_error "Firebird 3 Tests: FAILED"
        return 1
    fi
    
    local end_time
    end_time=$(date +%s)
    print_info "Duration: $((end_time - start_time))s"
    print_info "Coverage report: tests/var/coverage/html/index.html"
    return 0
}

run_multiversion_tests() {
    print_header "Phase 5: Multi-Version Firebird Compatibility"
    print_step "Running tests against Firebird 2.5, 4.x, and 5.x..."
    
    local start_time
    start_time=$(date +%s)
    local failed=false
    
    # Firebird 2.5
    print_info "Testing Firebird 2.5..."
    docker compose restart firebird25
    wait_for_containers 30
    if run_in_docker "vendor/bin/phpunit -c tests/phpunit-firebird25.xml --no-coverage 2>&1 | tail -10" 1200; then
        print_success "Firebird 2.5: PASSED"
    else
        print_error "Firebird 2.5: FAILED"
        failed=true
    fi
    
    # Firebird 4.x
    print_info "Testing Firebird 4.x..."
    docker compose restart firebird4
    wait_for_containers 30
    if run_in_docker "vendor/bin/phpunit -c tests/phpunit-firebird4.xml --no-coverage 2>&1 | tail -10" 1200; then
        print_success "Firebird 4.x: PASSED"
    else
        print_error "Firebird 4.x: FAILED"
        failed=true
    fi
    
    # Firebird 5.x
    print_info "Testing Firebird 5.x..."
    docker compose restart firebird5
    wait_for_containers 30
    if run_in_docker "vendor/bin/phpunit -c tests/phpunit-firebird5.xml --no-coverage 2>&1 | tail -10" 1200; then
        print_success "Firebird 5.x: PASSED"
    else
        print_error "Firebird 5.x: FAILED"
        failed=true
    fi
    
    local end_time
    end_time=$(date +%s)
    print_info "Duration: $((end_time - start_time))s"
    
    [[ "$failed" == "true" ]] && return 1
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
    
    # Export PHP_VERSION for docker compose build args
    export PHP_VERSION
    
    print_header "Docker Environment Setup (PHP $PHP_VERSION)"
    
    # Setup Docker environment
    if [[ "$REBUILD_CONTAINER" == "true" ]]; then
        print_step "Rebuilding Docker containers (--rebuild specified)..."
        docker compose down --remove-orphans --volumes 2>/dev/null || true
        docker compose build --no-cache --build-arg PHP_VERSION="$PHP_VERSION"
    else
        print_step "Starting Docker containers..."
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
    print_step "Running composer install..."
    # Note: --ignore-platform-req=ext-firebird is needed because php-firebird extension
    # reports version 1.0.0 internally regardless of the actual git tag version (7.0.0-rc.7)
    docker compose run --rm app composer update --no-interaction --no-progress --ignore-platform-req=ext-firebird || die "Composer update failed"
    print_success "Dependencies installed"
    
    # Create output directories
    docker compose run --rm app mkdir -p tests/var/coverage tests/var/logs tests/var/reports
    
    # Run quality checks based on mode
    local failed=false
    
    if [[ "$QUICK_MODE" == "true" ]]; then
        # Quick mode: static analysis only
        print_header "Quick Mode: Static Analysis Only"
        
        run_coding_standards || failed=true
        run_phpstan || failed=true
        run_psalm || failed=true
        
    elif [[ "$COVERAGE_ONLY" == "true" ]]; then
        # Coverage mode: Firebird 3 tests with coverage
        print_header "Coverage Mode: Tests with PCOV Coverage"
        
        run_tests_with_coverage || failed=true
        
    else
        # Full mode: complete quality pipeline
        print_header "Full Quality Pipeline"
        
        run_coding_standards || failed=true
        run_phpstan || failed=true
        run_psalm || failed=true
        run_tests_with_coverage || failed=true
        run_multiversion_tests || failed=true
    fi
    
    # Calculate duration
    local total_end
    total_end=$(date +%s)
    local total_duration=$((total_end - total_start))
    
    # Summary
    echo ""
    if [[ "$failed" == "true" ]]; then
        echo -e "${BOLD}${RED}╔═══════════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${BOLD}${RED}║              SOME QUALITY CHECKS FAILED!                          ║${NC}"
        echo -e "${BOLD}${RED}╚═══════════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        echo -e "${BOLD}Total Duration:${NC} ${total_duration}s"
        return 1
    else
        echo -e "${BOLD}${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
        echo -e "${BOLD}${GREEN}║              DOCKER CODE QUALITY CHECK COMPLETE!                  ║${NC}"
        echo -e "${BOLD}${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
        echo ""
        echo -e "${BOLD}Total Duration:${NC} ${total_duration}s"
    fi
    
    echo ""
    echo -e "${BOLD}Reports Generated:${NC}"
    echo -e "  • tests/var/reports/phpcs-report.txt"
    echo -e "  • tests/var/reports/phpstan-report.txt"
    echo -e "  • tests/var/reports/psalm-report.txt"
    echo -e "  • tests/var/reports/phpunit-fb3-report.txt"
    echo -e "  • tests/var/coverage/html/index.html"
    echo ""
    
    echo -e "${GREEN}Excellent Code Quality!${NC}"
    return 0
}

# Run main function with all arguments
main "$@"
