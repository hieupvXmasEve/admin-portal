#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

print_header() {
    echo -e "\n${CYAN}========================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}========================================${NC}\n"
}

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

check_status() {
    if [ $? -eq 0 ]; then
        print_success "$1 completed successfully"
    else
        print_error "$1 failed"
        exit 1
    fi
}

print_header "🚀 SWINX PROJECT SETUP"
echo "This script will set up the complete development environment"
echo "including Docker Compose, testing pipeline, and validation."
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    print_error "Please run this script from the project root directory"
    exit 1
fi

# Validate system requirements
print_header "🔍 SYSTEM VALIDATION"
./scripts/validate-setup.sh
check_status "System validation"

# Setup environment
print_header "⚙️ ENVIRONMENT SETUP"

if [ ! -f ".env" ]; then
    print_status "Creating .env file from env.docker.example..."
    cp env.docker.example .env
    check_status "Environment file creation"
else
    print_success ".env file already exists"
fi

# Generate application key if needed
if ! grep -q "APP_KEY=base64:" .env; then
    print_status "Generating application key..."
    if command -v php >/dev/null 2>&1; then
        php artisan key:generate
        check_status "Application key generation"
    else
        print_warning "PHP not found locally. Key will be generated in Docker container."
    fi
fi

# Setup Docker environment
print_header "🐳 DOCKER ENVIRONMENT SETUP"

print_status "Starting Docker Compose development environment..."
./scripts/docker-compose-dev.sh up
check_status "Docker environment startup"

# Wait for services to be ready
print_status "Waiting for services to be ready..."
sleep 15

# Test the environment
print_header "🧪 ENVIRONMENT TESTING"

print_status "Testing application accessibility..."
timeout 30 bash -c 'until curl -f http://localhost:8080/health >/dev/null 2>&1; do sleep 2; done'
check_status "Application health check"

print_status "Testing database connectivity..."
./scripts/docker-compose-dev.sh artisan tinker --execute="echo 'DB Connection: ' . (DB::connection()->getPdo() ? 'OK' : 'FAILED');"
check_status "Database connectivity test"

print_status "Testing phpMyAdmin accessibility..."
timeout 10 bash -c 'until curl -f http://localhost:8081 >/dev/null 2>&1; do sleep 1; done'
check_status "phpMyAdmin accessibility test"

# Run comprehensive tests
print_header "🔬 COMPREHENSIVE TESTING"

print_status "Running comprehensive test suite..."
./scripts/test-local.sh
check_status "Comprehensive test suite"

# Setup complete
print_header "✅ SETUP COMPLETE"

print_success "🎉 Swinx development environment is ready!"
echo ""
print_status "Services running:"
echo "  🌐 Application: http://localhost:8080"
echo "  🗄️ phpMyAdmin: http://localhost:8081"
echo "  📊 MySQL: localhost:3306"
echo "  🔴 Redis: localhost:6379"
echo ""
print_status "Quick commands:"
echo "  • View logs: npm run docker:logs"
echo "  • Run tests: npm run test:local"
echo "  • Stop environment: npm run docker:down"
echo "  • Pre-push check: npm run pre-push:docker"
echo ""
print_status "Development workflow:"
echo "  1. Make your changes"
echo "  2. Run: ./scripts/pre-push.sh --docker"
echo "  3. Commit and push your code"
echo ""
print_success "Happy coding! 🚀"
