# ===========================================
# FrankenPHP Development Dockerfile
# ===========================================
# Based on official FrankenPHP image with Laravel optimizations

FROM dunglas/frankenphp:latest AS frankenphp-base

# Set environment variables for development
ENV APP_ENV=local
ENV APP_DEBUG=true
ENV FRANKENPHP_CONFIG=""
ENV FRANKENPHP_NUM_THREADS=auto

# Install additional system dependencies
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    curl \
    git \
    default-mysql-client \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Install additional PHP extensions that aren't in the base image
RUN install-php-extensions \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    intl \
    mbstring \
    opcache \
    bcmath \
    redis

# Configure PHP for development
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory to /app (FrankenPHP convention)
WORKDIR /app

# Copy application code
COPY . .

# Install PHP dependencies (include dev dependencies for development)
RUN composer install --optimize-autoloader --no-interaction --prefer-dist

# Install Node.js dependencies and build frontend assets
RUN npm ci

# Build frontend assets
RUN npm run build

# Set proper permissions for FrankenPHP
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Copy FrankenPHP configuration
COPY Caddyfile.dev /etc/caddy/Caddyfile

# Copy custom PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create startup script for FrankenPHP
COPY docker/start-frankenphp.sh /start.sh
RUN chmod +x /start.sh

# Expose port 80 for development (HTTP only)
EXPOSE 80

# Health check for development
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start FrankenPHP
CMD ["/start.sh"]
