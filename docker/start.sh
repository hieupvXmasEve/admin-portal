#!/bin/sh

# Exit on any error
set -e

echo "Starting Laravel application..."

# Wait for database connection if DB_HOST is set
if [ ! -z "$DB_HOST" ]; then
    echo "Waiting for database connection..."
    max_attempts=30
    attempt=0
    until php -r "
        try {
            \$pdo = new PDO('mysql:host=$DB_HOST;port=$DB_PORT', '$DB_USERNAME', '$DB_PASSWORD');
            echo 'Database connection successful';
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " > /dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ $attempt -ge $max_attempts ]; then
            echo "Failed to connect to database after $max_attempts attempts"
            exit 1
        fi
        echo "Database not ready, waiting... (attempt $attempt/$max_attempts)"
        sleep 3
    done
    echo "Database connection established!"
fi

# Run database migrations first
echo "Running database migrations..."
php artisan migrate --force

# Create cache table if using database cache
echo "Creating cache table if needed..."
php artisan cache:table --quiet || true
php artisan migrate --force

# Clear caches (now safe to do)
echo "Clearing application caches..."
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Only clear cache if using database cache driver
if [ "$CACHE_DRIVER" = "database" ]; then
    echo "Clearing database cache..."
    php artisan cache:clear
else
    echo "Skipping database cache clear (using $CACHE_DRIVER driver)"
fi

# Cache configurations only in production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configurations for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "Development environment - skipping config cache"
fi

# Generate Ziggy routes
echo "Generating Ziggy routes..."
php artisan ziggy:generate

# Set proper permissions
echo "Setting file permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "Application ready! Starting services..."

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm -D

# Start Nginx in foreground (this keeps container running)
echo "Starting Nginx..."
exec nginx -g "daemon off;"
