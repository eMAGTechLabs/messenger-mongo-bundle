FROM php:8.0-alpine

WORKDIR /var/www/html

RUN apk update \
  && apk upgrade \
  && apk add --no-cache git zip make autoconf g++ openssl-dev

RUN pecl -q install mongodb-1.9.1 xdebug \
  && docker-php-ext-enable mongodb

ENV COMPOSER_MEMORY_LIMIT=-1

RUN curl --silent --show-error https://getcomposer.org/installer | php \
  && mv composer.phar /usr/local/bin/composer \
  && chmod o+x /usr/local/bin/composer