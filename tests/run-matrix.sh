#!/usr/bin/env bash
# =============================================================================
# Matrix Test Runner - Doctrine Firebird Driver
# =============================================================================
# Primary test runner. Executes PHPUnit in Docker against Firebird.
#
# Matrix: PHP 8.2/8.3/8.4/8.5 x Firebird 3.0/4.0/5.0 = 12 combinations
#
# Default: PHP 8.4 x Firebird 3.0 (fastest single-combo, ~3 min)
#
# Usage:
#   ./tests/run-matrix.sh                          # Default: PHP 8.4 x FB 3.0
#   ./tests/run-matrix.sh --all                    # Full matrix (12 combos)
#   ./tests/run-matrix.sh --php 8.4                # PHP 8.4 x all FB (3 parallel)
#   ./tests/run-matrix.sh --fb 4.0                 # All PHP x FB 4.0
#   ./tests/run-matrix.sh --php 8.4 --fb 4.0       # Single combo
#   ./tests/run-matrix.sh --suite unit             # Unit tests only
#   ./tests/run-matrix.sh --filter Sequence        # Filter by name
#   ./tests/run-matrix.sh --coverage               # With PCOV coverage (FB3 only)
#   ./tests/run-matrix.sh --build                  # Build all 4 images, no tests
#   ./tests/run-matrix.sh --list                   # List versions and image status
#   ./tests/run-matrix.sh --clean                  # Stop containers, remove volumes
#   ./tests/run-matrix.sh --help                   # Show help
#
# Requires: docker, docker compose
# =============================================================================
set -euo pipefail

# =============================================================================
# Constants
# =============================================================================

SCRIPT_NAME="$(basename "$0")"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_DIR="$SCRIPT_DIR"
NETWORK_NAME="doctrine-firebird-test_default"
TEST_TIMEOUT=600  # 10 minutes per combo

ALL_PHP_VERSIONS=("8.2" "8.3" "8.4" "8.5")
ALL_FB_VERSIONS=("3.0" "4.0" "5.0")

# Source shared helpers
source "$SCRIPT_DIR/lib/common.sh"

# =============================================================================
# Configuration (defaults)
# =============================================================================

PHP_VERSIONS=("8.4")
FB_VERSIONS=("3.0")
SUITE=""
FILTER=""
WITH_COVERAGE=false
BUILD_ONLY=false
LIST_ONLY=false
CLEAN_ONLY=false
SHOW_HELP=false

export CURRENT_UID=$(id -u)
export CURRENT_GID=$(id -g)

# =============================================================================
# FB Version Helpers
# =============================================================================

# Map FB version to docker-compose service name and container name
get_fb_service() {
    case "$1" in
        3.0) echo "firebird3" ;;
        4.0) echo "firebird4" ;;
        5.0) echo "firebird5" ;;
        *)   echo "" ;;
    esac
}

get_fb_container() {
    case "$1" in
        3.0) echo "dfd-firebird3" ;;
        4.0) echo "dfd-firebird4" ;;
        5.0) echo "dfd-firebird5" ;;
        *)   echo "" ;;
    esac
}

get_fb_profile() {
    case "$1" in
        3.0) echo "" ;;
        4.0) echo "--profile fb4" ;;
        5.0) echo "--profile fb5" ;;
        *)   echo "" ;;
    esac
}

# Get Docker image name for a PHP version
get_image_name() {
    echo "dfd-app-php${1}"
}

# =============================================================================
# Docker Management
# =============================================================================

# Start a single Firebird version
start_firebird() {
    local fb_ver="$1"
    local service container profile
    service=$(get_fb_service "$fb_ver")
    container=$(get_fb_container "$fb_ver")
    profile=$(get_fb_profile "$fb_ver")

    print_step "Starting Firebird ${fb_ver} (${container})..."

    cd "$COMPOSE_DIR"
    if [ -n "$profile" ]; then
        docker compose $profile up -d "$service" 2>&1 | tail -1
    else
        docker compose up -d "$service" 2>&1 | tail -1
    fi

    # Wait for healthy (TCP port 3050)
    local retries=0
    while [ $retries -lt 30 ]; do
        if docker exec "$container" bash -c '</dev/tcp/localhost/3050' 2>/dev/null; then
            print_ok "Firebird ${fb_ver} ready"
            return 0
        fi
        retries=$((retries + 1))
        sleep 2
    done
    print_fail "Firebird ${fb_ver} did not become healthy"
    return 1
}

