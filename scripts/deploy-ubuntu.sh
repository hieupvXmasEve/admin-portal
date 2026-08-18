#!/usr/bin/env bash

set -Eeuo pipefail

log() {
    printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*"
}

fail() {
    log "ERROR: $*"
    exit 1
}

truthy() {
    case "${1:-}" in
        1|true|TRUE|yes|YES|on|ON) return 0 ;;
        *) return 1 ;;
    esac
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

load_shell_profile() {
    local profile

    for profile in /etc/profile "$HOME/.bash_profile" "$HOME/.bashrc" "$HOME/.profile"; do
        if [ -f "$profile" ]; then
            set +u
            # shellcheck source=/dev/null
            . "$profile" >/dev/null 2>&1 || true
            set -u
        fi
    done
}

add_path_if_exists() {
    if [ -d "$1" ]; then
        export PATH="$1:$PATH"
    fi
}

load_shell_profile
add_path_if_exists "$HOME/.local/share/pnpm"
add_path_if_exists "$HOME/.npm-global/bin"
add_path_if_exists "$HOME/.config/yarn/global/node_modules/.bin"

if [ -d "$HOME/.nvm/versions/node" ]; then
    latest_node_dir="$(find "$HOME/.nvm/versions/node" -maxdepth 1 -type d -name 'v*' | sort -V | tail -n 1 || true)"
    if [ -n "$latest_node_dir" ]; then
        add_path_if_exists "$latest_node_dir/bin"
    fi
fi

environment="${DEPLOY_ENVIRONMENT:-unknown}"
run_migrations="${DEPLOY_RUN_MIGRATIONS:-false}"
php_bin="${PHP_BIN:-php84}"
composer_bin="${COMPOSER_BIN:-composer84}"
pnpm_bin="${PNPM_BIN:-pnpm}"

log "Starting Swinx deploy"
log "Environment: ${environment}"
log "Path: $(pwd)"
log "Commit: $(git rev-parse --short HEAD 2>/dev/null || printf 'unknown')"
log "Run migrations: ${run_migrations}"

[ -f artisan ] || fail "artisan file not found; run this script from the Laravel project root"
[ -f composer.json ] || fail "composer.json not found"
[ -f package.json ] || fail "package.json not found"
[ -f .env ] || fail ".env not found on server; refusing to deploy without environment config"

require_command git
require_command "$php_bin"
require_command "$composer_bin"
require_command "$pnpm_bin"

log "Runtime versions"
"$php_bin" -v | head -n 1
"$composer_bin" --version
"$pnpm_bin" --version

composer_args=(install --no-interaction --prefer-dist --optimize-autoloader)
if [ "${environment}" = "production" ] || truthy "${DEPLOY_COMPOSER_NO_DEV:-false}"; then
    composer_args+=(--no-dev)
fi

log "Installing PHP dependencies"
"$composer_bin" "${composer_args[@]}"

log "Clearing stale Laravel caches"
"$php_bin" artisan optimize:clear

log "Generating Ziggy routes before asset build"
"$php_bin" artisan ziggy:generate

pnpm_args=(install)
if [ -f pnpm-lock.yaml ]; then
    pnpm_args+=(--frozen-lockfile)
else
    pnpm_args+=(--no-frozen-lockfile)
fi

log "Installing frontend dependencies"
"$pnpm_bin" "${pnpm_args[@]}"

log "Building frontend assets"
"$pnpm_bin" build

if [ -f resources/js/ziggy.js ] && ! git diff --quiet -- resources/js/ziggy.js; then
    log "Restoring generated Ziggy source to keep server worktree clean"
    git restore -- resources/js/ziggy.js
fi

log "Checking migration status"
"$php_bin" artisan migrate:status

if truthy "$run_migrations"; then
    log "Running database migrations"
    "$php_bin" artisan migrate --force
else
    log "Skipping database migrations; set run_migrations=true (or DEPLOY_RUN_MIGRATIONS=true) to run them"
fi

log "Syncing permissions and super_admin grants"
"$php_bin" artisan db:seed --class=UpdatePermissionsSeeder --force

log "Materializing Program Enrollments"
"$php_bin" artisan academic:backfill-program-enrollments

log "Verifying Program Enrollment coverage"
"$php_bin" artisan academic:backfill-program-enrollments --check

log "Rebuilding Laravel optimized caches"
"$php_bin" artisan optimize

log "Fixing Passport OAuth key permissions"
bash scripts/fix-oauth-key-permissions.sh storage

log "Restarting Laravel queue workers"
"$php_bin" artisan queue:restart

log "Deploy completed"
