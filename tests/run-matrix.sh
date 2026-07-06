#!/usr/bin/env bash
# =============================================================================
# Matrix Test Runner - PHP x Firebird
# =============================================================================
# Runs the full test suite across all supported PHP and Firebird versions.
# Matrix: PHP 8.2/8.3/8.4/8.5 x Firebird 3.0/4.0/5.0 = 12 combinations
#
# Usage:
#   ./tests/run-matrix.sh              # Full matrix
#   ./tests/run-matrix.sh 8.4 3.0      # Single combo
#   ./tests/run-matrix.sh 8.4          # All FB versions for PHP 8.4
#
# Requires: docker, docker compose
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$SCRIPT_DIR"

# Matrix definitions
PHP_VERSIONS=("8.2" "8.3" "8.4" "8.5")
FB_VERSIONS=("3.0" "4.0" "5.0")

# Parse args
if [ $# -ge 1 ]; then
    PHP_VERSIONS=("$1")
fi
if [ $# -ge 2 ]; then
    FB_VERSIONS=("$2")
fi

CURRENT_UID=$(id -u)
CURRENT_GID=$(id -g)
export CURRENT_UID CURRENT_GID

# Results tracking
declare -a RESULTS
PASS_COUNT=0
FAIL_COUNT=0

get_fb_info() {
    local fb_ver="$1"
    case "$fb_ver" in
        3.0) echo "3" "firebird3" "dfd-firebird3" ;;
        4.0) echo "4" "firebird4" "dfd-firebird4" ;;
        5.0) echo "5" "firebird5" "dfd-firebird5" ;;
        *) echo "" "" "" ;;
    esac
}

start_firebird() {
    local fb_ver="$1"
    local fb_tag fb_service fb_container
    read -r fb_tag fb_service fb_container <<< "$(get_fb_info "$fb_ver")"

    echo ">>> Starting Firebird ${fb_ver} (${fb_container})..."

    # Stop everything first
    docker compose down -v --remove-orphans 2>/dev/null || true

    if [ "$fb_ver" = "3.0" ]; then
        docker compose up -d firebird3 2>&1 | tail -1
    else
        docker compose --profile "fb${fb_tag}" up -d "${fb_service}" 2>&1 | tail -1
    fi

    # Wait for healthy
    local retries=0
    while [ $retries -lt 30 ]; do
        if docker exec "$fb_container" bash -c '</dev/tcp/localhost/3050' 2>/dev/null; then
            echo ">>> Firebird ${fb_ver} is ready"
            return 0
        fi
        retries=$((retries + 1))
        sleep 2
    done
    echo ">>> ERROR: Firebird ${fb_ver} did not become healthy"
    return 1
}

run_tests() {
    local php_ver="$1"
    local fb_ver="$2"
    local fb_tag fb_service fb_container
    read -r fb_tag fb_service fb_container <<< "$(get_fb_info "$fb_ver")"

    local combo="PHP ${php_ver} / Firebird ${fb_ver}"
    printf "\n%s\n" "============================================================"
    printf "%s\n" "  ${combo}"
    printf "%s\n\n" "============================================================"

    # Start Firebird
    if ! start_firebird "$fb_ver"; then
        RESULTS+=("FAIL  ${combo} (Firebird unhealthy)")
        FAIL_COUNT=$((FAIL_COUNT + 1))
        return 1
    fi

    # Build app image for this PHP version
    echo ">>> Building app image for PHP ${php_ver}..."
    if ! docker compose build \
        --build-arg PHP_VERSION="${php_ver}" \
        --build-arg FIREBIRD_VERSION="${fb_ver}" \
        app 2>&1 | tail -3; then
        echo ">>> BUILD FAILED for ${combo}"
        RESULTS+=("FAIL  ${combo} (build failed)")
        FAIL_COUNT=$((FAIL_COUNT + 1))
        return 1
    fi

    # Install composer deps
    echo ">>> Installing composer dependencies..."
    docker compose run --rm app composer install --no-interaction --quiet 2>&1 | tail -2

    # Run tests
    echo ">>> Running test suite..."
    local output
    set +e
    output=$(docker compose run --rm \
        -e DB_HOST="${fb_service}" \
        -e DB_DBNAME="/var/lib/firebird/data/test.fdb" \
        -e DB_USER="SYSDBA" \
        -e DB_PASSWORD="masterkey" \
        app vendor/bin/phpunit -c phpunit.xml 2>&1)
    local exit_code=$?
    set -e

    # Display last 15 lines of output
    echo "$output" | tail -15

    if [ $exit_code -eq 0 ]; then
        RESULTS+=("PASS  ${combo}")
        PASS_COUNT=$((PASS_COUNT + 1))
    else
        local summary
        summary=$(echo "$output" | grep -E '^Tests: ' | head -1)
        RESULTS+=("FAIL  ${combo}  ${summary}")
        FAIL_COUNT=$((FAIL_COUNT + 1))
    fi
}

# Run matrix
echo "Starting matrix test run"
echo "  PHP versions: ${PHP_VERSIONS[*]}"
echo "  Firebird versions: ${FB_VERSIONS[*]}"
echo ""

for php_ver in "${PHP_VERSIONS[@]}"; do
    for fb_ver in "${FB_VERSIONS[@]}"; do
        run_tests "$php_ver" "$fb_ver" || true
    done
done

# Cleanup
echo ""
echo ">>> Cleaning up..."
docker compose down -v --remove-orphans 2>/dev/null || true

# Summary
printf "\n%s\n" "============================================================"
printf "%s\n" "  MATRIX RESULTS"
printf "%s\n" "============================================================"
for r in "${RESULTS[@]}"; do
    echo "  $r"
done
printf "\n%s\n" "============================================================"
printf "  Passed: %d  Failed: %d\n" "$PASS_COUNT" "$FAIL_COUNT"
printf "%s\n" "============================================================"

if [ $FAIL_COUNT -gt 0 ]; then
    exit 1
fi
