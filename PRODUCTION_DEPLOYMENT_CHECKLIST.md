# 📋 Production Deployment Checklist

Complete checklist for deploying Swinx application to production with CI/CD pipeline.

## ✅ Pre-Deployment Requirements

### 🖥️ Server Requirements
- [ ] Production server with Ubuntu 20.04+ (minimum 2GB RAM, 20GB storage)
- [ ] SSH access configured with key-based authentication
- [ ] Domain name purchased and DNS configured
- [ ] SSL certificate ready or Let's Encrypt available

### 🔐 Security Requirements
- [ ] Firewall configured (ports 22, 80, 443 only)
- [ ] Fail2ban installed and configured
- [ ] Strong passwords generated for all services
- [ ] SSH keys generated for deployment

## 🚀 Step 1: Server Setup

### 1.1 Run Server Setup Script
```bash
# On production server as root
wget https://raw.githubusercontent.com/your-username/swinx/main/scripts/server-setup.sh
chmod +x server-setup.sh
./server-setup.sh
```

**Verify:**
- [ ] Docker and Docker Compose installed
- [ ] User 'swinx' created with Docker access
- [ ] Firewall active and configured
- [ ] Basic security measures in place

### 1.2 SSH Key Configuration
```bash
# On local machine
ssh-keygen -t ed25519 -C "deployment@swinx" -f ~/.ssh/swinx_deploy

# Add public key to server
cat ~/.ssh/swinx_deploy.pub | ssh root@your-server "sudo -u swinx tee -a /home/swinx/.ssh/authorized_keys"
```

**Test:**
- [ ] SSH connection works: `ssh -i ~/.ssh/swinx_deploy swinx@your-server`

## 🌐 Step 2: Domain and SSL

### 2.1 DNS Configuration
- [ ] A record: your-domain.com → server IP
- [ ] A record: www.your-domain.com → server IP
- [ ] DNS propagation verified: `dig your-domain.com`

### 2.2 SSL Certificate
```bash
# On production server
sudo certbot certonly --standalone -d your-domain.com -d www.your-domain.com --email admin@your-domain.com --agree-tos --non-interactive
```

**Verify:**
- [ ] Certificate obtained successfully
- [ ] Certificate linked to application directory

## 📦 Step 3: Repository Setup

### 3.1 Clone and Configure
```bash
# On production server as swinx user
sudo -u swinx git clone https://github.com/your-username/swinx.git /opt/swinx
cd /opt/swinx
```

### 3.2 Environment Configuration
```bash
# Copy and customize production environment
sudo -u swinx cp .env.docker.production .env.docker.production.local
sudo -u swinx nano .env.docker.production.local
```

**Required Updates:**
- [ ] `APP_URL=https://your-actual-domain.com`
- [ ] `DB_PASSWORD=secure-database-password`
- [ ] `DB_ROOT_PASSWORD=secure-root-password`
- [ ] `REDIS_PASSWORD=secure-redis-password`
- [ ] Domain-specific settings updated

## 🐙 Step 4: GitHub Configuration

### 4.1 Repository Secrets
Add these secrets in GitHub → Settings → Secrets and variables → Actions:

- [ ] `PRODUCTION_HOST`: your-server-ip-or-domain
- [ ] `PRODUCTION_USER`: swinx
- [ ] `PRODUCTION_SSH_KEY`: (private key content)
- [ ] `PRODUCTION_PORT`: 22
- [ ] `PRODUCTION_APP_PATH`: /opt/swinx
- [ ] `PRODUCTION_URL`: https://your-domain.com

### 4.2 Workflow Configuration
- [ ] Update `.github/workflows/deploy.yml` with correct repository name
- [ ] Verify workflow triggers on main/production branch

## 🚀 Step 5: Initial Deployment

### 5.1 Prepare Deployment
```bash
# On production server
sudo -u swinx echo "DOCKER_IMAGE=ghcr.io/your-username/swinx:latest" > /opt/swinx/.env.docker.image
sudo -u swinx chmod +x /opt/swinx/scripts/deploy.sh
```

