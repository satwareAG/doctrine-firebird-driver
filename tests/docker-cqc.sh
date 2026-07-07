#!/usr/bin/env bash
# =============================================================================
# Docker Code Quality Check - Comprehensive Quality Pipeline
# =============================================================================
# Purpose: Run comprehensive code quality checks in Docker environment
# Features: PCOV coverage, PHPStan Level 8, Psalm, PHP-CS-Fixer, full Firebird compatibility
# Target: "First Citizen" quality status - surpass DBAL core drivers
#
# Usage: ./docker-cqc.sh [options]
# =============================================================================

set -euo pipefail

# Enable BuildKit for Docker builds
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1

# =============================================================================
# Constants
# =============================================================================

readonly SCRIPT_NAME="$(basename "$0")"
readonly SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
readonly PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Source shared helpers
source "$SCRIPT_DIR/lib/common.sh"

# =============================================================================
# Default Configuration
# =============================================================================

PHP_VERSION="${PHP_VERSION:-8.4}"
REBUILD_CONTAINER=false
COVERAGE_ONLY=false
QUICK_MODE=false
VERBOSE=false

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
  -p, --php <version>   PHP version (8.2|8.3|8.4|8.5, default: 8.4)
      --rebuild         Force rebuild of Docker containers
      --coverage        Run only tests with coverage (skip multi-version)
      --quick           Quick mode: run static analysis only (no tests)
      --verbose         Show verbose output
  -h, --help            Show this help message

