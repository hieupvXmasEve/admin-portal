#!/bin/bash

set -euo pipefail

DEV_COMPOSE="docker/docker-compose.dev.yml"
LOCAL_PROD_COMPOSE="docker/docker-compose.local-prod.yml"
PROD_COMPOSE="docker/docker-compose.production.yml"
ENV_FILE=".env"

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

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is required."
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Docker daemon is not running."
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    cp .env.example "$ENV_FILE"
    echo "Created $ENV_FILE from .env.example"
fi

for file in "$DEV_COMPOSE" "$LOCAL_PROD_COMPOSE" "$PROD_COMPOSE" "docker/Dockerfile" "docker/Dockerfile.production" ".env.example"; do
    if [ ! -f "$file" ]; then
        echo "Missing required file: $file"
        exit 1
    fi
done

ABLY_KEY="$(env_value ABLY_KEY '')" docker compose --env-file "$ENV_FILE" -f "$DEV_COMPOSE" config >/dev/null
ABLY_KEY="$(env_value ABLY_KEY '')" docker compose --env-file "$ENV_FILE" -f "$LOCAL_PROD_COMPOSE" config >/dev/null
ABLY_KEY="$(env_value ABLY_KEY '')" docker compose --env-file "$ENV_FILE" -f "$PROD_COMPOSE" config >/dev/null

echo "Docker setup looks valid."
