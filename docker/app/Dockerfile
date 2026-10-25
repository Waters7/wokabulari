# syntax=docker/dockerfile:1

# ---- base: PHP 8.4 + Nginx (serversideup) dengan ekstensi yang dibutuhkan
FROM serversideup/php:8.4-fpm-nginx AS base
USER root
RUN install-php-extensions intl gd exif bcmath redis zip pcntl
ENV TZ=Asia/Makassar \
    PHP_UPLOAD_MAX_FILE_SIZE=60M \
    PHP_POST_MAX_SIZE=64M \
    PHP_MEMORY_LIMIT=512M \
    PHP_DATE_TIMEZONE=Asia/Makassar
USER www-data

# ---- dev: UID/GID disamakan dengan pengguna host agar tidak ada masalah izin file
FROM base AS dev
ARG USER_ID=1000
ARG GROUP_ID=1000
USER root
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
 && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx
USER www-data

# ---- vendor (produksi)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---- assets (produksi)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
COPY scripts ./scripts
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---- prod
FROM base AS prod
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=false
COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=vendor /app/vendor /var/www/html/vendor
COPY --chown=www-data:www-data --from=assets /app/public/build /var/www/html/public/build
COPY --chown=www-data:www-data --from=assets /app/public/models /var/www/html/public/models
# Tautan public/storage dibuat di image agar tidak hilang setiap build ulang (berkas tetap di volume storage-app).
RUN ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage
