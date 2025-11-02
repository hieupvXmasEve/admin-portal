# 🚀 Hướng Dẫn Deploy Laravel trên Ubuntu với aaPanel

Hướng dẫn chi tiết triển khai project SWINX trên server Ubuntu sử dụng aaPanel.

## 📋 Yêu Cầu Hệ Thống

- **OS**: Ubuntu 20.04+ / 22.04+
- **PHP**: 8.2+ (bạn đã cài PHP 8.3 ✓)
- **Database**: MySQL 8.0+ hoặc MariaDB 10.6+
- **Web Server**: Nginx (đã có trong aaPanel)
- **Node.js**: 18.x hoặc 20.x
- **Composer**: Latest version
- **Git**: Để clone repository

## 🔧 Bước 1: Kiểm Tra và Cài Đặt Các Dependencies

### 1.1. Kiểm tra PHP Extensions

Project cần các PHP extensions sau. Kiểm tra trong aaPanel:

```bash
# Kiểm tra extensions đã cài
php -m | grep -E 'pdo|mbstring|openssl|curl|zip|gd|intl|opcache|bcmath|redis|mysqli'

# Nếu thiếu extension nào, cài đặt qua aaPanel:
# PHP Settings → Extensions → Install
```

**Các extensions bắt buộc:**
- `pdo_mysql`
- `mysqli`
- `mbstring`
- `openssl`
- `curl`
- `zip`
- `gd`
- `intl`
- `opcache`
- `bcmath`
- `redis` (nếu dùng Redis)

### 1.2. Cài Đặt Composer

```bash
# Tải và cài đặt Composer
cd /tmp
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Kiểm tra
composer --version
```

### 1.3. Cài Đặt Node.js và npm

```bash
# Cài đặt Node.js 20.x (LTS)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt-get install -y nodejs

# Hoặc dùng nvm (khuyến nghị)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc
nvm install 20
nvm use 20

# Kiểm tra
node --version
npm --version
```

### 1.4. Cài Đặt Supervisor (Cho Queue Worker)

```bash
apt-get update
apt-get install -y supervisor

# Khởi động service
systemctl enable supervisor
systemctl start supervisor
```

## 📁 Bước 2: Tạo Database và User

### 2.1. Tạo Database qua aaPanel

1. Vào **Database** → **MySQL**
2. Tạo database mới (ví dụ: `swinx_production`)
3. Tạo user và cấp quyền cho database đó
4. Lưu lại thông tin: Database name, Username, Password

### 2.2. Hoặc tạo qua command line

```bash
mysql -u root -p

# Trong MySQL console
CREATE DATABASE swinx_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'swinx_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON swinx_production.* TO 'swinx_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 📂 Bước 3: Clone và Setup Project

### 3.1. Tạo thư mục cho project

```bash
# Tạo thư mục (thường là /www/wwwroot/your-domain.com)
# Hoặc tạo domain trong aaPanel trước, sau đó vào thư mục đó
cd /www/wwwroot/your-domain.com

# Hoặc tạo thư mục riêng
mkdir -p /www/wwwroot/swinx
cd /www/wwwroot/swinx
```

### 3.2. Clone Repository

```bash
# Clone project (thay URL bằng repository của bạn)
git clone https://github.com/your-org/swinx.git .
# Hoặc nếu đã có code, chỉ cần pull
git pull origin main
```

### 3.3. Cài Đặt PHP Dependencies

```bash
# Cài đặt dependencies (production - không có dev dependencies)
composer install --no-dev --optimize-autoloader --no-interaction

# Nếu gặp lỗi memory limit
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader
```

### 3.4. Cài Đặt Node.js Dependencies và Build

```bash
# Cài đặt npm packages
npm ci --only=production

# Build frontend assets cho production
npm run build

# Nếu dùng SSR (Server-Side Rendering)
# npm run build:ssr
```

## ⚙️ Bước 4: Cấu Hình Environment

### 4.1. Tạo file .env

```bash
# Copy file .env.example
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4.2. Cấu hình .env cho Production

Mở file `.env` và cập nhật các thông tin sau:

```env
# Application
APP_NAME="SWINX Production"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:... (đã generate ở trên)
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=swinx_production
DB_USERNAME=swinx_user
DB_PASSWORD=your_strong_password

# Cache & Session
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids

# Mail (cấu hình email server của bạn)
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-domain.com
MAIL_PORT=587
MAIL_USERNAME=your-email@your-domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"

# Filesystem
FILESYSTEM_DISK=local
# Hoặc dùng S3 nếu cần
# FILESYSTEM_DISK=s3
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=
# AWS_BUCKET=

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error

# Security
SANCTUM_STATEFUL_DOMAINS=your-domain.com,www.your-domain.com
SESSION_SECURE_COOKIE=true

# Production Tools
TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false
```

## 🗄️ Bước 5: Database Migration và Seeding

```bash
# Chạy migrations
php artisan migrate --force

# Chạy seeders (nếu cần)
php artisan db:seed --class=UpdatePermissionsSeeder

# Hoặc seed tất cả (cẩn thận với dữ liệu production)
# php artisan db:seed --force
```

## 🔐 Bước 6: Cấu Hình Permissions

```bash
# Set ownership (thay www-data bằng user của web server)
chown -R www-data:www-data /www/wwwroot/swinx

# Set permissions
chmod -R 755 /www/wwwroot/swinx
chmod -R 775 /www/wwwroot/swinx/storage
chmod -R 775 /www/wwwroot/swinx/bootstrap/cache

# Tạo storage link
php artisan storage:link
```

## 🌐 Bước 7: Cấu Hình Nginx trên aaPanel

### 7.1. Tạo Site trong aaPanel

1. Vào **Website** → **Add Site**
2. Nhập domain name
3. Chọn PHP version: **PHP 8.3**
4. Tạo site

### 7.2. Cấu hình Nginx

Vào **Website** → Chọn site → **Settings** → **Config File**

Cập nhật cấu hình Nginx như sau:

```nginx
server
{
    listen 80;
    server_name your-domain.com www.your-domain.com;
    index index.php index.html index.htm;
    root /www/wwwroot/swinx/public;

    # SSL configuration (sẽ cấu hình sau khi có SSL)
    # listen 443 ssl http2;
    # ssl_certificate /www/server/panel/vhost/cert/your-domain.com/fullchain.pem;
    # ssl_certificate_key /www/server/panel/vhost/cert/your-domain.com/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Hide sensitive files
    location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README.md) {
        return 404;
    }

    # Main location block
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        fastcgi_pass unix:/tmp/php-cgi-83.sock;  # Thay đổi theo PHP version
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # PHP settings
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Deny access to storage and bootstrap/cache except public
    location ~ ^/(storage|bootstrap/cache) {
        deny all;
        return 404;
    }

    # Allow access to public storage
    location ~ ^/storage/(.*)$ {
        alias /www/wwwroot/swinx/storage/app/public/$1;
        access_log off;
    }

    # Well-known for SSL
    location ~ \.well-known {
        allow all;
    }

    access_log /www/wwwlogs/your-domain.com.log;
    error_log /www/wwwlogs/your-domain.com.error.log;
}
```

**Lưu ý:** Thay `unix:/tmp/php-cgi-83.sock` bằng socket PHP của bạn. Kiểm tra trong aaPanel:
- **PHP Settings** → **PHP Version** → xem socket path

### 7.3. Test và Reload Nginx

```bash
# Test cấu hình
nginx -t

# Reload Nginx (trong aaPanel hoặc command)
# Trong aaPanel: Website → Settings → Reload
# Hoặc:
systemctl reload nginx
```

## 🔒 Bước 8: Cấu Hình SSL (Let's Encrypt)

### 8.1. Qua aaPanel (Khuyến nghị)

1. Vào **Website** → Chọn site → **SSL**
2. Chọn **Let's Encrypt**
3. Nhập email
4. Chọn domain (cả www và non-www)
5. Click **Apply**

### 8.2. Hoặc qua Command Line

```bash
# Cài đặt Certbot
apt-get install certbot python3-certbot-nginx

# Tạo SSL certificate
certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal đã được cấu hình tự động
```

## ⚡ Bước 9: Tối Ưu Hóa Laravel cho Production

```bash
cd /www/wwwroot/swinx

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Generate Ziggy routes (nếu dùng)
php artisan ziggy:generate

# Optimize autoloader
composer dump-autoload --optimize --classmap-authoritative
```

## 🔄 Bước 10: Cấu Hình Queue Worker với Supervisor

### 10.1. Tạo Supervisor Config

```bash
nano /etc/supervisor/conf.d/swinx-worker.conf
```

Nội dung file:

```ini
[program:swinx-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /www/wwwroot/swinx/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/www/wwwroot/swinx/storage/logs/worker.log
stopwaitsecs=3600
```

### 10.2. Khởi động Supervisor

```bash
# Reload supervisor config
supervisorctl reread
supervisorctl update

# Start worker
supervisorctl start swinx-worker:*

# Kiểm tra status
supervisorctl status

# Xem logs
tail -f /www/wwwroot/swinx/storage/logs/worker.log
```

## ⏰ Bước 11: Cấu Hình Laravel Scheduler (Cron Job)

### 11.1. Thêm Cron Job trong aaPanel

1. Vào **Cron** → **Add Cron**
2. Cấu hình:
   - **Name**: Laravel Scheduler
   - **Type**: Shell Script
   - **Minute**: `*`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Week**: `*`
   - **Script**: 
   ```bash
   * * * * * cd /www/wwwroot/swinx && php artisan schedule:run >> /dev/null 2>&1
   ```

### 11.2. Hoặc thêm qua Command Line

