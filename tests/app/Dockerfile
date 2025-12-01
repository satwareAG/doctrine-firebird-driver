ARG PHP_VERSION=8.1
FROM php:8.1-cli AS base

RUN apt-get update  \
    && apt-get install -y firebird-dev firebird3.0-utils git zip unzip \
    && docker-php-source extract \
    && git clone --branch fix/validate-6.2.0-release --depth 1 https://github.com/satwareAG/php-firebird.git /usr/src/php/ext/interbase \
    && docker-php-ext-install interbase pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set up entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "-a"]
