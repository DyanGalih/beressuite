# ==============================================================================
# Stage 1: Frontend Asset Compilation (Node 24 + Vite)
# ==============================================================================
FROM node:24-alpine AS frontend-builder

WORKDIR /app

# Install npm dependencies using cache
COPY package*.json ./
RUN npm ci

# Copy frontend configuration and source assets
COPY vite.config.ts tsconfig.json postcss.config.js tailwind.config.js ./
COPY resources ./resources
COPY public ./public

# Build production assets into public/build
RUN npm run build

# ==============================================================================
# Stage 2: PHP Vendor Dependencies (Composer 2)
# ==============================================================================
FROM composer:2 AS vendor-builder

WORKDIR /app

# Copy dependency definitions and autoload sources
COPY composer.json composer.lock ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY database ./database

# Install production dependencies without dev tools
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ==============================================================================
# Stage 3: Production Runtime (PHP 8.3-FPM)
# ==============================================================================
FROM php:8.3-fpm

LABEL maintainer="BeresSuite Team <dyan.galih@gmail.com>" \
      description="Production container image for BeresSuite Invoicing Application"

# Install production system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libmagickwand-dev \
    mariadb-client \
    zip \
    unzip \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick redis \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Composer binary from official image for administrative tasks
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application source code
COPY . /var/www

# Copy compiled PHP vendor dependencies from Stage 2
COPY --from=vendor-builder /app/vendor /var/www/vendor

# Copy compiled frontend assets and generated service worker from Stage 1
COPY --from=frontend-builder /app/public/build /var/www/public/build
COPY --from=frontend-builder /app/public/sw.js /app/public/workbox-*.js /var/www/public/

# Ensure proper storage directories exist and have permissions for www-data
RUN mkdir -p /var/www/storage/framework/cache/data \
             /var/www/storage/framework/sessions \
             /var/www/storage/framework/views \
             /var/www/storage/logs \
             /var/www/storage/app/public \
             /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
