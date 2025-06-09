#!/bin/bash
# Script tự động setup Swinx local test
# Chạy: chmod +x setup-local-test.sh && ./setup-local-test.sh

set -e  # Exit on any error

echo "🚀 Bắt đầu setup Swinx local test..."
echo "=================================="

# Kiểm tra Docker
echo "🔍 Kiểm tra Docker..."
if ! command -v docker &> /dev/null; then
    echo "❌ Docker chưa được cài đặt!"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose chưa được cài đặt!"
    exit 1
fi

# 1. Chuẩn bị environment
echo "📝 Chuẩn bị file environment..."
if [ ! -f ".env.docker.dev" ]; then
    echo "❌ File .env.docker.dev không tồn tại!"
    exit 1
fi

cp .env.docker.dev .env
echo "✅ Đã copy .env.docker.dev thành .env"

# 2. Tạo APP_KEY
echo "🔑 Tạo APP_KEY mới..."
APP_KEY=$(docker run --rm php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32));")

if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s|APP_KEY=base64:your-app-key-here|APP_KEY=$APP_KEY|" .env
else
    # Linux
    sed -i "s|APP_KEY=base64:your-app-key-here|APP_KEY=$APP_KEY|" .env
fi

echo "✅ Đã tạo APP_KEY: $APP_KEY"

# 3. Tạo thư mục và phân quyền
echo "📁 Tạo thư mục cần thiết..."
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/app
mkdir -p bootstrap/cache

chmod -R 775 storage/ 2>/dev/null || echo "⚠️  Không thể chmod storage/, tiếp tục..."
chmod -R 775 bootstrap/cache/ 2>/dev/null || echo "⚠️  Không thể chmod bootstrap/cache/, tiếp tục..."

echo "✅ Đã tạo thư mục và phân quyền"

# 4. Stop containers cũ nếu có
echo "🛑 Dừng containers cũ (nếu có)..."
docker-compose down -v 2>/dev/null || echo "Không có container nào đang chạy"

# 5. Build và start Docker
echo "🐳 Build và khởi động Docker containers..."
docker-compose up -d --build

# 6. Đợi database ready
echo "⏳ Đợi database khởi động (45 giây)..."
sleep 45

# Kiểm tra database connection
echo "🔍 Kiểm tra kết nối database..."
for i in {1..10}; do
    if docker exec swinx-app php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>/dev/null; then
        echo "✅ Database đã sẵn sàng!"
        break
    else
        echo "⏳ Đợi database... (thử lần $i/10)"
        sleep 5
    fi

    if [ $i -eq 10 ]; then
        echo "❌ Không thể kết nối database sau 10 lần thử!"
        echo "📋 Kiểm tra logs:"
        docker-compose logs db
        exit 1
    fi
done

# 7. Chạy migrations
echo "🗄️ Chạy database migrations..."
docker exec swinx-app php artisan migrate --force

# 8. Build frontend
echo "🎨 Cài đặt NPM dependencies..."
docker exec swinx-app npm install

echo "🎨 Build frontend assets..."
docker exec swinx-app npm run build

# 9. Clear cache
echo "🧹 Clear cache..."
docker exec swinx-app php artisan config:clear
docker exec swinx-app php artisan cache:clear
docker exec swinx-app php artisan route:clear
docker exec swinx-app php artisan view:clear

# 10. Tạo Ziggy routes
echo "🔀 Tạo Ziggy routes..."
docker exec swinx-app php artisan ziggy:generate 2>/dev/null || echo "⚠️  Ziggy không có, bỏ qua..."

# 11. Kiểm tra trạng thái
echo "🔍 Kiểm tra trạng thái containers..."
docker-compose ps

# 12. Test cơ bản
echo "✅ Chạy tests cơ bản..."
if docker exec swinx-app php artisan test --stop-on-failure 2>/dev/null; then
    echo "✅ Tests đã pass!"
else
    echo "⚠️  Một số tests failed, nhưng ứng dụng vẫn có thể chạy được"
fi

# 13. Kiểm tra application status
echo "🔍 Kiểm tra application status..."
docker exec swinx-app php artisan about

echo ""
echo "🎉 Setup hoàn thành!"
echo "=================================="
echo "📱 Truy cập ứng dụng: http://localhost:8080"
echo "🗄️  Truy cập phpMyAdmin: http://localhost:8081"
echo ""
echo "📋 Thông tin đăng nhập phpMyAdmin:"
echo "   Server: db"
echo "   Username: swinx_user"
echo "   Password: swinx_password"
echo ""
echo "🛠️  Các lệnh hữu ích:"
echo "   - Xem logs: docker-compose logs -f app"
echo "   - Vào container: docker exec -it swinx-app bash"
echo "   - Stop: docker-compose down"
echo "   - Restart: docker-compose restart app"
echo ""

# Kiểm tra cuối cùng
echo "🔍 Kiểm tra cuối cùng..."
sleep 5

if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200\|302\|301"; then
    echo "✅ Ứng dụng đang chạy thành công!"
    echo "🌐 Mở trình duyệt và truy cập: http://localhost:8080"
else
    echo "⚠️  Ứng dụng có thể chưa sẵn sàng hoặc có lỗi"
    echo "📋 Kiểm tra logs để debug:"
    echo "   docker-compose logs app"
fi

echo ""
echo "✨ Hoàn thành! Happy coding! ✨"
