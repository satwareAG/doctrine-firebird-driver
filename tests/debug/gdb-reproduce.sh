#!/bin/bash
# =============================================================================
# GDB Reproduce Script - Doctrine Firebird Driver
# =============================================================================
# Purpose: Run PHPUnit tests with GDB in batch mode to capture segmentation faults
# Output: Redirects all GDB output to tests/var/logs/gdb.log
# =============================================================================

set -euo pipefail

# Project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${PROJECT_ROOT}"

# Create logs directory if it doesn't exist
mkdir -p tests/var/logs

# GDB log file
GDB_LOG="tests/var/logs/gdb.log"

echo "Running GDB in batch mode (output redirected to ${GDB_LOG})..."

# Run GDB inside Docker app container
# We use -T for non-interactive mode and pass the command to bash
docker compose -f tests/docker-compose.yml run --rm -T app bash -c "
    gdb -batch \
        -ex 'handle SIGSEGV stop' \
        -ex 'handle SIGABRT stop' \
        -ex 'run -d pcov.enabled=0 vendor/bin/phpunit -c tests/phpunit-firebird4.xml --no-coverage' \
        -ex 'bt full' \
        -ex 'info registers' \
        --args php
" > "${GDB_LOG}" 2>&1

echo "GDB session complete. Results stored in ${GDB_LOG}"
