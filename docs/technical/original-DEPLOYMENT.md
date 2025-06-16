# 🚀 Production Deployment Guide

This guide provides step-by-step instructions for setting up a complete CI/CD pipeline for the Swinx application using Docker Compose on your own production server.

## 📋 Prerequisites

- Production server with Ubuntu 20.04+ or similar Linux distribution
- Domain name configured to point to your server
- SSH access to the production server
- GitHub repository with appropriate permissions

## 🏗️ Architecture Overview

```
Internet → Nginx (SSL/Reverse Proxy) → Laravel App → MySQL/Redis
                                    ↓
                              Queue Workers & Scheduler
```

## 📦 Phase 1: Server Setup

### 1.1 Initial Server Configuration

Run the server setup script on your production server:

```bash
# On your production server (as root)
wget https://raw.githubusercontent.com/swinburne-edu/swin-new/main/scripts/server-setup.sh
chmod +x server-setup.sh
./server-setup.sh
```

This script will:
- Install Docker and Docker Compose
- Create application user and directories
- Configure firewall and security
- Set up SSL certificates
- Configure log rotation and monitoring

### 1.2 Manual Configuration Steps

After running the setup script:

1. **Add deployment SSH key:**
   ```bash
   # Generate SSH key pair for deployment (on your local machine)
   ssh-keygen -t ed25519 -C "deployment@swinx" -f ~/.ssh/swinx_deploy
   
   # Add public key to server
   cat ~/.ssh/swinx_deploy.pub | ssh root@your-server "sudo -u swinx tee -a /home/swinx/.ssh/authorized_keys"
   ```

2. **Configure SSL certificate:**
   ```bash
   # On production server (after DNS is configured)
   certbot certonly --standalone -d your-domain.com -d www.your-domain.com --email admin@your-domain.com --agree-tos --non-interactive
   ```

3. **Clone repository:**
   ```bash
   # On production server as swinx user
   sudo -u swinx git clone https://github.com/swinburne-edu/swin-new.git /opt/swinx
   cd /opt/swinx
   ```

## 🔧 Phase 2: GitHub Configuration

### 2.1 Repository Secrets

Configure the following secrets in your GitHub repository (Settings → Secrets and variables → Actions):

```
PRODUCTION_HOST=your-server-ip-or-domain
PRODUCTION_USER=swinx
PRODUCTION_SSH_KEY=<contents of ~/.ssh/swinx_deploy private key>
PRODUCTION_PORT=22
PRODUCTION_APP_PATH=/opt/swinx
PRODUCTION_URL=https://your-domain.com
```

### 2.2 Environment Variables

Update `.env.docker.production` with your actual values:

```bash
# Copy and customize production environment
cp .env.docker.production .env.docker.production.local
nano .env.docker.production.local
```

Required changes:
- `APP_URL`: Your actual domain
- `DB_PASSWORD`: Strong database password
- `DB_ROOT_PASSWORD`: Strong root password
- `REDIS_PASSWORD`: Strong Redis password
- Domain-specific settings

## 🐳 Phase 3: Docker Configuration

### 3.1 Production Docker Compose

The production setup includes:
- **Nginx**: Reverse proxy with SSL termination
- **Laravel App**: Main application container
- **MySQL**: Database with production optimizations
- **Redis**: Cache and session storage
- **Queue Worker**: Background job processing
- **Scheduler**: Cron job handling

### 3.2 SSL Certificate Setup

```bash
# Create SSL directory
mkdir -p /opt/swinx/ssl

# Link Let's Encrypt certificates
ln -sf /etc/letsencrypt/live/your-domain.com/fullchain.pem /opt/swinx/ssl/fullchain.pem
ln -sf /etc/letsencrypt/live/your-domain.com/privkey.pem /opt/swinx/ssl/privkey.pem
```

## 🚀 Phase 4: Deployment Process

### 4.1 Initial Deployment

```bash
# On production server as swinx user
cd /opt/swinx

# Set up environment
cp .env.docker.production.local .env.docker.production

# Create image tag file
echo "DOCKER_IMAGE=ghcr.io/swinburne-edu/swin-new:latest" > .env.docker.image

# Run initial deployment
./scripts/deploy.sh
```

### 4.2 Automated Deployment

Once configured, deployments happen automatically:

1. **Push to main branch** triggers GitHub Actions
2. **Tests run** to ensure code quality
3. **Docker image builds** and pushes to GitHub Container Registry
4. **Deployment script runs** on production server
5. **Health checks verify** successful deployment

## 🔍 Phase 5: Monitoring and Maintenance

### 5.1 Health Checks

The application includes several health check endpoints:
- `https://your-domain.com/up` - Laravel health check
- `https://your-domain.com/health` - Nginx health check

### 5.2 Log Monitoring

```bash
# View application logs
docker-compose -f docker-compose.production.yml logs -f app

# View Nginx logs
docker-compose -f docker-compose.production.yml logs -f nginx

# View deployment logs
tail -f /opt/swinx/logs/deploy.log
```

### 5.3 Database Backups

Automatic backups are created during each deployment:
```bash
# View backups
ls -la /opt/swinx/backups/

# Manual backup
docker-compose -f docker-compose.production.yml exec db mysqldump -u root -p --all-databases > backup.sql
```

## 🛠️ Troubleshooting

### Common Issues

1. **SSL Certificate Issues:**
   ```bash
   # Check certificate status
   certbot certificates
   
   # Renew certificate
   certbot renew --dry-run
   ```

2. **Container Health Issues:**
   ```bash
   # Check container status
   docker-compose -f docker-compose.production.yml ps
   
   # Restart specific service
   docker-compose -f docker-compose.production.yml restart app
   ```

3. **Database Connection Issues:**
   ```bash
   # Check database logs
   docker-compose -f docker-compose.production.yml logs db
   
   # Connect to database
   docker-compose -f docker-compose.production.yml exec db mysql -u root -p
   ```

### Emergency Procedures

1. **Rollback Deployment:**
   ```bash
   # Use previous image
   echo "DOCKER_IMAGE=ghcr.io/swinburne-edu/swin-new:previous-tag" > .env.docker.image
   ./scripts/deploy.sh
   ```

2. **Scale Services:**
   ```bash
   # Scale app containers
   docker-compose -f docker-compose.production.yml up -d --scale app=3
   ```

## 📊 Performance Optimization

### Resource Limits

The production configuration includes resource limits:
- **App**: 1GB RAM, 1 CPU
- **Database**: 1GB RAM, 0.5 CPU
- **Redis**: 256MB RAM, 0.25 CPU

### Scaling

To handle increased load:
```bash
# Scale application containers
docker-compose -f docker-compose.production.yml up -d --scale app=3 --scale queue=2
```

## 🔒 Security Considerations

1. **Regular Updates:**
   - Keep Docker images updated
   - Apply security patches
   - Update SSL certificates

2. **Access Control:**
   - Use strong passwords
   - Limit SSH access
   - Monitor access logs

3. **Backup Strategy:**
   - Regular database backups
   - Application file backups
   - Test restore procedures

## 📞 Support

For deployment issues:
1. Check logs in `/opt/swinx/logs/`
2. Review container status
3. Verify environment configuration
4. Contact development team with specific error messages
