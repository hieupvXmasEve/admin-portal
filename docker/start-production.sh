#!/bin/sh

# ===========================================
# Production Startup Script for Swinx Laravel Application
# ===========================================
# This script handles the startup sequence for production containers

set -e

echo "🚀 Starting Swinx Laravel Application (Production Mode)..."

# Wait for database connection if DB_HOST is set
if [ ! -z "$DB_HOST" ]; then
    echo "⏳ Waiting for database connection..."
    max_attempts=60
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
            echo "❌ Failed to connect to database after $max_attempts attempts"
            exit 1
        fi
        echo "⏳ Database not ready, waiting... (attempt $attempt/$max_attempts)"
        sleep 2
    done
    echo "✅ Database connection established!"
fi

# Run database migrations
echo "🗃️ Running database migrations..."
php artisan migrate --force

# Create cache table if using database cache
if [ "$CACHE_DRIVER" = "database" ]; then
    echo "📊 Creating cache table..."
    php artisan cache:table --quiet || true
    php artisan migrate --force
fi

# Clear all caches
echo "🧹 Clearing application caches..."
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan cache:clear

# Cache configurations for production
echo "⚡ Caching configurations for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Generate Ziggy routes
echo "🗺️ Generating Ziggy routes..."
php artisan ziggy:generate

# Optimize for production
echo "🔧 Optimizing for production..."
php artisan optimize

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage link..."
    php artisan storage:link
fi

# Set proper permissions (as much as possible in container)
echo "🔒 Setting file permissions..."
chmod -R 755 storage bootstrap/cache
find storage -type f -exec chmod 644 {} \;
find bootstrap/cache -type f -exec chmod 644 {} \;

echo "✅ Application initialization complete!"

# Start cron daemon for Laravel scheduler
echo "⏰ Starting cron daemon..."
crond -l 2 -f &

# Start supervisor to manage PHP-FPM and Nginx
echo "🎯 Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
