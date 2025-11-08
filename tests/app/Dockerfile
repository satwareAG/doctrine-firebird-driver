ARG PHP_VERSION=8.1
FROM webdevops/php-dev:${PHP_VERSION}

RUN apt-get update  \
    && apt-get install -y firebird-dev firebird3.0-utils \
    && docker-php-source extract \
    && git clone --branch v3.0.1 --depth 1 https://github.com/FirebirdSQL/php-firebird.git /usr/src/php/ext/interbase \
    && docker-php-ext-install interbase

