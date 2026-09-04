#!/usr/bin/env bash
# Retry wrapper for composer against transient upstream failures
# (packagist dist 429 / codeload 504 during downloads). #179.
#
# Usage: bash scripts/ci/composer-retry.sh update --prefer-stable ...
# (all arguments are passed through to composer)
#
# Fails the job only after MAX_ATTEMPTS consecutive failures.
set -u

MAX_ATTEMPTS=3
DELAY=15

attempt=1
until composer "$@"; do
    if [ "$attempt" -ge "$MAX_ATTEMPTS" ]; then
        echo "composer $* failed after $MAX_ATTEMPTS attempts - giving up" >&2
        exit 1
    fi
    echo "composer $* failed (attempt $attempt/$MAX_ATTEMPTS) - likely transient rate limit/outage (#179), retrying in ${DELAY}s" >&2
    sleep "$DELAY"
    attempt=$((attempt + 1))
    DELAY=$((DELAY * 2))
done
