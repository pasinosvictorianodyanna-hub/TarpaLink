# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# 1. Front-end assets
#
# Built separately so node_modules never reaches the runtime image. `npm ci`
# rather than `npm install`: the lock file is the contract.
# ---------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------------------
# 2. PHP dependencies
#
# --no-dev because this image only ever runs the application, never the tests.
# --no-scripts because artisan cannot boot before .env exists; the entrypoint
# runs the post-install commands once the container has its environment.
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction \
    --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------------------------------------------------------------------------
# 3. Runtime
#
# nginx and php-fpm in one container: for an app this size a separate web
# container would need a shared volume just to serve public/, which is more
# moving parts than the split is worth. The queue worker is separate, because
# it must be able to restart without dropping HTTP traffic.
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine

# Runtime libs stay; the -dev headers are removed in the same layer so they
# never inflate the image.
RUN apk add --no-cache \
        nginx \
        supervisor \
        icu-libs \
        libzip \
        libpng \
        freetype \
        oniguruma \
        curl \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libpng-dev \
        freetype-dev \
        oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        intl \
        gd \
        zip \
        opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Writable paths. The queue worker and web process run as www-data.
RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["web"]
