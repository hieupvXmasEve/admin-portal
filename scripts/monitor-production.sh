#!/bin/bash

# ===========================================
# Script Giám Sát Production - SWINX
# ===========================================
# Script giám sát tình trạng ứng dụng SWINX trong môi trường production

set -e

# Cấu hình
COMPOSE_FILE="docker-compose.production.yml"
LOG_DIR="./logs"
LOG_FILE="$LOG_DIR/monitor.log"
HEALTH_CHECK_URL="http://localhost:8080/up"
ALERT_EMAIL=""  # Thêm email để nhận cảnh báo

# Màu sắc cho output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

# Hàm logging
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[LỖI]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[OK]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[CẢNH BÁO]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${PURPLE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

# Tạo thư mục log
mkdir -p "$LOG_DIR"

echo "🔍 GIÁM SÁT PRODUCTION SWINX - $(date)"
echo "=================================================="

# Kiểm tra Docker
if ! command -v docker &> /dev/null; then
    error "Docker không khả dụng"
    exit 1
fi

# Kiểm tra trạng thái containers
echo ""
echo "📊 TRẠNG THÁI CONTAINERS:"
echo "------------------------"
CONTAINERS_STATUS=0

# Danh sách containers cần kiểm tra (simplified production setup)
CONTAINERS=("swinx-app" "swinx-db" "swinx-redis")

for container in "${CONTAINERS[@]}"; do
    if docker ps --format "table {{.Names}}\t{{.Status}}" | grep -q "$container.*Up"; then
        success "$container: Đang chạy"
    else
        error "$container: Không chạy hoặc có vấn đề"
        CONTAINERS_STATUS=1
    fi
done

# Kiểm tra health check
echo ""
echo "🏥 HEALTH CHECK:"
echo "---------------"
if curl -f "$HEALTH_CHECK_URL" &>/dev/null; then
    success "Health check: PASS"
    HEALTH_STATUS=0
else
    error "Health check: FAIL"
    HEALTH_STATUS=1
fi

# Kiểm tra kết nối database
echo ""
echo "🗃️ KIỂM TRA DATABASE:"
echo "--------------------"
if docker exec swinx-db mysql -u swinx_prod_user -pSwinxProd2024!@#SecurePass -e "SELECT 1" &>/dev/null; then
    success "Database: Kết nối thành công"
    DB_STATUS=0
else
    error "Database: Kết nối thất bại"
    DB_STATUS=1
fi

# Kiểm tra Redis
echo ""
echo "📦 KIỂM TRA REDIS:"
echo "-----------------"
if docker exec swinx-redis redis-cli -a RedisProd2024!@#SecurePass ping 2>/dev/null | grep -q "PONG"; then
    success "Redis: Hoạt động bình thường"
    REDIS_STATUS=0
else
    error "Redis: Có vấn đề"
    REDIS_STATUS=1
fi

# Kiểm tra sử dụng tài nguyên
echo ""
echo "💻 SỬ DỤNG TÀI NGUYÊN:"
echo "---------------------"
docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}"

# Kiểm tra dung lượng ổ cứng
echo ""
echo "💾 DUNG LƯỢNG Ổ CỨNG:"
echo "--------------------"
df -h | grep -E "(Filesystem|/dev/)"

# Kiểm tra logs gần đây
echo ""
echo "📋 LOGS GẦN ĐÂY (5 phút):"
echo "------------------------"
echo "=== APP LOGS ==="
docker logs swinx-app --since 5m --tail 10 2>/dev/null || echo "Không có logs app"

echo ""
echo "=== DATABASE LOGS ==="
docker logs swinx-db --since 5m --tail 5 2>/dev/null || echo "Không có logs database"

# Kiểm tra backup gần nhất
echo ""
echo "📦 BACKUP GẦN NHẤT:"
echo "------------------"
LATEST_BACKUP=$(ls -t ./backups/swinx_backup_*.sql.gz 2>/dev/null | head -1)
if [ -n "$LATEST_BACKUP" ]; then
    BACKUP_DATE=$(stat -f "%Sm" -t "%Y-%m-%d %H:%M:%S" "$LATEST_BACKUP" 2>/dev/null || date -r "$LATEST_BACKUP" "+%Y-%m-%d %H:%M:%S" 2>/dev/null)
    BACKUP_SIZE=$(du -h "$LATEST_BACKUP" | cut -f1)
    success "Backup gần nhất: $LATEST_BACKUP"
    info "Thời gian: $BACKUP_DATE"
    info "Kích thước: $BACKUP_SIZE"
else
    warning "Không tìm thấy backup nào"
fi

# Tổng kết
echo ""
echo "📊 TỔNG KẾT:"
echo "============"
TOTAL_ISSUES=$((CONTAINERS_STATUS + HEALTH_STATUS + DB_STATUS + REDIS_STATUS))

if [ $TOTAL_ISSUES -eq 0 ]; then
    success "🎉 Tất cả services hoạt động bình thường!"
    echo "✅ Containers: OK"
    echo "✅ Health Check: OK"
    echo "✅ Database: OK"
    echo "✅ Redis: OK"
else
    error "⚠️ Phát hiện $TOTAL_ISSUES vấn đề cần xử lý!"
    [ $CONTAINERS_STATUS -ne 0 ] && echo "❌ Containers: CÓ VẤN ĐỀ"
    [ $HEALTH_STATUS -ne 0 ] && echo "❌ Health Check: THẤT BẠI"
    [ $DB_STATUS -ne 0 ] && echo "❌ Database: CÓ VẤN ĐỀ"
    [ $REDIS_STATUS -ne 0 ] && echo "❌ Redis: CÓ VẤN ĐỀ"
fi

# Ghi log tổng kết
log "Monitor completed - Issues: $TOTAL_ISSUES"

echo ""
echo "🔧 LỆNH HỮU ÍCH:"
echo "==============="
echo "- Xem logs realtime: docker-compose -f $COMPOSE_FILE logs -f"
echo "- Restart service: docker-compose -f $COMPOSE_FILE restart <service>"
echo "- Backup database: ./scripts/backup-database.sh"
echo "- Deploy lại: ./scripts/deploy-production.sh"
echo ""

# Trả về exit code dựa trên số lượng vấn đề
exit $TOTAL_ISSUES
