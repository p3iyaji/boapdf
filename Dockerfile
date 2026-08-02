# syntax=docker/dockerfile:1
#
# Multi-stage production image for BOA PDF (Laravel + conversion toolchain).
# PHP constraint: ^8.3 <8.4 — stay on 8.3.

ARG TARGETARCH

# -----------------------------------------------------------------------------
# Stage: vendor — Composer dependencies (no-dev)
# -----------------------------------------------------------------------------
FROM php:8.3-cli-bookworm AS vendor

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

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

RUN composer dump-autoload \
    --optimize \
    --classmap-authoritative \
    --no-dev \
    --no-interaction

# -----------------------------------------------------------------------------
# Stage: frontend — Vite / Tailwind build
# -----------------------------------------------------------------------------
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# -----------------------------------------------------------------------------
# Stage: app — PHP-FPM + conversion tools
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS app

ARG TARGETARCH

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    VIRTUAL_ENV=/var/www/html/.venv \
    PATH="/var/www/html/.venv/bin:${PATH}"

WORKDIR /var/www/html

# System libraries for PHP extensions + PDF conversion toolchain
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        ca-certificates \
        gosu \
        git \
        unzip \
        # PHP extension build deps / runtime libs
        $PHPIZE_DEPS \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        # Conversion & OCR
        ghostscript \
        qpdf \
        poppler-utils \
        libreoffice-writer \
        ocrmypdf \
        tesseract-ocr \
        tesseract-ocr-eng \
        fonts-dejavu-core \
        fonts-liberation \
        python3 \
        python3-venv \
        python3-pip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Application PHP settings (overwrite default pool — duplicate [www] pools fail)
COPY docker/php/zz-boa.ini /usr/local/etc/php/conf.d/zz-boa.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Application source (tests excluded via .dockerignore)
COPY --chown=www-data:www-data . .

# Vendor + built frontend assets from earlier stages
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Python venv for pdf2docx (and any other pinned conversion deps)
RUN python3 -m venv /var/www/html/.venv \
    && /var/www/html/.venv/bin/pip install --no-cache-dir --upgrade pip \
    && /var/www/html/.venv/bin/pip install --no-cache-dir -r requirements-conversion.txt \
    && chown -R www-data:www-data /var/www/html/.venv

# Writable dirs for Laravel
RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Run as root so entrypoint can chown named volumes, then drop to www-data
# (php-fpm master stays root; pool workers use www-data from www.conf).

# FPM config test (curl /up needs nginx; this validates the pool)
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD php-fpm -t || exit 1

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# -----------------------------------------------------------------------------
# Stage: nginx — static public/ + reverse proxy to php-fpm
# -----------------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
