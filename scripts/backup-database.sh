#!/bin/bash

# ===========================================
# Script Backup Database - SWINX Production
# ===========================================
# Script tự động backup database MySQL trong môi trường production

set -e

# Cấu hình
COMPOSE_FILE="docker-compose.production.yml"
BACKUP_DIR="./backups"
LOG_DIR="./logs"
LOG_FILE="$LOG_DIR/backup.log"
RETENTION_DAYS=30

# Màu sắc cho output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Tạo thư mục cần thiết
mkdir -p "$BACKUP_DIR" "$LOG_DIR"

log "📦 Bắt đầu backup database SWINX..."

# Kiểm tra Docker Compose
if ! command -v docker-compose &> /dev/null; then
    error "Docker Compose chưa được cài đặt"
fi

# Kiểm tra container database có đang chạy không
if ! docker-compose -f "$COMPOSE_FILE" ps db | grep -q "Up"; then
    error "Database container không đang chạy"
fi

# Tạo tên file backup
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/swinx_backup_$TIMESTAMP.sql"

log "🗃️ Tạo backup database..."

# Thực hiện backup
if docker-compose -f "$COMPOSE_FILE" exec -T db mysqldump \
    -u root -p"RootProd2024!@#SecurePass" \
    --single-transaction \
    --routines \
    --triggers \
    --all-databases > "$BACKUP_FILE"; then
    
    success "Backup thành công: $BACKUP_FILE"
    
    # Kiểm tra kích thước file backup
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    log "📊 Kích thước backup: $BACKUP_SIZE"
    
    # Nén file backup
    log "🗜️ Nén file backup..."
    gzip "$BACKUP_FILE"
    COMPRESSED_FILE="${BACKUP_FILE}.gz"
    COMPRESSED_SIZE=$(du -h "$COMPRESSED_FILE" | cut -f1)
    success "File đã nén: $COMPRESSED_FILE (${COMPRESSED_SIZE})"
    
else
    error "Backup thất bại"
fi

# Dọn dẹp backup cũ
log "🧹 Dọn dẹp backup cũ (giữ lại $RETENTION_DAYS ngày)..."
find "$BACKUP_DIR" -name "swinx_backup_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete

# Hiển thị danh sách backup
log "📋 Danh sách backup hiện tại:"
ls -lah "$BACKUP_DIR"/swinx_backup_*.sql.gz 2>/dev/null || log "Không có backup nào"

# Tính tổng dung lượng backup
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1 || echo "0")
log "💾 Tổng dung lượng backup: $TOTAL_SIZE"

success "✅ Backup database hoàn tất"

echo ""
echo "📋 THÔNG TIN BACKUP:"
echo "   - File backup: $COMPRESSED_FILE"
echo "   - Kích thước: $COMPRESSED_SIZE"
echo "   - Thời gian: $(date)"
echo ""
echo "🔧 KHÔI PHỤC DATABASE:"
echo "   - Giải nén: gunzip $COMPRESSED_FILE"
echo "   - Khôi phục: docker exec -i swinx-db mysql -u root -p < ${BACKUP_FILE}"
echo ""
