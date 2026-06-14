#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

env_value() {
    local key="$1"
    local fallback="${2:-}"
    local line

    if [ ! -f "$ENV_FILE" ]; then
        printf '%s' "$fallback"
        return
    fi

    line=$(grep -E "^${key}[[:space:]]*=" "$ENV_FILE" | tail -n 1 || true)
    if [ -z "$line" ]; then
        printf '%s' "$fallback"
        return
    fi

    printf '%s' "$line" | sed -E 's/^[^=]+=[[:space:]]*//; s/[[:space:]]+$//; s/^"//; s/"$//'
}

DB_CONTAINER="${DB_CONTAINER:-swinx-db-dev}"
DB_NAME="${DB_NAME:-$(env_value DB_DATABASE asia)}"
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-$(env_value DB_ROOT_PASSWORD root)}"
BACKUP_FILE="${BACKUP_FILE:-$ROOT_DIR/backups/asia.sql}"
YES=0

usage() {
    cat <<'USAGE'
Usage: ./scripts/reset-local-asia-db.sh [--yes]

Drops and recreates the local asia database, imports backups/asia.sql, then runs:
  ./scripts/dev.sh artisan migrate --force

Environment overrides:
  DB_CONTAINER       default: swinx-db-dev
  DB_NAME            default: DB_DATABASE from .env, or asia
  DB_ROOT_USER       default: root
  DB_ROOT_PASSWORD   default: DB_ROOT_PASSWORD from .env, or root
  BACKUP_FILE        default: backups/asia.sql

Options:
  -y, --yes          Skip the interactive confirmation prompt.
  -h, --help         Show this help.
USAGE
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        -y|--yes)
            YES=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
    shift
done

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Refusing unsafe database name: $DB_NAME" >&2
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "Backup file not found: $BACKUP_FILE" >&2
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    echo "docker is required but was not found in PATH." >&2
    exit 1
fi

if ! docker inspect "$DB_CONTAINER" >/dev/null 2>&1; then
    echo "Database container not found: $DB_CONTAINER" >&2
    echo "Start the local stack first: ./scripts/dev.sh start" >&2
    exit 1
fi

if [ "$(docker inspect -f '{{.State.Running}}' "$DB_CONTAINER")" != "true" ]; then
    echo "Database container is not running: $DB_CONTAINER" >&2
    echo "Start the local stack first: ./scripts/dev.sh start" >&2
    exit 1
fi

cd "$ROOT_DIR"

if [ "$YES" -ne 1 ]; then
    echo "This will DROP and recreate database '$DB_NAME' in container '$DB_CONTAINER'."
    echo "Backup source: $BACKUP_FILE"
    read -r -p "Type 'reset $DB_NAME' to continue: " confirmation

    if [ "$confirmation" != "reset $DB_NAME" ]; then
        echo "Aborted."
        exit 1
    fi
fi

echo "Dropping and recreating database '$DB_NAME'..."
docker exec "$DB_CONTAINER" mariadb -u"$DB_ROOT_USER" -p"$DB_ROOT_PASSWORD" \
    -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Importing '$BACKUP_FILE' into '$DB_NAME'..."
docker exec -i "$DB_CONTAINER" mariadb -u"$DB_ROOT_USER" -p"$DB_ROOT_PASSWORD" "$DB_NAME" < "$BACKUP_FILE"

echo "Running Laravel migrations..."
./scripts/dev.sh artisan migrate --force

echo "Local '$DB_NAME' database reset, imported, and migrated successfully."