# Start all requested Firebird versions
start_all_firebird() {
    local fb_ver service profile
    cd "$COMPOSE_DIR"

    for fb_ver in "${FB_VERSIONS[@]}"; do
        service=$(get_fb_service "$fb_ver")
        profile=$(get_fb_profile "$fb_ver")
        if [ -n "$profile" ]; then
            docker compose $profile up -d "$service" 2>&1 | tail -1
        else
            docker compose up -d "$service" 2>&1 | tail -1
        fi
    done

    # Wait for all to be healthy
    for fb_ver in "${FB_VERSIONS[@]}"; do
        local container
        container=$(get_fb_container "$fb_ver")
        local retries=0
        while [ $retries -lt 30 ]; do
            if docker exec "$container" bash -c '</dev/tcp/localhost/3050' 2>/dev/null; then
                break
            fi
            retries=$((retries + 1))
            sleep 2
        done
        if [ $retries -ge 30 ]; then
            print_fail "Firebird ${fb_ver} unhealthy"
            return 1
        fi
    done
    print_ok "All Firebird versions ready"
    return 0
}

# Build Docker image for a PHP version
build_image() {
    local php_ver="$1"
    local image
    image=$(get_image_name "$php_ver")

    print_step "Building image ${image}..."

    cd "$COMPOSE_DIR"
    if docker compose build \
        --build-arg PHP_VERSION="${php_ver}" \
        --build-arg FIREBIRD_VERSION="4.0" \
        app 2>&1 | tail -3; then

        # Tag the image for reuse
        docker tag doctrine-firebird-test-app "$image" 2>/dev/null || true
        print_ok "Image ${image} built"
        return 0
    else
        print_fail "Build failed for ${image}"
        return 1
    fi
}

# Ensure image exists (build if missing)
ensure_image() {
    local php_ver="$1"
    local image
    image=$(get_image_name "$php_ver")

    if docker image inspect "$image" >/dev/null 2>&1; then
        print_info "Image ${image} exists (cached)"
        return 0
    fi
    build_image "$php_ver" || return 1
}

# =============================================================================
# Test Execution
# =============================================================================

# Run PHPUnit for a single PHP x FB combination
run_single_combo() {
    local php_ver="$1"
    local fb_ver="$2"
    local image
    image=$(get_image_name "$php_ver")
    local service
    service=$(get_fb_service "$fb_ver")

    local combo="PHP ${php_ver} / Firebird ${fb_ver}"
    print_header "$combo"

    # Build phpunit command
    local cmd="vendor/bin/phpunit -c phpunit.xml"

    if [ "$WITH_COVERAGE" = true ]; then
        cmd="php -d pcov.enabled=1 -d pcov.directory=/app/src vendor/bin/phpunit -c phpunit.xml --coverage-text"
    fi

    if [ -n "$SUITE" ]; then
        cmd="$cmd --testsuite ${SUITE}"
    fi

    if [ -n "$FILTER" ]; then
        cmd="$cmd --filter '${FILTER}'"
    fi

    print_step "Running: ${cmd}"
    print_step "DB_HOST=${service}, timeout=${TEST_TIMEOUT}s"

    cd "$PROJECT_ROOT"
    local output exit_code

    set +e
    output=$(timeout "$TEST_TIMEOUT" docker run --rm \
        --network "$NETWORK_NAME" \
        -v "$(pwd):/app" \
        -e DB_HOST="$service" \
        -e DB_DBNAME=/var/lib/firebird/data/test.fdb \
        -e DB_USER=SYSDBA \
        -e DB_PASSWORD=masterkey \
        -e CI=true \
        -w /app \
        "$image" \
        bash -c "$cmd" 2>&1)
    exit_code=$?
    set -e

    # Display last 10 lines
    echo "$output" | tail -10

    if [ $exit_code -eq 0 ]; then
        print_ok "$combo"
        echo "PASS|${combo}" >> /tmp/matrix-results.tmp
        return 0
    elif [ $exit_code -eq 124 ]; then
        print_fail "$combo (timeout after ${TEST_TIMEOUT}s)"
        echo "FAIL|${combo} (timeout)" >> /tmp/matrix-results.tmp
        return 1
    else
        local summary
        summary=$(echo "$output" | grep -E '^Tests: ' | head -1)
        print_fail "$combo  ${summary}"
        echo "FAIL|${combo}  ${summary}" >> /tmp/matrix-results.tmp
        return 1
    fi
}

