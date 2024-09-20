#!/bin/sh
docker compose down --remove-orphans && docker compose up -d
echo Running Firebird 2.5 Testsuite:
docker exec --user=application -it -w /app/tests app-doctrine-firebird-driver php -d memory_limit=4G ../vendor/bin/phpunit  -c phpunit-firebird25.xml
echo Firebird 2.5 finished
echo Running Firebird 3 Testsuite:
docker exec --user=application -it -w /app/tests app-doctrine-firebird-driver php -d memory_limit=4G ../vendor/bin/phpunit -c phpunit.xml
echo Firebird 3 finished
echo Running Firebird 4 Testsuite:
docker exec --user=application -it -w /app/tests app-doctrine-firebird-driver php -d memory_limit=4G ../vendor/bin/phpunit -c phpunit-firebird4.xml
echo Firebird 4 finished
echo Running Firebird 5 Testsuite:
docker exec --user=application -it -w /app/tests app-doctrine-firebird-driver php -d memory_limit=4G ../vendor/bin/phpunit -c phpunit-firebird5.xml
echo Firebird 5 finished
docker compose down
