# ====================================================
# Multi-Stage Dockerfile for UniLibrary (Laravel + Vue 3)
# Optimized for Render.com and Supabase PostgreSQL
# ====================================================

# ----------------------------------------------------
# Stage 1: Build Frontend Assets with Node 20
# ----------------------------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.ts tsconfig.json index.html* ./
COPY public public
RUN npm run build

# ----------------------------------------------------
# Stage 2: Install Composer Dependencies
# ----------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ----------------------------------------------------
# Stage 3: Production Web Server (PHP 8.3 FPM + Nginx)
# ----------------------------------------------------
FROM php:8.3-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk update && apk add --no-cache \
    nginx \
    gettext \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        bcmath \
        intl \
        opcache

# Use production PHP configuration and ensure PHP-FPM workers preserve container environment variables
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy vendor dependencies from composer stage
COPY --from=vendor /app/vendor ./vendor

# Copy compiled frontend assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# Copy Nginx template and entrypoint script
COPY docker/nginx.conf /etc/nginx/templates/default.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Default container port (Render injects $PORT at runtime)
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
