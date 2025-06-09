#!/bin/bash
# Script khắc phục nhanh lỗi APP_KEY
# Chạy: chmod +x fix-app-key.sh && ./fix-app-key.sh

echo "🔧 Khắc phục lỗi APP_KEY..."

# Kiểm tra file .env có tồn tại không
if [ ! -f ".env" ]; then
    echo "📝 Tạo file .env từ .env.docker.dev..."
    if [ -f ".env.docker.dev" ]; then
        cp .env.docker.dev .env
    else
        echo "❌ Không tìm thấy file .env.docker.dev!"
        exit 1
    fi
fi

# Tạo APP_KEY mới
echo "🔑 Tạo APP_KEY mới..."
APP_KEY=$(docker run --rm php:8.3-cli php -r "echo 'base64:' . base64_encode(random_bytes(32));")

# Cập nhật APP_KEY trong file .env
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s|APP_KEY=.*|APP_KEY=$APP_KEY|" .env
else
    # Linux
    sed -i "s|APP_KEY=.*|APP_KEY=$APP_KEY|" .env
fi

echo "✅ Đã cập nhật APP_KEY: $APP_KEY"

# Restart container app
echo "🔄 Restart container app..."
docker-compose restart app

# Đợi container khởi động
echo "⏳ Đợi container khởi động..."
sleep 10

# Clear cache
echo "🧹 Clear cache..."
docker exec swinx-app php artisan config:clear 2>/dev/null || echo "Container chưa sẵn sàng, thử lại..."
sleep 5
docker exec swinx-app php artisan config:clear
docker exec swinx-app php artisan cache:clear

echo "✅ Hoàn thành! Thử truy cập http://localhost:8080"

# Test connection
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200\|302\|301"; then
    echo "🎉 Ứng dụng đang chạy thành công!"
else
    echo "⚠️  Nếu vẫn lỗi, kiểm tra logs:"
    echo "   docker-compose logs app"
fi
