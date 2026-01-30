FROM php:8.2-fpm-alpine

RUN docker-php-ext-install pdo pdo_mysql
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

copy --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www