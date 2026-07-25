---
title: Controlled MCP Server Operations
status: current
type: runbook
scope: Controlled MCP deployment and operations
last_verified: "2026-07-25"
owner: Platform Team
audience:
  - platform operators
  - developers
---

# Controlled MCP Server Operations

## Purpose

This runbook covers deployment and diagnosis of the public Controlled MCP
endpoint. Architecture and risk decisions remain in the MCP ADRs; this file
contains only the current operating path.

The connector URL is:

```text
https://<domain>/mcp/swinx
```

## Runtime contract

`POST /mcp/swinx` exposes three read-only, allowlisted tools:

- `query_metrics`
- `search_entities`
- `get_entity_profile`

The registered tools and their current descriptions are owned by
`app/Mcp/Servers/SwinxMcpServer.php` and `app/Mcp/Tools/`. Each call acts as the
OAuth user and is constrained to that user's permissions and campus access.
Tool calls are recorded through `app/Mcp/Support/McpAuditRecorder.php`.

The endpoint middleware order in `routes/ai.php` is part of the security
boundary:

1. validate the Host and any supplied Origin;
2. reject oversized tool arguments;
3. authenticate the Passport user;
4. apply a per-user rate limit.

OAuth discovery and dynamic client registration are registered at the
application root by `Mcp::oauthRoutes()` in `routes/web.php`. Their per-IP rate
limits are configured separately.

Related decisions:

- `docs/adr/0009-mcp-server-replaces-staff-copilot-chat-ui.md`
- `docs/adr/0010-mcp-access-is-per-user-oauth-impersonation.md`
- `docs/adr/0011-mcp-server-is-public-and-accepts-redacted-data-egress.md`

## Configuration

The application URL must be the public HTTPS origin. Behind a proxy, preserve
the forwarded scheme and host.

```env
APP_URL=https://<domain>
APP_DEBUG=false
TRUSTED_PROXIES=*
GOOGLE_REDIRECT_URI=https://<domain>/auth/google/callback
```

Optional MCP hardening values are read by `config/mcp.php`:

- `MCP_ALLOWED_HOSTS`
- `MCP_ALLOWED_ORIGINS`
- `MCP_MAX_ARGUMENT_BYTES`
- `MCP_TOOL_CALLS_PER_MINUTE`
- `MCP_OAUTH_PER_MINUTE`
- `MCP_REGISTRATION_PER_HOUR`
- `MCP_AUTHORIZATION_SERVER`

`config/cors.php` owns browser-client access to `.well-known/*`, `oauth/*`, and
`mcp/*`. A browser origin may pass CORS discovery and still be rejected by the
stricter MCP Host/Origin guard, so diagnose those layers separately.

## Deploy

The preferred server path is the host Docker deployment:

```bash
cp .env.example .env
./scripts/host.sh deploy
```

Point the reverse proxy at `http://127.0.0.1:8080` or the configured
`HOST_APP_PORT`. Use `docker/nginx-proxy-snippet.conf` as the reverse-proxy
reference. The production container entrypoint creates Passport keys and then
repairs their ownership and mode.

For a bare-PHP deployment, create keys with the deployed PHP runtime and then
run:

```bash
bash scripts/fix-oauth-key-permissions.sh storage
```

Passport private keys must remain readable only by the application user.
Re-run the repair script after any broad storage permission change.

## External verification

Run these checks through the same public hostname the connector uses:

```bash
curl -sI "https://<domain>/.well-known/oauth-authorization-server"
curl -sI "https://<domain>/.well-known/oauth-protected-resource/mcp/swinx"
curl -s -X POST "https://<domain>/mcp/swinx" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \
  -D - -o /dev/null
```

Expected results:

- both metadata requests return HTTP 200 with JSON;
- the unauthenticated MCP request returns HTTP 401 with
  `WWW-Authenticate`;
- an OAuth client can register, authenticate a staff member, show consent,
  and receive a token.

Run the focused contract tests with:

```bash
./scripts/dev.sh artisan test --compact --filter=Mcp
```

## Diagnosis

### Discovery returns 404

If Laravel emits valid metadata with a 404 status only behind bare aaPanel
nginx, the `.well-known` location is not forwarding to `index.php`. Its location
must include the Laravel `try_files` fallback. Docker/FrankenPHP deployments do
not need that nginx-specific rule.

### External request returns Cloudflare HTML

Compare an origin-only request with the public request. An origin 200 plus a
public Cloudflare 403 means the application was never reached. Adjust the WAF
or bot rule for the exact hostname; do not weaken Laravel authentication to
work around it.

### OAuth redirects to an internal or HTTP URL

Check `APP_URL`, `GOOGLE_REDIRECT_URI`, proxy forwarding, and
`TRUSTED_PROXIES`. Use one hostname throughout discovery, Google login, consent,
and the connector configuration.

### MCP returns 403 after discovery succeeds

Check `MCP_ALLOWED_HOSTS` and `MCP_ALLOWED_ORIGINS`, then inspect
`app/Http/Middleware/ValidateMcpOrigin.php`. Non-browser clients may omit
`Origin`; a supplied Origin must be allowed.

### OAuth authorization returns 500

Verify Passport migrations and key presence, ownership, and `0600` mode. The
repair owner is `scripts/fix-oauth-key-permissions.sh`.
