#!/bin/bash

# ===========================================
# Development Environment Management Script
# ===========================================
# Manages the development Docker environment with FrankenPHP + MySQL

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
COMPOSE_FILE="docker-compose.dev.yml"
ENV_FILE=".env.docker.dev"
PROJECT_NAME="swinx-dev"

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
        else
            print_error "No example environment file found. Please create $ENV_FILE manually."
            exit 1
        fi
    fi
}

# Main commands
start() {
    print_header "Starting Development Environment"
    check_env_file
    
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" up -d
    
    print_success "Development environment started!"
    print_warning "Application will be available at: http://localhost:8080"
    print_warning "Database is available at: localhost:3306"
    
    # Show logs for a few seconds
    echo ""
    print_header "Initial Logs (press Ctrl+C to stop following)"
    sleep 2
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" logs -f --tail=50
}

stop() {
    print_header "Stopping Development Environment"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" down
    print_success "Development environment stopped!"
}

restart() {
    print_header "Restarting Development Environment"
    stop
    sleep 2
    start
}

rebuild() {
    print_header "Rebuilding Development Environment"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" down
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" build --no-cache
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" up -d
    print_success "Development environment rebuilt and started!"
}

logs() {
    print_header "Development Environment Logs"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" logs -f "${2:-}"
}

status() {
    print_header "Development Environment Status"
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
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec db mysql -u swinx_user -pswinx_password swinburne
}

clean() {
    print_header "Cleaning Development Environment"
    print_warning "This will remove all containers, volumes, and images for this project!"
    read -p "Are you sure? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" down -v --remove-orphans
        docker system prune -f
        print_success "Development environment cleaned!"
    else
        print_warning "Clean operation cancelled."
    fi
}

# Laravel specific commands
artisan() {
    print_header "Running Artisan Command: ${*:2}"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app php artisan "${@:2}"
}

composer() {
    print_header "Running Composer Command: ${*:2}"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app composer "${@:2}"
}

npm() {
    print_header "Running NPM Command: ${*:2}"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app npm "${@:2}"
}

test() {
    print_header "Running Tests"
    docker-compose -f "$COMPOSE_FILE" -p "$PROJECT_NAME" exec app php artisan test "${@:2}"
}

# Help function
show_help() {
    echo "Development Environment Management Script"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  start       Start the development environment"
    echo "  stop        Stop the development environment"
    echo "  restart     Restart the development environment"
    echo "  rebuild     Rebuild and start the environment"
    echo "  status      Show environment status and resource usage"
    echo "  logs        Show logs (optionally for specific service)"
    echo "  shell       Access application container shell"
    echo "  mysql       Access MySQL shell"
    echo "  clean       Remove all containers, volumes, and images"
    echo ""
    echo "Laravel Commands:"
    echo "  artisan     Run artisan commands"
    echo "  composer    Run composer commands"
    echo "  npm         Run npm commands"
    echo "  test        Run tests"
    echo ""
    echo "Examples:"
    echo "  $0 start"
    echo "  $0 logs app"
    echo "  $0 artisan migrate"
    echo "  $0 composer install"
    echo "  $0 npm run dev"
    echo "  $0 test --filter=UserTest"
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
    artisan)
        artisan "$@"
        ;;
    composer)
        composer "$@"
        ;;
    npm)
        npm "$@"
        ;;
    test)
        test "$@"
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
