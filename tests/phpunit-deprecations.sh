#!/bin/sh
docker exec --user=application -it -w /app/tests app-doctrine-firebird-driver php -dxdebug.mode=coverage ../vendor/bin/phpunit --deprecation
