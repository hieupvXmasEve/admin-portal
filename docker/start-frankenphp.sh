#!/bin/sh

# ===========================================
# FrankenPHP Development Startup Script
# ===========================================
# For local development with HTTP only

# Exit on any error
set -e

echo "🚀 Starting Swinx Laravel Application with FrankenPHP (Development Mode)..."

# Wait for database connection if DB_HOST is set
if [ ! -z "$DB_HOST" ]; then
    echo "⏳ Waiting for database connection..."
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
            echo "❌ Failed to connect to database after $max_attempts attempts"
            exit 1
        fi
        echo "⏳ Database not ready, waiting... (attempt $attempt/$max_attempts)"
        sleep 3
    done
    echo "✅ Database connection established!"
fi

# Clear all caches for development
echo "🧹 Clearing development caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

# Run database migrations
echo "🗃️ Running database migrations..."
php artisan migrate --force

# Generate application key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Generate Ziggy routes
echo "🗺️ Generating Ziggy routes..."
php artisan ziggy:generate

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    echo "🔗 Creating storage link..."
    php artisan storage:link
fi

# Set proper permissions
echo "🔒 Setting file permissions..."
chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Set FrankenPHP environment variables for development
export FRANKENPHP_NUM_THREADS=${FRANKENPHP_NUM_THREADS:-auto}

echo "✅ Application initialization complete! Starting FrankenPHP..."

# Start FrankenPHP with development configuration
echo "🌐 Starting FrankenPHP on port 80 (HTTP only)..."
exec frankenphp run --config /etc/caddy/Caddyfile
