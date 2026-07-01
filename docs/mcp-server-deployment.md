# MCP Server Deployment & Troubleshooting

Last updated: 2026-07-01  
Owner: Platform Team  
Status: Operational runbook

## 1) Scope

This guide documents how to enable and verify the **Controlled MCP server** (`POST /mcp/swinx`) when cloning or deploying Swinx to a new Ubuntu host (aaPanel/nginx + PHP 8.4) behind Cloudflare.

It covers server-side fixes only. The MCP OAuth surface is implemented in application code (`routes/web.php`, `routes/ai.php`, Passport, `config/cors.php`). Most production failures are **infrastructure configuration**, not missing application features.

**Connector URL (ChatGPT / Claude custom connector):**

```text
https://<your-domain>/mcp/swinx
```

Related ADRs: [0010](adr/0010-mcp-access-is-per-user-oauth-impersonation.md), [0011](adr/0011-mcp-server-is-public-and-accepts-redacted-data-egress.md), [0012](adr/0012-chatgpt-connector-is-technically-feasible-deferred-on-dpa.md).

Known deployments (same server `157.10.186.103`):

| Domain | Server path |
| --- | --- |
| `dev-x.asia-vn.edu.vn` | `/www/wwwroot/dev-x.asia-vn.edu.vn/asia-admin-portal` |
| `x.asia-vn.edu.vn` | `/www/wwwroot/x.asia-vn.edu.vn/public_html` |
| `x.metropolia.edu.vn` | `/www/wwwroot/x.metropolia.edu.vn/asia-admin-portal` |

---

## 2) First-time checklist (new clone)

Run from the deployed app root (not Docker on these hosts — use host `php84`):

```bash
cd /www/wwwroot/<domain>/<app-path>

# 1. OAuth encryption keys (required — MCP/Passport will 500 without them)
php84 artisan passport:keys --force
bash scripts/fix-oauth-key-permissions.sh

# 2. Passport tables (run only oauth migrations if a full migrate fails on unrelated guards)
php84 artisan migrate --force --path=database/migrations/2026_06_30_223313_create_oauth_auth_codes_table.php
php84 artisan migrate --force --path=database/migrations/2026_06_30_223314_create_oauth_access_tokens_table.php
php84 artisan migrate --force --path=database/migrations/2026_06_30_223315_create_oauth_refresh_tokens_table.php
php84 artisan migrate --force --path=database/migrations/2026_06_30_223316_create_oauth_clients_table.php
php84 artisan migrate --force --path=database/migrations/2026_06_30_223317_create_oauth_device_codes_table.php

# 3. Environment (behind Cloudflare / reverse proxy)
# Add to .env if not present:
#   APP_URL=https://<your-domain>
#   GOOGLE_REDIRECT_URI=https://<your-domain>/auth/google/callback
#   TRUSTED_PROXIES=*

php84 artisan optimize:clear
```

**OAuth key permissions** must stay `www:www` + `chmod 600`. Re-run `scripts/fix-oauth-key-permissions.sh` after any broad `chmod -R 775 storage` (common on aaPanel deploys). Symptom: `500` on `/oauth/authorize` with *"permissions 775"* or *"key not readable"*.

---

## 3) Verification commands

Replace `<domain>` with your hostname. All checks should pass before testing ChatGPT.

### 3.1 External (through Cloudflare)

```bash
# OAuth discovery — must be HTTP 200 + application/json (NOT 403 HTML, NOT 404)
curl -sI "https://<domain>/.well-known/oauth-authorization-server" | head -5

# Protected resource metadata (RFC 9728 path ChatGPT follows from WWW-Authenticate)
curl -sI "https://<domain>/.well-known/oauth-protected-resource/mcp/swinx" | head -5

# MCP challenge — must be HTTP 401 with WWW-Authenticate header
curl -s -X POST "https://<domain>/mcp/swinx" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \
  -D - -o /dev/null | grep -iE "HTTP/|www-authenticate"

# Dynamic client registration (ChatGPT self-registers)
curl -s -X POST "https://<domain>/oauth/register" \
  -H "Content-Type: application/json" \
  -d '{"client_name":"ChatGPT","redirect_uris":["https://chatgpt.com/connector_platform_oauth_redirect"]}' \
  -w "\nHTTP:%{http_code}\n"

# CORS for ChatGPT browser probe
curl -sI "https://<domain>/.well-known/oauth-authorization-server" \
  -H "Origin: https://chatgpt.com" | grep -i access-control
```

Expected:

| Check | Expected |
| --- | --- |
| `/.well-known/oauth-authorization-server` | `200`, `content-type: application/json` |
| `/.well-known/oauth-protected-resource/mcp/swinx` | `200` |
| `POST /mcp/swinx` (no token) | `401` + `WWW-Authenticate: Bearer … resource_metadata=…` |
| `POST /oauth/register` | `201` + `client_id` |
| CORS with `Origin: https://chatgpt.com` | `access-control-allow-origin: https://chatgpt.com` |

### 3.2 Origin-only (bypass Cloudflare — SSH on server)

Use this to tell **server vs Cloudflare** failures apart:

```bash
curl -skI "https://127.0.0.1/.well-known/oauth-authorization-server" \
  -H "Host: <domain>" | head -5
```

- Origin **200** + external **403 HTML** (*Attention Required! | Cloudflare*) → Cloudflare block (see §5).
- Origin **404** + JSON body → nginx `.well-known` misconfiguration (see §4).
- Origin **500** on `/mcp/swinx` → missing OAuth keys or Passport tables (see §2).

---

## 4) nginx (aaPanel): `.well-known` returns 404 with JSON body

### Symptom

