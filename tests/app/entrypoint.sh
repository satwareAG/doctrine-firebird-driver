#!/bin/bash
# =============================================================================
# Docker Entrypoint - Doctrine Firebird Driver Test Container
# =============================================================================
# Purpose: Initialize container environment and execute commands
# Security: Runs as non-root user by default (see Dockerfile USER directive)
# =============================================================================

set -euo pipefail

# -----------------------------------------------------------------------------
# Environment Setup
# -----------------------------------------------------------------------------

# Ensure vendor directory exists and is writable for composer
if [[ -d "/app" ]] && [[ ! -d "/app/vendor" ]]; then
    mkdir -p /app/vendor 2>/dev/null || true
fi

# Ensure Git doesn't complain about dubious ownership in Docker volumes
if [[ -d "/app/.git" ]] || [[ -d "/app" ]]; then
    git config --global --add safe.directory /app 2>/dev/null || true
fi

# Wait for Firebird containers if this is a test run
wait_for_firebird() {
    local host="$1"
    local port="${2:-3050}"
    local timeout="${3:-30}"
    local start_time
    start_time=$(date +%s)
    
    echo "Waiting for Firebird at ${host}:${port}..."
    
    while ! nc -z "$host" "$port" 2>/dev/null; do
        local elapsed
        elapsed=$(($(date +%s) - start_time))
        if [[ $elapsed -ge $timeout ]]; then
            echo "Warning: Timeout waiting for Firebird at ${host}:${port}" >&2
            return 1
        fi
        sleep 1
    done
    
    echo "Firebird at ${host}:${port} is ready"
    return 0
}

# Check if we should wait for Firebird services
if [[ "${WAIT_FOR_FIREBIRD:-false}" == "true" ]]; then
    wait_for_firebird "firebird3" 3050 30 || true
    wait_for_firebird "firebird4" 3050 30 || true
    wait_for_firebird "firebird5" 3050 30 || true
fi

# -----------------------------------------------------------------------------
# Execute Command
# -----------------------------------------------------------------------------

exec "$@"
