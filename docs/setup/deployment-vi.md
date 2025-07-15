# 🚀 Hướng Dẫn Triển Khai

Hướng dẫn triển khai hoàn chỉnh cho Hệ Thống Quản Lý Dự Án Swinburne với Docker và CI/CD pipeline.

## 📋 Yêu Cầu Tiên Quyết

### Yêu Cầu Server
- **OS**: Ubuntu 20.04+ hoặc Linux distribution tương tự
- **RAM**: Tối thiểu 4GB (khuyến nghị 8GB)
- **Storage**: Tối thiểu 20GB SSD
- **Network**: Public IP address với domain name
- **Access**: SSH access với sudo privileges

### Dịch Vụ Bắt Buộc
- **Docker**: Phiên bản mới nhất
- **Docker Compose**: V2 trở lên
- **Git**: Cho quản lý repository
- **SSL Certificate**: Let's Encrypt (tự động)

## 🏗️ Tổng Quan Kiến Trúc

```
Internet → FrankenPHP (SSL/HTTP) → Laravel App → MySQL/Redis
                                ↓
                          Queue Workers & Scheduler
```

### Các Loại Môi Trường
- **Development**: Chỉ HTTP, debug enabled, hot reload
- **Local Production**: HTTPS testing, môi trường giống production
- **Production**: HTTPS đầy đủ, SSL certificates, performance tối ưu

## 🐳 Triển Khai Docker

### Lệnh Khởi Động Nhanh

```bash
# Môi trường development
./dev.sh start

# Kiểm thử local production
./local-prod.sh start

# Triển khai production
./prod.sh deploy
```

### Cấu Hình Môi Trường

Mỗi môi trường sử dụng file cấu hình riêng:
- **Development**: `.env.docker.dev`
- **Local Production**: `.env.docker.local-prod`
- **Production**: `.env.docker.production`

## 🔧 Thiết Lập Production Server

### 1. Cấu Hình Server Ban Đầu

```bash
# Cập nhật hệ thống
sudo apt update && sudo apt upgrade -y

# Cài đặt Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Cài đặt Docker Compose
sudo apt install docker-compose-plugin

# Tạo application user
sudo useradd -m -s /bin/bash swinx
sudo usermod -aG docker swinx
```

### 2. Thiết Lập SSL Certificate

```bash
# Cài đặt Certbot
sudo apt install certbot

# Tạo SSL certificate
sudo certbot certonly --standalone \
  -d your-domain.com \
  -d www.your-domain.com \
  --email admin@your-domain.com \
  --agree-tos --non-interactive

# Tạo SSL directory
sudo mkdir -p /opt/swinx/ssl
sudo ln -sf /etc/letsencrypt/live/your-domain.com/fullchain.pem /opt/swinx/ssl/fullchain.pem
sudo ln -sf /etc/letsencrypt/live/your-domain.com/privkey.pem /opt/swinx/ssl/privkey.pem
```

### 3. Triển Khai Ứng Dụng

```bash
# Clone repository
sudo -u swinx git clone <repository-url> /opt/swinx
cd /opt/swinx

# Cấu hình môi trường
sudo -u swinx cp .env.docker.production.example .env.docker.production
sudo -u swinx nano .env.docker.production

# Triển khai ứng dụng
sudo -u swinx ./prod.sh deploy
```

## ⚙️ Cấu Hình Môi Trường

### Biến Môi Trường Production

```env
# Ứng dụng
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

# Cấu hình SSL
SSL_ENABLED=true
DOMAIN_NAME=your-domain.com
```

### Cấu Hình Bảo Mật

```env
# Bảo mật
APP_KEY=base64:your-generated-key
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Upload File
IMPORT_MAX_FILE_SIZE=10MB
UPLOAD_MAX_FILESIZE=10M
POST_MAX_SIZE=10M
```

## 🔄 CI/CD Pipeline

### Thiết Lập GitHub Actions

Cấu hình repository secrets trong GitHub:
```
PRODUCTION_HOST=your-server-ip
PRODUCTION_USER=swinx
PRODUCTION_SSH_KEY=<private-key-content>
PRODUCTION_APP_PATH=/opt/swinx
```

### Quy Trình Triển Khai Tự Động

1. **Code Push**: Push lên main branch kích hoạt deployment
2. **Testing**: Automated tests chạy trong CI environment
3. **Build**: Docker image builds và pushes lên registry
4. **Deploy**: Ứng dụng triển khai lên production server
5. **Health Check**: Xác minh deployment thành công