### 5.2 Run Initial Deployment
```bash
cd /opt/swinx
./scripts/deploy.sh
```

**Verify:**
- [ ] All containers started successfully
- [ ] Database migrations completed
- [ ] Application accessible via HTTPS
- [ ] Health checks passing

## 🔄 Step 6: Test CI/CD Pipeline

### 6.1 Test Deployment
```bash
# On local machine
git checkout -b test-deployment
echo "# Test CI/CD" >> README.md
git add README.md
git commit -m "Test: CI/CD pipeline"
git push origin test-deployment
```

### 6.2 Verify Pipeline
- [ ] Create and merge PR to main branch
- [ ] GitHub Actions workflow runs successfully
- [ ] Application deploys automatically
- [ ] Health checks pass after deployment

## ✅ Step 7: Post-Deployment Verification

### 7.1 Functional Testing
- [ ] Application loads: https://your-domain.com
- [ ] SSL certificate valid (green lock)
- [ ] HTTP redirects to HTTPS
- [ ] Login functionality works
- [ ] Database operations work
- [ ] File uploads work (if applicable)

### 7.2 Performance Testing
- [ ] Page load times acceptable (<3 seconds)
- [ ] Database queries performing well
- [ ] Memory usage within limits
- [ ] CPU usage normal

### 7.3 Security Testing
- [ ] Security headers present
- [ ] No sensitive information exposed
- [ ] Rate limiting working
- [ ] HTTPS enforced

## 🔧 Step 8: Monitoring Setup

### 8.1 Log Monitoring
```bash
# Set up log aliases
echo 'alias app-logs="docker-compose -f /opt/swinx/docker-compose.production.yml logs -f app"' >> ~/.bashrc
echo 'alias nginx-logs="docker-compose -f /opt/swinx/docker-compose.production.yml logs -f nginx"' >> ~/.bashrc
```

### 8.2 Health Monitoring
```bash
# Add health check cron job
echo "*/5 * * * * curl -f https://your-domain.com/up || echo 'Health check failed' | logger" | crontab -
```

**Verify:**
- [ ] Log monitoring accessible
- [ ] Health checks automated
- [ ] Backup procedures tested

## 🆘 Emergency Procedures

### Rollback Process
```bash
# If deployment fails
cd /opt/swinx
echo "DOCKER_IMAGE=ghcr.io/your-username/swinx:previous-tag" > .env.docker.image
./scripts/deploy.sh
```

### Service Management
```bash
# Restart all services
docker-compose -f docker-compose.production.yml restart

# Scale services if needed
docker-compose -f docker-compose.production.yml up -d --scale app=2
```

## 📊 Final Verification

### Production Readiness Checklist
- [ ] Application accessible via HTTPS
- [ ] SSL certificate valid and auto-renewing
- [ ] CI/CD pipeline working end-to-end
- [ ] Monitoring and alerting in place
- [ ] Backup procedures tested
- [ ] Emergency procedures documented
- [ ] Team notified of deployment
- [ ] Documentation updated

### Performance Metrics
- [ ] Response time < 3 seconds
- [ ] Memory usage < 80%
- [ ] CPU usage < 70%
- [ ] Disk usage < 80%

### Security Verification
- [ ] All default passwords changed
- [ ] Security headers configured
- [ ] Firewall rules active
- [ ] SSL/TLS properly configured
- [ ] Access logs monitored

## 📞 Support Information

**Emergency Contacts:**
- Development Team: dev@your-company.com
- Server Admin: admin@your-company.com
- Emergency Phone: +1-xxx-xxx-xxxx

**Key Resources:**
- Application URL: https://your-domain.com
- Server IP: your-server-ip
- GitHub Repository: https://github.com/your-username/swinx
- Documentation: DEPLOYMENT.md

## 🎉 Deployment Complete!

**Congratulations!** Your production deployment is now complete and operational.

**Next Steps:**
1. Monitor application performance for 24-48 hours
2. Set up regular backup schedules
3. Plan for scaling if needed
4. Schedule regular security updates
