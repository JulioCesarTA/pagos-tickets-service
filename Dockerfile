# ============ STAGE 1: deps + runtime unificado (misma versión PHP) ============
FROM php:8.3-cli-alpine AS build
RUN apk add --no-cache postgresql-dev libzip-dev oniguruma-dev icu-dev git unzip \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pcntl mbstring zip opcache intl gd
# Instalar composer en esta misma imagen (misma versión PHP que el runtime)
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json ./
RUN composer install --no-interaction --no-scripts --no-dev --prefer-dist --ignore-platform-req=php
COPY app ./app
COPY public ./public
COPY bootstrap ./bootstrap
RUN composer dump-autoload --optimize --no-dev \
    && rm -f /app/vendor/composer/platform_check.php \
    && sed -i "s|require __DIR__ . '/platform_check.php';||g" /app/vendor/composer/autoload_real.php

# ============ STAGE 2: runtime limpio ============
FROM php:8.3-cli-alpine
RUN apk add --no-cache postgresql-dev libzip-dev oniguruma-dev icu-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev libpng libjpeg-turbo freetype \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pcntl mbstring zip opcache intl gd
RUN addgroup -S app && adduser -S app -G app
WORKDIR /app
COPY --from=build /app /app
RUN chown -R app:app /app
USER app
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=5s --start-period=15s --retries=5 \
  CMD wget -q -O - http://localhost:8080/health || exit 1
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
