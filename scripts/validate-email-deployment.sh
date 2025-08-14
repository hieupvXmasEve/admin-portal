#!/bin/bash

# ===========================================
# Email System Deployment Validation Script
# ===========================================
# Validates email system configuration and deployment readiness

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration
VALIDATION_LOG="logs/email-validation-$(date +%Y%m%d_%H%M%S).log"
REQUIRED_ENV_VARS=(
    "MAIL_MAILER"
    "MAIL_HOST"
    "MAIL_PORT"
    "MAIL_USERNAME"
    "MAIL_PASSWORD"
    "MAIL_FROM_ADDRESS"
    "MAIL_FROM_NAME"
)

# Create logs directory if it doesn't exist
mkdir -p logs

# Helper functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$VALIDATION_LOG"
}

success() {
    echo -e "${GREEN}✅ $1${NC}" | tee -a "$VALIDATION_LOG"
}

warning() {
    echo -e "${YELLOW}⚠️  $1${NC}" | tee -a "$VALIDATION_LOG"
}

error() {
    echo -e "${RED}❌ $1${NC}" | tee -a "$VALIDATION_LOG"
}

info() {
    echo -e "${PURPLE}ℹ️  $1${NC}" | tee -a "$VALIDATION_LOG"
}

print_header() {
    echo -e "\n${BLUE}=== $1 ===${NC}" | tee -a "$VALIDATION_LOG"
}

