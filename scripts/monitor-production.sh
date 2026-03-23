#!/bin/bash

set -euo pipefail

COMPOSE_FILE="docker/docker-compose.production.yml"
ENV_FILE=".env"
PROJECT_NAME="swinx-production"

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

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" -p "$PROJECT_NAME" ps
curl -fsS "https://$(env_value SERVER_NAME)/up"
