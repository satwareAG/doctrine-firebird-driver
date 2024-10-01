#!/bin/sh
set -e

# Determine the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/.."

echo ''|vendor/bin/phpcs
vendor/bin/phpstan analyse
vendor/bin/psalm
vendor/bin/phpunit -c tests/phpunit.xml
vendor/bin/phpunit -c tests/phpunit-firebird25.xml
