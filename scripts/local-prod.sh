#!/bin/bash

# ===========================================
# Local Production Environment Management Script
# ===========================================
# Manages the local production Docker environment for testing production configurations

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
COMPOSE_FILE="docker-compose.local-prod.yml"
ENV_FILE=".env.docker.production"
PROJECT_NAME="swinx-local-prod"

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

# Check if environment file exists
check_env_file() {
    if [ ! -f "$ENV_FILE" ]; then
        print_warning "Environment file $ENV_FILE not found!"
        if [ -f "env.docker.example" ]; then
            print_warning "Copying from env.docker.example..."
            cp env.docker.example "$ENV_FILE"
            print_success "Created $ENV_FILE from example"
            print_warning "Please update production settings in $ENV_FILE"
        else
            print_error "No example environment file found. Please create $ENV_FILE manually."
            exit 1
        fi
    fi
}

# Check SSL certificates
check_ssl() {
    if [ ! -d "ssl" ]; then
        print_warning "SSL directory not found. Creating self-signed certificates for local testing..."
        mkdir -p ssl
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout ssl/privkey.pem \
            -out ssl/fullchain.pem \
            -subj "/C=AU/ST=Victoria/L=Melbourne/O=Swinburne/OU=IT/CN=swinx.test"
        print_success "Self-signed certificates created for local testing"
    fi
}

# Main commands
start() {
    print_header "Starting Local Production Environment"
    check_env_file
    check_ssl
    
    print_warning "This will start production-like environment with HTTPS"
    print_warning "Make sure to add '127.0.0.1 swinx.test' to your /etc/hosts file"
    
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" up -d
    
    print_success "Local production environment started!"
    print_warning "Application will be available at: https://swinx.test"
    print_warning "Database is available at: localhost:3306"
    
    # Show logs for a few seconds
    echo ""
    print_header "Initial Logs (press Ctrl+C to stop following)"
    sleep 2
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" logs -f --tail=50
}

stop() {
    print_header "Stopping Local Production Environment"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" down
    print_success "Local production environment stopped!"
}

restart() {
    print_header "Restarting Local Production Environment"
    stop
    sleep 2
    start
}

rebuild() {
    print_header "Rebuilding Local Production Environment"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" down
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" build --no-cache
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" up -d
    print_success "Local production environment rebuilt and started!"
}

logs() {
    print_header "Local Production Environment Logs"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" logs -f "${2:-}"
}

status() {
    print_header "Local Production Environment Status"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" ps
    echo ""
    print_header "Resource Usage"
    docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}" \
        $(docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" ps -q) 2>/dev/null || echo "No containers running"
}

shell() {
    print_header "Accessing Application Container Shell"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app bash
}

mysql() {
    print_header "Accessing MySQL Shell"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec db mysql -u swinx_prod_user -pSwinxProd2024!@#SecurePass swinburne
}

clean() {
    print_header "Cleaning Local Production Environment"
    print_warning "This will remove all containers, volumes, and images for this project!"
    read -p "Are you sure? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" down -v --remove-orphans
        docker system prune -f
        print_success "Local production environment cleaned!"
    else
        print_warning "Clean operation cancelled."
    fi
}

# Production testing commands
test_ssl() {
    print_header "Testing SSL Configuration"
    curl -k -I https://swinx.test || print_error "SSL test failed"
}

test_performance() {
    print_header "Running Performance Test"
    print_warning "This will run a basic performance test against the application"
    ab -n 100 -c 10 https://swinx.test/ || print_error "Performance test failed (install apache2-utils)"
}

backup() {
    print_header "Creating Database Backup"
    BACKUP_FILE="backups/local-prod-backup-$(date +%Y%m%d_%H%M%S).sql"
    mkdir -p backups
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec db mysqldump -u swinx_prod_user -pSwinxProd2024!@#SecurePass swinburne > "$BACKUP_FILE"
    print_success "Database backup created: $BACKUP_FILE"
}

# Laravel specific commands
artisan() {
    print_header "Running Artisan Command: ${*:2}"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app php artisan "${@:2}"
}

# Help function
show_help() {
    echo "Local Production Environment Management Script"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  start       Start the local production environment"
    echo "  stop        Stop the local production environment"
    echo "  restart     Restart the local production environment"
    echo "  rebuild     Rebuild and start the environment"
    echo "  status      Show environment status and resource usage"
    echo "  logs        Show logs (optionally for specific service)"
    echo "  shell       Access application container shell"
    echo "  mysql       Access MySQL shell"
    echo "  clean       Remove all containers, volumes, and images"
    echo ""
    echo "Production Testing:"
    echo "  test-ssl    Test SSL configuration"
    echo "  test-perf   Run performance test"
    echo "  backup      Create database backup"
    echo ""
    echo "Laravel Commands:"
    echo "  artisan     Run artisan commands"
    echo ""
    echo "Examples:"
    echo "  $0 start"
    echo "  $0 logs app"
    echo "  $0 test-ssl"
    echo "  $0 backup"
    echo "  $0 artisan optimize"
}

# Main script logic
case "${1:-}" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        restart
        ;;
    rebuild)
        rebuild
        ;;
    status)
        status
        ;;
    logs)
        logs "$@"
        ;;
    shell)
        shell
        ;;
    mysql)
        mysql
        ;;
    clean)
        clean
        ;;
    test-ssl)
        test_ssl
        ;;
    test-perf)
        test_performance
        ;;
    backup)
        backup
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