- `curl` shows `HTTP/2 404` but response body is valid OAuth JSON.
- ChatGPT: *"Error fetching OAuth configuration"* / *"does not implement OAuth"*.
- `php84 artisan tinker` simulating the same request returns **200** — Laravel is fine; nginx is wrong.

### Cause

aaPanel vhost regex location matches `.well-known` but omits `try_files`, so nginx does not forward the request to `index.php`. Laravel may still run via error-page fallback, but the **HTTP status stays 404**.

### Fix

Edit `/www/server/panel/vhost/nginx/<domain>.conf`. Compare with a working site (e.g. `dev-x.asia-vn.edu.vn`).

**Broken (common on new sites):**

```nginx
location ~ \.well-known{
    allow all;
}
```

**Fixed:**

```nginx
location ~ \.well-known{
    allow all;
    try_files $uri $uri/ /index.php?$query_string;
}
```

Then:

```bash
nginx -t && nginx -s reload
```

Backup the vhost before editing (aaPanel overwrites are possible on panel saves):

```bash
cp /www/server/panel/vhost/nginx/<domain>.conf \
   /www/server/panel/vhost/nginx/<domain>.conf.bak-mcp-$(date +%Y%m%d)
```

Re-run §3.1 — discovery must return **200**.

---

## 5) Cloudflare: entire subdomain returns 403

### Symptom

- Every URL on the subdomain returns `403` with HTML title *Attention Required! | Cloudflare*.
- Origin check (§3.2) returns **200**.
- Other subdomains on the same zone work (e.g. `exam.metropolia.edu.vn` OK, `x.metropolia.edu.vn` blocked).

ChatGPT cannot fetch OAuth metadata → same *"does not implement OAuth"* error.

### Cause

Cloudflare security (Bot Fight Mode, high Security Level, or WAF custom rule) blocks the hostname before traffic reaches nginx. This is **not** fixable in the Laravel repo.

### Fix (Cloudflare Dashboard)

Zone → **Security**:

1. **WAF custom rule (recommended)** — skip rules for the admin hostname:
   - Expression: `(http.host eq "x.<your-zone>")`
   - Action: **Skip** → all remaining custom rules
2. **Bots** — disable Bot Fight Mode or add exception for the hostname.
3. **Settings** — lower Security Level for the hostname if needed.

**Temporary test only:** DNS → set the `x` record to **DNS only** (grey cloud). Exposes origin IP; revert after confirming MCP works.

### Verify

```bash
curl -sI "https://<domain>/.well-known/oauth-authorization-server" | head -3
# Must show: HTTP/2 200
```

---

## 6) ChatGPT-specific errors

| User-visible error | Typical root cause | Fix section |
| --- | --- | --- |
| *Error fetching OAuth configuration* | Discovery not reachable (CF 403, nginx 404) | §4, §5 |
| *does not implement OAuth* | Browser got HTML/error instead of JSON metadata | §4, §5 |
| OAuth works in MCP Inspector but not ChatGPT | CORS — `config/cors.php` must allow `https://chatgpt.com` on `.well-known/*`, `oauth/*`, `mcp/*` | Already in repo; run `php84 artisan config:clear` after deploy |
| Redirect to `http://127.0.0.1` after Google login | `APP_URL` / `GOOGLE_REDIRECT_URI` mismatch; missing `TRUSTED_PROXIES=*` behind proxy | §2 `.env` |
| `500` on `/oauth/authorize` | OAuth key permissions or missing keys | §2, `scripts/fix-oauth-key-permissions.sh` |

**Note:** `ValidateMcpOrigin` (in `routes/ai.php`) may return `403` for disallowed `Origin` headers on `POST /mcp/swinx`. ChatGPT's primary failure mode for *"does not implement OAuth"* is the **discovery fetch** (`.well-known/*`), which uses CORS from `config/cors.php`, not the MCP origin guard. If discovery passes but connector auth still fails, compare `MCP_ALLOWED_ORIGINS` in `.env` with a working reference deployment.

---

## 7) Google OAuth (staff login before MCP consent)

MCP OAuth consent requires an authenticated staff session (often via Google SSO).

In Google Cloud Console, authorized redirect URI must match production:

```text
https://<domain>/auth/google/callback
```

In `.env`:

```env
APP_URL=https://<domain>
GOOGLE_REDIRECT_URI=https://<domain>/auth/google/callback
```

Use one consistent hostname end-to-end (no mixing `localhost` vs `127.0.0.1` in dev).

---

## 8) Quick diagnosis flow

```text
ChatGPT connector fails
        │
        ▼
curl /.well-known/oauth-authorization-server (external)
        │
   ┌────┴────┐
 403 HTML  404 JSON
   │         │
   ▼         ▼
 §5 CF    §4 nginx
 block    try_files
   │
 200 OK ──► curl POST /mcp/swinx → expect 401 + WWW-Authenticate
   │
   ▼
 Complete OAuth in browser (Google → consent → token)
```

---

## 9) Reference: automated tests in repo

Feature tests lock the ChatGPT connector contract:

- `tests/Feature/Mcp/McpChatGptConnectorSpikeTest.php`
- `tests/Feature/Mcp/McpOAuthSkeletonTest.php`
- `tests/Feature/Mcp/McpHardeningTest.php`

Run in Docker dev:

```bash
./scripts/dev.sh artisan test --compact --filter=Mcp
```

---

## 10) Related docs

- [Deployment Guide](deployment-guide.md) — CI/CD paths and branch → server mapping
- [plans/swinx-mcp-server-v1.notes.md](../plans/swinx-mcp-server-v1.notes.md) — spike findings and integration blockers
- `scripts/fix-oauth-key-permissions.sh` — OAuth key ownership and mode repair