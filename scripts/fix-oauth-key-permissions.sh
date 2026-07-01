#!/usr/bin/env sh
# league/oauth2-server rejects Passport key files that are world/group-executable (e.g. 775).
# Run after any broad storage chmod or passport:keys / passport:install.

set -eu

storage_dir="${1:-storage}"

for key in "${storage_dir}/oauth-private.key" "${storage_dir}/oauth-public.key"; do
    if [ -f "$key" ]; then
        chmod 600 "$key"
    fi
done