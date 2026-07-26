#!/bin/sh

set -eu

echo "Starting FrankenPHP production container..."

# ACME/TLS Caddyfile.prod uses {env.SERVER_NAME}; Caddyfile.proxy listens on :80 only.
if grep -q '{env.SERVER_NAME}' /etc/caddy/Caddyfile 2>/dev/null; then
    if [ -z "${SERVER_NAME:-}" ]; then
        echo "SERVER_NAME must be set for TLS Caddyfile."
        exit 1
    fi
fi

if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    attempt=0
    max_attempts=60
    until php -r "
        try {
            new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    "; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "Database did not become ready in time."
            exit 1
        fi
        sleep 2
    done
fi

php artisan optimize:clear || true
php artisan package:discover --ansi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    echo "Generating Passport OAuth keys..."
    php artisan passport:keys --force
fi

php artisan ziggy:generate || true

if [ -L public/storage ] && [ ! -e public/storage ]; then
    rm public/storage
fi

if [ ! -L public/storage ]; then
    php artisan storage:link || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache
sh /app/scripts/fix-oauth-key-permissions.sh /app/storage

exec frankenphp run --config /etc/caddy/Caddyfile
