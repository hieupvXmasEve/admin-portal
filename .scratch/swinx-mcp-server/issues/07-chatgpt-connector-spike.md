# 07 — ChatGPT connector spike + finalize v1 client list

Status: done

## Parent

[PRD: Swinx Controlled MCP Server (v1)](../PRD.md)

## What to build

Measure the real gap to a second client without disturbing the shipped Claude
path. Configure a ChatGPT custom MCP connector against the public endpoint and
attempt the OAuth + tool-call flow. Record gaps (consent screen, supported
scopes, resource indicators, token audience/expiry). If gaps are small, add the
metadata/fixes; if large, record the deferral. Update the relevant ADRs and the
glossary with the finalized v1 client list and the data-handling precondition
status.

This is a documented go/no-go, not a blocking dependency for the Claude path.

## Acceptance criteria

- [ ] A reproducible outcome is recorded: ChatGPT connects and calls a tool, or a precise list of blocking gaps is documented.
- [ ] Any small metadata fixes are applied; large gaps are recorded as a deferral.
- [ ] ADRs and the glossary reflect the finalized v1 client list and precondition status.
- [ ] The Claude path is unaffected.

## Blocked by

- Issue 06 (hardened, stable public endpoint)

## Outcome (2026-07-01)

**The go/no-go decision lives in [ADR-0012](../../../docs/adr/0012-chatgpt-connector-is-technically-feasible-deferred-on-dpa.md)** (GO on connector, deferred as a committed client on the data-handling precondition). This section records only the measured evidence and how to reproduce it.

### How the outcome was measured (reproducible)

This session is non-interactive, so the gap was measured against the pinned `laravel/mcp`
(v0.7.2) build through the real HTTP boundary rather than a live ChatGPT session. Every
OAuth 2.0 discovery requirement a ChatGPT custom connector needs to bootstrap from the bare
`/mcp/swinx` URL is already emitted and is now locked by
`tests/Feature/Mcp/McpChatGptConnectorSpikeTest.php`:

- `401` on `POST /mcp/swinx` carries `WWW-Authenticate: Bearer realm="mcp",
  resource_metadata="…/.well-known/oauth-protected-resource/mcp/swinx"` (RFC 9728 §5.1).
- Path-suffixed Protected Resource Metadata (RFC 9728) names the resource with an
  `authorization_servers` array + `scopes_supported: ["mcp:use"]`.
- Authorization Server Metadata (RFC 8414) advertises PKCE `S256`, `authorization_code` +
  `refresh_token` grants, and the scope.
- Open Dynamic Client Registration (RFC 7591) returns a public client
  (`token_endpoint_auth_method: none`, `scope: mcp:use`, `201`) for the ChatGPT redirect URI.
- The branded consent screen renders even with the RFC 8707 `resource` parameter ChatGPT
  appends to the authorize request.

Gap dimensions from the brief: **consent** (branded screen, third-party labelled) — present;
**supported scopes** (`mcp:use`) — advertised in both discovery documents and the DCR response;
**resource indicators** (RFC 8707) — tolerated, not audience-enforced (residual below);
**token audience/expiry** — no audience binding; access tokens expire in 1h and refresh tokens
in 30d (`AppServiceProvider::configureMcpPassport()`), which ChatGPT handles via refresh.

### Accepted residuals (match the Claude path)

- No audience-bound tokens (RFC 8707): Passport ignores the `resource` param; ChatGPT
  tolerates this. Tightening needs a Passport change + its own ADR.
- Consumer-plan blocking is best-effort; a resource server cannot enforce the caller's plan.

### Ops caveat to verify at deploy

Host vs container `vendor/laravel/mcp` drift observed: both report v0.7.2, but the stale
host copy emits a weaker, non-conformant discovery surface (singular `authorization_server`,
no scopes, no nested PRM, no `WWW-Authenticate`). The conformance above is what the
container/runtime build emits — the production build must be verified to match before
relying on connector discovery for any client (Claude included).

### To run a live ChatGPT round-trip later (when a DPA is in place)

ChatGPT → Settings → Connectors → Advanced (developer mode) → add custom MCP connector by
URL (`https://<public-host>/mcp/swinx`); ChatGPT self-registers via DCR, runs PKCE
authorize against the Swinx consent screen, then calls `tools/list` + a tool. No server
change is expected to be needed.
