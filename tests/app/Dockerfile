# syntax=docker/dockerfile:1
# =============================================================================
# Doctrine Firebird Driver - Test Application Dockerfile
# =============================================================================
# BuildKit syntax 1 required for optimal caching and security features
# Base images: PHP CLI for test execution
# Extensions: firebird (php-firebird v10.3.6), pcov (fast coverage)
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Builder - Install extensions and dependencies
# -----------------------------------------------------------------------------
ARG PHP_VERSION=8.1
ARG FIREBIRD_VERSION=3.0

# php:8.1-cli-bookworm SHA256 digest for reproducible builds
# To update: docker pull php:${PHP_VERSION}-cli && docker inspect --format='{{index .RepoDigests 0}}' php:${PHP_VERSION}-cli
FROM php:${PHP_VERSION}-cli AS builder

# Use BuildKit cache mounts for faster rebuilds
# hadolint ignore=DL3008
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get install -y --no-install-recommends \
        firebird-dev \
        firebird3.0-utils \
        git \
        zip \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# Install php-firebird extension from source (v10.3.6)
# Note: git describe --tags requires tag to be fetched; --depth 1 clone may not
# include tag refs. We fetch tags explicitly so config.m4 version detection works.
#
# FB_API_VER=40 enables IBatch API support (requires Firebird 4.0+ AND client libraries)
# Only set for Firebird 4.0/5.0 to avoid API mismatch crashes with Firebird 3.0 client libraries
#
# v10.3.5: Fixed server-side prepared statement leak in fbird_query() SELECT
# v10.3.6: Fixed PDO debug printf corruption, test SKIPIF fixes
RUN docker-php-source extract \
    && git clone https://github.com/satwareAG/php-firebird.git /usr/src/php/ext/firebird \
    && git -C /usr/src/php/ext/firebird checkout v10.3.6 \
    && git -C /usr/src/php/ext/firebird fetch --tags --depth 1 \
    && if [ "${FIREBIRD_VERSION}" = "4.0" ] || [ "${FIREBIRD_VERSION}" = "5.0" ]; then \
        echo "Enabling FB_API_VER=40 for Firebird ${FIREBIRD_VERSION}"; \
        export CPPFLAGS="-DFB_API_VER=40"; \
    else \
        echo "Using default API for Firebird ${FIREBIRD_VERSION}"; \
    fi \
    && docker-php-ext-install firebird pcntl \
    && mkdir -p /usr/local/lib/php/Firebird \
    && cp /usr/src/php/ext/firebird/src/Firebird/*.php /usr/local/lib/php/Firebird/ \
    && docker-php-source delete \
    && php -r " \
        \$ver = phpversion('firebird') ?: phpversion('interbase'); \
        if (!\$ver) { \
            fwrite(STDERR, 'ERROR: php-firebird extension not loaded (checked firebird + interbase)' . PHP_EOL); \
            exit(1); \
        } \
        echo 'php-firebird version: ' . \$ver . ' OK' . PHP_EOL; \
    "

# Install PCOV for fast code coverage (2-5x faster than Xdebug)
# Reference: https://github.com/krakjoe/pcov
RUN pecl install pcov \
    && docker-php-ext-enable pcov

# -----------------------------------------------------------------------------
# Stage 2: Runtime - Final test environment
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli AS runtime

# Install runtime dependencies + debugging tools
# hadolint ignore=DL3008
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get install -y --no-install-recommends \
        libfbclient2 \
        firebird3.0-utils \
        git \
        zip \
        unzip \
        strace \
        gdb \
        procps \
    && rm -rf /var/lib/apt/lists/*

# Copy PHP extensions from builder stage
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/lib/php/Firebird/ /usr/local/lib/php/Firebird/

# Configure PCOV - disabled by default to reduce active plugins
RUN { \
        echo "extension=pcov.so"; \
        echo "pcov.enabled=0"; \
        echo "pcov.directory=/app/src"; \
        echo "pcov.exclude=\"~vendor~\""; \
    } > /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Security: Create non-root user for runtime
# UID/GID 1000 matches typical host user for volume permissions
RUN groupadd --gid 1000 appuser \
    && useradd --uid 1000 --gid appuser --shell /bin/bash --create-home appuser \
    && mkdir -p /app \
    && chown appuser:appuser /app

# Set working directory
WORKDIR /app

# Copy entrypoint script
COPY --chmod=755 entrypoint.sh /entrypoint.sh

# Default to non-root user (can be overridden in docker-compose)
USER appuser

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "-a"]

# =============================================================================
# Build Arguments Reference:
# - PHP_VERSION: PHP version to use (8.1, 8.2, 8.3, 8.4, 8.5)
# - FIREBIRD_VERSION: Firebird version (3.0, 4.0, 5.0)
#   Controls whether IBatch API is enabled via FB_API_VER=40
#
# Usage:
#   DOCKER_BUILDKIT=1 docker build --build-arg PHP_VERSION=8.3 --build-arg FIREBIRD_VERSION=4.0 -t test-app .
# =============================================================================