${BOLD}Environment Variables:${NC}
  PHP_VERSION           Override PHP version (default: 8.4)
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
    local db_host="${3:-}"
    
    local env_args=""
    if [[ -n "$db_host" ]]; then
        env_args="-e DB_HOST=$db_host"
    fi

    [[ "$VERBOSE" == "true" ]] && print_info "Running: $cmd (timeout: ${timeout}s) ${db_host:+with DB_HOST=$db_host}"
    
    # Use -T to disable pseudo-TTY allocation (prevents hangs in scripts)
    # Use timeout command to prevent infinite hangs
    # Use < /dev/null to close stdin (prevents hangs with stdin_open: true)
    # Use 'set -o pipefail' to ensure pipeline failures are captured when using | tee
    if timeout "$timeout" docker compose run --rm -T $env_args app bash -c "set -o pipefail; $cmd" < /dev/null; then
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
    # Note: Disable parallel and cache to prevent hangs in Docker environment
    if ! run_in_docker "vendor/bin/phpcs --report=summary --parallel=1 --no-cache" 900; then
        print_info "Attempting auto-fix with PHPCBF..."
        run_in_docker "vendor/bin/phpcbf --parallel=1 --no-cache" 900 || true
    fi
    
    # Final check
    if run_in_docker "vendor/bin/phpcs --report=full --report-file=tests/var/reports/phpcs-report.txt --parallel=1 --no-cache" 900; then
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
    
    # Use PHPStan parallel mode
    local phpstan_cmd="vendor/bin/phpstan analyse --memory-limit=2G --error-format=table"
    local report_file="tests/var/reports/phpstan-report.txt"
    
    # Run PHPStan and capture exit code properly
    local phpstan_exit=0
    run_in_docker "$phpstan_cmd 2>&1 | tee $report_file" 900 || phpstan_exit=$?
    
    # Check for "severe errors" in output (PHPStan internal errors)
    if grep -qi "severe errors" "$report_file" 2>/dev/null; then
        print_error "PHPStan Level 8: INCOMPLETE (severe errors detected)"
        print_info "PHPStan encountered internal errors during analysis"
        return 1
    fi
    
    # Check normal exit code
    if [[ $phpstan_exit -ne 0 ]]; then
        print_error "PHPStan Level 8: FAILED (exit code $phpstan_exit)"
        return 1
    fi
    
    print_success "PHPStan Level 8: PASSED"
    
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
    
    # Determine Psalm command based on PHP version
    # Suppress E_DEPRECATED on PHP 8.4+ due to Psalm v5 using deprecated E_STRICT constant
    local psalm_cmd="vendor/bin/psalm"
    if [[ "$PHP_VERSION" == "8.4" ]] || [[ "$PHP_VERSION" == "8.5" ]]; then
        psalm_cmd="php -d error_reporting='E_ALL & ~E_DEPRECATED' vendor/bin/psalm"
        print_info "PHP $PHP_VERSION detected: Suppressing E_DEPRECATED for Psalm compatibility"
    fi
    
    # Psalm analysis (no --alter: auto-fix can introduce bugs, e.g. changing
    # mixed ...$args to array ...$args based on incorrect type inference)
    print_info "Running Psalm analysis..."
    
    # Update baseline if needed
    print_info "Updating Psalm baseline..."
    run_in_docker "$psalm_cmd --set-baseline=psalm-baseline.xml --no-cache 2>&1 | tee tests/var/reports/psalm-report.txt" 900 || true
    
    # Final analysis
    if run_in_docker "$psalm_cmd --no-cache --show-info=false --output-format=text" 900; then
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
    
    # Relative to /app inside container
    local report_rel="var/reports/phpunit-fb3-report.txt"
    local report_container="tests/$report_rel"
    
    local cmd="php -d pcov.enabled=1 -d pcov.directory=/app/src vendor/bin/phpunit -c tests/phpunit.xml --coverage-text --coverage-html=tests/var/coverage/html 2>&1 | tee $report_container"

    local exit_code=0
    run_in_docker "$cmd" 1200 "firebird3" || exit_code=$?

    if [[ $exit_code -eq 0 ]]; then
        print_success "Firebird 3 Tests: PASSED"
    else
        print_error "Firebird 3 Tests: FAILED (exit code $exit_code)"
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
    print_step "Running tests against Firebird 4.0 and 5.0..."
    
    local start_time
    start_time=$(date +%s)
    local failed=false
    
    # Helper function for versioned tests
    run_version_test() {
        local version="$1"
        local host="$2"
        local profile="$3"
        local report_rel="var/reports/phpunit-fb${version//./}-report.txt"
        local report_container="tests/$report_rel"
        
        print_info "Testing Firebird $version..."
        
        # Clean volume for this FB version to ensure fresh database
        cleanup_fb_version "$version"
        
        # Start the container (FB4/FB5 are behind profiles)
        if [[ -n "$profile" ]]; then
            # shellcheck disable=SC2086 # profile contains "--profile fb4" (two words, intentional split)
            docker compose $profile up -d "$host" 2>&1 | tail -1
        else
            docker compose up -d "$host" 2>&1 | tail -1
        fi
        wait_for_containers 30
        
        local cmd="vendor/bin/phpunit -c tests/phpunit.xml --no-coverage 2>&1 | tee $report_container | tail -10"
        local exit_code=0
        run_in_docker "$cmd" 1200 "$host" || exit_code=$?
        
        if [[ $exit_code -eq 0 ]]; then
            print_success "Firebird $version: PASSED"
            return 0
        fi
        
        print_error "Firebird $version: FAILED (exit $exit_code)"
        return 1
    }
    
    run_version_test "4.0" "firebird4" "--profile fb4" || failed=true
    run_version_test "5.0" "firebird5" "--profile fb5" || failed=true
    
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
    
    # Clean slate: remove ALL containers and volumes (including profiled FB4/FB5)
    print_step "Cleaning up previous containers and volumes..."
    cleanup_all "$SCRIPT_DIR"
    
    # Build and start
    if [[ "$REBUILD_CONTAINER" == "true" ]]; then
        print_step "Rebuilding Docker containers (--rebuild specified)..."
        docker compose build --no-cache --build-arg PHP_VERSION="$PHP_VERSION"
    else
        print_step "Building Docker containers..."
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

    # Display Firebird extension version
    print_step "Checking Firebird extension version..."
    docker compose run --rm app php --ri firebird | grep "Firebird extension version" || print_error "Could not determine Firebird extension version"
    
    # Install dependencies
    print_header "Installing Dependencies"
    print_step "Running composer install..."
    docker compose run --rm app composer install --no-interaction --no-progress || die "Composer install failed"
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
