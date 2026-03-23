#!/bin/bash

set -euo pipefail

COMPOSE_FILE="docker/docker-compose.local-prod.yml"
ENV_FILE=".env"
PROJECT_NAME="swinx-local-prod"

ensure_env() {
    if [ ! -f "$ENV_FILE" ]; then
        cp .env.example "$ENV_FILE"
        echo "Created $ENV_FILE from .env.example"
    fi
}

load_env() { :; }

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

dc() {
    ABLY_KEY="$(env_value ABLY_KEY '')" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" -p "$PROJECT_NAME" "$@"
}

wait_for_db() {
    local attempt=0
    local max_attempts=30

    until dc exec -T db mariadb-admin ping -h localhost -uroot -p"$(env_value DB_ROOT_PASSWORD root)" --silent >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "Local production DB did not become ready in time."
            exit 1
        fi
        sleep 2
    done
}

case "${1:-}" in
    start)
        ensure_env
        dc up -d --build
        echo "Local production: https://localhost:${LOCAL_PROD_HTTPS_PORT:-8443}/up"
        ;;
    stop) dc down ;;
    restart) dc down && dc up -d --build ;;
    rebuild) dc down && dc build --no-cache && dc up -d ;;
    logs) shift; dc logs -f "$@" ;;
    status) dc ps ;;
    shell) dc exec app sh ;;
    mysql)
        ensure_env
        dc exec db mariadb -u"$(env_value DB_USERNAME swinx)" -p"$(env_value DB_PASSWORD swinx)" "$(env_value DB_DATABASE asia)"
        ;;
    artisan) shift; dc exec app php artisan "$@" ;;
    test-ssl) curl -kI "https://localhost:${LOCAL_PROD_HTTPS_PORT:-8443}/up" ;;
    backup)
        ensure_env
        mkdir -p backups
        dc up -d db
        wait_for_db
        dc exec -T db mariadb-dump \
            --single-transaction \
            --routines \
            --triggers \
            -uroot \
            -p"$(env_value DB_ROOT_PASSWORD root)" \
            "$(env_value DB_DATABASE asia)" > "backups/local-prod-$(date +%Y%m%d_%H%M%S).sql"
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|rebuild|logs|status|shell|mysql|artisan|test-ssl|backup}"
        exit 1
        ;;
esac
