# syntax=docker/dockerfile:1
# =============================================================================
# Doctrine Firebird Driver - Test Application Dockerfile
# =============================================================================
# BuildKit syntax 1 required for optimal caching and security features
# Base images: PHP CLI for test execution
# Extensions: firebird (php-firebird v7.0.0-rc.37), pcov (fast coverage)
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Builder - Install extensions and dependencies
# -----------------------------------------------------------------------------
ARG PHP_VERSION=8.1

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

# Install php-firebird extension from source (includes Issue #55 IS_RESOURCE arginfo fix for PHPStan SIGSEGV)
RUN docker-php-source extract \
    && git clone --branch v7.0.0-rc.37 --depth 1 \
        https://github.com/satwareAG/php-firebird.git \
        /usr/src/php/ext/firebird \
    && docker-php-ext-install firebird pcntl \
    && docker-php-source delete

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

# Configure PCOV for optimal performance
RUN { \
        echo "pcov.enabled=1"; \
        echo "pcov.directory=/app/src"; \
        echo "pcov.exclude=\"~vendor~\""; \
    } >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

# Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Security: Create non-root user for runtime
# UID/GID 1000 matches typical host user for volume permissions
RUN groupadd --gid 1000 appuser \
    && useradd --uid 1000 --gid appuser --shell /bin/bash --create-home appuser

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
#
# Usage:
#   DOCKER_BUILDKIT=1 docker build --build-arg PHP_VERSION=8.3 -t test-app .
# =============================================================================
