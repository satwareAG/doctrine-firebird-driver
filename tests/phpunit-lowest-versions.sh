#!/bin/sh
docker exec --user=application -it -w /app  app-doctrine-firebird-driver composer update --prefer-lowest
docker exec --user=application -it -w /app/tests -e SYMFONY_DEPRECATIONS_HELPER=disabled app-doctrine-firebird-driver php -dxdebug.mode=coverage ../vendor/bin/phpunit
