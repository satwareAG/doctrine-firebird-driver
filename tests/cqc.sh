#!/bin/sh
set -e
echo ''|vendor/bin/phpcs
vendor/bin/phpstan
vendor/bin/psalm
vendor/bin/phpunit
