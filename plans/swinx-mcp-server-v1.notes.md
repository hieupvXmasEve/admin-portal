# Swinx MCP Server v1 — spike notes (Issues 01–03)

Running log of findings from the OAuth + MCP skeleton spike and the two parallel
prefactors. Companion to `plans/swinx-mcp-server-v1.md` (the blueprint) and `PRD.md`.

## Installed versions (pinned candidates)

- `laravel/mcp` **v0.7.0** (promoted from transitive to a direct `require` — `composer.json`: `^0.7`).
- `laravel/passport` **v13.7** (`^13.7`).

> ⚠️ API surprise: v0.7.0 is the **new** Laravel MCP surface and differs substantially
> from older 0.5/0.6 docs and the blueprint's assumptions:
> - Facade is `Laravel\Mcp\Facades\Mcp` (not `…\Server\Facades\Mcp`).
> - `routes/ai.php` is loaded with **no route prefix** (`Route::group([], …)`), so the
>   server path is whatever you pass: we register `Mcp::web('mcp/swinx', …)` → `/mcp/swinx`.
> - Tools extend `Laravel\Mcp\Server\Tool` (which extends `Primitive`), define input via
>   `schema(JsonSchema $schema): array`, return `Laravel\Mcp\Response` (e.g. `Response::text()`),
>   and `handle(Request $request)` uses **method injection**. The canonical name is pinned via
>   the `$name` property (or `#[Name]`); read-only via `#[IsReadOnly]`.
> - `Mcp::oauthRoutes()` advertises a single OAuth scope `mcp:use` and registers
>   `.well-known/oauth-protected-resource` + `.well-known/oauth-authorization-server`
>   (plus nested variants) and `POST oauth/register` (DCR via `OAuthRegisterController`).

## OAuth discovery metadata (verified via feature test)

`GET /.well-known/oauth-authorization-server` returns:
- `authorization_endpoint` → `route('passport.authorizations.authorize')` (`/oauth/authorize`)
- `token_endpoint` → `route('passport.token')` (`/oauth/token`)
- `registration_endpoint` → `/oauth/register`
- `response_types_supported: ['code']`, `code_challenge_methods_supported: ['S256']`
- `grant_types_supported: ['authorization_code', 'refresh_token']`
- `scopes_supported: ['mcp:use']`

`GET /.well-known/oauth-protected-resource` returns `resource` + `authorization_servers`.

`POST /oauth/register` (DCR, unauthenticated, CSRF-exempt) returns a `client_id`.

## Consent screen

Branded Swinx consent at `resources/views/mcp/authorize.blade.php`, registered via
`Passport::authorizationView('mcp.authorize')`. Shows: client name (with a **Third-party
application** label), redirect URI, signed-in user, and the access being granted. Approve
posts to `passport.authorizations.approve`; cancel to `passport.authorizations.deny`.

## Token lifetime

Short-lived access tokens to shrink the orphaned-token window (PRD / ADR-0010):
`tokensExpireIn(1h)`, `refreshTokensExpireIn(30d)`, `personalAccessTokensExpireIn(1h)`.
The **active-status gate** (Step 2) is the primary offboarding control; there is no
token-revocation hook in v1.

## CSRF carve-out

`oauth/register` and `.well-known/*` are added to `validateCsrfTokens(except: …)` in
`bootstrap/app.php` so unauthenticated external clients are not 419'd. The `/mcp/swinx`
endpoint is registered outside the `web` group, so it is not CSRF-protected; it is gated
solely by the `api` (Passport) guard.

## Spike gate status

