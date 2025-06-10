#!/bin/bash

# ===========================================
# Script Triển Khai Production - SWINX
# ===========================================
# Script tự động triển khai ứng dụng SWINX trong môi trường production
# sử dụng Docker Compose với các tính năng bảo mật và monitoring

set -e

# Cấu hình
COMPOSE_FILE="docker-compose.production.yml"
BACKUP_DIR="./backups"
LOG_DIR="./logs"
SSL_DIR="./ssl"
LOG_FILE="$LOG_DIR/deploy-production.log"
HEALTH_CHECK_URL="http://localhost:8080/up"
MAX_HEALTH_CHECKS=30
HEALTH_CHECK_INTERVAL=10

# Màu sắc cho output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Hàm logging
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[LỖI]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

success() {
    echo -e "${GREEN}[THÀNH CÔNG]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[CẢNH BÁO]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${PURPLE}[THÔNG TIN]${NC} $1" | tee -a "$LOG_FILE"
}

# Tạo thư mục cần thiết
mkdir -p "$BACKUP_DIR" "$LOG_DIR" "$SSL_DIR" "./storage/logs/nginx"

log "🚀 Bắt đầu triển khai production SWINX..."

# Kiểm tra Docker và Docker Compose
if ! command -v docker &> /dev/null; then
    error "Docker chưa được cài đặt hoặc không có trong PATH"
fi

if ! command -v docker-compose &> /dev/null; then
    error "Docker Compose chưa được cài đặt hoặc không có trong PATH"
fi

info "✅ Docker và Docker Compose đã sẵn sàng"

# Kiểm tra file cần thiết
if [ ! -f "$COMPOSE_FILE" ]; then
    error "Không tìm thấy file Docker Compose: $COMPOSE_FILE"
fi

if [ ! -f ".env.docker.production" ]; then
    error "Không tìm thấy file môi trường production: .env.docker.production"
fi

info "✅ Tất cả file cấu hình đã sẵn sàng"

# Sao chép file môi trường production
log "📋 Cấu hình môi trường production..."
cp .env.docker.production .env
success "File môi trường production đã được sao chép"

# Tạo SSL certificate nếu chưa có
if [ ! -f "$SSL_DIR/privkey.pem" ] || [ ! -f "$SSL_DIR/fullchain.pem" ]; then
    log "🔐 Tạo SSL certificate..."
    openssl genrsa -out "$SSL_DIR/privkey.pem" 2048
    openssl req -new -x509 -key "$SSL_DIR/privkey.pem" -out "$SSL_DIR/fullchain.pem" -days 365 \
        -subj "/C=VN/ST=HCM/L=HoChiMinh/O=Swinx/CN=localhost"
    success "SSL certificate đã được tạo"
else
    info "SSL certificate đã tồn tại"
fi

# Dọn dẹp môi trường cũ
log "🧹 Dọn dẹp môi trường cũ..."
docker-compose -f "$COMPOSE_FILE" down -v --remove-orphans || true
docker system prune -f || true
success "Môi trường cũ đã được dọn dẹp"

# Backup database nếu có
log "📦 Tạo backup database..."
if docker-compose -f "$COMPOSE_FILE" ps db 2>/dev/null | grep -q "Up"; then
    BACKUP_FILE="$BACKUP_DIR/mysql_backup_$(date +%Y%m%d_%H%M%S).sql"
    docker-compose -f "$COMPOSE_FILE" exec -T db mysqldump \
        -u root -p"RootProd2024!@#SecurePass" \
        --all-databases --routines --triggers > "$BACKUP_FILE" 2>/dev/null || warning "Backup database thất bại"
    
    if [ -f "$BACKUP_FILE" ]; then
        success "Database backup đã tạo: $BACKUP_FILE"
        # Giữ lại 7 backup gần nhất
        find "$BACKUP_DIR" -name "mysql_backup_*.sql" -type f -mtime +7 -delete 2>/dev/null || true
    fi
else
    info "Database container chưa chạy, bỏ qua backup"
fi

# Build và khởi động services
log "🔨 Build và khởi động services..."
docker-compose -f "$COMPOSE_FILE" build --no-cache
docker-compose -f "$COMPOSE_FILE" up -d

# Đợi services khởi động
log "⏳ Đợi services khởi động..."
sleep 30

# Kiểm tra trạng thái containers
log "🔍 Kiểm tra trạng thái containers..."
docker-compose -f "$COMPOSE_FILE" ps

# Kiểm tra health check
log "🏥 Kiểm tra health check..."
HEALTH_CHECKS=0
while [ $HEALTH_CHECKS -lt $MAX_HEALTH_CHECKS ]; do
    if curl -f "$HEALTH_CHECK_URL" &>/dev/null; then
        success "Ứng dụng đã sẵn sàng!"
        break
    fi

    HEALTH_CHECKS=$((HEALTH_CHECKS + 1))
    log "Health check $HEALTH_CHECKS/$MAX_HEALTH_CHECKS thất bại, thử lại sau ${HEALTH_CHECK_INTERVAL}s..."
    sleep $HEALTH_CHECK_INTERVAL
done

if [ $HEALTH_CHECKS -eq $MAX_HEALTH_CHECKS ]; then
    error "Ứng dụng thất bại trong health check sau khi triển khai"
fi

# Chạy database migrations
log "🗃️ Chạy database migrations..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force

# Tối ưu hóa Laravel cho production
log "⚡ Tối ưu hóa Laravel cho production..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan config:clear
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan config:cache
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan route:cache
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan view:cache
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan event:cache

# Tạo storage link
log "🔗 Tạo storage link..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan storage:link

# Generate Ziggy routes
log "🗺️ Tạo Ziggy routes..."
docker-compose -f "$COMPOSE_FILE" exec -T app php artisan ziggy:generate

# Dọn dẹp Docker images cũ
log "🧹 Dọn dẹp Docker images cũ..."
docker image prune -f

# Kiểm tra cuối cùng
log "🔍 Kiểm tra cuối cùng..."
if curl -f "$HEALTH_CHECK_URL" &>/dev/null; then
    success "🎉 Triển khai production hoàn tất thành công!"

    echo ""
    echo "🌐 ĐIỂM TRUY CẬP:"
    echo "   - Ứng dụng chính: http://localhost:8080"
    echo "   - Health check: http://localhost:8080/up"
    echo ""
    echo "📊 TRẠNG THÁI SERVICES:"
    docker-compose -f "$COMPOSE_FILE" ps
    echo ""
    echo "📈 SỬ DỤNG TÀI NGUYÊN:"
    docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}"
    
else
    error "Kiểm tra cuối cùng thất bại"
fi

log "✅ Script triển khai production hoàn tất"

# Hiển thị hướng dẫn sử dụng
echo ""
echo "📋 HƯỚNG DẪN SỬ DỤNG:"
echo "   - Xem logs: docker-compose -f $COMPOSE_FILE logs -f"
echo "   - Dừng services: docker-compose -f $COMPOSE_FILE down"
echo "   - Backup database: ./scripts/backup-database.sh"
echo "   - Cập nhật ứng dụng: git pull && ./scripts/deploy-production.sh"
echo ""
echo "🔧 LỆNH HỮU ÍCH:"
echo "   - Vào container app: docker exec -it swinx-app bash"
echo "   - Xem logs app: docker logs swinx-app --tail 50"
echo "   - Kiểm tra database: docker exec -it swinx-db mysql -u swinx_prod_user -p"
echo ""
