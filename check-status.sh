#!/bin/bash
# Script kiểm tra trạng thái tổng thể
# Chạy: chmod +x check-status.sh && ./check-status.sh

echo "🔍 Kiểm tra trạng thái ứng dụng Swinx..."
echo "===================================="

# 1. Kiểm tra Docker containers
echo "🐳 1. Kiểm tra Docker containers:"
docker-compose ps

echo ""
echo "📊 2. Thống kê resource containers:"
docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}"

echo ""

# 3. Kiểm tra APP_KEY
echo "🔑 3. Kiểm tra APP_KEY:"
if [ -f ".env" ]; then
    if grep -q "APP_KEY=base64:" .env && ! grep -q "APP_KEY=base64:your-app-key-here" .env; then
        echo "✅ APP_KEY đã được cấu hình"
    else
        echo "❌ APP_KEY chưa được cấu hình đúng!"
        echo "   Chạy: ./fix-app-key.sh"
    fi
else
    echo "❌ File .env không tồn tại!"
    echo "   Chạy: cp .env.docker.dev .env"
fi

# 4. Kiểm tra database connection
echo ""
echo "🗄️  4. Kiểm tra kết nối database:"
if docker exec swinx-app php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database OK';" 2>/dev/null; then
    echo "✅ Database connection thành công"
else
    echo "❌ Không thể kết nối database!"
    echo "   Kiểm tra: docker-compose logs db"
fi

# 5. Kiểm tra migrations
echo ""
echo "📋 5. Kiểm tra database migrations:"
docker exec swinx-app php artisan migrate:status 2>/dev/null || echo "❌ Không thể kiểm tra migrations"

# 6. Kiểm tra frontend assets
echo ""
echo "🎨 6. Kiểm tra frontend assets:"
if docker exec swinx-app ls -la public/build/ 2>/dev/null | grep -q "manifest"; then
    echo "✅ Frontend assets đã được build"
    docker exec swinx-app ls -la public/build/
else
    echo "❌ Frontend assets chưa được build!"
    echo "   Chạy: docker exec swinx-app npm run build"
fi

# 7. Kiểm tra web response
echo ""
echo "🌐 7. Kiểm tra web response:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 || echo "000")

case $HTTP_CODE in
    200)
        echo "✅ Website đang hoạt động bình thường (HTTP 200)"
        ;;
    302|301)
        echo "✅ Website redirect (HTTP $HTTP_CODE) - có thể cần đăng nhập"
        ;;
    500)
        echo "❌ Lỗi server (HTTP 500) - kiểm tra logs ứng dụng"
        ;;
    000)
        echo "❌ Không thể kết nối đến localhost:8080"
        ;;
    *)
        echo "⚠️  HTTP Code: $HTTP_CODE"
        ;;
esac

# 8. Kiểm tra phpMyAdmin
echo ""
echo "🔧 8. Kiểm tra phpMyAdmin:"
PMA_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8081 || echo "000")
if [ "$PMA_CODE" = "200" ]; then
    echo "✅ phpMyAdmin accessible tại http://localhost:8081"
else
    echo "❌ phpMyAdmin không accessible (HTTP $PMA_CODE)"
fi

# 9. Kiểm tra logs gần đây
echo ""
echo "📝 9. Logs gần đây (5 dòng cuối):"
echo "--- App Logs ---"
docker-compose logs --tail=5 app 2>/dev/null || echo "Không thể lấy app logs"

echo ""
echo "--- Database Logs ---"
docker-compose logs --tail=5 db 2>/dev/null || echo "Không thể lấy database logs"

# 10. Kiểm tra disk usage
echo ""
echo "💾 10. Kiểm tra disk usage:"
echo "Docker system:"
docker system df
echo ""
echo "Container volumes:"
docker volume ls | grep swinx

# 11. Tóm tắt
echo ""
echo "📋 TÓM TẮT:"
echo "=========="

# Count services running
RUNNING_SERVICES=$(docker-compose ps --services --filter "status=running" | wc -l | tr -d ' ')
TOTAL_SERVICES=$(docker-compose ps --services | wc -l | tr -d ' ')

echo "🐳 Services: $RUNNING_SERVICES/$TOTAL_SERVICES đang chạy"

if [ -f ".env" ] && grep -q "APP_KEY=base64:" .env && ! grep -q "APP_KEY=base64:your-app-key-here" .env; then
    echo "🔑 APP_KEY: ✅ OK"
else
    echo "🔑 APP_KEY: ❌ CẦN SỬA"
fi

if docker exec swinx-app php artisan tinker --execute="DB::connection()->getPdo();" 2>/dev/null; then
    echo "🗄️  Database: ✅ OK"
else
    echo "🗄️  Database: ❌ CẦN SỬA"
fi

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ] || [ "$HTTP_CODE" = "301" ]; then
    echo "🌐 Website: ✅ OK"
else
    echo "🌐 Website: ❌ CẦN SỬA"
fi

echo ""
echo "🔗 LINKS:"
echo "   - Website: http://localhost:8080"
echo "   - phpMyAdmin: http://localhost:8081"
echo ""
echo "🛠️  LỆNH HỮU ÍCH:"
echo "   - Xem logs chi tiết: docker-compose logs -f app"
echo "   - Restart: docker-compose restart app"
echo "   - Vào container: docker exec -it swinx-app bash"
echo "   - Fix APP_KEY: ./fix-app-key.sh"
echo "   - Setup hoàn chỉnh: ./setup-local-test.sh"
