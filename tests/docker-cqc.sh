#!/bin/sh
set -e  # Stop script on any command failure
docker compose down --remove-orphans && docker compose up -d
echo Running Code Quality Checks
docker exec --user=application -it app-doctrine-firebird-driver tests/cqc.sh
docker compose down
echo Excelent Code Quality
