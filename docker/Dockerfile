# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Stage 0: dependências PHP (Ziggy é importado pelo Vite em resources/js/app.js)
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# -----------------------------------------------------------------------------
# Stage 1: frontend (Vite / Vue / PWA)
# -----------------------------------------------------------------------------
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=composer-deps /app/vendor ./vendor
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: PHP-FPM (Laravel)
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS php

RUN apk add --no-cache \
    git curl zip unzip libzip-dev libpng-dev oniguruma-dev postgresql-dev icu-dev \
    linux-headers $PHPIZE_DEPS \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_pgsql pgsql bcmath intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/talents.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .
COPY --from=frontend /app/public/build ./public/build

# Parser do espelho RHID (EspelhoPdfIngestService): PyMuPDF + pdfplumber
RUN apk add --no-cache python3 py3-pip \
    && python3 -m pip install --break-system-packages --no-cache-dir -r tools/rhid-espelho-parser/requirements.txt

RUN cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan package:discover --ansi \
    && rm -f .env

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod +x /var/www/html/docker/entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["php-fpm", "-F"]

# -----------------------------------------------------------------------------
# Stage 3: Nginx (estáticos + proxy para PHP-FPM)
# -----------------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=php /var/www/html/public /var/www/html/public

RUN rm -f /var/www/html/public/storage \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
