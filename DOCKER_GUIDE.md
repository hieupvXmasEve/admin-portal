# 🐳 Swinx Docker Environment Guide

Complete guide for managing the Swinx Laravel application using Docker with FrankenPHP.

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Environment Overview](#environment-overview)
3. [Development Environment](#development-environment)
4. [Local Production Testing](#local-production-testing)
5. [Production Deployment](#production-deployment)
6. [Environment Configuration](#environment-configuration)
7. [Troubleshooting](#troubleshooting)
8. [Advanced Usage](#advanced-usage)

---

## 🚀 Quick Start

### Prerequisites
- Docker Engine 20.10+
- Docker Compose 2.0+
- 8GB+ RAM recommended
- 10GB+ free disk space

### Choose Your Environment

```bash
# Development (recommended for daily work)
./dev.sh start

# Local Production Testing
./local-prod.sh start

# Production Deployment
./prod.sh deploy
```

---

## 🏗️ Environment Overview

The Docker setup provides three clean, purpose-built environments:

### 🔧 Development Environment
- **Purpose**: Daily development work
- **Services**: FrankenPHP + MySQL
- **Features**: Hot reload, debug mode, HTTP only
- **Port**: http://localhost:8080
- **Script**: `./dev.sh`

### 🧪 Local Production Testing
- **Purpose**: Test production configurations locally
- **Services**: FrankenPHP + MySQL (production config)
- **Features**: HTTPS, production optimizations, SSL certificates
- **URL**: https://swinx.test
- **Script**: `./local-prod.sh`

### 🚀 Production Environment
- **Purpose**: Live production deployment
- **Services**: FrankenPHP + MySQL (production hardened)
- **Features**: Full SSL, monitoring, backups, safety checks
- **Script**: `./prod.sh`

---

## 🔧 Development Environment

### Getting Started

```bash
# Start development environment
./dev.sh start

# View logs
./dev.sh logs

# Check status
./dev.sh status

# Access application shell
./dev.sh shell

# Stop environment
./dev.sh stop
```

### Laravel Development Commands

```bash
# Run artisan commands
./dev.sh artisan migrate
./dev.sh artisan make:controller UserController

# Composer operations
./dev.sh composer install
./dev.sh composer require package/name

# NPM operations
./dev.sh npm install
./dev.sh npm run dev

# Run tests
./dev.sh test
./dev.sh test --filter=UserTest
```

### Development Workflow

1. **Start Environment**: `./dev.sh start`
2. **Code Changes**: Edit files normally (hot reload enabled)
3. **Database Changes**: `./dev.sh artisan migrate`
4. **Frontend Changes**: `./dev.sh npm run dev`
5. **Run Tests**: `./dev.sh test`
6. **Stop Environment**: `./dev.sh stop`

### File Structure
```
├── docker-compose.dev.yml    # Development configuration
├── Dockerfile               # Development container
├── Caddyfile.dev           # FrankenPHP development config
├── .env.docker.dev         # Development environment variables
└── dev.sh                  # Development management script
```

---

## 🧪 Local Production Testing

### Setup

```bash
# Add to /etc/hosts (required for SSL)
echo "127.0.0.1 swinx.test" | sudo tee -a /etc/hosts

# Start local production environment
./local-prod.sh start
```

### Testing Commands

```bash
# Test SSL configuration
./local-prod.sh test-ssl

# Run performance tests
./local-prod.sh test-perf

# Create database backup
./local-prod.sh backup

# View production logs
./local-prod.sh logs
```

### SSL Certificates

The script automatically creates self-signed certificates for local testing:
- **Certificate**: `ssl/fullchain.pem`
- **Private Key**: `ssl/privkey.pem`
- **Domain**: `swinx.test`

### File Structure
```
├── docker-compose.local-prod.yml  # Local production configuration
├── Dockerfile.production         # Production container
├── Caddyfile.local-prod          # FrankenPHP production config
├── .env.docker.production        # Production environment variables
├── ssl/                          # SSL certificates directory
└── local-prod.sh                 # Local production management script
```

---

## 🚀 Production Deployment

### Pre-deployment Checklist

1. **Environment File**: Ensure `.env.docker.production` is properly configured
2. **Domain Setup**: Configure DNS to point to your server
3. **SSL Certificates**: Let's Encrypt will auto-generate certificates
4. **Backup Strategy**: Ensure backup directory exists

### Deployment Process

```bash
# Full production deployment (includes backup)
./prod.sh deploy

# Manual steps
./prod.sh backup          # Create backup first
./prod.sh start           # Start production environment
./prod.sh health          # Verify deployment
```

### Production Management

```bash
# Monitor status
./prod.sh status
./prod.sh logs

# Maintenance mode
./prod.sh maintenance on
./prod.sh maintenance off

# Health checks
./prod.sh health

# Database backup
./prod.sh backup
```

### Safety Features

- **Confirmation Required**: All production commands require explicit confirmation
- **Automatic Backups**: Database backup before deployment
- **Health Checks**: Comprehensive application and container health monitoring
- **Maintenance Mode**: Easy maintenance mode toggle

### File Structure
```
├── docker-compose.production.yml  # Production configuration
├── Dockerfile.production         # Production container
├── Caddyfile.prod                # FrankenPHP production config
├── .env.docker.production        # Production environment variables
├── backups/                      # Database backups directory
└── prod.sh                       # Production management script
```

---

## ⚙️ Environment Configuration

### Environment Files

Create environment files based on your needs:

```bash
# Copy example file
cp env.docker.example .env.docker.dev
cp env.docker.example .env.docker.production
```

### Key Configuration Variables

#### Development (.env.docker.dev)
```env
APP_ENV=local
APP_DEBUG=true
DB_HOST=db
DB_DATABASE=swinburne
DB_USERNAME=swinx_user
DB_PASSWORD=swinx_password
```

#### Production (.env.docker.production)
```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=db
DB_DATABASE=swinburne
DB_USERNAME=swinx_prod_user
DB_PASSWORD=SecureProductionPassword
SERVER_NAME=yourdomain.com
ACME_EMAIL=admin@yourdomain.com
```

### Database Configuration

Each environment uses separate database volumes:
- **Development**: `mysql_data_dev`
- **Local Production**: `mysql_data_local_prod`
- **Production**: `mysql_data`

### FrankenPHP Configuration

Environment-specific Caddyfiles:
- **Development**: `Caddyfile.dev` (HTTP only)
- **Local Production**: `Caddyfile.local-prod` (HTTPS with self-signed certs)
- **Production**: `Caddyfile.prod` (HTTPS with Let's Encrypt)

---

## 🔧 Troubleshooting

### Common Issues

#### Port Already in Use
```bash
# Check what's using the port
sudo lsof -i :8080
sudo lsof -i :3306

# Stop conflicting services
./dev.sh stop
./local-prod.sh stop
```

#### Permission Issues
```bash
# Fix storage permissions
./dev.sh shell
chown -R www-data:www-data /app/storage
chmod -R 775 /app/storage
```

#### Database Connection Issues
```bash
# Check database status
./dev.sh status

# View database logs
./dev.sh logs db

# Access database directly
./dev.sh mysql
```

#### SSL Certificate Issues (Local Production)
```bash
# Regenerate self-signed certificates
rm -rf ssl/
./local-prod.sh start  # Will recreate certificates
```

### Debug Commands

```bash
# View all container logs
./dev.sh logs

# Check container health
docker ps
docker stats

# Access container shell
./dev.sh shell

# Check FrankenPHP configuration
./dev.sh shell
frankenphp validate --config /etc/caddy/Caddyfile
```

### Clean Reset

```bash
# Complete environment reset
./dev.sh clean          # Development
./local-prod.sh clean    # Local production
./prod.sh backup && ./prod.sh stop  # Production (backup first!)

# System cleanup
docker system prune -a
docker volume prune
```

---

## 🚀 Advanced Usage

### Custom Commands

Add custom commands to the management scripts by editing:
- `dev.sh` - Development commands
- `local-prod.sh` - Local production commands  
- `prod.sh` - Production commands

### Multiple Environments

Run multiple environments simultaneously:
```bash
# Development on port 8080
./dev.sh start

# Local production on port 443
./local-prod.sh start
```

### Performance Monitoring

```bash
# Resource usage
./dev.sh status

# Performance testing
./local-prod.sh test-perf

# Production health monitoring
./prod.sh health
```

### Backup Strategy

```bash
# Automated backups (add to cron)
0 2 * * * /path/to/swinx/prod.sh backup

# Backup retention (keep last 7 days)
find backups/ -name "*.gz" -mtime +7 -delete
```

---

## 📚 Additional Resources

### Docker Commands Reference
```bash
# View all containers
docker ps -a

# View images
docker images

# View volumes
docker volume ls

# System information
docker system df
docker system info
```

### FrankenPHP Resources
- [FrankenPHP Documentation](https://frankenphp.dev/)
- [Caddy Documentation](https://caddyserver.com/docs/)
- [Laravel Octane](https://laravel.com/docs/octane)

### Support
- Check logs first: `./[env].sh logs`
- Verify configuration: `./[env].sh status`
- Clean reset if needed: `./[env].sh clean`
- Review this guide for common solutions

---

## 📝 Summary

This Docker setup provides three clean, purpose-built environments:

1. **Development** (`./dev.sh`) - Fast, feature-rich development environment
2. **Local Production** (`./local-prod.sh`) - Test production configurations locally
3. **Production** (`./prod.sh`) - Secure, monitored production deployment

Each environment is completely isolated with its own:
- Docker Compose configuration
- Environment variables
- Database volumes
- Management script
- SSL configuration

The setup eliminates complexity while providing powerful, easy-to-use tools for every stage of development and deployment.
