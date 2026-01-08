#!/bin/bash
#
# SIGSEGV Isolation Test Runner
# 
# Runs minimal PHP scripts to isolate the root cause of SIGSEGV (exit 139)
# that occurs during PHP shutdown with php-firebird persistent connections.
#
# Usage: ./tests/debug/run-sigsegv-tests.sh
#
# Expected results:
#   - test-connect-regular.php: Exit 0 (no crash)
#   - test-pconnect-*.php: Exit 139 (SIGSEGV) if bug present
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

echo -e "${BLUE}=== SIGSEGV Isolation Test Runner ===${NC}"
echo "php-firebird version: v7.0.0-rc.29"
echo "Testing persistent connection shutdown behavior"
echo ""

# Ensure Docker services are running
echo -e "${YELLOW}Starting Docker services...${NC}"
cd "${PROJECT_ROOT}"
docker compose -f tests/docker-compose.yml up -d firebird3

# Wait for Firebird to be ready
echo -e "${YELLOW}Waiting for Firebird to be ready...${NC}"
sleep 5

# Build the app container
echo -e "${YELLOW}Building app container...${NC}"
docker compose -f tests/docker-compose.yml build app

# Create test database
echo ""
echo -e "${YELLOW}Creating test database...${NC}"
docker compose -f tests/docker-compose.yml run --rm app php /app/tests/debug/setup-test-database.php
if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to create test database${NC}"
    exit 1
fi

# Array to store results
declare -A RESULTS

# Function to run a single test
run_test() {
    local test_name="$1"
    local test_file="$2"
    
    echo ""
    echo -e "${BLUE}--- Running: ${test_name} ---${NC}"
    
    # Run the test and capture exit code
    set +e
    docker compose -f tests/docker-compose.yml run --rm app php "/app/tests/debug/${test_file}" 2>&1
    local exit_code=$?
    set -e
    
    echo ""
    
    # Interpret exit code
    if [ $exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ Exit code: 0 (Clean shutdown)${NC}"
        RESULTS["$test_name"]="EXIT_0"
    elif [ $exit_code -eq 139 ]; then
        echo -e "${RED}✗ Exit code: 139 (SIGSEGV - Segmentation Fault)${NC}"
        RESULTS["$test_name"]="SIGSEGV"
    elif [ $exit_code -eq 137 ]; then
        echo -e "${YELLOW}! Exit code: 137 (SIGKILL - Killed)${NC}"
        RESULTS["$test_name"]="SIGKILL"
    else
        echo -e "${YELLOW}? Exit code: ${exit_code} (Unknown)${NC}"
        RESULTS["$test_name"]="EXIT_${exit_code}"
    fi
    
    return 0
}

# Run all tests
echo ""
echo -e "${BLUE}=== Running Tests ===${NC}"

run_test "Regular Connection (Control)" "test-connect-regular.php"
run_test "Persistent Connection (Single)" "test-pconnect-single.php"
run_test "Persistent Connection (Multiple)" "test-pconnect-multiple.php"
run_test "Persistent Connection (Transaction)" "test-pconnect-transaction.php"

# Print summary
echo ""
echo -e "${BLUE}=== Test Summary ===${NC}"
echo ""
printf "%-45s %s\n" "Test" "Result"
printf "%-45s %s\n" "----" "------"

for test_name in "Regular Connection (Control)" "Persistent Connection (Single)" "Persistent Connection (Multiple)" "Persistent Connection (Transaction)"; do
    result="${RESULTS[$test_name]}"
    
    if [ "$result" == "EXIT_0" ]; then
        printf "%-45s ${GREEN}%s${NC}\n" "$test_name" "✓ Clean (exit 0)"
    elif [ "$result" == "SIGSEGV" ]; then
        printf "%-45s ${RED}%s${NC}\n" "$test_name" "✗ SIGSEGV (exit 139)"
    else
        printf "%-45s ${YELLOW}%s${NC}\n" "$test_name" "? $result"
    fi
done

echo ""
echo -e "${BLUE}=== Analysis ===${NC}"

# Analyze results
if [ "${RESULTS["Regular Connection (Control)"]}" == "EXIT_0" ] && \
   [ "${RESULTS["Persistent Connection (Single)"]}" == "SIGSEGV" ]; then
    echo -e "${RED}CONFIRMED: SIGSEGV is caused by persistent connections${NC}"
    echo "The issue is in php-firebird's MSHUTDOWN phase when cleaning up"
    echo "persistent connections (pconnect)."
    echo ""
    echo "Root cause (from Issue #51):"
    echo "  _php_fbird_close_plink() calls zend_hash_str_del() on executor"
    echo "  globals that may already be destroyed during MSHUTDOWN."
elif [ "${RESULTS["Persistent Connection (Single)"]}" == "EXIT_0" ]; then
    echo -e "${GREEN}No SIGSEGV detected with persistent connections.${NC}"
    echo "The issue may be fixed in this version or requires different conditions."
else
    echo -e "${YELLOW}Inconclusive results. Further investigation needed.${NC}"
fi

echo ""
echo "Report this to: https://github.com/satwareAG/php-firebird/issues/54"
