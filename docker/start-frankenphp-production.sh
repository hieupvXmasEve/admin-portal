#!/bin/sh

# ===========================================
# FrankenPHP Production Startup Script
# ===========================================
# For production deployment with HTTPS and Let's Encrypt

# Exit on any error
set -e

echo "🚀 Starting Swinx Laravel Application with FrankenPHP (Production Mode)..."

# Skip database connection for now to test FrankenPHP
echo "⚠️ Skipping database connection check for FrankenPHP testing..."
attempt=30  # Set to max to skip database operations

# Clear all caches first (before migrations)
echo "🧹 Clearing all caches before migrations..."
php artisan config:clear || true
php artisan view:clear || true
php artisan route:clear || true
php artisan cache:clear || true
php artisan event:clear || true

# Run database migrations (only if database is available)
if [ $attempt -lt $max_attempts ]; then
    echo "🗃️ Running database migrations..."
    php artisan migrate --force

    # Create cache table if using database cache
    echo "📊 Creating cache table if needed..."
    php artisan cache:table --quiet || true
    php artisan migrate --force
else
    echo "⚠️ Skipping database migrations (no database connection)"
fi

# Clear caches (now safe to do)
echo "🧹 Clearing application caches..."
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Only clear cache if using database cache driver
if [ "$CACHE_DRIVER" = "database" ]; then
    echo "🧹 Clearing database cache..."
    php artisan cache:clear
else
    echo "ℹ️ Skipping database cache clear (using $CACHE_DRIVER driver)"
fi

# Cache configurations for production (always in production)
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

# Set proper permissions
echo "🔒 Setting file permissions..."
chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Set FrankenPHP environment variables for production
export FRANKENPHP_NUM_THREADS=${FRANKENPHP_NUM_THREADS:-auto}
export SERVER_NAME=${SERVER_NAME:-localhost}
export ACME_EMAIL=${ACME_EMAIL:-""}

# Validate required environment variables for production
if [ "$APP_ENV" = "production" ] && [ -z "$SERVER_NAME" ]; then
    echo "❌ SERVER_NAME environment variable is required for production"
    exit 1
fi

if [ "$APP_ENV" = "production" ] && [ "$SERVER_NAME" != "localhost" ] && [ -z "$ACME_EMAIL" ]; then
    echo "⚠️ ACME_EMAIL is recommended for Let's Encrypt certificates in production"
fi

echo "✅ Application initialization complete! Starting FrankenPHP..."

# Start FrankenPHP with production configuration
echo "🌐 Starting FrankenPHP on ports 80 and 443 (HTTP/HTTPS)..."
echo "🔒 Domain: $SERVER_NAME"
echo "📧 ACME Email: ${ACME_EMAIL:-'Not set'}"

exec frankenphp run --config /etc/caddy/Caddyfile
