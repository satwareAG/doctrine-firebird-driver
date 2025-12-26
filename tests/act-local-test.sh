#!/bin/bash
# Local GitHub Actions validation script for doctrine-firebird-driver
# This script provides 1:1 parity testing with GitHub Actions CI workflow
#
# Usage:
#   ./tests/act-local-test.sh                    # Run all tests (static + unit tests)
#   ./tests/act-local-test.sh static             # Run static analysis only
#   ./tests/act-local-test.sh test 5             # Run PHPUnit tests against Firebird 5.0
#   ./tests/act-local-test.sh test 4             # Run PHPUnit tests against Firebird 4.0
#   ./tests/act-local-test.sh test 3             # Run PHPUnit tests against Firebird 3.0
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Default values
FIREBIRD_VERSION="${2:-5}"
MODE="${1:-all}"

print_usage() {
    echo "Usage: $0 [mode] [firebird_version]"
    echo ""
    echo "Modes:"
    echo "  all     - Run static analysis + PHPUnit tests (default)"
    echo "  static  - Run static analysis only (no Firebird required)"
    echo "  test    - Run PHPUnit tests only"
    echo ""
    echo "Firebird versions: 3, 4, 5 (default: 5)"
    echo ""
    echo "Examples:"
    echo "  $0                  # All tests, Firebird 5.0"
    echo "  $0 static           # Static analysis only"
    echo "  $0 test 4           # PHPUnit tests, Firebird 4.0"
}

cleanup() {
    log_info "Cleaning up Firebird container..."
    docker rm -f doctrine-firebird-test 2>/dev/null || true
}

trap cleanup EXIT

run_static_analysis() {
    log_info "=== Running Static Analysis (act -j static-analysis) ==="
    
    # This step has 1:1 parity with GitHub Actions
    act -j static-analysis 2>&1 | tail -100
    
    log_info "Static analysis completed"
}

start_firebird() {
    local version="$1"
    local docker_tag
    
    case "$version" in
        3) docker_tag="3" ;;
        4) docker_tag="4" ;;
        5) docker_tag="5" ;;
        *) log_error "Unknown Firebird version: $version"; exit 1 ;;
    esac
    
    log_info "Starting Firebird $version container (firebirdsql/firebird:$docker_tag)..."
    
    # Remove any existing container
    docker rm -f doctrine-firebird-test 2>/dev/null || true
    
    # Find a free port
    local port="${FIREBIRD_PORT:-3050}"
    if [ -z "${FIREBIRD_PORT:-}" ]; then
        # If FIREBIRD_PORT is not set, try to find a free port starting from 3050
        while lsof -i:$port -sTCP:LISTEN -t >/dev/null 2>&1; do
            port=$((port + 1))
        done
    fi
    
    log_info "Using port $port for Firebird..."
    export DB_PORT=$port

    # Start Firebird with same configuration as GitHub Actions
    docker run -d \
        --name doctrine-firebird-test \
        -e ISC_PASSWORD=masterkey \
        -e FIREBIRD_DATABASE=test.fdb \
        -e FIREBIRD_USER=SYSDBA \
        -e FIREBIRD_PASSWORD=masterkey \
        -p $port:3050 \
        "firebirdsql/firebird:$docker_tag"
    
    log_info "Waiting for Firebird to be ready..."
    
    # Wait for Firebird to be healthy (matching GitHub Actions health check)
    local max_attempts=40
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        attempt=$((attempt + 1))
        
        if docker exec doctrine-firebird-test fbsvcmgr localhost:3050:service_mgr user SYSDBA password masterkey info_server_version 2>/dev/null; then
            log_info "Firebird $version is ready!"
            return 0
        fi
        
        echo "Waiting for Firebird (attempt $attempt/$max_attempts)..."
        sleep 2
    done
    
    log_error "Firebird failed to start within timeout"
    docker logs doctrine-firebird-test
    exit 1
}

run_phpunit_tests() {
    local version="$1"
    local phpunit_config
    
    case "$version" in
        3) phpunit_config="phpunit.xml" ;;
        4) phpunit_config="phpunit-firebird4.xml" ;;
        5) phpunit_config="phpunit-firebird5.xml" ;;
    esac
    
    log_info "=== Running PHPUnit Tests (Firebird $version) ==="
    log_info "Config: tests/$phpunit_config"
    
    # Start Firebird
    start_firebird "$version"
    
    # Install dependencies (matching GitHub Actions)
    log_info "Installing Composer dependencies..."
    composer install --no-progress --prefer-dist --ignore-platform-req=ext-firebird
    
    # Run PHPUnit (matching GitHub Actions exactly)
    log_info "Running PHPUnit..."
    
    export DB_HOST=127.0.0.1
    # DB_PORT is already exported in start_firebird
    export DB_USER=SYSDBA
    export DB_PASSWORD=masterkey
    export DB_DATABASE=/firebird/data/test.fdb
    
    ./vendor/bin/phpunit \
        -c "tests/$phpunit_config" \
        --coverage-text \
        --colors=always \
        --testdox
}

# Main execution
case "$MODE" in
    static)
        run_static_analysis
        ;;
    test)
        run_phpunit_tests "$FIREBIRD_VERSION"
        ;;
    all)
        run_static_analysis
        run_phpunit_tests "$FIREBIRD_VERSION"
        ;;
    help|-h|--help)
        print_usage
        exit 0
        ;;
    *)
        log_error "Unknown mode: $MODE"
        print_usage
        exit 1
        ;;
esac

log_info "=== Local CI Test Complete ==="
