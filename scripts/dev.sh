#!/bin/bash

set -euo pipefail

COMPOSE_FILE="docker/docker-compose.dev.yml"
ENV_FILE=".env"
PROJECT_NAME="swinx-dev"

ensure_env() {
    if [ ! -f "$ENV_FILE" ]; then
        cp .env.example "$ENV_FILE"
        echo "Created $ENV_FILE from .env.example"
    fi
}

load_env() {
    :
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

dc() {
    ABLY_KEY="$(env_value ABLY_KEY '')" docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" -p "$PROJECT_NAME" "$@"
}

start() {
    ensure_env
    dc up -d --build
    echo "App: http://localhost:$(env_value APP_PORT 8000)"
    echo "Vite: http://localhost:$(env_value VITE_PORT 5173)"
    echo "Mailpit: http://localhost:$(env_value MAILPIT_HTTP_PORT 8026)"
}

case "${1:-}" in
    start) start ;;
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
    composer) shift; dc exec app composer "$@" ;;
    npm) shift; dc exec app pnpm "$@" ;;
    pnpm) shift; dc exec app pnpm "$@" ;;
    test) shift; dc exec app php artisan test "$@" ;;
    tunnel)
        # Share via Cloudflare Tunnel: built assets only (avoids Cloudflare 429 on Vite dev requests).
        ensure_env
        dc exec app pnpm run build
        rm -f public/hot
        dc stop vite 2>/dev/null || true
        echo "Tunnel mode: built assets served from public/build/"
        echo "App: https://$(env_value APP_URL https://mcp.asia-vn.edu.vn | sed -E 's#^https?://##')"
        echo "Local HMR again: ./scripts/dev.sh start  (restarts vite + hot file)"
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|rebuild|logs|status|shell|mysql|artisan|composer|npm|pnpm|test|tunnel}"
        exit 1
        ;;
esac
