FROM php:8.3-alpine

WORKDIR /var/www/html

RUN apk update \
  && apk upgrade \
  && apk add --no-cache git zip make autoconf g++ openssl-dev linux-headers \
  && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS

RUN pecl -q install mongodb-1.17.2  \
  && docker-php-ext-enable mongodb \
  && pecl -q install xdebug  \
  && docker-php-ext-enable xdebug

ENV COMPOSER_MEMORY_LIMIT=-1

RUN curl --silent --show-error https://getcomposer.org/installer | php \
  && mv composer.phar /usr/local/bin/composer \
  && chmod o+x /usr/local/bin/composer

ENTRYPOINT ["tail", "-f", "/dev/null"]