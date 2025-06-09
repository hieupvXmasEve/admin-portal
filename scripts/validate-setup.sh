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

check_requirement() {
    local command=$1
    local name=$2
    local version_flag=${3:-"--version"}
    
    if command -v $command >/dev/null 2>&1; then
        local version=$($command $version_flag 2>&1 | head -n1)
        print_success "$name is installed: $version"
        return 0
    else
        print_error "$name is not installed"
        return 1
    fi
}

print_header "🔍 DOCKER COMPOSE SETUP VALIDATION"

# Check system requirements
print_status "Checking system requirements..."

REQUIREMENTS_MET=true

if ! check_requirement "docker" "Docker"; then
    REQUIREMENTS_MET=false
fi

if ! check_requirement "docker-compose" "Docker Compose"; then
    REQUIREMENTS_MET=false
fi

if ! check_requirement "git" "Git"; then
    REQUIREMENTS_MET=false
fi

if ! check_requirement "curl" "cURL"; then
    REQUIREMENTS_MET=false
fi

if [ "$REQUIREMENTS_MET" = false ]; then
    print_error "Some requirements are missing. Please install them before continuing."
    exit 1
fi

# Check Docker daemon
print_status "Checking Docker daemon..."
if docker info >/dev/null 2>&1; then
    print_success "Docker daemon is running"
else
    print_error "Docker daemon is not running. Please start Docker."
    exit 1
fi

# Check project structure
print_header "📁 PROJECT STRUCTURE VALIDATION"

REQUIRED_FILES=(
    "artisan"
    "composer.json"
    "package.json"
    "docker-compose.yml"
    "docker-compose.dev.yml"
    "docker-compose.test.yml"
    "Dockerfile"
    ".env.example"
    "env.docker.example"
    "phpunit.xml"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        print_success "Found: $file"
    else
        print_error "Missing: $file"
        REQUIREMENTS_MET=false
    fi
done

REQUIRED_DIRS=(
    "app"
    "tests"
    "resources"
    "docker"
    "scripts"
)

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        print_success "Found directory: $dir"
    else
        print_error "Missing directory: $dir"
        REQUIREMENTS_MET=false
    fi
done

if [ "$REQUIREMENTS_MET" = false ]; then
    print_error "Project structure is incomplete."
    exit 1
fi

# Check script permissions
print_header "🔐 SCRIPT PERMISSIONS"

SCRIPTS=(
    "scripts/docker-compose-dev.sh"
    "scripts/pre-push.sh"
    "scripts/test-local.sh"
    "scripts/validate-setup.sh"
)

for script in "${SCRIPTS[@]}"; do
    if [ -f "$script" ]; then
        if [ -x "$script" ]; then
            print_success "$script is executable"
        else
            print_warning "$script is not executable, fixing..."
            chmod +x "$script"
            print_success "Fixed permissions for $script"
        fi
    else
        print_error "Missing script: $script"
    fi
done

# Validate Docker Compose files
print_header "🐳 DOCKER COMPOSE VALIDATION"

print_status "Validating docker-compose.yml..."
if docker-compose -f docker-compose.yml config >/dev/null 2>&1; then
    print_success "docker-compose.yml is valid"
else
    print_error "docker-compose.yml has syntax errors"
    docker-compose -f docker-compose.yml config
    exit 1
fi

print_status "Validating docker-compose.dev.yml..."
if docker-compose -f docker-compose.yml -f docker-compose.dev.yml config >/dev/null 2>&1; then
    print_success "docker-compose.dev.yml is valid"
else
    print_error "docker-compose.dev.yml has syntax errors"
    exit 1
fi

print_status "Validating docker-compose.test.yml..."
if docker-compose -f docker-compose.yml -f docker-compose.test.yml config >/dev/null 2>&1; then
    print_success "docker-compose.test.yml is valid"
else
    print_error "docker-compose.test.yml has syntax errors"
    exit 1
fi

# Check environment files
print_header "⚙️ ENVIRONMENT CONFIGURATION"

if [ ! -f ".env" ]; then
    print_warning ".env file not found, creating from .env.example"
    cp .env.example .env
fi

print_status "Checking .env file..."
if grep -q "APP_KEY=" .env; then
    if grep -q "APP_KEY=base64:" .env; then
        print_success ".env has application key"
    else
        print_warning ".env missing application key"
    fi
else
    print_error ".env missing APP_KEY"
fi

# Check port availability
print_header "🔌 PORT AVAILABILITY"

PORTS=(8080 3306 3307 6379 8081)

for port in "${PORTS[@]}"; do
    if lsof -i :$port >/dev/null 2>&1; then
        print_warning "Port $port is in use"
        lsof -i :$port
    else
        print_success "Port $port is available"
    fi
done

# Test Docker build
print_header "🏗️ DOCKER BUILD TEST"

print_status "Testing Docker build..."
if docker build -t swinx-test . >/dev/null 2>&1; then
    print_success "Docker build successful"
    docker rmi swinx-test >/dev/null 2>&1
else
    print_error "Docker build failed"
    print_status "Running build with output..."
    docker build -t swinx-test .
    exit 1
fi

# Test basic Docker Compose
print_header "🧪 BASIC DOCKER COMPOSE TEST"

print_status "Testing basic Docker Compose setup..."
if docker-compose -f docker-compose.yml config >/dev/null 2>&1; then
    print_success "Docker Compose configuration is valid"
else
    print_error "Docker Compose configuration is invalid"
    exit 1
fi

# Check disk space
print_header "💾 SYSTEM RESOURCES"

print_status "Checking disk space..."
AVAILABLE_SPACE=$(df . | awk 'NR==2 {print $4}')
REQUIRED_SPACE=2097152  # 2GB in KB

if [ "$AVAILABLE_SPACE" -gt "$REQUIRED_SPACE" ]; then
    print_success "Sufficient disk space available ($(($AVAILABLE_SPACE / 1024 / 1024))GB)"
else
    print_warning "Low disk space. At least 2GB recommended for Docker operations"
fi

print_status "Checking memory..."
if command -v free >/dev/null 2>&1; then
    AVAILABLE_MEMORY=$(free -m | awk 'NR==2{print $7}')
    if [ "$AVAILABLE_MEMORY" -gt 1024 ]; then
        print_success "Sufficient memory available (${AVAILABLE_MEMORY}MB)"
    else
        print_warning "Low memory. At least 1GB recommended for Docker operations"
    fi
elif command -v vm_stat >/dev/null 2>&1; then
    # macOS
    print_success "Memory check skipped on macOS"
else
    print_warning "Cannot check memory usage on this system"
fi

# Final validation
print_header "✅ VALIDATION SUMMARY"

print_success "All validations completed!"
echo ""
print_status "Your system is ready for Docker Compose development!"
echo ""
print_status "Next steps:"
echo "  1. Run: npm run docker:up"
echo "  2. Run: npm run test:local"
echo "  3. Start developing!"
echo ""
print_status "Quick commands:"
echo "  • Start development: ./scripts/docker-compose-dev.sh up"
echo "  • Run tests: ./scripts/test-local.sh"
echo "  • Pre-push check: ./scripts/pre-push.sh --docker"
echo ""
print_success "🚀 Happy coding!"
