# laravel/mcp v0.7.2 — OAuth + discovery code-path review

Status: reviewed-for-production

> Issue 06 acceptance: "The MCP package version is pinned and its OAuth/discovery
> path has been reviewed." Pin: `composer.json` → `laravel/mcp: 0.7.2` (exact);
> `composer.lock` → `v0.7.2`. This is the price of a public endpoint on a pre-1.0
> dependency — reviewing the surface, not editing the package README.

## What was read

- `vendor/laravel/mcp/src/Server/Registrar.php` — `oauthRoutes()`, `web()`,
  `authorizationServerMetadata()`, `protectedResourceMetadata()`, `ensureMcpScope()`.
- `vendor/laravel/mcp/src/Server/Http/Controllers/OAuthRegisterController.php` — DCR.
- `vendor/laravel/mcp/src/Server/McpServiceProvider.php` — route/config registration.
- `vendor/laravel/mcp/config/mcp.php` — shipped defaults (merged via `mergeConfigFrom`).

## Routes the package registers (confirmed via `route:list`)

| Route | Action | Notes |
|---|---|---|
| `GET /.well-known/oauth-authorization-server` (+ `/{path}`) | closure | issuer, authorize/token/register endpoints, `S256`, scopes `mcp:use`, grants `authorization_code`+`refresh_token` |
| `GET /.well-known/oauth-protected-resource` (+ `/{path}`) | closure | resource + authorization_servers + `mcp:use` |
| `POST /oauth/register` | `OAuthRegisterController` | dynamic client registration (DCR) |
| `GET/POST /oauth/authorize`, `POST /oauth/token` | Passport | the actual OAuth endpoints |

`oauth/token` already carries Passport's own `ThrottleRequests`. The discovery + DCR
routes carry only `web` until this issue wraps them in `throttle:mcp-oauth` (per IP).

## Findings

1. **DCR validates redirect URIs against `config('mcp.redirect_domains')`.** Default
   shipped value is `['*']` (open). With an empty/missing list, every https URI is
   rejected — so the app config must keep the key. We restate it explicitly in
   `config/mcp.php` (`['*']`, ADR-0011: consent screen is the anti-phishing control,
   not a closed redirect list). Localhost is permitted only when a localhost domain is
   listed; custom (RFC-8252) schemes only when in `config('mcp.custom_schemes')`.
   **Action taken:** redirect policy is now explicit and operator-tightenable.

2. **DCR creates a public (non-confidential) client, device-flow disabled, auth method
   `none`.** Scope fixed to `mcp:use`. No secret is issued — correct for a public
   PKCE client. No authentication on DCR by design (zero-install); abuse is bounded by
   the new per-IP `registration_per_hour` throttle (5/hr) added in this issue.

3. **Discovery metadata is unauthenticated and CSRF-exempt** (bootstrap/app.php). It
   leaks only public endpoint URLs and supported params — no secrets. Acceptable.

4. **`ensureMcpScope()` registers the `mcp:use` Passport scope on boot.** Idempotent;
   does not widen any existing scope set. Fine.

5. **`Mcp::web()` (the tool endpoint) has no built-in Host/Origin protection** — the
   package README warns of DNS-rebinding. Closed in this issue by `ValidateMcpOrigin`
   attached at registration (not globally).

## Conclusion

No unreviewed or unsafe surface for a public, OAuth-gated, read-only deployment once
this issue's wrap (origin/host validation + per-IP OAuth/DCR throttle + per-user tool
throttle + argument ceiling) is in place. Re-review required on any version bump off
`0.7.2`.
