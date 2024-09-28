#!/bin/sh
set -e  # Stop script on any command failure
docker compose down --remove-orphans && docker compose up -d
sleep 5
echo Running Firebird 2.5 Testsuite:
docker exec --user=application -it -w /app/tests app-doctrine-firebird-driver php -d memory_limit=4G ../vendor/bin/phpunit -c phpunit-firebird25.xml
docker compose down
echo Everything works as expected
