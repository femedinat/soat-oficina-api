# syntax=docker/dockerfile:1.7
# Multi-stage: base -> dev (local/CI, com PCOV p/ cobertura) | prod (imagem enxuta, non-root)
ARG PHP_VERSION=8.4

############################ base ############################
FROM php:${PHP_VERSION}-fpm-alpine AS base

RUN apk add --no-cache icu-libs libpq libzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql bcmath opcache zip pcntl \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

############################ dev #############################
FROM base AS dev

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
 && pecl install pcov \
 && docker-php-ext-enable pcov \
 && apk del .build-deps

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-app.ini

# Usuário com o mesmo UID/GID do host -> sem problemas de permissão no bind mount
ARG UID=1000
ARG GID=1000
RUN addgroup -g "${GID}" app && adduser -D -u "${UID}" -G app app
USER app

########################## vendor ############################
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

############################ prod ############################
FROM base AS prod

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --no-dev \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
