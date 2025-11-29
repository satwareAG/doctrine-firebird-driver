#!/bin/sh
set -e  # Stop script on any command failure
# Determine the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

docker compose down --remove-orphans && docker compose up -d
sleep 1
echo Running Firebird 2.5 Testsuite:
docker compose run --rm app php vendor/bin/phpunit -c tests/phpunit-firebird25.xml --stop-on-error --stop-on-failure --stop-on-warning
echo Firebird 2.5 finished
echo Running Firebird 3 Testsuite:
docker compose run --rm app php vendor/bin/phpunit -c tests/phpunit.xml --stop-on-error --stop-on-failure --stop-on-warning
echo Firebird 3 finished
echo Running Firebird 4 Testsuite:
docker compose run --rm app php vendor/bin/phpunit -c tests/phpunit-firebird4.xml --stop-on-error --stop-on-failure --stop-on-warning
echo Firebird 4 finished
echo Running Firebird 5 Testsuite:
docker compose run --rm app php vendor/bin/phpunit -c tests/phpunit-firebird5.xml --stop-on-error --stop-on-failure --stop-on-warning
echo Firebird 5 finished
docker compose down
echo Everything works as expected
