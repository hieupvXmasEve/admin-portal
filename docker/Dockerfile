FROM dunglas/frankenphp:1-php8.4-bookworm AS development

ENV APP_ENV=local \
    APP_DEBUG=true \
    COMPOSER_ALLOW_SUPERUSER=1 \
    FRANKENPHP_CONFIG="" \
    FRANKENPHP_NUM_THREADS=auto

RUN apt-get update && apt-get install -y \
    curl \
    default-mysql-client \
    git \
    npm \
    unzip \
    zip \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    mbstring \
    mysqli \
    opcache \
    pdo_mysql \
    redis \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .
RUN composer install --no-interaction --prefer-dist --no-scripts

RUN npm ci

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && mkdir -p /app/storage /app/bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

COPY docker/Caddyfile.dev /etc/caddy/Caddyfile
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/start-frankenphp.sh /usr/local/bin/start-frankenphp
RUN chmod +x /usr/local/bin/start-frankenphp

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -fsS http://localhost/health || exit 1

CMD ["/usr/local/bin/start-frankenphp"]
