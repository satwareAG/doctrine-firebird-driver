#!/bin/sh
set -e

# Determine the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/.."

echo ''|vendor/bin/phpcs || vendor/bin/phpcbf
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/psalm --alter --issues=MissingReturnType,MissingParamType --no-cache || true
vendor/bin/psalm --set-baseline=psalm-baseline.xml --no-cache
vendor/bin/phpunit -c tests/phpunit.xml
vendor/bin/phpunit -c tests/phpunit-firebird25.xml
vendor/bin/phpunit -c tests/phpunit-firebird4.xml
vendor/bin/phpunit -c tests/phpunit-firebird5.xml
