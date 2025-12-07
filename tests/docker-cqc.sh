#!/bin/bash
# =============================================================================
# Docker Code Quality Check Wrapper - 2025 State-of-the-Art Edition
# =============================================================================
# Purpose: Run comprehensive code quality checks in Docker environment
# Features: PCOV coverage, PHPStan Level 8, Psalm, full Firebird compatibility
# Target: "First Citizen" quality status - surpass DBAL core drivers
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
# Command Line Arguments
# =============================================================================

REBUILD_CONTAINER=false
COVERAGE_ONLY=false
QUICK_MODE=false

while [[ "$#" -gt 0 ]]; do
    case $1 in
        --rebuild) REBUILD_CONTAINER=true ;;
        --coverage) COVERAGE_ONLY=true ;;
        --quick) QUICK_MODE=true ;;
        -h|--help)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --rebuild    Force rebuild of Docker containers"
            echo "  --coverage   Run only tests with coverage (skip multi-version)"
            echo "  --quick      Quick mode: run static analysis only (no tests)"
            echo "  -h, --help   Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                # Full quality check"
            echo "  $0 --coverage     # Tests + coverage only"
            echo "  $0 --quick        # Static analysis only"
            exit 0
            ;;
        *) echo "Unknown parameter: $1"; exit 1 ;;
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

# Track timing
TOTAL_START=$(date +%s)

# =============================================================================
# Docker Environment Setup
# =============================================================================

print_header "Docker Environment Setup"

# Check if we need to rebuild
if [ "$REBUILD_CONTAINER" = true ]; then
    print_step "Rebuilding Docker containers (--rebuild specified)..."
    docker compose down --remove-orphans --volumes 2>/dev/null || true
    docker compose build --no-cache
else
    print_step "Starting Docker containers..."
    docker compose down --remove-orphans 2>/dev/null || true
fi

docker compose up -d

# Wait for containers to be ready
print_info "Waiting for Firebird containers to initialize..."
sleep 5

# Check container health
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
print_step "Running composer install..."

docker compose run --rm app composer install --prefer-stable

print_success "Dependencies installed"

# =============================================================================
# Run Quality Checks
# =============================================================================

if [ "$QUICK_MODE" = true ]; then
    # Quick mode: static analysis only
    print_header "Quick Mode: Static Analysis Only"
    
    print_step "Running PHP_CodeSniffer..."
    docker compose run --rm app vendor/bin/phpcs || docker compose run --rm app vendor/bin/phpcbf
    print_success "Code style check complete"
    
    print_step "Running PHPStan Level 8..."
    docker compose run --rm app vendor/bin/phpstan analyse --memory-limit=2G
    print_success "PHPStan analysis complete"
    
    print_step "Running Psalm..."
    docker compose run --rm app vendor/bin/psalm --no-cache
    print_success "Psalm analysis complete"
    
elif [ "$COVERAGE_ONLY" = true ]; then
    # Coverage mode: Firebird 3 tests with coverage
    print_header "Coverage Mode: Tests with PCOV Coverage"
    
    print_step "Running PHPUnit with PCOV coverage..."
    docker compose run --rm app php -d pcov.enabled=1 vendor/bin/phpunit -c tests/phpunit.xml --coverage-text --coverage-html=tests/var/coverage/html
    print_success "Coverage tests complete"
    
    print_info "Coverage report: tests/var/coverage/html/index.html"
    
else
    # Full mode: complete quality pipeline
    print_header "Full Quality Pipeline"
    print_step "Running comprehensive code quality checks..."
    
    docker compose run --rm app tests/cqc.sh
    print_success "Full quality pipeline complete"
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
echo -e "${BOLD}${GREEN}╔═══════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║              DOCKER CODE QUALITY CHECK COMPLETE!                  ║${NC}"
echo -e "${BOLD}${GREEN}╚═══════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BOLD}Total Duration:${NC} ${TOTAL_DURATION}s"
echo ""

if [ -f "var/coverage/html/index.html" ]; then
    echo -e "${BOLD}Coverage Report:${NC}"
    echo -e "  Open: tests/var/coverage/html/index.html"
    echo ""
fi

echo -e "${GREEN}Excellent Code Quality!${NC}"
