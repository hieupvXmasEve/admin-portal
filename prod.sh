#!/bin/bash

# ===========================================
# Production Environment Management Script
# ===========================================
# Manages the production Docker environment with safety checks

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
COMPOSE_FILE="docker-compose.production.yml"
ENV_FILE=".env.docker.production"
PROJECT_NAME="swinx-production"

# Helper functions
print_header() {
    echo -e "${BLUE}=== $1 ===${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Safety check for production
production_safety_check() {
    print_warning "🚨 PRODUCTION ENVIRONMENT DETECTED 🚨"
    print_warning "This will affect the live production system!"
    echo ""
    read -p "Are you sure you want to proceed? Type 'PRODUCTION' to continue: " -r
    echo
    if [[ $REPLY != "PRODUCTION" ]]; then
        print_error "Production operation cancelled for safety."
        exit 1
    fi
}

# Check if environment file exists
check_env_file() {
    if [ ! -f "$ENV_FILE" ]; then
        print_error "Production environment file $ENV_FILE not found!"
        print_error "Please create the production environment file with proper settings."
        exit 1
    fi
    
    # Check for required production variables
    if ! grep -q "APP_ENV=production" "$ENV_FILE"; then
        print_error "APP_ENV must be set to 'production' in $ENV_FILE"
        exit 1
    fi
    
    if grep -q "APP_DEBUG=true" "$ENV_FILE"; then
        print_error "APP_DEBUG must be set to 'false' in production!"
        exit 1
    fi
}

# Pre-deployment checks
pre_deployment_checks() {
    print_header "Running Pre-deployment Checks"
    
    # Check if required files exist
    local required_files=("Dockerfile.production" "Caddyfile.prod" "$ENV_FILE")
    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            print_error "Required file missing: $file"
            exit 1
        fi
    done
    
    # Check if backup directory exists
    if [ ! -d "backups" ]; then
        print_warning "Creating backups directory..."
        mkdir -p backups
    fi
    
    print_success "Pre-deployment checks passed"
}

# Main commands
deploy() {
    print_header "Deploying to Production"
    production_safety_check
    check_env_file
    pre_deployment_checks
    
    # Create backup before deployment
    backup
    
    print_header "Building and Starting Production Environment"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" up -d --build
    
    print_success "Production deployment completed!"
    print_warning "Application should be available at your configured domain"
    
    # Show initial logs
    echo ""
    print_header "Deployment Logs"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" logs --tail=20
}

start() {
    print_header "Starting Production Environment"
    production_safety_check
    check_env_file
    
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" up -d
    print_success "Production environment started!"
}

stop() {
    print_header "Stopping Production Environment"
    production_safety_check
    
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" down
    print_success "Production environment stopped!"
}

restart() {
    print_header "Restarting Production Environment"
    production_safety_check
    
    stop
    sleep 5
    start
}

logs() {
    print_header "Production Environment Logs"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" logs -f "${2:-}"
}

status() {
    print_header "Production Environment Status"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" ps
    echo ""
    print_header "Resource Usage"
    docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}" \
        $(docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" ps -q) 2>/dev/null || echo "No containers running"
}

shell() {
    print_header "Accessing Production Application Container Shell"
    print_warning "You are accessing the PRODUCTION environment!"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app bash
}

mysql() {
    print_header "Accessing Production MySQL Shell"
    print_warning "You are accessing the PRODUCTION database!"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec db mysql -u swinx_prod_user -p swinburne
}

# Backup and maintenance
backup() {
    print_header "Creating Production Database Backup"
    BACKUP_FILE="backups/production-backup-$(date +%Y%m%d_%H%M%S).sql"
    mkdir -p backups
    
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec db mysqldump \
        -u swinx_prod_user -p swinburne > "$BACKUP_FILE"
    
    # Compress backup
    gzip "$BACKUP_FILE"
    print_success "Database backup created: ${BACKUP_FILE}.gz"
}

maintenance_mode() {
    case "${2:-}" in
        on)
            print_header "Enabling Maintenance Mode"
            docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app php artisan down
            print_success "Maintenance mode enabled"
            ;;
        off)
            print_header "Disabling Maintenance Mode"
            docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app php artisan up
            print_success "Maintenance mode disabled"
            ;;
        *)
            print_error "Usage: $0 maintenance [on|off]"
            exit 1
            ;;
    esac
}

# Monitoring
health_check() {
    print_header "Production Health Check"
    
    # Check container health
    local unhealthy=$(docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" ps --filter "health=unhealthy" -q)
    if [ -n "$unhealthy" ]; then
        print_error "Unhealthy containers detected!"
        docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" ps
        exit 1
    fi
    
    # Check application response
    local app_url=$(grep "SERVER_NAME" "$ENV_FILE" | cut -d'=' -f2)
    if [ -n "$app_url" ]; then
        if curl -f -s "https://$app_url/up" > /dev/null; then
            print_success "Application is responding"
        else
            print_error "Application is not responding"
            exit 1
        fi
    fi
    
    print_success "Health check passed"
}

# Laravel specific commands
artisan() {
    print_header "Running Production Artisan Command: ${*:2}"
    print_warning "This will run on PRODUCTION environment!"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app php artisan "${@:2}"
}

# Help function
show_help() {
    echo "Production Environment Management Script"
    echo ""
    echo "⚠️  WARNING: This script manages the PRODUCTION environment!"
    echo "    All commands require explicit confirmation for safety."
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Deployment Commands:"
    echo "  deploy      Full production deployment with backup"
    echo "  start       Start the production environment"
    echo "  stop        Stop the production environment"
    echo "  restart     Restart the production environment"
    echo ""
    echo "Monitoring Commands:"
    echo "  status      Show environment status and resource usage"
    echo "  logs        Show logs (optionally for specific service)"
    echo "  health      Run comprehensive health check"
    echo ""
    echo "Maintenance Commands:"
    echo "  backup      Create database backup"
    echo "  maintenance Enable/disable maintenance mode [on|off]"
    echo "  shell       Access application container shell"
    echo "  mysql       Access MySQL shell"
    echo ""
    echo "Laravel Commands:"
    echo "  artisan     Run artisan commands"
    echo ""
    echo "Examples:"
    echo "  $0 deploy"
    echo "  $0 status"
    echo "  $0 backup"
    echo "  $0 maintenance on"
    echo "  $0 health"
}

# Main script logic
case "${1:-}" in
    deploy)
        deploy
        ;;
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        restart
        ;;
    status)
        status
        ;;
    logs)
        logs "$@"
        ;;
    health)
        health_check
        ;;
    shell)
        shell
        ;;
    mysql)
        mysql
        ;;
    backup)
        backup
        ;;
    maintenance)
        maintenance_mode "$@"
        ;;
    artisan)
        artisan "$@"
        ;;
    help|--help|-h)
        show_help
        ;;
    "")
        print_error "No command specified. Use '$0 help' for usage information."
        exit 1
        ;;
    *)
        print_error "Unknown command: $1"
        print_warning "Use '$0 help' for usage information."
        exit 1
        ;;
esac
