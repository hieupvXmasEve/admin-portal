#!/bin/bash
# Swinx host deployment — Docker all-in-one, single exposed port.
# Usage: configure .env → ./scripts/host.sh deploy → point domain at HOST_APP_PORT.

set -euo pipefail

COMPOSE_FILE="docker/docker-compose.host.yml"
ENV_FILE=".env"
PROJECT_NAME="swinx-host"

ensure_env() {
    if [ ! -f "$ENV_FILE" ]; then
        echo "$ENV_FILE not found. Copy .env.example and set production values first."
        exit 1
    fi

    if ! grep -q '^APP_ENV=production$' "$ENV_FILE"; then
        echo "APP_ENV must be production in $ENV_FILE for host deploy."
        exit 1
    fi

    if grep -q '^APP_DEBUG=true$' "$ENV_FILE"; then
        echo "APP_DEBUG must be false in production."
        exit 1
    fi
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

wait_for_app() {
    local port="${1:-8080}"
    local attempt=0
    local max_attempts=60

    until curl -fsS "http://127.0.0.1:${port}/up" >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "App did not become healthy on port ${port} in time."
            dc logs app --tail 50
            exit 1
        fi
        sleep 2
    done
}

case "${1:-}" in
    deploy)
        ensure_env
        mkdir -p backups logs
        dc up -d --build
        wait_for_app "$(env_value HOST_APP_PORT 8080)"
        echo ""
        echo "Deploy complete. App listening on http://127.0.0.1:$(env_value HOST_APP_PORT 8080)"
        echo "Point your domain reverse proxy at that port (see docker/nginx-proxy-snippet.conf)."
        ;;
    start)
        ensure_env
        dc up -d
        ;;
    stop)
        dc down
        ;;
    restart)
        ensure_env
        dc down
        dc up -d
        ;;
    rebuild)
        ensure_env
        dc down
        dc build --no-cache
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
        dc exec app sh
        ;;
    mysql)
        ensure_env
        dc exec db mariadb -u"$(env_value DB_USERNAME)" -p"$(env_value DB_PASSWORD)" "$(env_value DB_DATABASE)"
        ;;
    artisan)
        shift
        ensure_env
        dc exec app php artisan "$@"
        ;;
    composer)
        shift
        ensure_env
        dc exec app composer "$@"
        ;;
    backup)
        ensure_env
        mkdir -p backups/db
        backup_path="${2:-backups/db/host-$(date +%Y%m%d_%H%M%S).sql}"
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
        port="$(env_value HOST_APP_PORT 8080)"
        curl -fsS "http://127.0.0.1:${port}/up"
        echo ""
        ;;
    *)
        echo "Usage: $0 {deploy|start|stop|restart|rebuild|logs|status|shell|mysql|artisan|composer|backup|health}"
        exit 1
        ;;
esac