#!/bin/sh
set -e  # Stop script on any command failure
# Determine the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

docker compose down --remove-orphans && docker compose up -d
sleep 1
echo Running Firebird 3 Testsuite:
docker exec --user=application -w /app/tests app-doctrine-firebird-driver php ../vendor/bin/phpunit -c phpunit.xml "$@"
docker compose down
echo All tests executed
