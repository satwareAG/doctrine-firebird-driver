#!/usr/bin/env bash
# check-prerequisites.sh — Verify doctrine-firebird-driver dev environment
set -euo pipefail

PASS=0
FAIL=0

check() {
    local name="$1"
    local cmd="$2"
    if eval "$cmd" &>/dev/null; then
        echo "  ✅ $name"
        ((PASS++)) || true
    else
        echo "  ❌ $name"
        ((FAIL++)) || true
    fi
}

echo "=== doctrine-firebird-driver Prerequisites ==="
echo ""

echo "--- Runtime ---"
check "PHP 8.1+"          "php -r 'exit(version_compare(PHP_VERSION, \"8.1.0\", \">=\") ? 0 : 1);'"
check "Composer"          "composer --version"
check "Docker"            "docker --version"
check "Docker Compose"    "docker compose version"
check "git"               "git --version"

echo ""
echo "--- PHP Extensions ---"
check "ext-firebird"      "php -m | grep -q firebird"
check "ext-posix"         "php -m | grep -q posix"

echo ""
echo "--- Vendor Tools ---"
check "PHPUnit"           "test -f vendor/bin/phpunit"
check "PHPStan"           "test -f vendor/bin/phpstan"
check "Psalm"             "test -f vendor/bin/psalm"
check "PHP_CodeSniffer"   "test -f vendor/bin/phpcs"
check "Rector"            "test -f vendor-bin/rector/vendor/bin/rector"

echo ""
echo "--- Project Structure ---"
check ".specify/ exists"  "test -d .specify"
check "constitution.md"   "test -f .specify/memory/constitution.md"
check "src/ exists"       "test -d src"
check "tests/ exists"     "test -d tests"

echo ""
echo "=== Summary: ${PASS} passed, ${FAIL} failed ==="
if [[ $FAIL -gt 0 ]]; then
    echo "Run 'composer install' to install vendor tools."
    exit 1
fi
