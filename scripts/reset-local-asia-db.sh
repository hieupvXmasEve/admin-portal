#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
BACKUP_PRESET="asia"

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
BACKUP_FILE="${BACKUP_FILE:-}"
CUSTOM_DATABASE=0
DEV_ASIA_MODE=0
RUN_APP_STEPS=1
YES=0

usage() {
    cat <<'USAGE'
Usage: ./scripts/reset-local-asia-db.sh [options]

Drops and recreates the local database and imports a SQL backup. By default it then runs:
  ./scripts/dev.sh artisan migrate --force
  ./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder

Quick test-database restore (no Artisan commands):
  ./scripts/reset-local-dev-asia-db.sh

Backup presets:
  --asia (default)   backups/asia.sql
  --metro            backups/metropolia.sql

Database options:
  --dev-asia         Use database dev_asia and skip migrations/seeders.
  --database NAME    Override the target database name.
  --import-only      Skip migrations/seeders after importing the SQL dump.

Environment overrides:
  DB_CONTAINER       default: swinx-db-dev
  DB_NAME            default: DB_DATABASE from .env, or asia
  DB_ROOT_USER       default: root
  DB_ROOT_PASSWORD   default: DB_ROOT_PASSWORD from .env, or root
  BACKUP_FILE        explicit backup path (overrides --asia/--metro)

Options:
  -y, --yes          Skip the interactive confirmation prompt.
  -h, --help         Show this help.
USAGE
}

resolve_backup_file() {
    if [ -n "$BACKUP_FILE" ]; then
        return
    fi

    case "$BACKUP_PRESET" in
        asia)
            BACKUP_FILE="$ROOT_DIR/backups/asia.sql"
            ;;
        metro)
            BACKUP_FILE="$ROOT_DIR/backups/metropolia.sql"
            ;;
        *)
            echo "Unknown backup preset: $BACKUP_PRESET" >&2
            exit 1
            ;;
    esac
}

choose_backup_preset() {
    if [ -n "$BACKUP_FILE" ]; then
        return
    fi

    echo "Choose backup file:"
    echo "  1) asia        (backups/asia.sql) [default]"
    echo "  2) metro        (backups/metropolia.sql)"
    read -r -p "Selection [1]: " backup_choice

    case "${backup_choice:-1}" in
        1|asia|asia.sql)
            BACKUP_PRESET="asia"
            ;;
        2|metro|metropolia|metropolia.sql)
            BACKUP_PRESET="metro"
            ;;
        *)
            echo "Invalid selection: $backup_choice" >&2
            exit 1
            ;;
    esac
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        -y|--yes)
            YES=1
            ;;
        --asia)
            BACKUP_PRESET="asia"
            ;;
        --metro|--metropolia)
            BACKUP_PRESET="metro"
            ;;
        --dev-asia)
            DB_NAME="dev_asia"
            DEV_ASIA_MODE=1
            RUN_APP_STEPS=0
            ;;
        --database)
            if [ "$#" -lt 2 ]; then
                echo "Missing database name after --database." >&2
                exit 1
            fi
            DB_NAME="$2"
            CUSTOM_DATABASE=1
            shift
            ;;
        --import-only)
            RUN_APP_STEPS=0
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

resolve_backup_file

if [ "$DEV_ASIA_MODE" -eq 1 ] && [ "$CUSTOM_DATABASE" -eq 1 ]; then
    echo "--dev-asia cannot be combined with --database; its target is always dev_asia." >&2
    exit 1
fi

if [[ ! "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Refusing unsafe database name: $DB_NAME" >&2
    exit 1
fi

if [ "$RUN_APP_STEPS" -eq 1 ]; then
    APP_DB_NAME="$(env_value DB_DATABASE asia)"
    if [ "$DB_NAME" != "$APP_DB_NAME" ]; then
        echo "Refusing to run migrations: import target '$DB_NAME' differs from .env DB_DATABASE '$APP_DB_NAME'." >&2
        echo "Use --import-only, or use --dev-asia for the isolated SQL test database." >&2
        exit 1
    fi
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
    choose_backup_preset
    resolve_backup_file

    if [ ! -f "$BACKUP_FILE" ]; then
        echo "Backup file not found: $BACKUP_FILE" >&2
        exit 1
    fi

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

IMPORTED_TABLE_COUNT=$(docker exec "$DB_CONTAINER" mariadb -u"$DB_ROOT_USER" -p"$DB_ROOT_PASSWORD" -N \
    -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$DB_NAME';")

if [ "$IMPORTED_TABLE_COUNT" -eq 0 ]; then
    echo "Import verification failed: database '$DB_NAME' contains no tables." >&2
    exit 1
fi

echo "SQL verification passed: '$DB_NAME' contains $IMPORTED_TABLE_COUNT tables."

if [ "$RUN_APP_STEPS" -eq 1 ]; then
    echo "Running Laravel migrations..."
    ./scripts/dev.sh artisan migrate --force

    echo "Syncing permissions..."
    ./scripts/dev.sh artisan db:seed --class=UpdatePermissionsSeeder

    echo "Local '$DB_NAME' database reset, imported, migrated, and permissions synced successfully."
else
    echo "Local '$DB_NAME' database reset and imported successfully (Artisan steps skipped)."
fi
