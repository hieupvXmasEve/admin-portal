# 🐳 SWINX DOCKER DEPLOYMENT - STEP BY STEP GUIDE

## 📋 **OVERVIEW**
Complete guide to deploy SWINX Laravel application using Docker with development and production environments.

---

## 🎯 **PREREQUISITES**
- Docker Desktop installed and running
- Docker Compose v2.0+
- Git repository cloned
- Terminal/Command Line access

---

## 🚀 **STEP-BY-STEP DEPLOYMENT**

### **Step 1: Clean Environment**
```bash
# Stop any running containers
docker-compose down -v --remove-orphans

# Clean Docker system
docker system prune -f

# Verify clean state
docker ps -a
```

### **Step 2: Setup Development Environment**
```bash
# Copy development environment file
cp .env.docker.dev .env

# Generate new APP_KEY
openssl rand -base64 32

# Update .env with the generated key
# Replace: APP_KEY=base64:GENERATED_KEY_HERE
```

### **Step 3: Build and Start Services**
```bash
# Build and start all services
docker-compose up -d --build

# This will start:
# - swinx-app (Laravel + Nginx + PHP-FPM)
# - swinx-db (MySQL 8.0 main database)
# - swinx-test-db (MySQL 8.0 test database)
# - swinx-redis (Redis 7.4 cache)
# - swinx-phpmyadmin (Database admin interface)
```

### **Step 4: Verify Services Status**
```bash
# Check all containers status
docker-compose ps

# Expected output:
# ✅ swinx-db          - Up (healthy)
# ✅ swinx-test-db     - Up (healthy)  
# ✅ swinx-redis       - Up (healthy)
# ✅ swinx-phpmyadmin  - Up
# ⚠️  swinx-app        - Up or Restarting
```

### **Step 5: Debug App Container (if restarting)**
```bash
# Check app logs
docker logs swinx-app --tail 20

# Check environment variables
docker exec swinx-app env | grep -E "DB_|APP_"

# Manual database connection test
docker exec swinx-app php artisan tinker
# In tinker: DB::connection()->getPdo();
```

### **Step 6: Manual Migration (if needed)**
```bash
# Wait for database to be ready
sleep 30

# Run migrations manually
docker exec swinx-app php artisan migrate --force

# Seed database (optional)
docker exec swinx-app php artisan db:seed --force
```

### **Step 7: Test Application**
```bash
# Test HTTP response
curl -I http://localhost:8080

# Expected: HTTP/1.1 200 OK or 302 Found (redirect)
# NOT: HTTP/1.1 500 Internal Server Error
```

---

## 🌐 **ACCESS POINTS**

| Service | URL | Purpose |
|---------|-----|---------|
| **Main App** | http://localhost:8080 | Laravel Application |
| **phpMyAdmin** | http://localhost:8081 | Database Management |
| **MySQL Main** | localhost:3306 | Main Database |
| **MySQL Test** | localhost:3307 | Test Database |
| **Redis** | localhost:6379 | Cache Service |

---

## 🔧 **ENVIRONMENT FILES**

### **Development (.env.docker.dev)**
- Debug mode enabled
- Local database credentials
- Development APP_KEY
- Detailed error reporting

### **Production (.env.docker.production)**
- Debug mode disabled
- Secure database credentials
- Production APP_KEY
- Minimal error reporting

---

## 🛠️ **COMMON ISSUES & SOLUTIONS**

### **Issue 1: App Container Restarting**
**Symptoms:** `swinx-app` shows "Restarting" status
**Causes:** 
- Database connection failure
- Invalid APP_KEY
- Missing environment variables

**Solutions:**
```bash
# Check logs
docker logs swinx-app --tail 20

# Verify .env file
cat .env | grep -E "DB_|APP_"

# Rebuild with fresh environment
docker-compose down
cp .env.docker.dev .env
docker-compose up -d --build
```

### **Issue 2: Database Connection Error**
**Symptoms:** "Access denied for user" or "Connection refused"
**Solutions:**
```bash
# Wait for database initialization
sleep 60

# Check database health
docker exec swinx-db mysql -u swinx_user -pswinx_password -e "SELECT 1"

# Reset database
docker-compose down -v
docker-compose up -d
```

### **Issue 3: Port Conflicts**
**Symptoms:** "Port already in use" errors
**Solutions:**
```bash
# Check port usage
lsof -i :8080
lsof -i :3306

# Stop conflicting services
sudo service mysql stop
sudo service nginx stop

# Or change ports in docker-compose.yml
```

