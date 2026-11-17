# AGAPAY cloud copy: one image for any host (Railway, Render, Fly.io, a VPS). The host only supplies environment
# variables (APP_KEY, APP_URL, DB_*, BACKUP_*); see DEVELOPMENT.md "Cloud server".

# ---- 1. Front-end assets (Vite + Tailwind)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- 2. PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---- 3. The server: PHP 8.5 + Apache, the office copy's extensions, and mysqldump for the backups
FROM php:8.5-apache
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd intl zip exif bcmath opcache \
    && apt-get update && apt-get install -y --no-install-recommends mariadb-client \
    && rm -rf /var/lib/apt/lists/* \
    && a2dismod -f mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite headers

COPY docker/php.ini /usr/local/etc/php/conf.d/agapay.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
RUN rm -rf portable tests node_modules \
    && mkdir -p storage/app/private storage/app/backups storage/app/backup-temp storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --chmod=0755 docker/start.sh /usr/local/bin/agapay-start

ENV APP_ENV=production APP_DEBUG=false AGAPAY_EDITION=cloud TRUSTED_PROXIES=* \
    SESSION_DRIVER=database SESSION_LIFETIME=30 SESSION_SECURE_COOKIE=true \
    LOG_CHANNEL=stderr QUEUE_CONNECTION=sync PORT=8080

EXPOSE 8080
CMD ["agapay-start"]
