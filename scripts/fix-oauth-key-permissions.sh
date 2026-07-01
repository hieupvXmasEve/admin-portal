#!/usr/bin/env sh
# league/oauth2-server rejects Passport key files that are world/group-executable (e.g. 775).
# PHP-FPM must also be able to read them (owner = web user, mode 600).
# Run after any broad storage chmod or passport:keys / passport:install.

set -eu

storage_dir="${1:-storage}"
web_user="${OAUTH_KEY_OWNER:-${DEPLOY_WEB_USER:-www}}"

for key in "${storage_dir}/oauth-private.key" "${storage_dir}/oauth-public.key"; do
    if [ -f "$key" ]; then
        if id "$web_user" >/dev/null 2>&1; then
            chown "$web_user":"$web_user" "$key"
        fi
        chmod 600 "$key"
    fi
done