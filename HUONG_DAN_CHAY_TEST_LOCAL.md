# 🚀 Hướng Dẫn Chạy Test Local - Ứng Dụng Swinx

## 📋 Mục Lục
1. [Chuẩn Bị Môi Trường](#chuẩn-bị-môi-trường)
2. [Cài Đặt Ban Đầu](#cài-đặt-ban-đầu)
3. [Chạy Ứng Dụng](#chạy-ứng-dụng)
4. [Kiểm Tra và Test](#kiểm-tra-và-test)
5. [Xử Lý Lỗi Thường Gặp](#xử-lý-lỗi-thường-gặp)
6. [Các Lệnh Hữu Ích](#các-lệnh-hữu-ích)

---

## 🔧 Chuẩn Bị Môi Trường

### Yêu Cầu Hệ Thống
- **Docker**: Phiên bản 20.10 trở lên
- **Docker Compose**: Phiên bản 2.0 trở lên
- **RAM**: Tối thiểu 4GB (khuyến nghị 8GB)
- **Ổ cứng**: Ít nhất 10GB trống

### Kiểm Tra Docker
```bash
# Kiểm tra Docker đã cài đặt
docker --version
docker-compose --version

# Kiểm tra Docker đang chạy
docker ps
```

---

## ⚙️ Cài Đặt Ban Đầu

### Bước 1: Chuẩn Bị File Môi Trường
```bash
# Di chuyển vào thư mục dự án
cd /path/to/swinx

# Copy file cấu hình development
cp .env.docker.dev .env

# Kiểm tra file đã copy thành công
ls -la .env
```

### Bước 2: Tạo APP_KEY (QUAN TRỌNG!)
```bash
# Tạo APP_KEY mới
docker run --rm -v $(pwd):/app -w /app php:8.3-cli php -r "echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"

# Hoặc sử dụng lệnh này
openssl rand -base64 32
```

**📝 Lưu ý**: Copy kết quả và cập nhật vào file `.env`:
```bash
# Mở file .env và thay thế dòng:
# APP_KEY=base64:your-app-key-here
# Bằng APP_KEY mới vừa tạo
nano .env
```

### Bước 3: Tạo Thư Mục Cần Thiết
```bash
# Tạo thư mục storage nếu chưa có
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

# Phân quyền cho thư mục
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

---

## 🐳 Chạy Ứng Dụng

### Bước 1: Build và Khởi Động Docker
```bash
# Build và khởi động tất cả services
docker-compose up -d --build

# Xem trạng thái containers
docker-compose ps
```

### Bước 2: Chờ Database Khởi Động
```bash
# Kiểm tra logs của database
docker-compose logs -f db

# Đợi đến khi thấy: "MySQL init process done. Ready for start up."
# Nhấn Ctrl+C để thoát khỏi logs
```

### Bước 3: Chạy Migration Database
```bash
# Chạy migrations
docker exec swinx-app php artisan migrate --force

# Nếu muốn có dữ liệu mẫu
docker exec swinx-app php artisan db:seed
```

### Bước 4: Build Frontend Assets
```bash
# Cài đặt dependencies
docker exec swinx-app npm install

# Build assets cho development
docker exec swinx-app npm run build
```

### Bước 5: Clear Cache
```bash
# Clear tất cả cache
docker exec swinx-app php artisan config:clear
docker exec swinx-app php artisan cache:clear
docker exec swinx-app php artisan route:clear
docker exec swinx-app php artisan view:clear
```

---

## ✅ Kiểm Tra và Test

### Kiểm Tra Ứng Dụng
```bash
# Mở trình duyệt và truy cập:
open http://localhost:8080
```

**Kết quả mong đợi**:
- Trang web hiển thị bình thường
- Không có lỗi 500 hoặc cipher key
- Có thể đăng nhập/đăng ký

### Kiểm Tra Database
```bash
# Truy cập phpMyAdmin
open http://localhost:8081

# Thông tin đăng nhập:
# Server: db
# Username: swinx_user
# Password: swinx_password
```

### Chạy Tests
```bash
# Chạy tất cả tests
docker exec swinx-app php artisan test

# Chạy test cụ thể
docker exec swinx-app php artisan test --testsuite=Feature
docker exec swinx-app php artisan test --testsuite=Unit
```

---

## 🚨 Xử Lý Lỗi Thường Gặp

### Lỗi "Unsupported cipher or incorrect key length" ⚠️ **ĐANG GẶP PHẢI**
**Nguyên nhân**: Chưa có APP_KEY hoặc APP_KEY không đúng format.

**Cách sửa NHANH** (cho trường hợp bạn đã chạy `docker compose up -d --build`):
```bash
# Cách 1: Sử dụng script tự động (KHUYẾN NGHỊ)
chmod +x fix-app-key.sh
./fix-app-key.sh

# Cách 2: Sửa thủ công
# Tạo APP_KEY mới
docker run --rm php:8.3-cli php -r "echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"

# Copy kết quả và cập nhật vào file .env
nano .env

# Restart container
docker-compose restart app

# Clear cache
docker exec swinx-app php artisan config:clear
docker exec swinx-app php artisan cache:clear
```

**Kiểm tra sau khi sửa**:
```bash
# Kiểm tra website
curl -I http://localhost:8080

# Hoặc sử dụng script kiểm tra tổng thể
chmod +x check-status.sh
./check-status.sh
```

### Lỗi Database Connection
**Cách sửa**:
```bash
# Kiểm tra database container
docker-compose logs db

# Restart database
docker-compose restart db

# Đợi 30 giây rồi test lại
docker exec swinx-app php artisan tinker
>>> DB::connection()->getPdo();
```

### Lỗi Permission Denied
**Cách sửa**:
```bash
# Fix quyền thư mục
sudo chown -R $USER:$USER storage/
sudo chown -R $USER:$USER bootstrap/cache/
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Restart container
docker-compose restart app
```

### Lỗi "Port already in use"
**Cách sửa**:
```bash
# Kiểm tra port đang sử dụng
sudo lsof -i :8080
sudo lsof -i :3306

# Stop container đang chạy
docker-compose down

# Hoặc thay đổi port trong docker-compose.yml
```

### Lỗi Frontend Assets
**Cách sửa**:
```bash
# Rebuild frontend
docker exec swinx-app npm run build

# Clear cache browser
# Hoặc mở incognito mode

# Kiểm tra Vite manifest
docker exec swinx-app ls -la public/build/
```

---

## 🛠️ Các Lệnh Hữu Ích

### Quản Lý Containers
```bash
# Xem tất cả containers
docker-compose ps

# Xem logs của app
docker-compose logs -f app

# Restart specific service
docker-compose restart app

# Stop tất cả
docker-compose down

# Stop và xóa volumes
docker-compose down -v
```

### Truy Cập Container
```bash
# Vào container app
docker exec -it swinx-app bash

# Vào database MySQL
docker exec -it swinx-db mysql -u swinx_user -p

# Chạy lệnh trong container
docker exec swinx-app php artisan migrate:status
```

### Debug và Troubleshooting
```bash
# Kiểm tra trạng thái Laravel
docker exec swinx-app php artisan about

# Kiểm tra route list
docker exec swinx-app php artisan route:list

# Test database connection
docker exec swinx-app php artisan tinker
>>> DB::connection()->getPdo();

# Kiểm tra environment variables
docker exec swinx-app php -r "phpinfo();" | grep -i env
```

### Development Workflow
```bash
# Install new PHP package
docker exec swinx-app composer require package/name

# Install new NPM package
docker exec swinx-app npm install package-name

# Run migration
docker exec swinx-app php artisan migrate

# Create new migration
docker exec swinx-app php artisan make:migration create_table_name

# Watch frontend changes
docker exec swinx-app npm run dev
```

---

## 📋 Checklist Hoàn Thành

### Trước Khi Bắt Đầu
- [ ] Docker và Docker Compose đã cài đặt
- [ ] Đã copy `.env.docker.dev` thành `.env`
- [ ] Đã tạo APP_KEY mới
- [ ] Thư mục storage có quyền đúng

### Sau Khi Khởi Động
- [ ] Tất cả containers đang chạy: `docker-compose ps`
- [ ] Database đã ready: `docker-compose logs db`
- [ ] Migrations đã chạy: `docker exec swinx-app php artisan migrate:status`
- [ ] Frontend assets đã build: `ls public/build/`

### Kiểm Tra Cuối Cùng
- [ ] Website accessible tại http://localhost:8080
- [ ] phpMyAdmin accessible tại http://localhost:8081
- [ ] Không có lỗi trong logs: `docker-compose logs app`
- [ ] Tests pass: `docker exec swinx-app php artisan test`

---

## 🎯 Kịch Bản Test Hoàn Chỉnh

```bash
#!/bin/bash
# Script tự động để test local

echo "🚀 Bắt đầu setup Swinx local test..."

# 1. Chuẩn bị environment
echo "📝 Copy environment file..."
cp .env.docker.dev .env

# 2. Tạo APP_KEY
echo "🔑 Tạo APP_KEY..."
APP_KEY=$(docker run --rm php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32));")
sed -i "s/APP_KEY=base64:your-app-key-here/APP_KEY=$APP_KEY/" .env

# 3. Tạo thư mục và phân quyền
echo "📁 Tạo thư mục cần thiết..."
mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache
chmod -R 775 storage/ bootstrap/cache/

# 4. Build và start Docker
echo "🐳 Khởi động Docker containers..."
docker-compose up -d --build

# 5. Đợi database ready
echo "⏳ Đợi database khởi động..."
sleep 30

# 6. Chạy migrations
echo "🗄️ Chạy database migrations..."
docker exec swinx-app php artisan migrate --force

# 7. Build frontend
echo "🎨 Build frontend assets..."
docker exec swinx-app npm install
docker exec swinx-app npm run build

# 8. Clear cache
echo "🧹 Clear cache..."
docker exec swinx-app php artisan config:clear
docker exec swinx-app php artisan cache:clear

# 9. Test
echo "✅ Chạy tests..."
docker exec swinx-app php artisan test

echo "🎉 Setup hoàn thành! Truy cập http://localhost:8080"
```

**Cách sử dụng script**:
```bash
chmod +x setup-local-test.sh
./setup-local-test.sh
```

---

## 📞 Hỗ Trợ

### Khi Cần Giúp Đỡ
1. **Kiểm tra logs**: `docker-compose logs app`
2. **Xem status**: `docker-compose ps`
3. **Check database**: `docker exec swinx-app php artisan tinker`
4. **Reset hoàn toàn**: `docker-compose down -v && docker system prune -f`

### Liên Hệ
- Tạo issue trên GitHub repository
- Kiểm tra documentation trong `DOCKER_DEVELOPMENT_GUIDE.md`
- Tham khảo `IMPLEMENTATION_SUMMARY.md` cho chi tiết kỹ thuật

---

**Cập nhật**: 9 tháng 6, 2025  
**Phiên bản**: 1.0.0  
**Ngôn ngữ**: Tiếng Việt 
