# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# 1. PHP base — extensions compiled once, reused by both later stages.
#
# Composer runs against this image rather than composer:2 because Filament
# requires ext-intl, and a separate vendor stage on composer:2 either fails or
# needs --ignore-platform-reqs, which would hide a genuinely missing extension.
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS phpbase

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

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# 2. PHP dependencies
# ---------------------------------------------------------------------------
FROM phpbase AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && rm /usr/bin/composer

# ---------------------------------------------------------------------------
# 3. Front-end assets
#
# After `vendor`, not before: the panel theme imports Filament's own theme.css
# out of vendor, so the CSS build needs Composer to have run. Building this
# stage first is what broke the image the first time.
# ---------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=vendor /var/www/html/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------------------
# 4. Runtime
#
# nginx and php-fpm in one container: splitting them would need a shared volume
# just to serve public/. The queue worker is a separate service because it must
# restart without dropping HTTP.
# ---------------------------------------------------------------------------
FROM phpbase AS runtime

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

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

# Shell form, not exec form: ${PORT} has to be expanded at run time, because the
# port is chosen by whatever platform starts the container.
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT:-80}/up" || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["web"]
