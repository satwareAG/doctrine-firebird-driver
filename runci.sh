#!/bin/bash
cd /driver
export FIREBIRD_TEST_DSN="fbird:host=firebird30;dbname=/firebird/data/test.fdb;charset=UTF8"
export FIREBIRD_TEST_USER=SYSDBA FIREBIRD_TEST_PASSWORD=masterkey
export DB_HOST=firebird30 DB_DBNAME=/firebird/data/test.fdb DB_USER=SYSDBA DB_PASSWORD=masterkey DB_CHARSET=UTF8
exec php -d extension=/ext/modules/firebird.so -d extension=/ext/pdo_fbird/modules/pdo_fbird.so vendor/bin/phpunit --no-coverage "$@"
