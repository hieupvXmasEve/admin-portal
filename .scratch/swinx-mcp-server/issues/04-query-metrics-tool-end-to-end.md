# 04 — First tool end-to-end: `query_metrics` over MCP

Status: ready-for-agent

## Parent

[PRD: Swinx Controlled MCP Server (v1)](../PRD.md)

## What to build

The architecture-proving tracer bullet: one real tool, callable by an
OAuth-authenticated MCP client, enforcing per-user identity and campus, writing
audit, and returning structured evidence — end to end. Later tool slices reuse
this harness.

Build the shared MCP identity layer once, here:

- **Actor resolver** — resolve the Swinx user from the OAuth guard, and **refuse
  unless the account status is exactly active** (allowlist of one — suspended,
  banned, locked, pending, inactive, etc. are all refused), read directly off the
  user record so revocation is immediate even while a token is still valid.
- **Campus resolver** — accept an explicit `campus_id` and validate it is within
  the user's permitted campuses (the campuses where they hold a role). Decision
  rules:
  - explicit `campus_id` → validate ∈ permitted set, else the campus-scope safe error;
  - omitted + exactly one permitted campus → use it;
  - omitted + multiple permitted campuses + not an all-campus holder → return `clarification_required` (a clarification, not a deny);
  - all-campus holder → may omit and span all campuses.
- **All-campus permission** — seed a new dedicated AI-wide permission (e.g.
  `view_ai_all_campus`) and assign it to HQ/BOD roles. Do not reuse the finance
  all-campus permission.

Then wire the `query_metrics` MCP tool: canonical name `query_metrics`,
read-only, schema mirroring the existing metrics tool input; resolve actor →
resolve campus → dispatch through `ToolDispatcher` (the only data path) → write
audit via the MCP recorder → return. Map the result to structured JSON text
(value + evidence: source, scope, freshness, permission result, hidden sections);
map a failed permission/safe-error to an MCP error result carrying the safe code.

## Acceptance criteria

- [ ] An OAuth-authenticated MCP client can call `query_metrics` and receive structured JSON with evidence.
- [ ] Metric gating enforces the metric's required permission plus any domain permission.
- [ ] A campus in scope succeeds; a campus out of scope returns the campus-scope safe error.
- [ ] Omitted campus: single auto-resolves; multiple → `clarification_required`; all-campus holder spans.
- [ ] A non-active account is refused even with a valid token.
- [ ] Exactly one MCP-channel audit row per call, carrying actor and client id and the evidence fields.
- [ ] The tool reaches data only through `ToolDispatcher` (no direct model/query use).
- [ ] The MCP inspector lists `query_metrics` under its canonical name and executes it.

## Blocked by

- Issue 01 (OAuth + MCP skeleton)
- Issue 02 (campus as explicit argument)
- Issue 03 (audit schema + recorder)