```bash
crontab -e -u www-data

# Thêm dòng này:
* * * * * cd /www/wwwroot/swinx && php artisan schedule:run >> /dev/null 2>&1
```

## 🔍 Bước 12: Kiểm Tra và Test

### 12.1. Kiểm tra Permissions

```bash
# Kiểm tra ownership
ls -la /www/wwwroot/swinx

# Kiểm tra storage permissions
ls -la /www/wwwroot/swinx/storage
```

### 12.2. Test Application

```bash
# Test artisan commands
php artisan about
php artisan route:list | head -20

# Test database connection
php artisan migrate:status

# Test queue connection
php artisan queue:listen --once
```

### 12.3. Kiểm tra Website

1. Truy cập: `https://your-domain.com`
2. Kiểm tra console browser (F12) xem có lỗi không
3. Kiểm tra logs:
   ```bash
   tail -f /www/wwwroot/swinx/storage/logs/laravel.log
   tail -f /www/wwwlogs/your-domain.com.error.log
   ```

## 🛠️ Bước 13: Monitoring và Maintenance

### 13.1. Cấu hình Log Rotation

```bash
nano /etc/logrotate.d/swinx
```

Nội dung:

```
/www/wwwroot/swinx/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        supervisorctl restart swinx-worker:*
    endscript
}
```

### 13.2. Health Check Script

Tạo script kiểm tra health:

```bash
nano /www/wwwroot/swinx/health-check.sh
```

```bash
#!/bin/bash
# Health check script
URL="https://your-domain.com/up"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" $URL)

if [ $HTTP_CODE -eq 200 ]; then
    echo "OK - Application is healthy"
    exit 0
else
    echo "ERROR - Application returned HTTP $HTTP_CODE"
    exit 1
fi
```

```bash
chmod +x /www/wwwroot/swinx/health-check.sh
```

## 📝 Checklist Deployment

- [ ] PHP 8.3 và extensions đã cài đặt
- [ ] Composer đã cài đặt
- [ ] Node.js và npm đã cài đặt
- [ ] Database đã tạo và user đã được cấp quyền
- [ ] Project đã clone về server
- [ ] Composer dependencies đã cài đặt (--no-dev)
- [ ] npm packages đã cài đặt và build
- [ ] File .env đã cấu hình đúng
- [ ] APP_KEY đã generate
- [ ] Database migrations đã chạy
- [ ] Permissions đã set đúng
- [ ] storage:link đã tạo
- [ ] Nginx đã cấu hình đúng
- [ ] SSL certificate đã cài đặt
- [ ] Laravel cache đã optimize
- [ ] Supervisor worker đã cấu hình và chạy
- [ ] Cron job đã setup
- [ ] Website đã test và hoạt động

## 🚨 Troubleshooting

### Lỗi 500 Internal Server Error

```bash
# Kiểm tra logs
tail -f /www/wwwroot/swinx/storage/logs/laravel.log
tail -f /www/wwwlogs/your-domain.com.error.log

# Kiểm tra permissions
ls -la /www/wwwroot/swinx/storage
ls -la /www/wwwroot/swinx/bootstrap/cache

# Clear cache
php artisan config:clear
php artisan cache:clear
```

### Lỗi Database Connection

```bash
# Test kết nối database
php artisan tinker
# Trong tinker:
DB::connection()->getPdo();

# Kiểm tra .env
cat .env | grep DB_
```

### Lỗi Queue Worker không chạy

```bash
# Kiểm tra supervisor
supervisorctl status
supervisorctl tail -f swinx-worker:stdout

# Restart worker
supervisorctl restart swinx-worker:*
```

### Lỗi Permission Denied

```bash
# Fix permissions
chown -R www-data:www-data /www/wwwroot/swinx
chmod -R 755 /www/wwwroot/swinx
chmod -R 775 /www/wwwroot/swinx/storage
chmod -R 775 /www/wwwroot/swinx/bootstrap/cache
```

## 📚 Tài Liệu Tham Khảo

- [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
- [aaPanel Documentation](https://doc.aapanel.com/)
- [Nginx Configuration for Laravel](https://laravel.com/docs/deployment#nginx)

## 🔄 Cập Nhật Application

Khi cần cập nhật code:

```bash
cd /www/wwwroot/swinx

# Backup database trước
mysqldump -u swinx_user -p swinx_production > backup_$(date +%Y%m%d_%H%M%S).sql

# Pull code mới
git pull origin main

# Cài đặt dependencies mới (nếu có)
composer install --no-dev --optimize-autoloader
npm ci --only=production

# Build assets mới
npm run build

# Chạy migrations mới
php artisan migrate --force

# Clear và cache lại
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue worker
supervisorctl restart swinx-worker:*

# Generate Ziggy routes (nếu cần)
php artisan ziggy:generate
```

---

**Chúc bạn deploy thành công! 🎉**

