ARG PHP_VERSION=8.1
FROM php:8.1-cli AS base

# Install system dependencies and PHP extensions
RUN apt-get update  \
    && apt-get install -y firebird-dev firebird3.0-utils git zip unzip \
    && docker-php-source extract \
    && git clone --branch v6.2.0 --depth 1 https://github.com/satwareAG/php-firebird.git /usr/src/php/ext/interbase \
    && docker-php-ext-install interbase pcntl

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
