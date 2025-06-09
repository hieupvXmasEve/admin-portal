#!/bin/bash

# ===========================================
# Production Deployment Script
# ===========================================
# This script handles zero-downtime deployment
# for the Swinx application using Docker Compose

set -e

# Configuration
COMPOSE_FILE="docker-compose.production.yml"
BACKUP_DIR="./backups"
LOG_FILE="./logs/deploy.log"
HEALTH_CHECK_URL="http://localhost/health"
MAX_HEALTH_CHECKS=30
HEALTH_CHECK_INTERVAL=10

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

# Create necessary directories
mkdir -p "$BACKUP_DIR" "$(dirname "$LOG_FILE")" ./ssl ./storage/logs/nginx

log "🚀 Starting production deployment..."

# Check if Docker and Docker Compose are available
if ! command -v docker &> /dev/null; then
    error "Docker is not installed or not in PATH"
fi

if ! command -v docker-compose &> /dev/null; then
    error "Docker Compose is not installed or not in PATH"
fi

# Check if required files exist
if [ ! -f "$COMPOSE_FILE" ]; then
    error "Docker Compose file not found: $COMPOSE_FILE"
fi

if [ ! -f ".env.docker.production" ]; then
    error "Production environment file not found: .env.docker.production"
fi

# Load environment variables
if [ -f ".env.docker.image" ]; then
    source .env.docker.image
    log "Loaded image configuration: $DOCKER_IMAGE"
else
    warning "No image configuration found, using default"
fi

# Backup current database
log "📦 Creating database backup..."
if docker-compose -f "$COMPOSE_FILE" ps db | grep -q "Up"; then
    BACKUP_FILE="$BACKUP_DIR/mysql_backup_$(date +%Y%m%d_%H%M%S).sql"
    docker-compose -f "$COMPOSE_FILE" exec -T db mysqldump \
        -u root -p"${DB_ROOT_PASSWORD:-root_password}" \
        --all-databases --routines --triggers > "$BACKUP_FILE" || warning "Database backup failed"
    
    if [ -f "$BACKUP_FILE" ]; then
        success "Database backup created: $BACKUP_FILE"
        # Keep only last 7 backups
        find "$BACKUP_DIR" -name "mysql_backup_*.sql" -type f -mtime +7 -delete
    fi
else
    log "Database container not running, skipping backup"
fi

# Pull latest images
log "📥 Pulling latest Docker images..."
docker-compose -f "$COMPOSE_FILE" pull

# Build and start services with zero downtime
log "🔄 Deploying new version..."

# Start new containers alongside old ones
docker-compose -f "$COMPOSE_FILE" up -d --no-deps --scale app=2 app

# Wait for new container to be healthy
log "⏳ Waiting for new application container to be healthy..."
HEALTH_CHECKS=0
while [ $HEALTH_CHECKS -lt $MAX_HEALTH_CHECKS ]; do
    if curl -f "$HEALTH_CHECK_URL" &>/dev/null; then
        success "Application is healthy!"
        break
    fi
    
    HEALTH_CHECKS=$((HEALTH_CHECKS + 1))
    log "Health check $HEALTH_CHECKS/$MAX_HEALTH_CHECKS failed, retrying in ${HEALTH_CHECK_INTERVAL}s..."
    sleep $HEALTH_CHECK_INTERVAL
done

if [ $HEALTH_CHECKS -eq $MAX_HEALTH_CHECKS ]; then
    error "Application failed health checks after deployment"
fi

# Scale down old containers
log "🔄 Scaling down old containers..."
docker-compose -f "$COMPOSE_FILE" up -d --scale app=1

# Update other services
log "🔄 Updating other services..."
docker-compose -f "$COMPOSE_FILE" up -d

# Run database migrations
log "🗃️ Running database migrations..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force

# Clear and cache configurations
log "🧹 Clearing and caching configurations..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan config:clear
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan config:cache
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan route:cache
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan view:cache

# Generate Ziggy routes
log "🗺️ Generating Ziggy routes..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan ziggy:generate

# Clean up old Docker images
log "🧹 Cleaning up old Docker images..."
docker image prune -f

# Final health check
log "🏥 Performing final health check..."
if curl -f "$HEALTH_CHECK_URL" &>/dev/null; then
    success "🎉 Deployment completed successfully!"
    
    # Send deployment notification (optional)
    if [ -n "$SLACK_WEBHOOK_URL" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data '{"text":"🚀 Swinx application deployed successfully!"}' \
            "$SLACK_WEBHOOK_URL" || warning "Failed to send Slack notification"
    fi
else
    error "Final health check failed"
fi

# Display running services
log "📊 Current running services:"
docker-compose -f "$COMPOSE_FILE" ps

log "✅ Deployment script completed"
