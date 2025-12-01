#!/bin/sh
set -e  # Stop script on any command failure
# Determine the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

docker compose run --rm app composer update --prefer-lowest
docker compose run --rm app php -dxdebug.mode=coverage vendor/bin/phpunit  -c tests/phpunit-firebird25.xml --stop-on-error --stop-on-failure --stop-on-warning