| Gate | Status |
|---|---|
| `laravel/mcp` + `laravel/passport` are direct deps | ✅ done (`composer.json`) |
| `api` guard resolves; `web` + `student` still resolve | ✅ guard regression test green |
| Discovery advertises authorize/token/registration; DCR returns client_id (not 419) | ✅ feature test green |
| Branded consent screen (third-party label, redirect URI, access) | ✅ view present + registered |
| Authenticated actor can call read-only `ping` over the server | ✅ in-process feature test green |
| Unauthenticated MCP request refused | ✅ feature test green (401) |
| Frozen actor/campus + result→audit contracts committed as stubs | ✅ `app/Mcp/Support/*` |
| Existing AI feature suite stays green | ✅ verified |
| **Live external-client OAuth round-trip (`mcp:inspector` / Claude Desktop)** | ⏳ **HUMAN GATE** |

### Why the live round-trip is not auto-completed here

`php artisan mcp:inspector swinx` launches the Node **MCP Inspector UI in a browser** (via
`Symfony\Process` → `npx @modelcontextprotocol/inspector`) and requires a human to click
through the OAuth consent. It is not a headless pass/fail CLI. The transport has been
de-risked in-process (the feature test above drives guard → discovery → DCR → consent →
authenticated tool call), but the end-to-end external-client OAuth round-trip must still be
confirmed by a human with the inspector or a real Claude Desktop connector before production
exposure. Per the PRD this is the hard pass/fail; treat it as the remaining acceptance step.

## Frozen contracts committed (consumed by Issues 02/04)

- `app/Mcp/Support/McpActorResolver` — OAuth user → active-status-gated Swinx actor.
- `app/Mcp/Support/McpCampusResolver` — explicit `campus_id` → `CampusScopeSnapshot`
  (Clarification-first; returns the multi-campus-capable snapshot).
- `app/Mcp/Support/McpResultAuditMap` — documented result-accessor → audit-column map.
- `app/Modules/AI/Support/CampusScopeSnapshot` — multi-campus-capable scope snapshot
  (serializes backward-compatibly to `['campus_ids' => int[]]`).
- `clarification_required` registered as a safe error code (a clarification, not a deny)
  across the three tool catalogs.

## Security review (Issues 01–03) — dispositions

- **Route path** — verified `POST /mcp/swinx` (NOT `/mcp/mcp/swinx`): v0.7's
  `McpServiceProvider::loadAiRoutes()` uses `Route::group([], …)` with no prefix, so the
  handle passed to `Mcp::web('mcp/swinx', …)` is the full path. Confirmed via `route:list`
  and the unauthenticated-401 feature test. (A reviewer reading the *stale host* vendor
  copy — which had `Route::prefix('mcp')` — would wrongly expect a double prefix.)
- **Migration `down()`** — fixed: deletes null-FK (MCP) rows before re-tightening the
  conversation/trace columns to NOT NULL, so rollback can't hit a CONSTRAINT error.
- **Known gap — public OAuth/DCR endpoints are unthrottled.** `POST /oauth/register`,
  `/oauth/*`, and `/mcp/swinx` have **no rate limiter yet**. This is **deferred to Step 5
  (hardening)** by design — origin/Host validation, per-user tool throttle (~60/min),
  per-IP OAuth (~10/min) + DCR (~5/hour), and the `laravel/mcp` version pin all land
  together there. These endpoints must NOT be production-exposed until Step 5 ships (this
  is already gated behind the live OAuth-round-trip human gate above).

## Ops gotcha — Passport key file permissions

`passport:install` generated `storage/oauth-private.key` / `oauth-public.key` as `775`
(the container's umask added exec bits). `league/oauth2-server` rejects keys that are not
`600`/`660`, so token *validation* fails at the `/mcp/swinx` call with a 500
("Key file … permissions are not correct …") even though consent + code→token succeeded.

Fix: `chmod 600 storage/oauth-*.key` (do it inside the container:
`./scripts/dev.sh shell` → `chmod 600 storage/oauth-*.key`). Re-apply after any
`passport:install`/key regeneration. For production, prefer loading the keys from env
(`Passport::loadKeysFrom` / `PASSPORT_PRIVATE_KEY`) to avoid the file-permission check
entirely. The keys are gitignored (secrets) and must not be committed.

## ChatGPT go/no-go

Deferred to Step 6 (not in scope for Issues 01–03).
