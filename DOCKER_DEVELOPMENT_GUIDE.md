# 🐳 Swinx Application - Complete Docker Development & Deployment Guide

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Local Development Workflow](#local-development-workflow)
3. [Testing Procedures](#testing-procedures)
4. [Production Deployment](#production-deployment)
5. [Environment Configuration](#environment-configuration)
6. [Monitoring & Maintenance](#monitoring--maintenance)
7. [Troubleshooting](#troubleshooting)
8. [Advanced Configuration](#advanced-configuration)

---

## 🚀 Quick Start

### Prerequisites
- Docker Engine 20.10+
- Docker Compose 2.0+
- Git
- 8GB+ RAM recommended
- 10GB+ free disk space

### Initial Setup
```bash
# 1. Clone the repository
git clone <repository-url>
cd swinx

# 2. Copy environment file
cp .env.docker.dev .env

# 3. Generate application key
docker run --rm -v $(pwd):/app -w /app php:8.3-cli php artisan key:generate --show

# 4. Update .env with the generated key
# Edit .env and replace APP_KEY=base64:your-app-key-here

# 5. Build and start services
docker-compose up -d

# 6. Run database migrations
docker exec swinx-app php artisan migrate

# 7. Seed database (optional)
docker exec swinx-app php artisan db:seed

# 8. Access application
open http://localhost:8080
```

---

## 💻 Local Development Workflow

### Daily Development Process

#### 1. Start Development Environment
```bash
# Start all services
docker-compose up -d

# Check service status
docker-compose ps

# View logs
docker-compose logs -f app
```

#### 2. Code Development
```bash
# Install new PHP dependencies
docker exec swinx-app composer install

# Install new Node.js dependencies
docker exec swinx-app npm install

# Run database migrations
docker exec swinx-app php artisan migrate

# Clear application cache
docker exec swinx-app php artisan cache:clear
docker exec swinx-app php artisan config:clear
docker exec swinx-app php artisan route:clear
docker exec swinx-app php artisan view:clear

# Generate IDE helper files
docker exec swinx-app php artisan ide-helper:generate
docker exec swinx-app php artisan ide-helper:models
```

#### 3. Frontend Development
```bash
# Build frontend assets for development
docker exec swinx-app npm run dev

# Build frontend assets for production
docker exec swinx-app npm run build

# Watch for changes (development)
docker exec swinx-app npm run watch
```

#### 4. Database Operations
```bash
# Access MySQL directly
docker exec -it swinx-db mysql -u swinx_user -p swinburne

# Access phpMyAdmin
open http://localhost:8081

# Create new migration
docker exec swinx-app php artisan make:migration create_example_table

# Run specific migration
docker exec swinx-app php artisan migrate --path=/database/migrations/2025_06_09_113105_remove_degree_level_from_programs_table.php

# Rollback migrations
docker exec swinx-app php artisan migrate:rollback

# Fresh migration with seeding
docker exec swinx-app php artisan migrate:fresh --seed
```

#### 5. Pre-Commit Testing
```bash
# Run all tests before committing
./scripts/test-local.sh

# Run specific test suite
docker exec swinx-app php artisan test --testsuite=Feature
docker exec swinx-app php artisan test --testsuite=Unit

# Run tests with coverage
docker exec swinx-app php artisan test --coverage

# Validate code style
docker exec swinx-app ./vendor/bin/phpstan analyse
docker exec swinx-app npm run lint
```

### File Synchronization
- **Source Code**: Real-time sync via volume mounts
- **Storage**: Persistent via `./storage` volume
- **Database**: Persistent via `mysql_data` volume
- **Cache**: Persistent via `redis_data` volume

---

## 🧪 Testing Procedures

### Local Testing Environment

#### 1. Unit & Feature Tests
```bash
# Run all tests
docker exec swinx-app php artisan test

# Run specific test file
docker exec swinx-app php artisan test tests/Feature/ProgramTest.php

# Run tests with specific filter
docker exec swinx-app php artisan test --filter=test_can_create_program

# Run tests in parallel
docker exec swinx-app php artisan test --parallel

# Generate test coverage report
docker exec swinx-app php artisan test --coverage-html coverage
```

#### 2. Database Testing
```bash
# Use test database
docker-compose up -d test-db

# Run migrations on test database
docker exec swinx-app php artisan migrate --database=mysql_test

# Run tests with test database
docker exec swinx-app php artisan test --env=testing
```

#### 3. Integration Testing
```bash
# Test complete application stack
./scripts/validate-setup.sh

# Test API endpoints
docker exec swinx-app php artisan test tests/Feature/Api/

# Test frontend components
docker exec swinx-app npm run test
```

#### 4. Performance Testing
```bash
# Profile application performance
docker exec swinx-app php artisan route:list --compact
docker exec swinx-app php artisan optimize

# Monitor resource usage
docker stats swinx-app swinx-db swinx-redis
```

### Continuous Integration Simulation
```bash
# Simulate GitHub Actions locally
./scripts/pre-push.sh

# This script runs:
# - Code style checks
# - Static analysis
# - Unit tests
# - Feature tests
# - Build verification
```

---

## 🚀 Production Deployment

### Production Environment Setup

#### 1. Server Requirements
- **OS**: Ubuntu 20.04+ or CentOS 8+
- **CPU**: 2+ cores
- **RAM**: 4GB+ (8GB recommended)
- **Storage**: 50GB+ SSD
- **Network**: Static IP, domain name configured

#### 2. Server Preparation
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Create application directory
sudo mkdir -p /opt/swinx
sudo chown $USER:$USER /opt/swinx
```

#### 3. Application Deployment
```bash
# Clone repository
cd /opt/swinx
git clone <repository-url> .

# Configure production environment
cp .env.docker.production .env

# CRITICAL: Update production values in .env
nano .env
# - Change APP_KEY to secure 32-character key
# - Update database passwords
# - Set APP_URL to your domain
# - Configure mail settings
# - Set Redis password
```

#### 4. SSL/TLS Configuration
```bash
# Install Certbot
sudo apt install certbot

# Generate SSL certificate
sudo certbot certonly --standalone -d your-domain.com

# Update nginx configuration for SSL
# (Configure reverse proxy if needed)
```

#### 5. Production Deployment
```bash
# Build and start production services
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Run production migrations
docker exec swinx-app php artisan migrate --force

# Optimize application
docker exec swinx-app php artisan config:cache
docker exec swinx-app php artisan route:cache
docker exec swinx-app php artisan view:cache
docker exec swinx-app php artisan optimize

# Set up log rotation
sudo logrotate -d /etc/logrotate.d/swinx
```

#### 6. Production Monitoring Setup
```bash
# Set up health checks
echo "*/5 * * * * curl -f http://localhost:8080/up || echo 'Health check failed'" | crontab -

# Configure backup script
echo "0 2 * * * /opt/swinx/scripts/backup.sh" | crontab -

# Set up log monitoring
tail -f storage/logs/laravel.log
```

---

## ⚙️ Environment Configuration

### Environment Files Overview

#### `.env.docker.dev` (Development)
```bash
APP_ENV=local
APP_DEBUG=true
DB_HOST=db
CACHE_DRIVER=file
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
DEBUGBAR_ENABLED=true
```

#### `.env.docker.production` (Production)
```bash
APP_ENV=production
APP_DEBUG=false
DB_HOST=db
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
DEBUGBAR_ENABLED=false
```

### Key Configuration Differences

| Setting | Development | Production |
|---------|-------------|------------|
| Debug Mode | `true` | `false` |
| Cache Driver | `file` | `redis` |
| Session Driver | `database` | `redis` |
| Queue Driver | `sync` | `redis` |
| Log Level | `debug` | `error` |
| SSL Required | `false` | `true` |

### Security Configuration

#### Development Security
- Relaxed CORS settings
- Debug toolbar enabled
- Detailed error messages
- Local file storage

#### Production Security
- Strict CORS settings
- Debug toolbar disabled
- Generic error messages
- Encrypted sessions
- HTTPS enforcement
- Rate limiting enabled

---

## 📊 Monitoring & Maintenance

### Health Monitoring

#### 1. Application Health
```bash
# Check application status
curl http://localhost:8080/up

# Monitor application logs
docker logs -f swinx-app

# Check database connectivity
docker exec swinx-app php artisan tinker
>>> DB::connection()->getPdo();
```

#### 2. System Resources
```bash
# Monitor container resources
docker stats

# Check disk usage
df -h
docker system df

# Monitor memory usage
free -h
```

#### 3. Database Monitoring
```bash
# Check database status
docker exec swinx-db mysqladmin status -u root -p

# Monitor slow queries
docker exec swinx-db mysql -u root -p -e "SHOW PROCESSLIST;"

# Check database size
docker exec swinx-db mysql -u root -p -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.tables GROUP BY table_schema;"
```

### Backup Procedures

#### 1. Database Backup
```bash
# Create backup script
cat > scripts/backup.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/opt/swinx/backups"
mkdir -p $BACKUP_DIR

# Database backup
docker exec swinx-db mysqldump -u root -p$DB_ROOT_PASSWORD swinburne > $BACKUP_DIR/db_backup_$DATE.sql

# Application files backup
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz storage/ .env

# Clean old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
EOF

chmod +x scripts/backup.sh
```

#### 2. Automated Backups
```bash
# Add to crontab
echo "0 2 * * * /opt/swinx/scripts/backup.sh" | crontab -

# Verify crontab
crontab -l
```

### Log Management

#### 1. Application Logs
```bash
# View recent logs
docker logs --tail 100 swinx-app

# Follow logs in real-time
docker logs -f swinx-app

# Search logs
docker logs swinx-app 2>&1 | grep ERROR
```

#### 2. Log Rotation
```bash
# Configure logrotate
sudo tee /etc/logrotate.d/swinx << EOF
/opt/swinx/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    notifempty
    create 644 www-data www-data
    postrotate
        docker exec swinx-app php artisan cache:clear
    endscript
}
EOF
```

### Performance Optimization

#### 1. Application Optimization
```bash
# Cache configuration
docker exec swinx-app php artisan config:cache

# Cache routes
docker exec swinx-app php artisan route:cache

# Cache views
docker exec swinx-app php artisan view:cache

# Optimize autoloader
docker exec swinx-app composer install --optimize-autoloader --no-dev
```

#### 2. Database Optimization
```bash
# Analyze database performance
docker exec swinx-db mysql -u root -p -e "SHOW ENGINE INNODB STATUS\G"

# Optimize tables
docker exec swinx-db mysql -u root -p -e "OPTIMIZE TABLE swinburne.programs, swinburne.specializations;"
```

---

## 🔧 Troubleshooting

### Common Issues & Solutions

#### 1. Container Won't Start
```bash
# Check container status
docker-compose ps

# View container logs
docker-compose logs app

# Rebuild container
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

#### 2. Database Connection Issues
```bash
# Check database container
docker-compose logs db

# Test database connectivity
docker exec swinx-app php artisan tinker
>>> DB::connection()->getPdo();

# Reset database
docker-compose down
docker volume rm swinx_mysql_data
docker-compose up -d
```

#### 3. Permission Issues
```bash
# Fix storage permissions
docker exec swinx-app chown -R www-data:www-data storage/
docker exec swinx-app chmod -R 775 storage/

# Fix bootstrap cache permissions
docker exec swinx-app chown -R www-data:www-data bootstrap/cache/
docker exec swinx-app chmod -R 775 bootstrap/cache/
```

#### 4. Frontend Asset Issues
```bash
# Clear and rebuild assets
docker exec swinx-app npm run build

# Check Vite configuration
docker exec swinx-app cat vite.config.ts

# Verify manifest file
docker exec swinx-app ls -la public/build/
```

#### 5. Memory Issues
```bash
# Increase PHP memory limit
docker exec swinx-app php -d memory_limit=512M artisan migrate

# Monitor memory usage
docker stats swinx-app

# Clear application cache
docker exec swinx-app php artisan cache:clear
docker exec swinx-app php artisan view:clear
```

### Debug Mode

#### Enable Debug Mode
```bash
# Temporarily enable debug
docker exec swinx-app php artisan down
docker exec swinx-app sed -i 's/APP_DEBUG=false/APP_DEBUG=true/' .env
docker exec swinx-app php artisan up
docker exec swinx-app php artisan config:clear
```

#### Disable Debug Mode
```bash
# Disable debug for production
docker exec swinx-app sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
docker exec swinx-app php artisan config:cache
```

### Emergency Procedures

#### 1. Application Recovery
```bash
# Stop all services
docker-compose down

# Restore from backup
tar -xzf backups/app_backup_YYYYMMDD_HHMMSS.tar.gz

# Restore database
docker-compose up -d db
docker exec -i swinx-db mysql -u root -p swinburne < backups/db_backup_YYYYMMDD_HHMMSS.sql

# Start application
docker-compose up -d
```

#### 2. Rollback Deployment
```bash
# Rollback to previous version
git checkout previous-stable-tag
docker-compose down
docker-compose build
docker-compose up -d
```

---

## 🔧 Advanced Configuration

### Custom Docker Configuration

#### 1. Environment-Specific Overrides
```bash
# Development with custom settings
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Production with custom settings
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Testing environment
docker-compose -f docker-compose.yml -f docker-compose.test.yml up -d
```

#### 2. Scaling Services
```bash
# Scale application containers
docker-compose up -d --scale app=3

# Load balancer configuration (nginx)
# Configure upstream servers for multiple app containers
```

#### 3. External Services Integration
```bash
# Use external database
# Update .env with external DB credentials
DB_HOST=external-db-host.com
DB_PORT=3306

# Use external Redis
REDIS_HOST=external-redis-host.com
REDIS_PORT=6379
REDIS_PASSWORD=external-redis-password
```

### Security Hardening

#### 1. Container Security
```bash
# Run containers as non-root user
# Update Dockerfile with USER directive

# Limit container resources
docker-compose.yml:
  deploy:
    resources:
      limits:
        memory: 1G
        cpus: '1'
```

#### 2. Network Security
```bash
# Use custom networks
docker network create swinx-internal --internal

# Restrict external access
# Only expose necessary ports
```

#### 3. Secrets Management
```bash
# Use Docker secrets for production
echo "db_password" | docker secret create db_password -
echo "app_key" | docker secret create app_key -

# Reference secrets in docker-compose.yml
secrets:
  - db_password
  - app_key
```

---

## 📞 Support & Resources

### Getting Help
- **Documentation**: Check this guide first
- **Logs**: Always check application and container logs
- **Community**: Laravel community forums
- **Issues**: Create GitHub issues for bugs

### Useful Commands Reference
```bash
# Quick status check
docker-compose ps && docker stats --no-stream

# Complete restart
docker-compose down && docker-compose up -d

# Emergency stop
docker-compose kill

# Clean everything
docker-compose down -v --remove-orphans
docker system prune -a
```

### Performance Benchmarks
- **Startup Time**: ~60 seconds (cold start)
- **Response Time**: <200ms (average)
- **Memory Usage**: ~512MB (app container)
- **Database**: ~256MB (MySQL container)

---

## 📚 Additional Resources

### Docker Commands Cheat Sheet
```bash
# Container Management
docker-compose up -d                    # Start services in background
docker-compose down                     # Stop and remove containers
docker-compose restart app              # Restart specific service
docker-compose logs -f app              # Follow logs for app service
docker-compose exec app bash            # Access app container shell

# Image Management
docker-compose build                    # Build all images
docker-compose build --no-cache app     # Rebuild app image from scratch
docker-compose pull                     # Pull latest images
docker images                           # List all images
docker image prune -a                   # Remove unused images

# Volume Management
docker volume ls                        # List all volumes
docker volume inspect swinx_mysql_data  # Inspect volume details
docker volume prune                     # Remove unused volumes

# Network Management
docker network ls                       # List networks
docker network inspect swinx_swinx-network  # Inspect network

# System Cleanup
docker system df                        # Show disk usage
docker system prune                     # Remove unused data
docker system prune -a --volumes        # Remove everything unused
```

### Laravel Artisan Commands
```bash
# Application Management
php artisan serve                       # Start development server
php artisan down                        # Put application in maintenance mode
php artisan up                          # Bring application out of maintenance mode
php artisan optimize                    # Optimize application for production
php artisan optimize:clear              # Clear all cached bootstrap files

# Database Commands
php artisan migrate                     # Run migrations
php artisan migrate:fresh               # Drop all tables and re-run migrations
php artisan migrate:fresh --seed        # Fresh migration with seeding
php artisan migrate:rollback            # Rollback migrations
php artisan migrate:status              # Show migration status
php artisan db:seed                     # Run database seeders
php artisan db:wipe                     # Drop all tables

# Cache Management
php artisan cache:clear                 # Clear application cache
php artisan config:clear                # Clear configuration cache
php artisan config:cache                # Cache configuration
php artisan route:clear                 # Clear route cache
php artisan route:cache                 # Cache routes
php artisan view:clear                  # Clear compiled views
php artisan view:cache                  # Cache views

# Queue Management
php artisan queue:work                  # Start processing queue jobs
php artisan queue:restart               # Restart queue workers
php artisan queue:failed                # List failed jobs
php artisan queue:retry all             # Retry all failed jobs

# Development Tools
php artisan make:controller UserController  # Create controller
php artisan make:model User             # Create model
php artisan make:migration create_users_table  # Create migration
php artisan make:seeder UserSeeder      # Create seeder
php artisan make:factory UserFactory    # Create factory
php artisan make:request StoreUserRequest  # Create form request
php artisan make:test UserTest          # Create test

# Debugging
php artisan tinker                      # Interactive shell
php artisan route:list                  # List all routes
php artisan event:list                  # List all events
php artisan schedule:list               # List scheduled commands
```

### Environment Variables Reference
```bash
# Core Application
APP_NAME="Application Name"
APP_ENV=local|production
APP_KEY=base64:32-character-key
APP_DEBUG=true|false
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=http://localhost:8080

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=database_name
DB_USERNAME=username
DB_PASSWORD=password

# Cache Configuration
CACHE_DRIVER=file|redis|database
CACHE_PREFIX=app_cache

# Session Configuration
SESSION_DRIVER=file|database|redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true|false

# Queue Configuration
QUEUE_CONNECTION=sync|database|redis
QUEUE_FAILED_DRIVER=database-uuids

# Mail Configuration
MAIL_MAILER=smtp|log
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=username
MAIL_PASSWORD=password
MAIL_ENCRYPTION=tls|ssl
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# Redis Configuration
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Logging
LOG_CHANNEL=stack|single|daily|stderr
LOG_LEVEL=debug|info|notice|warning|error|critical|alert|emergency

# Development Tools
DEBUGBAR_ENABLED=true|false
TELESCOPE_ENABLED=true|false
```

### File Structure Overview
```
swinx/
├── app/                          # Application code
│   ├── Console/                  # Artisan commands
│   ├── Http/                     # Controllers, middleware, requests
│   ├── Models/                   # Eloquent models
│   ├── Policies/                 # Authorization policies
│   ├── Providers/                # Service providers
│   └── Services/                 # Business logic services
├── bootstrap/                    # Application bootstrap
├── config/                       # Configuration files
├── database/                     # Migrations, seeders, factories
│   ├── factories/                # Model factories
│   ├── migrations/               # Database migrations
│   └── seeders/                  # Database seeders
├── docker/                       # Docker configuration
│   ├── mysql/                    # MySQL configuration
│   ├── nginx/                    # Nginx configuration
│   └── php/                      # PHP configuration
├── docs/                         # Documentation
├── public/                       # Web server document root
│   └── build/                    # Compiled frontend assets
├── resources/                    # Raw assets and views
│   ├── css/                      # Stylesheets
│   ├── js/                       # JavaScript/TypeScript
│   └── views/                    # Blade templates
├── routes/                       # Route definitions
├── scripts/                      # Utility scripts
├── storage/                      # Application storage
│   ├── app/                      # Application files
│   ├── framework/                # Framework files
│   └── logs/                     # Log files
├── tests/                        # Test files
│   ├── Feature/                  # Feature tests
│   └── Unit/                     # Unit tests
├── vendor/                       # Composer dependencies
├── .env.docker.dev               # Development environment
├── .env.docker.production        # Production environment
├── docker-compose.yml            # Main Docker Compose file
├── docker-compose.prod.yml       # Production overrides
├── Dockerfile                    # Application container definition
└── package.json                  # Node.js dependencies
```

### Security Checklist

#### Development Security
- [ ] Use `.env.docker.dev` for local development
- [ ] Keep debug mode enabled for development
- [ ] Use file-based cache and sessions for simplicity
- [ ] Enable debug toolbar for development insights
- [ ] Use sync queue driver for immediate feedback

#### Production Security
- [ ] Use `.env.docker.production` for production
- [ ] Disable debug mode (`APP_DEBUG=false`)
- [ ] Use Redis for cache and sessions
- [ ] Generate strong, unique `APP_KEY`
- [ ] Use strong database passwords
- [ ] Enable HTTPS (`FORCE_HTTPS=true`)
- [ ] Set secure session cookies (`SESSION_SECURE_COOKIE=true`)
- [ ] Configure proper CORS settings
- [ ] Set up rate limiting
- [ ] Enable log monitoring
- [ ] Configure automated backups
- [ ] Set up SSL certificates
- [ ] Use environment-specific secrets
- [ ] Restrict database access
- [ ] Monitor application logs
- [ ] Keep dependencies updated

### Performance Optimization Tips

#### Application Performance
1. **Caching Strategy**
   - Use Redis for production caching
   - Cache configuration, routes, and views
   - Implement query result caching
   - Use HTTP caching headers

2. **Database Optimization**
   - Add proper indexes
   - Use database query optimization
   - Implement connection pooling
   - Monitor slow queries

3. **Frontend Optimization**
   - Minify CSS and JavaScript
   - Use asset versioning
   - Implement lazy loading
   - Optimize images

4. **Server Optimization**
   - Use PHP OPcache
   - Configure proper memory limits
   - Use HTTP/2
   - Implement CDN for static assets

#### Container Optimization
1. **Resource Limits**
   - Set appropriate memory limits
   - Configure CPU limits
   - Monitor resource usage
   - Scale horizontally when needed

2. **Image Optimization**
   - Use multi-stage builds
   - Minimize image layers
   - Use Alpine Linux base images
   - Remove unnecessary packages

### Backup & Recovery Procedures

#### Automated Backup Script
```bash
#!/bin/bash
# /opt/swinx/scripts/backup.sh

set -e

# Configuration
BACKUP_DIR="/opt/swinx/backups"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
echo "Creating database backup..."
docker exec swinx-db mysqldump \
  -u root \
  -p$DB_ROOT_PASSWORD \
  --single-transaction \
  --routines \
  --triggers \
  swinburne > $BACKUP_DIR/db_backup_$DATE.sql

# Application files backup
echo "Creating application files backup..."
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='.git' \
  storage/ .env public/build/

# Clean old backups
echo "Cleaning old backups..."
find $BACKUP_DIR -name "*.sql" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed successfully!"
```

#### Recovery Procedure
```bash
#!/bin/bash
# Recovery from backup

# Stop application
docker-compose down

# Restore database
docker-compose up -d db
sleep 30
docker exec -i swinx-db mysql -u root -p$DB_ROOT_PASSWORD swinburne < backups/db_backup_YYYYMMDD_HHMMSS.sql

# Restore application files
tar -xzf backups/app_backup_YYYYMMDD_HHMMSS.tar.gz

# Start application
docker-compose up -d

# Verify restoration
docker exec swinx-app php artisan migrate:status
```

---

*Last Updated: June 9, 2025*
*Version: 1.0.0*
*Maintainer: Development Team*
