#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Default command
COMMAND=${1:-up}

# Docker compose files
COMPOSE_FILES="-f docker-compose.yml -f docker-compose.dev.yml"

case $COMMAND in
    "up"|"start")
        print_status "Starting development environment..."

        # Create .env if not exists
        if [ ! -f ".env" ]; then
            print_warning "Creating .env file from .env.example"
            cp .env.example .env
        fi

        # Generate app key if needed
        if ! grep -q "APP_KEY=base64:" .env; then
            print_warning "Generating application key..."
            php artisan key:generate || echo "Will generate key in container"
        fi

        # Build and start containers
        docker-compose $COMPOSE_FILES up -d --build

        print_status "Waiting for database to be ready..."
        sleep 10

        # Show status
        docker-compose $COMPOSE_FILES ps

        print_success "Development environment started!"
        echo ""
        echo "🌐 Application: http://localhost:8080"
        echo "🗄️  phpMyAdmin: http://localhost:8081"
        echo "📊 MySQL: localhost:3306"
        echo "🔴 Redis: localhost:6379"
        echo ""
        echo "To view logs: ./scripts/docker-compose-dev.sh logs"
        echo "To stop: ./scripts/docker-compose-dev.sh stop"
        ;;

    "down"|"stop")
        print_status "Stopping development environment..."
        docker-compose $COMPOSE_FILES down
        print_success "Development environment stopped!"
        ;;

    "restart")
        print_status "Restarting development environment..."
        docker-compose $COMPOSE_FILES down
        docker-compose $COMPOSE_FILES up -d
        print_success "Development environment restarted!"
        ;;

    "build")
        print_status "Building containers..."
        docker-compose $COMPOSE_FILES build --no-cache
        print_success "Containers built successfully!"
        ;;

    "logs")
        SERVICE=${2:-""}
        if [ -z "$SERVICE" ]; then
            docker-compose $COMPOSE_FILES logs -f
        else
            docker-compose $COMPOSE_FILES logs -f $SERVICE
        fi
        ;;

    "shell"|"bash")
        SERVICE=${2:-app}
        print_status "Opening shell in $SERVICE container..."
        docker-compose $COMPOSE_FILES exec $SERVICE sh
        ;;

    "mysql")
        print_status "Opening MySQL client..."
        docker-compose $COMPOSE_FILES exec db mysql -u swinx_user -pswinx_password swinburne
        ;;

    "artisan")
        shift
        print_status "Running artisan command: $@"
        docker-compose $COMPOSE_FILES exec app php artisan "$@"
        ;;

    "composer")
        shift
        print_status "Running composer command: $@"
        docker-compose $COMPOSE_FILES exec app composer "$@"
        ;;

    "npm")
        shift
        print_status "Running npm command: $@"
        docker-compose $COMPOSE_FILES exec app npm "$@"
        ;;

    "test")
        print_status "Running tests..."
        docker-compose $COMPOSE_FILES exec app ./vendor/bin/pest
        ;;

    "test:full"|"test-full")
        print_status "Running comprehensive test suite..."
        ./scripts/test-local.sh
        ;;

    "test:setup"|"test-setup")
        print_status "Setting up test environment..."
        docker-compose -f docker-compose.yml -f docker-compose.test.yml up -d --build
        print_success "Test environment ready!"
        echo "🧪 Test Database: localhost:3307"
        echo "🔧 Run tests with: ./scripts/test-local.sh"
        ;;

    "test:down"|"test-down")
        print_status "Stopping test environment..."
        docker-compose -f docker-compose.yml -f docker-compose.test.yml down -v --remove-orphans
        print_success "Test environment stopped!"
        ;;

    "migrate")
        print_status "Running database migrations..."
        docker-compose $COMPOSE_FILES exec app php artisan migrate
        ;;

    "seed")
        print_status "Seeding database..."
        docker-compose $COMPOSE_FILES exec app php artisan db:seed
        ;;

    "fresh")
        print_status "Fresh database migration with seeding..."
        docker-compose $COMPOSE_FILES exec app php artisan migrate:fresh --seed
        ;;

    "status"|"ps")
        print_status "Container status:"
        docker-compose $COMPOSE_FILES ps
        ;;

    "clean")
        print_warning "This will remove all containers, volumes, and images. Are you sure? (y/N)"
        read -r response
        if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
            print_status "Cleaning up Docker environment..."
            docker-compose $COMPOSE_FILES down -v --remove-orphans
            docker system prune -f
            docker volume prune -f
            print_success "Docker environment cleaned!"
        else
            print_status "Operation cancelled"
        fi
        ;;

    "help"|"--help"|"-h")
        echo "🐳 Docker Compose Development Helper"
        echo "Usage: $0 [COMMAND] [OPTIONS]"
        echo ""
        echo "Commands:"
        echo "  up, start         Start development environment"
        echo "  down, stop        Stop development environment"
        echo "  restart           Restart development environment"
        echo "  build             Build containers"
        echo "  logs [service]    Show logs (all services or specific)"
        echo "  shell [service]   Open shell in container (default: app)"
        echo "  mysql             Open MySQL client"
        echo "  artisan [cmd]     Run Laravel artisan command"
        echo "  composer [cmd]    Run composer command"
        echo "  npm [cmd]         Run npm command"
        echo "  test              Run tests"
        echo "  test:full         Run comprehensive test suite"
        echo "  test:setup        Setup test environment"
        echo "  test:down         Stop test environment"
        echo "  migrate           Run database migrations"
        echo "  seed              Seed database"
        echo "  fresh             Fresh migration with seeding"
        echo "  status, ps        Show container status"
        echo "  clean             Clean up Docker environment"
        echo "  help              Show this help"
        echo ""
        echo "Examples:"
        echo "  $0 up                    # Start development environment"
        echo "  $0 logs app              # Show app container logs"
        echo "  $0 shell db              # Open shell in database container"
        echo "  $0 artisan migrate       # Run migrations"
        echo "  $0 composer install      # Install PHP dependencies"
        echo "  $0 npm run dev           # Run frontend development"
        ;;

    *)
        print_error "Unknown command: $COMMAND"
        print_status "Use '$0 help' to see available commands"
        exit 1
        ;;
esac
