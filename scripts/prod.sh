#!/bin/bash

set -euo pipefail

COMPOSE_FILE="docker/docker-compose.production.yml"
ENV_FILE=".env"
PROJECT_NAME="swinx-production"

ensure_env() {
    if [ ! -f "$ENV_FILE" ]; then
        echo "$ENV_FILE not found. Copy .env.example and set production values first."
        exit 1
    fi

    if ! grep -q '^APP_ENV=production$' "$ENV_FILE"; then
        echo "APP_ENV must be production in $ENV_FILE"
        exit 1
    fi

    if grep -q '^APP_DEBUG=true$' "$ENV_FILE"; then
        echo "APP_DEBUG must be false in production"
        exit 1
    fi
}

dc() {
    ABLY_KEY="$(env_value ABLY_KEY '')" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" -p "$PROJECT_NAME" "$@"
}

env_value() {
    local key="$1"
    local fallback="${2:-}"
    local line
    line=$(grep -E "^${key}[[:space:]]*=" "$ENV_FILE" | tail -n 1 || true)
    if [ -z "$line" ]; then
        printf '%s' "$fallback"
        return
    fi

    printf '%s' "$line" | sed -E 's/^[^=]+=[[:space:]]*//; s/[[:space:]]+$//; s/^"//; s/"$//'
}

confirm_prod() {
    read -r -p "Type PRODUCTION to continue: " reply
    if [ "$reply" != "PRODUCTION" ]; then
        echo "Cancelled."
        exit 1
    fi
}

case "${1:-}" in
    deploy)
        ensure_env
        confirm_prod
        mkdir -p backups logs
        dc up -d --build
        dc exec -T app php artisan migrate --force
        ;;
    start)
        ensure_env
        confirm_prod
        dc up -d
        ;;
    stop)
        confirm_prod
        dc down
        ;;
    restart)
        ensure_env
        confirm_prod
        dc down
        dc up -d
        ;;
    logs)
        shift
        dc logs -f "$@"
        ;;
    status)
        dc ps
        ;;
    shell)
        confirm_prod
        dc exec app sh
        ;;
    mysql)
        ensure_env
        confirm_prod
        dc exec db mariadb -u"$(env_value DB_USERNAME)" -p"$(env_value DB_PASSWORD)" "$(env_value DB_DATABASE)"
        ;;
    artisan)
        shift
        ensure_env
        confirm_prod
        dc exec app php artisan "$@"
        ;;
    backup)
        ensure_env
        mkdir -p backups/db
        backup_path="${2:-backups/db/production-$(date +%Y%m%d_%H%M%S).sql}"
        dc exec -T db mariadb-dump \
            --single-transaction \
            --routines \
            --triggers \
            -u"root" \
            -p"$(env_value DB_ROOT_PASSWORD)" \
            "$(env_value DB_DATABASE)" > "$backup_path"
        echo "Backup written to $backup_path"
        ;;
    health)
        ensure_env
        curl -fsS "https://$(env_value SERVER_NAME)/up"
        ;;
    *)
        echo "Usage: $0 {deploy|start|stop|restart|logs|status|shell|mysql|artisan|backup|health}"
        exit 1
        ;;
esac
