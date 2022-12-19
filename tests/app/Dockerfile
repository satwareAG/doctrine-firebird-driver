FROM webdevops/php-dev:8.1

RUN apt-get update --allow-releaseinfo-change
RUN apt-get install -y software-properties-common
RUN apt-get install -y firebird-dev firebird3.0-utils
RUN docker-php-source extract
RUN git clone --branch v3.0.0 --depth 1 https://github.com/FirebirdSQL/php-firebird.git /usr/src/php/ext/interbase
RUN docker-php-ext-install interbase
# RUN cd /usr/src/php/ext/interbase && phpize && CPPFLAGS=-I/usr/include/firebird ./configure && make
