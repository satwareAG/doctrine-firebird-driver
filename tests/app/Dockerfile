ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-cli AS base

# Install system dependencies and PHP extensions
# Using php-firebird v7.0.0-rc.1 which removes all legacy interbase/ibase references
RUN apt-get update  \
    && apt-get install -y firebird-dev firebird3.0-utils git zip unzip \
    && docker-php-source extract \
    && git clone --branch v7.0.0-rc.1 --depth 1 https://github.com/satwareAG/php-firebird.git /usr/src/php/ext/firebird \
    && docker-php-ext-install firebird pcntl

# Install PCOV for fast code coverage (2-5x faster than Xdebug)
# Reference: https://github.com/krakjoe/pcov
RUN pecl install pcov \
    && docker-php-ext-enable pcov

# Configure PCOV for optimal performance
RUN echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini \
    && echo "pcov.directory=/app/src" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set up entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "-a"]
