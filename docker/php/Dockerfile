FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libpq-dev \
    oniguruma-dev \
    unzip \
    zip

RUN docker-php-ext-install \
    intl \
    opcache \
    pdo \
    pdo_pgsql \
    pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-custom.ini

WORKDIR /var/www/App
