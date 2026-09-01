# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Stage 0: dependências PHP (Ziggy é importado pelo Vite em resources/js/app.js)
# -----------------------------------------------------------------------------
FROM composer:2 AS composer-deps
WORKDIR /app
COPY composer.json composer.lock ./
# Imagem composer:2 não inclui ext-gd (exigida pelo phpoffice/phpword). Este estágio só
# materializa vendor/ para o build do Vite (Ziggy); o estágio php reinstala com PHP real.
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

# -----------------------------------------------------------------------------
# Stage 1: frontend (Vite / Vue / PWA)
# -----------------------------------------------------------------------------
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=composer-deps /app/vendor ./vendor
ENV NODE_OPTIONS=--max-old-space-size=4096
RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: PHP-FPM (Laravel)
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS php

# Community cedo: ffmpeg e alguns pacotes nao existem no main em builders minimos (Coolify).
RUN set -eux; \
    ALPINE_VER="$(cut -d. -f1,2 /etc/alpine-release)"; \
    grep -qs "/community" /etc/apk/repositories \
        || echo "https://dl-cdn.alpinelinux.org/alpine/v${ALPINE_VER}/community" >> /etc/apk/repositories

# Deps de build separadas das extensoes (menor pico de RAM; falhas mais legiveis no log).
RUN apk add --no-cache \
    git curl zip unzip libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev \
    oniguruma-dev postgresql-dev icu-dev \
    linux-headers $PHPIZE_DEPS

# -j2 evita OOM no builder remoto: nproc alto compila gd/intl/pgsql em paralelo e estoura RAM.
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j2 pdo_pgsql pgsql bcmath intl opcache mbstring \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 gd zip

RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear /root/.pearrc

RUN apk del $PHPIZE_DEPS linux-headers

# ffmpeg (community): chunking de audio em Entrevistas / Reunioes.
RUN apk add --no-cache ffmpeg

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/talents.ini

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .
COPY --from=frontend /app/public/build ./public/build

# Parser do espelho RHID (EspelhoPdfIngestService): pdfplumber + cartao_pdf_parser (paridade TALENTS6)
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

# Stage nginx: ver docker/Dockerfile.nginx (reutiliza talents-php:prod; evita rebuild duplicado no Coolify).
