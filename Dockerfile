# ──────────────────────────────────────────────
# Stage 1: base — PHP 8.4-FPM + системные зависимости
# ──────────────────────────────────────────────
FROM php:8.4-fpm-bookworm AS base

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/root/.composer

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libssl-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        mbstring \
        opcache \
        pcntl \
    && pecl install redis-6.1.0 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.8.4 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ──────────────────────────────────────────────
# Stage 2: dev — для локальной разработки
# ──────────────────────────────────────────────
FROM base AS dev

# Xdebug для coverage
RUN pecl install xdebug-3.4.0 \
    && docker-php-ext-enable xdebug

# PHP конфиг для dev
RUN echo "xdebug.mode=coverage,debug" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=no" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/xdebug.ini

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/99-dev.ini
COPY docker/php/fpm-dev.conf /usr/local/etc/php-fpm.d/zzz-dev.conf

# ──────────────────────────────────────────────
# Stage 3: deps — установка зависимостей
# ──────────────────────────────────────────────
FROM base AS deps

COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev

# ──────────────────────────────────────────────
# Stage 4: prod — production image
# ──────────────────────────────────────────────
FROM base AS prod

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-prod.ini
COPY docker/php/fpm-prod.conf /usr/local/etc/php-fpm.d/zzz-prod.conf

COPY --from=deps /app/vendor ./vendor
COPY --from=deps /app .

RUN chown -R www-data:www-data /app/runtime /app/public

EXPOSE 9000

CMD ["php-fpm"]