# Validation functions
validate_environment_file() {
    print_header "Environment File Validation"

    if [ ! -f ".env" ]; then
        error "Environment file (.env) not found!"
        return 1
    fi

    success "Environment file exists"

    # Check for required email variables
    local missing_vars=()
    for var in "${REQUIRED_ENV_VARS[@]}"; do
        if ! grep -q "^${var}=" .env; then
            missing_vars+=("$var")
        fi
    done

    if [ ${#missing_vars[@]} -gt 0 ]; then
        error "Missing required environment variables:"
        for var in "${missing_vars[@]}"; do
            error "  - $var"
        done
        return 1
    fi

    success "All required email environment variables are present"

    # Check for empty values
    local empty_vars=()
    for var in "${REQUIRED_ENV_VARS[@]}"; do
        value=$(grep "^${var}=" .env | cut -d'=' -f2- | tr -d '"' | tr -d "'")
        if [ -z "$value" ] || [ "$value" = "null" ]; then
            empty_vars+=("$var")
        fi
    done

    if [ ${#empty_vars[@]} -gt 0 ]; then
        warning "Environment variables with empty or null values:"
        for var in "${empty_vars[@]}"; do
            warning "  - $var"
        done
    fi

    return 0
}

validate_database_tables() {
    print_header "Database Tables Validation"

    # Check if email-related tables exist
    local required_tables=(
        "email_configurations"
        "email_templates"
        "email_logs"
        "user_email_preferences"
    )

    for table in "${required_tables[@]}"; do
        if php artisan tinker --execute="echo \Schema::hasTable('$table') ? 'exists' : 'missing';" 2>/dev/null | grep -q "exists"; then
            success "Table '$table' exists"
        else
            error "Table '$table' is missing"
            return 1
        fi
    done

    return 0
}

validate_email_models() {
    print_header "Email Models Validation"

    local models=(
        "EmailConfiguration"
        "EmailTemplate"
        "EmailLog"
        "UserEmailPreference"
    )

    for model in "${models[@]}"; do
        if php artisan tinker --execute="try { new App\\Models\\$model(); echo 'exists'; } catch (Exception \$e) { echo 'missing'; }" 2>/dev/null | grep -q "exists"; then
            success "Model '$model' exists and is loadable"
        else
            error "Model '$model' is missing or has issues"
            return 1
        fi
    done

    return 0
}

validate_email_services() {
    print_header "Email Services Validation"

    local services=(
        "EmailService"
        "SmtpConfigurationService"
        "EmailTemplateService"
        "NotificationService"
        "UserEmailPreferenceService"
    )

    for service in "${services[@]}"; do
        if php artisan tinker --execute="try { app('App\\Services\\$service'); echo 'exists'; } catch (Exception \$e) { echo 'missing'; }" 2>/dev/null | grep -q "exists"; then
            success "Service '$service' exists and is resolvable"
        else
            error "Service '$service' is missing or has issues"
            return 1
        fi
    done

    return 0
}

validate_queue_configuration() {
    print_header "Queue Configuration Validation"

    # Check queue connection
    local queue_connection=$(php artisan tinker --execute="echo config('queue.default');" 2>/dev/null | tail -1)
    info "Queue connection: $queue_connection"

    if [ "$queue_connection" = "sync" ]; then
        warning "Queue connection is set to 'sync' - emails will be sent synchronously"
        warning "Consider using 'redis' or 'database' for better performance"
    else
        success "Queue connection is properly configured: $queue_connection"
    fi

    # Check if queue workers are running (in production)
    if [ "$APP_ENV" = "production" ]; then
        if pgrep -f "queue:work" > /dev/null; then
            success "Queue workers are running"
        else
            warning "No queue workers detected - emails may not be processed"
        fi
    fi

    return 0
}

test_smtp_connection() {
    print_header "SMTP Connection Test"

    info "Testing SMTP connection..."

    # Create a test command to check SMTP connection
    php artisan tinker --execute="
        try {
            \Mail::raw('Test connection', function(\$message) {
                \$message->to('test@example.com')->subject('SMTP Test');
            });
            echo 'SMTP connection successful';
        } catch (Exception \$e) {
            echo 'SMTP connection failed: ' . \$e->getMessage();
        }
    " 2>/dev/null | tail -1 > /tmp/smtp_test_result

    local result=$(cat /tmp/smtp_test_result)
    if echo "$result" | grep -q "successful"; then
        success "SMTP connection test passed"
    else
        error "SMTP connection test failed: $result"
        return 1
    fi

    rm -f /tmp/smtp_test_result
    return 0
}

validate_email_templates() {
    print_header "Email Templates Validation"

    # Check if default templates exist
    local template_count=$(php artisan tinker --execute="echo \App\Models\EmailTemplate::count();" 2>/dev/null | tail -1)

    if [ "$template_count" -gt 0 ]; then
        success "Email templates found: $template_count templates"
    else
        warning "No email templates found - consider running email template seeder"
    fi

    # Check template directory
    if [ -d "resources/views/emails" ]; then
        local view_count=$(find resources/views/emails -name "*.blade.php" | wc -l)
        success "Email view templates found: $view_count templates"
    else
        warning "Email views directory not found"
    fi

    return 0
}

validate_permissions() {
    print_header "File Permissions Validation"

    # Check storage permissions
    if [ -w "storage/logs" ]; then
        success "Storage logs directory is writable"
    else
        error "Storage logs directory is not writable"
        return 1
    fi

    # Check email logs directory
    if [ -d "storage/app/email-logs" ]; then
        if [ -w "storage/app/email-logs" ]; then
            success "Email logs directory is writable"
        else
            error "Email logs directory is not writable"
            return 1
        fi
    else
        info "Email logs directory doesn't exist - will be created automatically"
    fi

    return 0
}

validate_security_settings() {
    print_header "Security Settings Validation"

    # Check if APP_KEY is set
    local app_key=$(grep "^APP_KEY=" .env | cut -d'=' -f2)
    if [ -n "$app_key" ] && [ "$app_key" != "base64:" ]; then
        success "Application key is set"
    else
        error "Application key is not set or invalid"
        return 1
    fi

    # Check if mail encryption is enabled
    local mail_encryption=$(grep "^MAIL_ENCRYPTION=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
    if [ "$mail_encryption" = "tls" ] || [ "$mail_encryption" = "ssl" ]; then
        success "Mail encryption is enabled: $mail_encryption"
    else
        warning "Mail encryption is not enabled - consider using TLS or SSL"
    fi

    # Check if production settings are secure
    if [ "$APP_ENV" = "production" ]; then
        local app_debug=$(grep "^APP_DEBUG=" .env | cut -d'=' -f2)
        if [ "$app_debug" = "false" ]; then
            success "Debug mode is disabled in production"
        else
            error "Debug mode should be disabled in production"
            return 1
        fi
    fi

    return 0
}

run_performance_checks() {
    print_header "Performance Checks"

    # Check Redis connection if using Redis queue
    local queue_connection=$(php artisan tinker --execute="echo config('queue.default');" 2>/dev/null | tail -1)
    if [ "$queue_connection" = "redis" ]; then
        if php artisan tinker --execute="try { \Redis::ping(); echo 'connected'; } catch (Exception \$e) { echo 'failed'; }" 2>/dev/null | grep -q "connected"; then
            success "Redis connection is working"
        else
            error "Redis connection failed"
            return 1
        fi
    fi

    # Check opcache status
    if php -m | grep -q "Zend OPcache"; then
        success "OPcache is enabled"
    else
        warning "OPcache is not enabled - consider enabling for better performance"
    fi

    return 0
}

generate_validation_report() {
    print_header "Validation Report"

    local total_checks=0
    local passed_checks=0
    local failed_checks=0
    local warnings=0

    # Count results from log file
    total_checks=$(grep -c "✅\|❌\|⚠️" "$VALIDATION_LOG" || echo "0")
    passed_checks=$(grep -c "✅" "$VALIDATION_LOG" || echo "0")
    failed_checks=$(grep -c "❌" "$VALIDATION_LOG" || echo "0")
    warnings=$(grep -c "⚠️" "$VALIDATION_LOG" || echo "0")

    echo ""
    echo "=== EMAIL SYSTEM VALIDATION SUMMARY ==="
    echo "Total Checks: $total_checks"
    echo "Passed: $passed_checks"
    echo "Failed: $failed_checks"
    echo "Warnings: $warnings"
    echo ""

    if [ "$failed_checks" -eq 0 ]; then
        success "All critical validations passed! Email system is ready for deployment."
        if [ "$warnings" -gt 0 ]; then
            warning "Please review the warnings above for optimal configuration."
        fi
        return 0
    else
        error "Some validations failed. Please fix the issues before deploying."
        return 1
    fi
}

# Main validation process
main() {
    log "Starting email system deployment validation..."

    local validation_failed=false

    # Run all validations
    validate_environment_file || validation_failed=true
    validate_database_tables || validation_failed=true
    validate_email_models || validation_failed=true
    validate_email_services || validation_failed=true
    validate_queue_configuration || validation_failed=true
    validate_email_templates || validation_failed=true
    validate_permissions || validation_failed=true
    validate_security_settings || validation_failed=true
    run_performance_checks || validation_failed=true

    # Only test SMTP connection if basic validations pass
    if [ "$validation_failed" = false ]; then
        test_smtp_connection || validation_failed=true
    else
        warning "Skipping SMTP connection test due to previous validation failures"
    fi

    # Generate final report
    generate_validation_report

    local exit_code=$?

    log "Validation completed. Log saved to: $VALIDATION_LOG"

    exit $exit_code
}

# Show help
show_help() {
    echo "Email System Deployment Validation Script"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --help, -h    Show this help message"
    echo "  --verbose, -v Enable verbose output"
    echo ""
    echo "This script validates:"
    echo "  - Environment configuration"
    echo "  - Database tables and models"
    echo "  - Email services"
    echo "  - Queue configuration"
    echo "  - SMTP connectivity"
    echo "  - Email templates"
    echo "  - File permissions"
    echo "  - Security settings"
    echo "  - Performance configuration"
    echo ""
    echo "Examples:"
    echo "  $0                 # Run full validation"
    echo "  $0 --verbose       # Run with verbose output"
}

# Parse command line arguments
case "${1:-}" in
    --help|-h)
        show_help
        exit 0
        ;;
    --verbose|-v)
        set -x
        main
        ;;
    "")
        main
        ;;
    *)
        echo "Unknown option: $1"
        echo "Use --help for usage information"
        exit 1
        ;;
esac
