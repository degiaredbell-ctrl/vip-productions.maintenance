# ---- Stage 1: build frontend (Vite) ----
FROM node:20-alpine AS nodebuild
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---- Stage 2: PHP-FPM ----
FROM php:8.3-fpm-alpine

RUN apk add --no-cache git unzip libzip-dev libpng-dev libjpeg-turbo-dev \
    freetype-dev icu-dev oniguruma-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_mysql mbstring zip gd intl bcmath opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# Hasil Vite ditaruh di /var/www/html/public-image, BUKAN ./public/build.
# /var/www/html/public ditutupi named volume `app_public` (lihat docker-compose.yml)
# dan volume hanya diisi dari image saat pertama kali dibuat. Kalau build ditaruh
# di ./public/build, ia tertimpa volume dan tidak akan pernah sampai ke nginx.
# app-entrypoint.sh menyalin folder ini ke volume tiap container start.
COPY --from=nodebuild /app/public/build ./public-image/build

COPY docker/app-entrypoint.sh /usr/local/bin/app-entrypoint.sh
RUN chmod +x /usr/local/bin/app-entrypoint.sh

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && composer install --no-dev --optimize-autoloader --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["app-entrypoint.sh"]
CMD ["php-fpm"]
