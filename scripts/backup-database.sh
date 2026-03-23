#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
BACKUP_DIR="$ROOT_DIR/backups/db"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
RETENTION_DAYS=14

if [ -f "$ENV_FILE" ]; then
    retention_line="$(grep -E '^BACKUP_RETENTION_DAYS[[:space:]]*=' "$ENV_FILE" | tail -n 1 || true)"
    if [ -n "$retention_line" ]; then
        RETENTION_DAYS="$(printf '%s' "$retention_line" | sed -E 's/^[^=]+=[[:space:]]*//; s/[[:space:]]+$//; s/^"//; s/"$//')"
    fi
fi

mkdir -p "$BACKUP_DIR"

cd "$ROOT_DIR"

./scripts/prod.sh backup "$BACKUP_DIR/production-$TIMESTAMP.sql"
gzip -f "$BACKUP_DIR/production-$TIMESTAMP.sql"

find "$BACKUP_DIR" -type f -name 'production-*.sql.gz' -mtime +"$RETENTION_DAYS" -delete

echo "Database backup completed: $BACKUP_DIR/production-$TIMESTAMP.sql.gz"
