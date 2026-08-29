# syntax=docker/dockerfile:1

FROM php:8.4-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    unzip \
    git \
    nodejs \
    npm \
    chromium \
    ffmpeg \
    && docker-php-ext-install pdo_mysql mbstring bcmath gd zip opcache pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# www-data's own passwd home dir, owned by root by default. Chromium (via
# Browsershot, run by php-fpm's www-data workers) reads this actual home
# directory for its crashpad crash-handler database - not the $HOME env var
# - and fails outright ("chrome_crashpad_handler: --database is required")
# if it can't write there. Confirmed empirically: no combination of Chromium
# launch flags or Linux capabilities (SYS_PTRACE, file capabilities) fixes
# this: it's specifically this directory's ownership.
RUN chown www-data:www-data /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Isolated from the frontend's package.json/node_modules (resources/, built by
# the separate `build` stage below) - this only exists so Browsershot (PDF
# report generation) has puppeteer-core to drive the system Chromium above.
COPY docker/browsershot/package.json docker/browsershot/package.json
RUN cd docker/browsershot && npm install --omit=dev

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ---------------------------------------------------------------------------
FROM base AS dev

RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader

COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs
RUN composer dump-autoload --optimize

# ---------------------------------------------------------------------------
FROM node:22-bookworm AS build

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install
COPY resources resources
COPY vite.config.js tsconfig.json ./
COPY public public
RUN npm run build

# ---------------------------------------------------------------------------
FROM base AS production

COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool-overrides.conf

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --no-dev --optimize-autoloader

COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs
RUN composer dump-autoload --optimize --no-dev
COPY --from=build /app/public/build ./public/build
