#!/bin/bash
cd /driver
export FIREBIRD_TEST_DSN="fbird:host=firebird30;dbname=/firebird/data/test.fdb;charset=UTF8"
export FIREBIRD_TEST_USER=SYSDBA
export FIREBIRD_TEST_PASSWORD=masterkey
export DB_HOST=firebird30
export DB_DBNAME=/firebird/data/test.fdb
export DB_USER=SYSDBA
export DB_PASSWORD=masterkey
export DB_CHARSET=UTF8
export ASAN_OPTIONS=detect_leaks=0:halt_on_error=1:abort_on_error=0:print_stacktrace=1
exec php \
    -d extension=/ext/modules/firebird.so \
    -d extension=/ext/pdo_fbird/modules/pdo_fbird.so \
    vendor/bin/phpunit --no-coverage "$@"