# Run all FB versions for a single PHP version (in parallel if >1)
run_php_version() {
    local php_ver="$1"
    local fb_ver
    local pids=()
    local logdir="/tmp/matrix-logs"
    mkdir -p "$logdir"

    for fb_ver in "${FB_VERSIONS[@]}"; do
        local logfile="${logdir}/php${php_ver}-fb${fb_ver}.log"
        (
            run_single_combo "$php_ver" "$fb_ver" > "$logfile" 2>&1
        ) &
        pids+=($!)
    done

    # Wait for all
    local all_ok=true
    local i=0
    for pid in "${pids[@]}"; do
        local fb_ver="${FB_VERSIONS[$i]}"
        if ! wait "$pid"; then
            all_ok=false
            # Show output of failed combo
            local logfile="${logdir}/php${php_ver}-fb${fb_ver}.log"
            echo -e "${RED}--- Failed: PHP ${php_ver} / FB ${fb_ver} ---${NC}"
            tail -15 "$logfile"
        fi
        i=$((i + 1))
    done

    # If parallel and all passed, show brief confirmation
    if [ "$all_ok" = true ] && [ ${#pids[@]} -gt 1 ]; then
        print_ok "PHP ${php_ver}: all ${#pids[@]} FB versions passed"
    fi

    [ "$all_ok" = true ]
}

# =============================================================================
# Special Modes
# =============================================================================

do_build() {
    print_header "Building All Docker Images"
    local failed=false
    for php_ver in "${ALL_PHP_VERSIONS[@]}"; do
        build_image "$php_ver" || { print_fail "Failed: PHP ${php_ver}"; failed=true; }
    done
    if [ "$failed" = true ]; then
        print_fail "Some images failed to build"
        return 1
    fi
    print_ok "All images built"
}

do_list() {
    print_header "Matrix Configuration"
    echo ""
    echo "Supported PHP versions: ${ALL_PHP_VERSIONS[*]}"
    echo "Supported FB versions:  ${ALL_FB_VERSIONS[*]}"
    echo ""
    echo "Docker images:"
    for php_ver in "${ALL_PHP_VERSIONS[@]}"; do
        local image
        image=$(get_image_name "$php_ver")
        if docker image inspect "$image" >/dev/null 2>&1; then
            local size
            size=$(docker image inspect "$image" --format='{{.Size}}' 2>/dev/null || echo "?")
            local size_mb=$(( size / 1024 / 1024 ))
            echo -e "  ${GREEN}OK${NC}   ${image} (${size_mb} MB)"
        else
            echo -e "  ${RED}MISS${NC} ${image}"
        fi
    done
    echo ""
    echo "Firebird containers:"
    for fb_ver in "${ALL_FB_VERSIONS[@]}"; do
        local container
        container=$(get_fb_container "$fb_ver")
        if docker ps --filter "name=${container}" --format '{{.Names}}' | grep -q "$container"; then
            echo -e "  ${GREEN}RUN${NC}  ${container} (Firebird ${fb_ver})"
        else
            echo -e "  ${RED}STOP${NC} ${container} (Firebird ${fb_ver})"
        fi
    done
}

do_clean() {
    print_header "Cleaning Up"
    cd "$COMPOSE_DIR"
    docker compose down -v --remove-orphans 2>/dev/null || true
    docker compose --profile fb4 down -v --remove-orphans 2>/dev/null || true
    docker compose --profile fb5 down -v --remove-orphans 2>/dev/null || true
    rm -f /tmp/matrix-results.tmp /tmp/matrix-logs/*.log 2>/dev/null || true
    print_ok "Containers stopped, volumes removed"
}

# =============================================================================
# Argument Parsing
# =============================================================================

show_help() {
    cat << EOF
${BOLD}Matrix Test Runner - Doctrine Firebird Driver${NC}

${BOLD}Usage:${NC} $SCRIPT_NAME [options]

${BOLD}Options:${NC}
      --all                  Full matrix: PHP 8.2-8.5 x FB 3.0/4.0/5.0 (12 combos)
      --php <version>        PHP version(s): 8.2, 8.3, 8.4, 8.5, or "all"
      --fb <version>         Firebird version(s): 3.0, 4.0, 5.0, or "all"
      --suite <name>         PHPUnit test suite: unit, functional, integration
      --filter <pattern>     PHPUnit test name filter
      --coverage             Enable PCOV code coverage (text output)
      --build                Build all 4 Docker images, no tests
      --list                 List versions and Docker image/container status
      --clean                Stop containers, remove volumes
  -h, --help                 Show this help

${BOLD}Defaults:${NC}
  PHP version:    8.4
  Firebird:       3.0
  Test suite:     all
  Timeout:        ${TEST_TIMEOUT}s per combo

${BOLD}Examples:${NC}
  $SCRIPT_NAME                          # PHP 8.4 x FB 3.0 (default)
  $SCRIPT_NAME --all                    # Full 12-combo matrix
  $SCRIPT_NAME --php 8.4                # PHP 8.4 x all FB (parallel)
  $SCRIPT_NAME --fb 4.0                 # All PHP x FB 4.0
  $SCRIPT_NAME --php 8.4 --fb 4.0       # Single combo
  $SCRIPT_NAME --suite unit             # Unit tests only (PHP 8.4 x FB 3.0)
  $SCRIPT_NAME --filter Sequence        # Filter by test name
  $SCRIPT_NAME --coverage               # With coverage report
  $SCRIPT_NAME --build                  # Build images only
  $SCRIPT_NAME --list                   # Show status
  $SCRIPT_NAME --clean                  # Cleanup

${BOLD}Matrix:${NC}
  PHP:     ${ALL_PHP_VERSIONS[*]}
  Firebird: ${ALL_FB_VERSIONS[*]}

${BOLD}Docker Images:${NC}
  Built as dfd-app-php{82,83,84,85} via tests/app/Dockerfile
  php-firebird v12.0.0-rc.11 (commit d4d3851, includes #310 fix)

EOF
    exit 0
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --all)
                PHP_VERSIONS=("${ALL_PHP_VERSIONS[@]}")
                FB_VERSIONS=("${ALL_FB_VERSIONS[@]}")
                shift
                ;;
            --php)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                if [ "$2" = "all" ]; then
                    PHP_VERSIONS=("${ALL_PHP_VERSIONS[@]}")
                else
                    PHP_VERSIONS=("$2")
                fi
                shift 2
                ;;
            --fb)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                if [ "$2" = "all" ]; then
                    FB_VERSIONS=("${ALL_FB_VERSIONS[@]}")
                else
                    FB_VERSIONS=("$2")
                fi
                shift 2
                ;;
            --suite)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                SUITE="$2"
                shift 2
                ;;
            --filter)
                [[ -z "${2:-}" ]] && die "Missing argument for $1"
                FILTER="$2"
                shift 2
                ;;
            --coverage)
                WITH_COVERAGE=true
                shift
                ;;
            --build)
                BUILD_ONLY=true
                shift
                ;;
            --list)
                LIST_ONLY=true
                shift
                ;;
            --clean)
                CLEAN_ONLY=true
                shift
                ;;
            -h|--help)
                SHOW_HELP=true
                shift
                ;;
            *)
                die "Unknown option: $1 (use --help for usage)"
                ;;
        esac
    done
}

# =============================================================================
# Main
# =============================================================================

main() {
    parse_args "$@"

    [ "$SHOW_HELP" = true ] && show_help
    [ "$LIST_ONLY" = true ] && { do_list; exit 0; }
    [ "$CLEAN_ONLY" = true ] && { do_clean; exit 0; }
    [ "$BUILD_ONLY" = true ] && { do_build; exit 0; }

    # Validate versions
    for php_ver in "${PHP_VERSIONS[@]}"; do
        local valid=false
        for v in "${ALL_PHP_VERSIONS[@]}"; do
            [ "$php_ver" = "$v" ] && valid=true
        done
        [ "$valid" = false ] && die "Unsupported PHP version: $php_ver (supported: ${ALL_PHP_VERSIONS[*]})"
    done

    for fb_ver in "${FB_VERSIONS[@]}"; do
        local valid=false
        for v in "${ALL_FB_VERSIONS[@]}"; do
            [ "$fb_ver" = "$v" ] && valid=true
        done
        [ "$valid" = false ] && die "Unsupported Firebird version: $fb_ver (supported: ${ALL_FB_VERSIONS[*]})"
    done

    # Summary
    print_header "Matrix Test Run"
    echo "  PHP:      ${PHP_VERSIONS[*]}"
    echo "  Firebird: ${FB_VERSIONS[*]}"
    [ -n "$SUITE" ] && echo "  Suite:    $SUITE"
    [ -n "$FILTER" ] && echo "  Filter:   $FILTER"
    [ "$WITH_COVERAGE" = true ] && echo "  Coverage: PCOV (text)"
    echo ""

    local total_start
    total_start=$(date +%s)

    # Ensure images exist
    for php_ver in "${PHP_VERSIONS[@]}"; do
        ensure_image "$php_ver" || die "Failed to build image for PHP ${php_ver}"
    done

    # Start Firebird containers
    if [ ${#FB_VERSIONS[@]} -gt 1 ]; then
        start_all_firebird || die "Failed to start Firebird containers"
    else
        start_firebird "${FB_VERSIONS[0]}" || die "Failed to start Firebird ${FB_VERSIONS[0]}"
    fi

    # Run tests
    rm -f /tmp/matrix-results.tmp

    for php_ver in "${PHP_VERSIONS[@]}"; do
        if [ ${#FB_VERSIONS[@]} -gt 1 ]; then
            # Multiple FB versions: run in parallel
            run_php_version "$php_ver" || true
        else
            # Single FB version: run directly
            run_single_combo "$php_ver" "${FB_VERSIONS[0]}" || true
        fi
    done

    # Count from results file (accurate for parallel mode)
    local pass_count fail_count
    pass_count=$(grep -c '^PASS' /tmp/matrix-results.tmp 2>/dev/null || echo 0)
    fail_count=$(grep -c '^FAIL' /tmp/matrix-results.tmp 2>/dev/null || echo 0)

    # Summary
    local total_end
    total_end=$(date +%s)
    local duration=$((total_end - total_start))

    print_header "MATRIX RESULTS"
    echo ""

    if [ -f /tmp/matrix-results.tmp ]; then
        while IFS='|' read -r status combo; do
            if [ "$status" = "PASS" ]; then
                echo -e "  ${GREEN}PASS${NC}  ${combo}"
            else
                echo -e "  ${RED}FAIL${NC}  ${combo}"
            fi
        done < /tmp/matrix-results.tmp
    fi

    echo ""
    echo -e "  ${BOLD}Passed: ${pass_count}  Failed: ${fail_count}  Duration: ${duration}s${NC}"
    echo ""

    if [ $fail_count -gt 0 ]; then
        echo -e "${BOLD}${RED}SOME TESTS FAILED${NC}"
        exit 1
    else
        echo -e "${BOLD}${GREEN}ALL TESTS PASSED${NC}"
        exit 0
    fi
}

main "$@"
