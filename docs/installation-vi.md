# 🚀 Hướng Dẫn Cài Đặt và Thiết Lập

Hướng dẫn cài đặt hoàn chỉnh cho Hệ Thống Quản Lý Dự Án Swinburne.

## 📋 Yêu Cầu Hệ Thống

### Yêu Cầu Cơ Bản
- **PHP**: 8.4 trở lên
- **Node.js**: 18.x trở lên
- **Composer**: Phiên bản mới nhất
- **Cơ sở dữ liệu**: MySQL 8.0 hoặc MariaDB 10.6+
- **Web Server**: FrankenPHP (khuyến nghị) hoặc Nginx/Apache

### Công Cụ Phát Triển (Khuyến Nghị)
- **Laravel Herd**: Cho môi trường phát triển local
- **Docker**: Cho phát triển containerized
- **Git**: Quản lý phiên bản
- **VS Code**: Với extensions PHP và Vue.js

## 🐳 Khởi Động Nhanh với Docker (Khuyến Nghị)

### Môi Trường Phát Triển
```bash
# Clone repository
git clone <repository-url>
cd swinx

# Khởi động môi trường phát triển
./dev.sh start

# Truy cập ứng dụng
# URL: http://localhost:8080
```

### Kiểm Thử Production Local
```bash
# Khởi động môi trường production local
./local-prod.sh start

# Truy cập ứng dụng
# URL: http://localhost:8080
```

### Triển Khai Production
```bash
# Triển khai lên production
./prod.sh deploy
```

## 🛠️ Cài Đặt Truyền Thống

### 1. Clone và Cài Đặt Dependencies

```bash
# Clone repository
git clone <repository-url>
cd swinx

# Cài đặt PHP dependencies
composer install

# Cài đặt Node.js dependencies
npm install
```

### 2. Cấu Hình Môi Trường

```bash
# Copy file môi trường
cp .env.example .env

# Tạo application key
php artisan key:generate
```

### 3. Cấu Hình Biến Môi Trường

Chỉnh sửa file `.env` với cấu hình của bạn:

```env
# Ứng dụng
APP_NAME="Swinburne Project Management"
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=http://localhost:8000

# Cơ sở dữ liệu
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=swinx
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Upload file
IMPORT_MAX_FILE_SIZE=2MB
```

### 4. Thiết Lập Cơ Sở Dữ Liệu

```bash
# Tạo database
mysql -u root -p
CREATE DATABASE swinx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit

# Chạy migrations
php artisan migrate

# Seed database (tùy chọn)
php artisan db:seed
```

### 5. Build Frontend Assets

```bash
# Development build với hot reload
npm run dev

# Production build
npm run build
```

### 6. Khởi Động Development Server

```bash
# Sử dụng server tích hợp của Laravel
php artisan serve

# Hoặc sử dụng Composer script
composer dev
```

## 🔧 Cấu Hình Nâng Cao

### Thiết Lập File Storage

```bash
# Tạo storage link
php artisan storage:link

# Thiết lập quyền phù hợp
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### Cấu Hình Queue (Tùy Chọn)

```bash
# Cho xử lý background jobs
php artisan queue:work
```

### Cấu Hình Cache

```bash
# Xóa tất cả cache
php artisan optimize:clear

# Tối ưu cho production
php artisan optimize
```

## 🧪 Xác Minh Cài Đặt

### Chạy Tests
```bash
# Chạy tất cả tests
php artisan test

# Chạy test suite cụ thể
php artisan test --testsuite=Feature
```

### Kiểm Tra Trạng Thái Hệ Thống
```bash
# Kiểm tra cài đặt Laravel
php artisan about

# Kiểm tra kết nối database
php artisan tinker --execute="DB::connection()->getPdo();"
```

## 🚨 Khắc Phục Sự Cố

### Các Vấn Đề Thường Gặp

**Lỗi Quyền:**
```bash
sudo chown -R www-data:www-data storage/
sudo chown -R www-data:www-data bootstrap/cache/
```

**Lỗi Kết Nối Database:**
- Xác minh thông tin đăng nhập database trong `.env`
- Đảm bảo dịch vụ MySQL đang chạy
- Kiểm tra cài đặt firewall

**Lỗi Build Assets:**
```bash
# Xóa Node modules và cài đặt lại
rm -rf node_modules package-lock.json
npm install
npm run build
```

**Lỗi Cache:**
```bash
# Xóa tất cả Laravel caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

## 🔗 Bước Tiếp Theo

Sau khi cài đặt thành công:

1. **[Hướng Dẫn Phát Triển](development-vi.md)** - Tìm hiểu tiêu chuẩn và quy trình phát triển
2. **[Hướng Dẫn Triển Khai](deployment-vi.md)** - Triển khai lên môi trường production
3. **[Hướng Dẫn Người Dùng](user-guide-vi.md)** - Hiểu chức năng hệ thống
4. **[Tài Liệu API](api-documentation-vi.md)** - Tích hợp với hệ thống

## 📞 Hỗ Trợ

Đối với các vấn đề cài đặt:
- Kiểm tra [Phần Khắc Phục Sự Cố](#-khắc-phục-sự-cố)
- Xem lại [Hướng Dẫn Phát Triển](development-vi.md)
- Liên hệ nhóm phát triển

---

*Cập nhật lần cuối: {{ date('Y-m-d') }}*
