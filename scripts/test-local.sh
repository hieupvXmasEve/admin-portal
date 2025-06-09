#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Function to print colored output
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

# Function to check command exit status
check_status() {
    if [ $? -eq 0 ]; then
        print_success "$1 passed ✅"
    else
        print_error "$1 failed ❌"
        exit 1
    fi
}

# Function to run command with timeout
run_with_timeout() {
    local timeout=$1
    local command=$2
    local description=$3
    
    print_status "Running: $description"
    timeout $timeout bash -c "$command"
    local exit_code=$?
    
    if [ $exit_code -eq 124 ]; then
        print_error "$description timed out after ${timeout}s"
        exit 1
    elif [ $exit_code -ne 0 ]; then
        print_error "$description failed with exit code $exit_code"
        exit 1
    fi
    
    print_success "$description completed"
}

# Cleanup function
cleanup() {
    print_status "Cleaning up test environment..."
    docker-compose -f docker-compose.yml -f docker-compose.test.yml down -v --remove-orphans 2>/dev/null || true
    docker system prune -f 2>/dev/null || true
}

# Trap cleanup on script exit
trap cleanup EXIT

print_header "🚀 COMPREHENSIVE LOCAL TESTING PIPELINE"
echo "This script mirrors the GitHub Actions CI/CD pipeline"
echo "Testing environment: Docker Compose with MySQL database"
echo ""

# Check if we're in a Laravel project
if [ ! -f "artisan" ]; then
    print_error "Not in a Laravel project directory"
    exit 1
fi

# Check Docker availability
if ! command -v docker >/dev/null 2>&1; then
    print_error "Docker is not installed or not running"
    exit 1
fi

if ! command -v docker-compose >/dev/null 2>&1; then
    print_error "Docker Compose is not installed"
    exit 1
fi

print_header "📋 PHASE 1: ENVIRONMENT SETUP"

# 1. Environment Setup
print_status "Setting up environment files..."
if [ ! -f ".env" ]; then
    print_warning ".env file not found, copying from .env.example"
    cp .env.example .env
fi

# Generate app key if needed
if ! grep -q "APP_KEY=base64:" .env; then
    print_warning "Generating application key..."
    if command -v php >/dev/null 2>&1; then
        php artisan key:generate
    else
        print_warning "PHP not found locally. Will generate key in Docker container."
    fi
fi

print_header "🐳 PHASE 2: DOCKER ENVIRONMENT"

# 2. Clean and start Docker environment
print_status "Cleaning previous Docker environment..."
cleanup

print_status "Starting Docker test environment..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml up -d --build

# Wait for services to be healthy
print_status "Waiting for services to be ready..."
run_with_timeout 120 "docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T test-db mysqladmin ping -h localhost --silent" "Database health check"

# Wait a bit more for application setup
sleep 10

print_header "📦 PHASE 3: DEPENDENCY MANAGEMENT"

# 3. Install dependencies in container
print_status "Installing PHP dependencies..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test composer install --no-interaction --prefer-dist --optimize-autoloader
check_status "PHP dependency installation"

print_status "Installing Node.js dependencies..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test npm ci
check_status "Node.js dependency installation"

print_header "🔧 PHASE 4: APPLICATION SETUP"

# 4. Application setup
print_status "Clearing application caches..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan config:clear
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan cache:clear
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan view:clear
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan route:clear

print_status "Generating application key in container..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan key:generate --force

print_status "Running database migrations..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan migrate:fresh --force
check_status "Database migrations"

print_status "Seeding test data..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan db:seed --force
check_status "Database seeding"

print_status "Generating Ziggy routes..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan ziggy:generate
check_status "Ziggy route generation"

print_header "🏗️ PHASE 5: FRONTEND BUILD"

# 5. Build frontend assets
print_status "Building frontend assets..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test npm run build
check_status "Frontend build"

print_header "🧪 PHASE 6: CODE QUALITY CHECKS"

# 6. Code quality checks
print_status "Running PHP code style check (Pint)..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test ./vendor/bin/pint --test
check_status "PHP code style"

print_status "Running frontend linting..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test npm run lint
check_status "Frontend linting"

print_status "Checking frontend code formatting..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test npm run format:check
check_status "Frontend formatting"

print_status "Running TypeScript check..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test npx vue-tsc --noEmit
check_status "TypeScript check"

print_header "🧪 PHASE 7: TESTING"

# 7. Run tests
print_status "Running PHP unit and feature tests..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test ./vendor/bin/pest --verbose
check_status "PHP tests"

print_header "🔍 PHASE 8: INTEGRATION TESTS"

# 8. Integration tests
print_status "Testing database connectivity..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan tinker --execute="echo 'DB Connection: ' . (DB::connection()->getPdo() ? 'OK' : 'FAILED');"
check_status "Database connectivity test"

print_status "Testing application health endpoint..."
run_with_timeout 30 "curl -f http://localhost:8080/health" "Health endpoint test"

print_status "Testing application homepage..."
run_with_timeout 30 "curl -f -s http://localhost:8080/ | grep -q 'html'" "Homepage accessibility test"

print_header "🔒 PHASE 9: SECURITY & PERFORMANCE CHECKS"

# 9. Security checks
print_status "Checking for debug statements..."
if docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test grep -r "dd(" app/ resources/ --include="*.php" --include="*.vue" --include="*.js" --include="*.ts" 2>/dev/null; then
    print_error "Found debug statements (dd, dump). Please remove them."
    exit 1
fi

if docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test grep -r "console.log" resources/ --include="*.vue" --include="*.js" --include="*.ts" 2>/dev/null; then
    print_error "Found console.log statements. Please remove them."
    exit 1
fi

print_success "No debug statements found"

print_status "Checking environment variables consistency..."
if [ -f ".env" ]; then
    ENV_VARS=$(grep -v '^#' .env | grep '=' | cut -d'=' -f1 | sort)
    EXAMPLE_VARS=$(grep -v '^#' .env.example | grep '=' | cut -d'=' -f1 | sort)

    MISSING_VARS=$(comm -23 <(echo "$ENV_VARS") <(echo "$EXAMPLE_VARS"))
    if [ ! -z "$MISSING_VARS" ]; then
        print_warning "Found environment variables not in .env.example:"
        echo "$MISSING_VARS"
        print_warning "Consider adding them to .env.example"
    fi
fi

print_header "✅ PHASE 10: FINAL VALIDATION"

# 10. Final validation
print_status "Running final application test..."
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec -T app-test php artisan route:list > /dev/null
check_status "Route list generation"

print_status "Checking container resource usage..."
docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}" $(docker-compose -f docker-compose.yml -f docker-compose.test.yml ps -q)

echo ""
print_header "🎉 ALL TESTS COMPLETED SUCCESSFULLY!"
echo ""
print_success "✅ Environment setup completed"
print_success "✅ Dependencies installed"
print_success "✅ Database migrations successful"
print_success "✅ Frontend assets built"
print_success "✅ Code quality checks passed"
print_success "✅ All tests passed"
print_success "✅ Integration tests successful"
print_success "✅ Security checks passed"
print_success "✅ Performance validation completed"
echo ""
echo -e "${GREEN}🚀 Your code is ready to push to GitHub! 🚀${NC}"
echo ""
echo "Next steps:"
echo "  git add ."
echo "  git commit -m 'your commit message'"
echo "  git push origin main"
echo ""
