# 🚀 Deployment Guide

Complete deployment guide for the Swinburne Project Management System with Docker and CI/CD pipeline.

## 📋 Prerequisites

### Server Requirements
- **OS**: Ubuntu 20.04+ or similar Linux distribution
- **RAM**: Minimum 4GB (8GB recommended)
- **Storage**: Minimum 20GB SSD
- **Network**: Public IP address with domain name
- **Access**: SSH access with sudo privileges

### Required Services
- **Docker**: Latest version
- **Docker Compose**: V2 or higher
- **Git**: For repository management
- **SSL Certificate**: Let's Encrypt (automated)

## 🏗️ Architecture Overview

```
Internet → FrankenPHP (SSL/HTTP) → Laravel App → MySQL/Redis
                                ↓
                          Queue Workers & Scheduler
```

### Environment Types
- **Development**: HTTP-only, debug enabled, hot reload
- **Local Production**: HTTPS testing, production-like environment
- **Production**: Full HTTPS, SSL certificates, optimized performance

## 🐳 Docker Deployment

### Quick Start Commands

```bash
# Development environment
./dev.sh start

# Local production testing
./local-prod.sh start

# Production deployment
./prod.sh deploy
```

### Environment Configuration

Each environment uses specific configuration files:
- **Development**: `.env.docker.dev`
- **Local Production**: `.env.docker.local-prod`
- **Production**: `.env.docker.production`

## 🔧 Production Server Setup

### 1. Initial Server Configuration

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo apt install docker-compose-plugin

# Create application user
sudo useradd -m -s /bin/bash swinx
sudo usermod -aG docker swinx
```

### 2. SSL Certificate Setup

```bash
# Install Certbot
sudo apt install certbot

# Generate SSL certificate
sudo certbot certonly --standalone \
  -d your-domain.com \
  -d www.your-domain.com \
  --email admin@your-domain.com \
  --agree-tos --non-interactive

# Create SSL directory
sudo mkdir -p /opt/swinx/ssl
sudo ln -sf /etc/letsencrypt/live/your-domain.com/fullchain.pem /opt/swinx/ssl/fullchain.pem
sudo ln -sf /etc/letsencrypt/live/your-domain.com/privkey.pem /opt/swinx/ssl/privkey.pem
```

### 3. Application Deployment

```bash
# Clone repository
sudo -u swinx git clone <repository-url> /opt/swinx
cd /opt/swinx

# Configure environment
sudo -u swinx cp .env.docker.production.example .env.docker.production
sudo -u swinx nano .env.docker.production

# Deploy application
sudo -u swinx ./prod.sh deploy
```

## ⚙️ Environment Configuration

### Production Environment Variables

```env
# Application
APP_NAME="Swinburne Project Management"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=swinx
DB_USERNAME=swinx
DB_PASSWORD=your-secure-password

# Cache
REDIS_HOST=redis
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379

# SSL Configuration
SSL_ENABLED=true
DOMAIN_NAME=your-domain.com
```

### Security Configuration

```env
# Security
APP_KEY=base64:your-generated-key
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# File Upload
IMPORT_MAX_FILE_SIZE=10MB
UPLOAD_MAX_FILESIZE=10M
POST_MAX_SIZE=10M
```

## 🔄 CI/CD Pipeline

### GitHub Actions Setup

Configure repository secrets in GitHub:
```
PRODUCTION_HOST=your-server-ip
PRODUCTION_USER=swinx
PRODUCTION_SSH_KEY=<private-key-content>
PRODUCTION_APP_PATH=/opt/swinx
```

### Automated Deployment Process

1. **Code Push**: Push to main branch triggers deployment
2. **Testing**: Automated tests run in CI environment
3. **Build**: Docker image builds and pushes to registry
4. **Deploy**: Application deploys to production server
5. **Health Check**: Verify deployment success

### Manual Deployment

```bash
# On production server
cd /opt/swinx

# Pull latest changes
git pull origin main

# Deploy with Docker
./prod.sh deploy

# Check deployment status
./prod.sh status
```

## 🔍 Monitoring and Maintenance

### Health Checks

```bash
# Check application status
curl https://your-domain.com/up

# Check container status
docker compose -f docker-compose.production.yml ps

# View logs
docker compose -f docker-compose.production.yml logs -f app
```

### Log Management

```bash
# Application logs
tail -f storage/logs/laravel.log

# Deployment logs
tail -f logs/deploy-production.log

# Access logs
tail -f logs/swinx-access.log
```

### Database Backups

```bash
# Automatic backup (runs during deployment)
./scripts/backup-database.sh

# Manual backup
docker compose -f docker-compose.production.yml exec db \
  mysqldump -u root -p swinx > backup-$(date +%Y%m%d).sql

# View backups
ls -la backups/
```

## 🛠️ Troubleshooting

### Common Issues

**SSL Certificate Problems:**
```bash
# Check certificate status
sudo certbot certificates

# Renew certificate
sudo certbot renew --dry-run

# Restart services after renewal
./prod.sh restart
```

**Container Issues:**
```bash
# Check container logs
docker compose -f docker-compose.production.yml logs app

# Restart specific service
docker compose -f docker-compose.production.yml restart app

# Rebuild and restart
./prod.sh rebuild
```

**Database Connection Issues:**
```bash
# Check database status
docker compose -f docker-compose.production.yml exec db mysql -u root -p

# Reset database connection
./prod.sh restart db
```

### Emergency Procedures

**Rollback Deployment:**
```bash
# Rollback to previous version
git checkout HEAD~1
./prod.sh deploy

# Or use backup
./scripts/restore-backup.sh backup-20231201.sql
```

**Scale Services:**
```bash
# Scale application containers
docker compose -f docker-compose.production.yml up -d --scale app=3
```

## 📊 Performance Optimization

### Resource Monitoring

```bash
# Monitor resource usage
docker stats

# Check disk usage
df -h

# Monitor memory usage
free -h
```

### Optimization Settings

```bash
# Optimize Laravel for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Database optimization
php artisan migrate --force
php artisan db:seed --force
```

## 🔒 Security Best Practices

### Server Security
- Keep system updated with security patches
- Use strong passwords and SSH keys
- Configure firewall rules
- Regular security audits

### Application Security
- Enable HTTPS with valid SSL certificates
- Use secure session configuration
- Implement proper authentication and authorization
- Regular dependency updates

### Backup Strategy
- Daily automated database backups
- Weekly full application backups
- Test restore procedures regularly
- Store backups in secure, off-site location

## 🔗 Related Documentation

- **[Installation Guide](installation-en.md)** - Setup and installation instructions
- **[Development Guide](development-en.md)** - Development standards and workflows
- **[User Guide](user-guide-en.md)** - System functionality and usage
- **[API Documentation](api-documentation-en.md)** - API endpoints and integration

## 📞 Support

For deployment issues:
1. Check application logs in `storage/logs/`
2. Review container status with `docker ps`
3. Verify environment configuration
4. Contact development team with specific error messages

---

*Last updated: {{ date('Y-m-d') }}*