---

## 🎯 **PRODUCTION DEPLOYMENT**

### **Step 1: Switch to Production Environment**
```bash
# Stop development
docker-compose down

# Copy production environment
cp .env.docker.production .env

# Generate secure APP_KEY
openssl rand -base64 32
# Update .env with new key

# Update database credentials in .env
```

### **Step 2: Deploy Production**
```bash
# Deploy with production overrides
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# Optimize Laravel for production
docker exec swinx-app php artisan config:cache
docker exec swinx-app php artisan route:cache
docker exec swinx-app php artisan view:cache
```

---

## 📊 **MONITORING & MAINTENANCE**

### **Health Checks**
```bash
# Check all services
docker-compose ps

# Check logs
docker-compose logs -f

# Check resource usage
docker stats
```

### **Backup Database**
```bash
# Backup main database
docker exec swinx-db mysqldump -u swinx_user -pswinx_password swinburne > backup.sql

# Restore database
docker exec -i swinx-db mysql -u swinx_user -pswinx_password swinburne < backup.sql
```

### **Update Application**
```bash
# Pull latest code
git pull origin main

# Rebuild and restart
docker-compose down
docker-compose up -d --build

# Run migrations
docker exec swinx-app php artisan migrate --force
```

---

## 🎉 **SUCCESS CRITERIA**

✅ **All containers running and healthy**
✅ **Application accessible at http://localhost:8080**
✅ **Database connections working**
✅ **phpMyAdmin accessible at http://localhost:8081**
✅ **No container restart loops**
✅ **Logs show no critical errors**

---

## 📞 **SUPPORT**

If you encounter issues:
1. Check this guide first
2. Review container logs: `docker logs <container_name>`
3. Verify environment files
4. Ensure all prerequisites are met
5. Try clean rebuild: `docker-compose down -v && docker-compose up -d --build`

---

## 🔍 **ADVANCED TROUBLESHOOTING**

### **Debug Container Environment**
```bash
# Check environment variables inside container
docker exec swinx-app env | sort

# Check mounted volumes
docker exec swinx-app ls -la /var/www/html

# Check .env file inside container
docker exec swinx-app cat /var/www/html/.env

# Test database connection manually
docker exec swinx-app php -r "
try {
    \$pdo = new PDO('mysql:host=db;port=3306', 'swinx_user', 'swinx_password');
    echo 'Database connection successful\n';
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . '\n';
}
"
```

### **Container Restart Loop Fix**
```bash
# Stop the problematic container
docker stop swinx-app

# Check if .env is properly mounted
docker run --rm -v $(pwd):/app alpine ls -la /app/.env

# Rebuild with no cache
docker-compose build --no-cache app
docker-compose up -d app

# If still failing, check start.sh script
docker exec swinx-app cat /start.sh
```

### **Database Issues**
```bash
# Reset database completely
docker-compose down -v
docker volume prune -f
docker-compose up -d db test-db redis
sleep 30
docker-compose up -d app phpmyadmin

# Check database logs
docker logs swinx-db --tail 50

# Connect to database directly
docker exec -it swinx-db mysql -u swinx_user -pswinx_password swinburne
```

### **Performance Optimization**
```bash
# Check container resource usage
docker stats --no-stream

# Optimize Laravel caching
docker exec swinx-app php artisan optimize
docker exec swinx-app php artisan config:cache
docker exec swinx-app php artisan route:cache
docker exec swinx-app php artisan view:cache

# Clear all caches if needed
docker exec swinx-app php artisan optimize:clear
```

---

## 📝 **DEPLOYMENT CHECKLIST**

### **Pre-Deployment**
- [ ] Docker Desktop running
- [ ] Repository up to date
- [ ] Environment files configured
- [ ] Ports 8080, 3306, 3307, 6379, 8081 available
- [ ] Sufficient disk space (>2GB)

### **During Deployment**
- [ ] All containers build successfully
- [ ] Database containers healthy
- [ ] App container not restarting
- [ ] No error logs in containers
- [ ] HTTP response from localhost:8080

### **Post-Deployment**
- [ ] Application loads correctly
- [ ] Database connections working
- [ ] phpMyAdmin accessible
- [ ] All features functional
- [ ] Performance acceptable

---

**Last Updated:** December 2024
**Docker Version:** 24.0+
**Docker Compose Version:** 2.0+
**Laravel Version:** 11.x
**PHP Version:** 8.4
