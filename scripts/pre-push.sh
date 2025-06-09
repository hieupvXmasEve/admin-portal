#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
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
        print_success "$1 passed"
    else
        print_error "$1 failed"
        exit 1
    fi
}

echo "🚀 Running pre-push checks..."
echo "=============================="

# Check if we're in a Laravel project
if [ ! -f "artisan" ]; then
    print_error "Not in a Laravel project directory"
    exit 1
fi

# Check for Docker testing option
DOCKER_TEST=${1:-false}
if [ "$DOCKER_TEST" = "--docker" ] || [ "$DOCKER_TEST" = "-d" ]; then
    print_status "Running comprehensive Docker-based testing..."
    ./scripts/test-local.sh
    exit $?
fi

# 1. Install dependencies if needed
print_status "Checking dependencies..."
if [ ! -d "vendor" ] || [ ! -d "node_modules" ]; then
    print_warning "Dependencies not found, installing..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
    npm ci
fi

# 2. Copy .env if not exists
if [ ! -f ".env" ]; then
    print_warning ".env file not found, copying from .env.example"
    cp .env.example .env
    if command -v php >/dev/null 2>&1; then
        php artisan key:generate
    else
        print_warning "PHP not found locally. Will generate key in Docker container."
    fi
fi

# 3. Generate Ziggy routes
print_status "Generating Ziggy routes..."
php artisan ziggy:generate
check_status "Ziggy generation"

# 4. Build frontend assets
print_status "Building frontend assets..."
npm run build
check_status "Frontend build"

# 5. Run PHP Code Style (Pint)
print_status "Running PHP code style check..."
vendor/bin/pint --test
check_status "PHP code style"

# 6. Run Frontend linting
print_status "Running frontend linting..."
npm run lint
check_status "Frontend linting"

# 7. Run Frontend formatting check
print_status "Checking frontend code formatting..."
npm run format:check
check_status "Frontend formatting"

# 8. Run TypeScript check
print_status "Running TypeScript check..."
npx vue-tsc --noEmit
check_status "TypeScript check"

# 9. Run PHP tests
print_status "Running PHP tests..."
./vendor/bin/pest
check_status "PHP tests"

# 10. Check for common issues
print_status "Checking for common issues..."

# Check for debug statements
if grep -r "dd(" app/ resources/ --include="*.php" --include="*.vue" --include="*.js" --include="*.ts"; then
    print_error "Found debug statements (dd, dump, console.log). Please remove them."
    exit 1
fi

if grep -r "console.log" resources/ --include="*.vue" --include="*.js" --include="*.ts"; then
    print_error "Found console.log statements. Please remove them."
    exit 1
fi

# Check for .env variables not in .env.example
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

echo ""
echo "=============================="
print_success "All checks passed! ✅"
print_success "Your code is ready to push! 🚀"
echo ""
print_status "💡 For comprehensive Docker-based testing, run:"
print_status "   ./scripts/pre-push.sh --docker"
print_status "   or"
print_status "   ./scripts/test-local.sh"
echo "=============================="
