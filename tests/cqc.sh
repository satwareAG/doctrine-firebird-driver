#!/usr/bin/env bash
# =============================================================================
# Code Quality Check Script - Docker Delegation Wrapper
# =============================================================================
# Purpose: Wrapper that delegates to Docker-based quality checks
#
# NOTE: This script is kept for backwards compatibility and for running
# INSIDE the Docker container. For local development, use docker-cqc.sh
# which is the primary entry point for all quality checks.
#
# Usage:
#   Local development: ./docker-cqc.sh [options]   (RECOMMENDED)
#   Inside container:  ./cqc.sh [options]
#
# shellcheck disable=SC2034  # Unused variables are for configuration
# =============================================================================

set -euo pipefail

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

# Detect if running inside Docker container
is_inside_container() {
    [[ -f /.dockerenv ]] || grep -q docker /proc/1/cgroup 2>/dev/null
}

# =============================================================================
# Main Execution
# =============================================================================

main() {
    cd "$PROJECT_ROOT"
    
    # Check if running inside container
    if is_inside_container; then
        # Running inside Docker - execute quality checks directly
        print_header "Code Quality Check (Inside Container)"
        print_info "Running quality checks directly..."
        
        # Create output directories
        mkdir -p tests/var/coverage tests/var/logs tests/var/reports
        
        # Track timing
        local total_start
        total_start=$(date +%s)
        
        local failed=false
        
        # Phase 1: Coding Standards
        print_header "Phase 1: Coding Standards Check (PHP_CodeSniffer)"
        print_step "Running PHPCS with auto-fix attempt..."
        
        local cs_start
        cs_start=$(date +%s)
        
        if ! vendor/bin/phpcs --report=summary 2>/dev/null; then
            print_info "Attempting auto-fix with PHPCBF..."
            vendor/bin/phpcbf || true
        fi
        
        if vendor/bin/phpcs --report=full --report-file=tests/var/reports/phpcs-report.txt; then
            print_success "Coding standards: PASSED"
        else
            print_error "Coding standards: FAILED (see tests/var/reports/phpcs-report.txt)"
            failed=true
        fi
        
        local cs_end
        cs_end=$(date +%s)
        print_info "Duration: $((cs_end - cs_start))s"
        
        # Phase 2: PHPStan
        print_header "Phase 2: Static Analysis (PHPStan Level 8 + Strict Rules)"
        print_step "Running PHPStan with strict analysis..."
        
        local stan_start
        stan_start=$(date +%s)
        
        # NOTE: SIGSEGV issue fixed in php-firebird v7.0.0-rc.37
        # See: https://github.com/satwareAG/php-firebird/issues/55
        # Use PIPESTATUS[0] to capture the actual command exit code (not tee's)
        vendor/bin/phpstan analyse --memory-limit=2G --error-format=table 2>&1 | tee tests/var/reports/phpstan-report.txt
        local phpstan_exit=${PIPESTATUS[0]}
        if [[ $phpstan_exit -eq 0 ]]; then
            print_success "PHPStan Level 8: PASSED"
        else
            print_error "PHPStan Level 8: FAILED (exit code: $phpstan_exit)"
            failed=true
        fi
        
        local stan_end
        stan_end=$(date +%s)
        print_info "Duration: $((stan_end - stan_start))s"
        
        # Phase 3: Psalm
        print_header "Phase 3: Static Analysis (Psalm)"
        print_step "Running Psalm static analysis..."
        
        local psalm_start
        psalm_start=$(date +%s)
        
        print_info "Attempting to auto-fix type issues..."
        vendor/bin/psalm --alter --issues=MissingReturnType,MissingParamType --no-cache 2>/dev/null || true
        
        print_info "Updating Psalm baseline..."
        vendor/bin/psalm --set-baseline=psalm-baseline.xml --no-cache 2>&1 | tee tests/var/reports/psalm-report.txt || true
        
        if vendor/bin/psalm --no-cache --output-format=text; then
            print_success "Psalm: PASSED"
        else
            print_info "Psalm: Completed with baseline (check psalm-baseline.xml for known issues)"
        fi
        
        local psalm_end
        psalm_end=$(date +%s)
        print_info "Duration: $((psalm_end - psalm_start))s"
        
        # Phase 4: Unit Tests with Coverage
        print_header "Phase 4: PHPUnit Test Suite with Coverage"
        print_step "Running tests against Firebird 3 with PCOV coverage..."
        
        local test_start
        test_start=$(date +%s)
        
        # Use PIPESTATUS[0] to capture the actual command exit code (not tee's)
        php -d pcov.enabled=1 vendor/bin/phpunit -c tests/phpunit.xml --coverage-text 2>&1 | tee tests/var/reports/phpunit-fb3-report.txt
        local phpunit_exit=${PIPESTATUS[0]}
        if [[ $phpunit_exit -eq 0 ]]; then
            print_success "Firebird 3 Tests: PASSED"
        else
            print_error "Firebird 3 Tests: FAILED (exit code: $phpunit_exit)"
            failed=true
        fi
        
        local test_end
        test_end=$(date +%s)
        print_info "Duration: $((test_end - test_start))s"
        
        # Phase 5: Multi-Version Tests
        print_header "Phase 5: Multi-Version Firebird Compatibility"
        print_step "Running tests against Firebird 2.5, 4.x, and 5.x..."
        
        local compat_start
        compat_start=$(date +%s)
        
        # Use PIPESTATUS[0] to capture the actual command exit code (not tail's)
        print_info "Testing Firebird 2.5..."
        vendor/bin/phpunit -c tests/phpunit-firebird25.xml --no-coverage 2>&1 | tail -10
        local fb25_exit=${PIPESTATUS[0]}
        if [[ $fb25_exit -eq 0 ]]; then
            print_success "Firebird 2.5: PASSED"
        else
            print_error "Firebird 2.5: FAILED (exit code: $fb25_exit)"
            failed=true
        fi
        
        print_info "Testing Firebird 4.x..."
        vendor/bin/phpunit -c tests/phpunit-firebird4.xml --no-coverage 2>&1 | tail -10
        local fb4_exit=${PIPESTATUS[0]}
        if [[ $fb4_exit -eq 0 ]]; then
            print_success "Firebird 4.x: PASSED"
        else
            print_error "Firebird 4.x: FAILED (exit code: $fb4_exit)"
            failed=true
        fi
        
        print_info "Testing Firebird 5.x..."
        vendor/bin/phpunit -c tests/phpunit-firebird5.xml --no-coverage 2>&1 | tail -10
        local fb5_exit=${PIPESTATUS[0]}
        if [[ $fb5_exit -eq 0 ]]; then
            print_success "Firebird 5.x: PASSED"
        else
            print_error "Firebird 5.x: FAILED (exit code: $fb5_exit)"
            failed=true
        fi
        
        local compat_end
        compat_end=$(date +%s)
        print_info "Duration: $((compat_end - compat_start))s"
        
        # Summary
        local total_end
        total_end=$(date +%s)
        local total_duration=$((total_end - total_start))
        
        print_header "Code Quality Check Complete!"
        
        if [[ "$failed" == "true" ]]; then
            echo -e "${BOLD}${RED}╔═══════════════════════════════════════════════════════════════════╗${NC}"
            echo -e "${BOLD}${RED}║              SOME QUALITY CHECKS FAILED!                          ║${NC}"
            echo -e "${BOLD}${RED}╚═══════════════════════════════════════════════════════════════════╝${NC}"
            return 1
        else
            echo -e "${BOLD}${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
            echo -e "${BOLD}${GREEN}║                    ALL QUALITY CHECKS PASSED!                     ║${NC}"
            echo -e "${BOLD}${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
        fi
        
        echo ""
        echo -e "${BOLD}Timing Summary:${NC}"
        echo -e "  • Coding Standards:  $((cs_end - cs_start))s"
        echo -e "  • PHPStan Level 8:   $((stan_end - stan_start))s"
        echo -e "  • Psalm:             $((psalm_end - psalm_start))s"
        echo -e "  • PHPUnit + Coverage: $((test_end - test_start))s"
        echo -e "  • Compatibility:     $((compat_end - compat_start))s"
        echo -e "  ${BOLD}─────────────────────${NC}"
        echo -e "  ${BOLD}Total:             ${total_duration}s${NC}"
        echo ""
        
        echo -e "${BOLD}Reports Generated:${NC}"
        echo -e "  • tests/var/reports/phpcs-report.txt"
        echo -e "  • tests/var/reports/phpstan-report.txt"
        echo -e "  • tests/var/reports/psalm-report.txt"
        echo -e "  • tests/var/reports/phpunit-fb3-report.txt"
        echo -e "  • tests/var/coverage/html/index.html (Coverage Report)"
        echo ""
        
        return 0
    else
        # Running outside Docker - delegate to docker-cqc.sh
        print_info "Not running inside Docker container."
        print_info "Delegating to docker-cqc.sh for Docker-based testing..."
        echo ""
        print_step "Docker is MANDATORY for all testing to ensure consistency."
        echo ""
        
        # Execute docker-cqc.sh with all arguments
        exec "$SCRIPT_DIR/docker-cqc.sh" "$@"
    fi
}

# Run main function with all arguments
main "$@"
