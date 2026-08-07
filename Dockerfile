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
    && docker-php-ext-install pdo_mysql mbstring bcmath gd zip opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

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

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --no-dev --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev
COPY --from=build /app/public/build ./public/build
