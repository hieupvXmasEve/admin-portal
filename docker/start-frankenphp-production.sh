#!/bin/sh

set -eu

echo "Starting FrankenPHP production container..."

if [ -z "${SERVER_NAME:-}" ]; then
    echo "SERVER_NAME must be set."
    exit 1
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

php artisan ziggy:generate || true

if [ ! -L public/storage ]; then
    php artisan storage:link || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

exec frankenphp run --config /etc/caddy/Caddyfile
