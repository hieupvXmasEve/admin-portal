#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

usage() {
    cat <<'USAGE'
Usage:
  ./scripts/dev-env.sh localhost
  ./scripts/dev-env.sh tunnel

Switches the Docker dev stack between localhost and Cloudflare tunnel profiles.
USAGE
}

profile_file_for() {
    case "$1" in
        localhost) printf '%s/.env.localhost' "$ROOT_DIR" ;;
        tunnel) printf '%s/.env.tunnel' "$ROOT_DIR" ;;
        *) return 1 ;;
    esac
}

set_env_value() {
    local key="$1"
    local value="$2"
    local tmp_file

    tmp_file="$(mktemp)"

    awk -v key="$key" -v value="$value" '
        BEGIN { replaced = 0 }
        $0 ~ "^[[:space:]]*" key "[[:space:]]*=" {
            print key "=" value
            replaced = 1
            next
        }
        { print }
        END {
            if (replaced == 0) {
                print key "=" value
            }
        }
    ' "$ENV_FILE" > "$tmp_file"

    mv "$tmp_file" "$ENV_FILE"
}

apply_profile() {
    local profile_file="$1"
    local raw_line line key value

    while IFS= read -r raw_line || [ -n "$raw_line" ]; do
        line="${raw_line#"${raw_line%%[![:space:]]*}"}"

        case "$line" in
            ''|\#*) continue ;;
        esac

        key="${line%%=*}"
        value="${line#*=}"

        if [[ ! "$key" =~ ^[A-Z0-9_]+$ ]]; then
            echo "Invalid env key in $profile_file: $key" >&2
            exit 1
        fi

        set_env_value "$key" "$value"
    done < "$profile_file"
}

env_value() {
    local key="$1"
    local fallback="${2:-}"
    local line

    line="$(grep -E "^${key}[[:space:]]*=" "$ENV_FILE" | tail -n 1 || true)"
    if [ -z "$line" ]; then
        printf '%s' "$fallback"
        return
    fi

    printf '%s' "$line" | sed -E 's/^[^=]+=[[:space:]]*//; s/[[:space:]]+$//; s/^"//; s/"$//'
}

mask_google_location() {
    sed -E 's/client_id=[^&]+/client_id=[masked]/; s/state=[^&]+/state=[masked]/'
}

verify_runtime() {
    local app_port app_url probe_url location

    app_port="$(env_value APP_PORT 8000)"
    app_url="$(env_value APP_URL)"
    probe_url="${app_url%/}/auth/google/redirect"

    echo
    ./scripts/dev.sh artisan config:show app.url
    ./scripts/dev.sh artisan tinker --execute 'echo "google.redirect=".config("services.google.redirect").PHP_EOL; echo "session.secure=".(config("session.secure") ? "true" : "false").PHP_EOL;'

    location=""
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        location="$(curl -sSI "$probe_url" 2>/dev/null | tr -d '\r' | awk 'BEGIN{IGNORECASE=1} /^Location:/{sub(/^Location:[[:space:]]*/, ""); print; exit}' || true)"

        if [ -n "$location" ]; then
            break
        fi

        sleep 2
    done

    if [ -z "$location" ]; then
        echo "Could not probe Google redirect at $probe_url" >&2
        exit 1
    fi

    echo
    echo "Google redirect probe:"
    printf '%s\n' "$location" | mask_google_location
    echo
    echo "Active app URL: $app_url"
}

mode="${1:-}"
if [ -z "$mode" ] || [ "$mode" = "-h" ] || [ "$mode" = "--help" ]; then
    usage
    exit 0
fi

profile_file="$(profile_file_for "$mode" || true)"
if [ -z "${profile_file:-}" ]; then
    usage >&2
    exit 1
fi

if [ ! -f "$profile_file" ]; then
    echo "Missing profile file: $profile_file" >&2
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    cp "$ROOT_DIR/.env.example" "$ENV_FILE"
    echo "Created .env from .env.example"
fi

timestamp="$(date +%Y%m%d%H%M%S)"
cp "$ENV_FILE" "$ROOT_DIR/.env.backup.${mode}.${timestamp}"

echo "Applying $mode profile from ${profile_file#$ROOT_DIR/}"
apply_profile "$profile_file"

cd "$ROOT_DIR"
./scripts/dev.sh restart-no-build
./scripts/dev.sh artisan optimize:clear

if [ "$mode" = "tunnel" ]; then
    ./scripts/dev.sh tunnel-assets
else
    echo "Localhost mode: Vite is running for HMR."
    echo "App: $(env_value APP_URL)"
fi

verify_runtime
