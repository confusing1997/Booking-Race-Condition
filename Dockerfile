FROM php:8.2-fpm-alpine

# 1. Cài đặt các thư viện hệ thống cần thiết
# linux-headers cực kỳ quan trọng trên Alpine để build các extension như Redis hay PCNTL
RUN apk add --no-cache \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    linux-headers \
    bash

# 2. Cài đặt các extension PHP cốt lõi
# Mình thêm pcntl (để quản lý process) và bcmath (để Horizon tính toán thời gian chính xác)
RUN docker-php-ext-install pdo pdo_mysql pcntl bcmath

# 3. Cài đặt Redis qua PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# 4. Copy Composer từ image chính thức
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www