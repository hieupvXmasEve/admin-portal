# 06 — Hardening + public-exposure safety

Status: ready-for-human

## Parent

[PRD: Swinx Controlled MCP Server (v1)](../PRD.md)

## What to build

Make the public endpoint safe to expose, and lock the invariants with tests.

- **Origin/Host validation** middleware attached at the MCP route registration
  (not globally), alongside the OAuth guard and throttle.
- **Rate limits:** tool calls limited **per user** (~60/min — the abuse boundary
  is a person, and per-client could be evaded by registering many clients); the
  OAuth endpoints limited per IP (~10/min); the DCR endpoint limited per IP
  (~5/hour). Apply tool-argument length limits.
- **Version pin + review:** pin the MCP package to the exact reviewed version,
  and read/review its OAuth + discovery code path before production exposure
  (this review — not editing the package README — is the price of a public
  endpoint on a pre-1.0 dependency).
- **Read-only architecture test:** assert every MCP tool class is annotated
  read-only and references only the dispatcher/resolvers/recorder (no direct
  model or query use).
- **Field-allowlist architecture test:** assert the MCP-exposed field surface
  stays within an approved allowlist, so adding a sensitive field (national id,
  address, phone, etc.) fails the build and forces review.
- **Chat UI left live:** confirm the in-house chat UI route and synthesis stack
  remain running (explicitly **not** neutralized) so staff without an external
  client are not stranded; the frozen stack is not edited.

## Acceptance criteria

- [ ] A bad Origin/Host is rejected; exceeding the per-user tool-call limit returns 429; OAuth and DCR endpoints are throttled.
- [ ] The MCP package version is pinned and its OAuth/discovery path has been reviewed.
- [ ] The read-only architecture test passes and would fail on a write path or a non-dispatcher data access.
- [ ] The field-allowlist architecture test passes and would fail if a sensitive field were added to the MCP surface.
- [ ] The chat UI route and synthesis stack are confirmed live and unedited.

## Blocked by

- Issue 05 (all three tools present for the read-only and allowlist architecture tests)
