FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip bcmath opcache \
    && pecl install redis && docker-php-ext-enable redis

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