### Triển Khai Thủ Công

```bash
# Trên production server
cd /opt/swinx

# Pull latest changes
git pull origin main

# Triển khai với Docker
./prod.sh deploy

# Kiểm tra trạng thái deployment
./prod.sh status
```

## 🔍 Giám Sát và Bảo Trì

### Health Checks

```bash
# Kiểm tra trạng thái ứng dụng
curl https://your-domain.com/up

# Kiểm tra trạng thái container
docker compose -f docker-compose.production.yml ps

# Xem logs
docker compose -f docker-compose.production.yml logs -f app
```

### Quản Lý Log

```bash
# Application logs
tail -f storage/logs/laravel.log

# Deployment logs
tail -f logs/deploy-production.log

# Access logs
tail -f logs/swinx-access.log
```

### Backup Database

```bash
# Backup tự động (chạy trong deployment)
./scripts/backup-database.sh

# Backup thủ công
docker compose -f docker-compose.production.yml exec db \
  mysqldump -u root -p swinx > backup-$(date +%Y%m%d).sql

# Xem backups
ls -la backups/
```

## 🛠️ Khắc Phục Sự Cố

### Các Vấn Đề Thường Gặp

**Vấn Đề SSL Certificate:**
```bash
# Kiểm tra trạng thái certificate
sudo certbot certificates

# Gia hạn certificate
sudo certbot renew --dry-run

# Restart services sau khi gia hạn
./prod.sh restart
```

**Vấn Đề Container:**
```bash
# Kiểm tra container logs
docker compose -f docker-compose.production.yml logs app

# Restart service cụ thể
docker compose -f docker-compose.production.yml restart app

# Rebuild và restart
./prod.sh rebuild
```

**Vấn Đề Kết Nối Database:**
```bash
# Kiểm tra trạng thái database
docker compose -f docker-compose.production.yml exec db mysql -u root -p

# Reset database connection
./prod.sh restart db
```

### Quy Trình Khẩn Cấp

**Rollback Deployment:**
```bash
# Rollback về phiên bản trước
git checkout HEAD~1
./prod.sh deploy

# Hoặc sử dụng backup
./scripts/restore-backup.sh backup-20231201.sql
```

**Scale Services:**
```bash
# Scale application containers
docker compose -f docker-compose.production.yml up -d --scale app=3
```

## 📊 Tối Ưu Performance

### Giám Sát Tài Nguyên

```bash
# Giám sát resource usage
docker stats

# Kiểm tra disk usage
df -h

# Giám sát memory usage
free -h
```

### Cài Đặt Tối Ưu

```bash
# Tối ưu Laravel cho production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Tối ưu database
php artisan migrate --force
php artisan db:seed --force
```

## 🔒 Best Practices Bảo Mật

### Bảo Mật Server
- Giữ hệ thống cập nhật với security patches
- Sử dụng mật khẩu mạnh và SSH keys
- Cấu hình firewall rules
- Kiểm tra bảo mật định kỳ

### Bảo Mật Ứng Dụng
- Kích hoạt HTTPS với SSL certificates hợp lệ
- Sử dụng cấu hình session bảo mật
- Triển khai authentication và authorization phù hợp
- Cập nhật dependencies định kỳ

### Chiến Lược Backup
- Backup database tự động hàng ngày
- Backup toàn bộ ứng dụng hàng tuần
- Kiểm tra quy trình restore định kỳ
- Lưu trữ backups ở vị trí bảo mật, off-site

## 🔗 Tài Liệu Liên Quan

- **[Hướng Dẫn Cài Đặt](installation-vi.md)** - Hướng dẫn setup và cài đặt
- **[Hướng Dẫn Phát Triển](development-vi.md)** - Tiêu chuẩn và quy trình phát triển
- **[Hướng Dẫn Người Dùng](user-guide-vi.md)** - Chức năng và cách sử dụng hệ thống
- **[Tài Liệu API](api-documentation-vi.md)** - API endpoints và tích hợp

## 📞 Hỗ Trợ

Đối với các vấn đề triển khai:
1. Kiểm tra application logs trong `storage/logs/`
2. Xem lại trạng thái container với `docker ps`
3. Xác minh cấu hình môi trường
4. Liên hệ nhóm phát triển với thông báo lỗi cụ thể

---

*Cập nhật lần cuối: {{ date('Y-m-d') }}*
