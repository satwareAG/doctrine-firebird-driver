#!/usr/bin/env bash
# =============================================================================
# Common helpers for test scripts
# =============================================================================
# Sourced by: run-matrix.sh, docker-cqc.sh, cqc.sh
#
# Usage: source "$(dirname "${BASH_SOURCE[0]}")/lib/common.sh"
# =============================================================================

# Colors (only when stdout is a terminal with color support)
if [[ -t 1 ]] && command -v tput &>/dev/null && [[ $(tput colors) -ge 8 ]]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    BLUE='\033[0;34m'
    CYAN='\033[0;36m'
    BOLD='\033[1m'
    NC='\033[0m'
else
    RED=''
    GREEN=''
    YELLOW=''
    BLUE=''
    CYAN=''
    BOLD=''
    NC=''
fi

# Print helpers
print_header() {
    echo ""
    echo -e "${BOLD}${BLUE}================================================================${NC}"
    echo -e "${BOLD}${CYAN}  $1${NC}"
    echo -e "${BOLD}${BLUE}================================================================${NC}"
}

print_step() {
    echo -e "${YELLOW}>>> $1${NC}"
}

print_success() {
    echo -e "${GREEN}OK  $1${NC}"
}

# Alias for scripts that use print_ok instead of print_success
print_ok() {
    print_success "$@"
}

print_fail() {
    echo -e "${RED}FAIL $1${NC}"
}

print_error() {
    echo -e "${RED}ERROR: $1${NC}" >&2
}

print_info() {
    echo -e "${CYAN}    $1${NC}"
}

die() {
    print_error "$1"
    exit "${2:-1}"
}

# Print a summary box (PASS or FAIL)
print_summary_box() {
    local status="$1"
    local message="$2"
    echo ""
    if [[ "$status" == "PASS" ]]; then
        echo -e "${BOLD}${GREEN}================================================================${NC}"
        echo -e "${BOLD}${GREEN}  ${message}${NC}"
        echo -e "${BOLD}${GREEN}================================================================${NC}"
    else
        echo -e "${BOLD}${RED}================================================================${NC}"
        echo -e "${BOLD}${RED}  ${message}${NC}"
        echo -e "${BOLD}${RED}================================================================${NC}"
    fi
}

# =============================================================================
# Docker Cleanup
# =============================================================================

# Remove all containers (including profiled FB4/FB5) and their volumes.
# Must be called from the docker-compose directory.
#
# Key insight: `docker compose down -v` WITHOUT `--profile fb4 --profile fb5`
# does NOT stop FB4/FB5 containers or remove their volumes, because those
# services are behind profiles. This leaves stale test data that causes
# 500+ errors on subsequent runs.
cleanup_all() {
    local compose_dir="$1"
    if [[ -n "$compose_dir" ]]; then
        cd "$compose_dir" || return 1
    fi

    # Stop ALL services (including profiled FB4/FB5) and remove volumes
    docker compose --profile fb4 --profile fb5 down -v --remove-orphans 2>/dev/null || true

    # Belt-and-suspenders: force-remove any stuck containers and volumes
    docker rm -f dfd-firebird3 dfd-firebird4 dfd-firebird5 dfd-app 2>/dev/null || true
    docker volume rm doctrine-firebird-test_fb3-data \
                   doctrine-firebird-test_fb4-data \
                   doctrine-firebird-test_fb5-data 2>/dev/null || true
}

# Remove volumes for a specific Firebird version only (keeps others running).
# Useful between FB version tests in Phase 5 of docker-cqc.sh.
cleanup_fb_version() {
    local fb_ver="$1"
    local container volume

    case "$fb_ver" in
        3.0) container="dfd-firebird3";  volume="doctrine-firebird-test_fb3-data" ;;
        4.0) container="dfd-firebird4";  volume="doctrine-firebird-test_fb4-data" ;;
        5.0) container="dfd-firebird5";  volume="doctrine-firebird-test_fb5-data" ;;
        *) return 1 ;;
    esac

    docker rm -f "$container" 2>/dev/null || true
    docker volume rm "$volume" 2>/dev/null || true
}
