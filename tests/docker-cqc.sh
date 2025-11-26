#!/bin/sh
set -e  # Stop script on any command failure
# Determine the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

docker compose down --remove-orphans && docker compose up -d
echo Running Code Quality Checks
docker exec --user=application  app-doctrine-firebird-driver tests/cqc.sh
docker compose down
echo Excelent Code Quality
