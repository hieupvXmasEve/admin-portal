# PRD: Swinx Controlled MCP Server (v1)

Status: ready-for-agent

> Source: `/to-prd` synthesis from a `/grilling` session over `plans/swinx-mcp-server-v1.md`, the Swinx AI module, ADR-0006/0008/0009/0010/0011, and the `tests/Feature/AI/` suite. This PRD is a standalone artifact; it does **not** replace or edit `plans/swinx-mcp-server-v1.md` (the step-by-step blueprint). Use the glossary vocabulary in `CONTEXT.md`: **Controlled MCP server**, **MCP client authorization**, **All-campus AI scope**, **Query-only AI capability**, **Clarification-first AI behavior**, **Campus scoping**, plus the existing **Staff Copilot**, **Allowed AI data**, **Connected AI provider**, **Bounded metric context**.

## Problem Statement

From a staff user's perspective: the in-house Staff Copilot chat UI is the only
way to ask Swinx natural-language questions, and it is tied to a synthesis stack
that the team no longer wants to maintain. Staff increasingly already work inside
external agent clients (Claude first, ChatGPT later) and want to reach Swinx data
from there — "who are the at-risk students at my campus", "what's this student's
fee balance" — without installing anything, configuring API keys, or copying data
between tools.

From an operator/reviewer perspective: opening Swinx data to an external agent
must not widen anyone's access. A connected client must see exactly what the
signed-in staff member can already see — no more campuses, no more permissions —
and every tool call must leave durable, redacted evidence of who asked, on which
campus, under which permissions, and what came back. The endpoint is reachable
from the public internet, so authentication, per-user impersonation, campus
validation, read-only guarantees, and abuse controls have to be enforced
server-side, not trusted to the client.

The problem this PRD solves: expose Swinx's three existing **Query-only AI
capability** tools as a **Controlled MCP server** that authorized staff reach
from external agent clients with zero install, under per-user OAuth identity,
campus-by-permission scoping, and full audit — while keeping the existing chat UI
running in parallel during the transition.

## Solution

From the staff user's perspective:

- I open my external agent client (Claude), add the Swinx connector by URL, and
  log in with my normal Swinx account through a Swinx-branded consent screen.
- After I approve, the client can call three read-only Swinx tools on my behalf:
  ask for metrics, search for entities, and pull an entity profile.
- The client only ever sees data I am already allowed to see. If I ask about a
  campus I have no role in, I get a clear refusal, not data. If my question is
  ambiguous about which campus I mean, the tool asks me to clarify rather than
  guessing or refusing.
- I never have to install software, paste an API key, or move data by hand.
- If I leave the organization or my account is locked, the connector stops
  working — even if the client still holds a token.

From the operator/reviewer perspective:

- Every tool call writes exactly one audit record tagged as the MCP channel, with
  the acting staff member, the OAuth client id, the campus scope applied, the
  permission result, source references, and a record count — using the same
  redacted-evidence contract as the chat channel.
- The data an external model can ever receive is bounded by the existing tool
  catalogs (a fixed field allowlist), so no separate egress redactor is needed,
  and an architecture test fails if a sensitive field is ever added to that
  surface.
- The public endpoint is OAuth-gated, origin/host-validated, and rate-limited
  per user; the MCP package is pinned to a reviewed version; the tools are
  provably read-only.
- The in-house chat UI keeps working in parallel so staff without an external
  client are not stranded during the transition.

## User Stories

1. As a staff member, I want to add the Swinx connector to my external agent client using only a URL, so that I can use Swinx data with zero installation or configuration.
2. As a staff member, I want to authenticate to the connector with my existing Swinx account, so that I do not manage a second credential.
3. As a staff member, I want a Swinx-branded consent screen that names the requesting client, shows its redirect URI, labels it as a third-party application, and lists the access being granted, so that I can recognize and refuse an impostor client.
4. As a staff member, I want the connector to expose exactly three read-only tools (query metrics, search entities, get entity profile), so that I have a predictable, query-only capability and no risk of mutating data.
5. As a staff member, I want each tool to appear under its canonical name (`query_metrics`, `search_entities`, `get_entity_profile`), so that prompts and client behavior are stable and recognizable.
6. As a staff member, I want the connected client to see only the data I am already permitted to see, so that connecting a client never widens my access.
7. As a staff member, I want to pass a campus as an argument when I have more than one, so that I can target the campus I mean.
8. As a staff member with access to a single campus, I want the tool to assume that campus when I omit it, so that I do not have to specify it every time.
9. As a staff member with access to multiple campuses, I want the tool to ask me which campus I mean when I omit it, so that I get a clarification instead of a wrong guess or a hard refusal.
10. As a staff member holding **All-campus AI scope**, I want to omit the campus argument and span all campuses, so that I can ask organization-wide questions.
11. As a staff member, I want a clear, safe refusal when I target a campus I have no role in, so that I understand the boundary instead of receiving partial or confusing data.
12. As a staff member, I want a profile request to return only the sections I am permitted to see and to tell me which sections were hidden, so that I understand what was withheld and why.
13. As a staff member, I want tool results returned as structured data with evidence (source, scope, freshness, permission result, hidden sections), so that my agent client can reason about and cite the answer.
14. As a former staff member whose account is locked, I want the connector to stop returning data immediately, so that a token left in a client cannot leak data after I leave.
15. As a staff member whose account is suspended/banned/pending/inactive, I want every tool call to be refused, so that only fully active accounts can use the connector.
16. As an operator, I want every MCP tool call to write exactly one audit record tagged with the MCP channel, the acting user, and the OAuth client id, so that I can attribute and review every access.
17. As an operator, I want MCP audit records to carry the same redacted technical evidence as chat-channel records (permission result, record count, source references, campus scope, hidden sections, safe error code), so that review is uniform across channels.
18. As an operator, I want MCP audit records stored as standalone rows with no conversation or run linkage, so that the audit model fits a channel that has no chat run.
19. As an operator, I want existing chat-channel audit rows to keep their channel label and behavior, so that the new channel does not disturb historical audit data.
20. As a security reviewer, I want the public endpoint to require OAuth, so that there is no anonymous access to Swinx data.
21. As a security reviewer, I want host/origin validation on the MCP and OAuth routes, so that DNS-rebinding and cross-origin abuse are blocked.
22. As a security reviewer, I want per-user rate limiting on tool calls and tighter limits on the OAuth and dynamic-client-registration endpoints, so that a runaway agent or a registration-spam attacker is throttled.
23. As a security reviewer, I want the exact MCP package version pinned and its OAuth/discovery code path reviewed before production exposure, so that a pre-1.0 dependency cannot silently change or ship an unreviewed surface.
24. As a security reviewer, I want an architecture test proving every MCP tool is read-only and reaches data only through the dispatcher, so that a future change cannot introduce a write path or bypass the enforcement boundary.
25. As a security reviewer, I want an architecture test asserting the MCP-exposed field surface stays within an approved allowlist, so that adding a sensitive field (national id, address, phone) forces an explicit review.
26. As a data owner, I want the data reachable over MCP bounded to the existing tool catalogs (student code, names, academic summary, enrollments, attendance, fee balance, lifecycle decisions), so that contact PII is never exposed by these tools.
27. As a staff member who does not use an external agent client, I want the existing chat UI to keep working during the transition, so that I am not stranded without AI access.
28. As a product owner, I want the synthesis stack and chat UI frozen but running (not deleted, not edited), so that we can revisit deprecation only after MCP adoption is real.
29. As a platform engineer, I want the new OAuth (`api`) guard to coexist with the existing session and student guards, so that existing SPA/API authentication keeps working unchanged.
30. As a platform engineer, I want the existing AI feature tests to stay green, so that the MCP work does not regress chat-channel behavior.
31. As a platform engineer, I want web-chat campus resolution to keep working when no campus is supplied in its existing way, so that moving campus to an explicit argument does not break the chat path.
32. As a platform engineer, I want a documented go/no-go for ChatGPT as a v1 client, so that we know the real connector gap without blocking the shipped Claude path.
33. As an agent client, I want failed permission or campus checks mapped to safe, stable error codes, so that I can react predictably without leaking internal detail.
34. As an agent client, I want an entity reference to remain permission- and campus-gated on resolution, so that holding a reference is never a way around access control.

## Implementation Decisions

### Identity, authorization, and access model

- **Per-user OAuth impersonation (ADR-0010).** Authentication uses Laravel Passport via a new `api` guard (`driver: passport`, `provider: users`) added alongside the existing session (`web`) and student (`sanctum`) guards; the default guard stays `web` and the student guard is untouched. The connected client inherits the staff member's **full** permission set — no separate MCP permission scope is invented.
- **Public + OAuth as the only gate (ADR-0011).** The endpoint is reachable from the public internet to satisfy zero-install; OAuth login + consent is the sole access gate. No VPN/proxy/IP-allowlist sits in front, because that would require staff to install or configure something. "Public" means *reachable*, not *anonymous*.
- **Dynamic Client Registration stays open.** The DCR endpoint is unauthenticated so external clients (Claude, ChatGPT) can self-register, preserving zero-install. The anti-phishing control is the **consent screen**, not closed registration.
- **Consent screen.** Minimal Swinx branding (logo + name) that displays the requesting client name (clearly marked as a third-party application), the redirect URI, and the access being granted. Not elaborate; just enough to let a staff member recognize and refuse an impostor.
- **Active-status gate (decision from grilling).** `PermissionService` does **not** check account status, and there is no custom user provider blocking inactive users, so a live token would otherwise keep working after offboarding. The MCP actor resolver therefore **must** verify the resolved user's status is exactly the active status (an allowlist of one — every other status: suspended, banned, locked, pending, inactive, etc. is refused), read directly off the user record (not via the cached permission list), so revocation is immediate. This is the primary offboarding control; orphaned tokens are rendered harmless by it rather than by token revocation machinery.
- **Token lifetime.** Access tokens are short-lived to shrink the orphaned-token window; long-lived sessions rely on refresh. There is no offboarding hook that revokes tokens in v1 — the active-status gate covers it.

### Campus scoping

- **Permitted-campus set = the campuses where the user holds a role** (the existing user↔campus↔role association used by `PermissionService`). A `campus_id` argument must be within this set or the call is refused with the existing campus-scope safe error.
- **All-campus AI scope = a new dedicated permission** (e.g. `view_ai_all_campus`), mirroring the existing finance all-campus permission precedent but AI-wide. It must be seeded and assigned to HQ/BOD roles. The existing finance all-campus permission is **not** reused for AI (wrong domain). Per-domain data remains gated by existing domain permissions; the new permission only governs campus span.
- **Campus argument resolution (Clarification-first):** explicit `campus_id` → validate ∈ permitted set; omitted with exactly one permitted campus → use it; omitted with multiple permitted campuses and not an all-campus holder → return a `clarification_required` safe result (a clarification, not a deny); all-campus holder omitting it → span. The campus-scope snapshot shape becomes multi-campus capable so the audit recorder can record a span.

### Tool mapping and enforcement boundary

- **`ToolDispatcher::dispatch()` is the only data path (ADR-0008).** The three MCP tools are thin wrappers that resolve the actor from the OAuth guard, resolve campus via the campus resolver, call the dispatcher, write audit, and map the result — they never touch models or queries directly.
- **Three tools, canonical names.** `query_metrics`, `search_entities`, `get_entity_profile`. Because the MCP base auto-derives tool names from the class name, each wrapper overrides its name to the canonical value. Each tool input schema mirrors the existing catalog tool input.
- **Per-tool gating is finer than the registry default.** `query_metrics` gates on the metric's required permission plus an optional domain permission; `search_entities` gates per entity type (e.g. student-view permission), not a flat metrics permission; `get_entity_profile` gates on the AI-metrics permission plus per-section permissions, returning partial sections with a hidden-sections list.
- **Structured output + evidence.** Results are returned as structured JSON text (value + evidence: source, scope, freshness, permission result, hidden sections). The MCP result type offers only text/error/items — there is no JSON helper — so structured output is JSON-encoded text. A failed permission result or safe error code maps to an MCP error result carrying the safe code.
- **Entity references stay gated.** An entity reference is bound to campus + expiry + catalog version (not to the issuing user); profile resolution re-checks the student-view permission and campus, so a reference is still permission-gated and works without a session. No per-user binding is claimed.

### Audit

- **Channel-aware audit (ADR-0006).** The audit table makes its conversation and agent-trace foreign keys nullable and adds: a `channel` column (default the chat value), an actor user foreign key (nullable), and an OAuth client id (nullable string).
- **A new MCP audit recorder** writes a standalone row per MCP call — channel = the MCP value, null conversation/trace links, actor user id, OAuth client id — by reading the result object's existing accessors (permission result, record count, source references, safe error code, status, campus-scope snapshot, hidden sections). It reuses the existing redactor for audit fields only; there is **no egress redaction** of tool output (ADR-0011).
- **Why a new recorder:** the existing recorder requires a non-null trace and the inner tools' audit hook early-returns when the trace is null (always true over MCP), so reusing it would produce zero rows. The inner tools' audit hook is left dormant by design; the MCP wrapper is the sole MCP audit source.
- **No run concept** for MCP — each call is one row. No backfill; existing rows keep the chat channel.

### Data egress boundary

- **The tool catalogs are the field allowlist (decision from grilling).** The profile tool exposes only academic-identity, academic-summary, enrollment, attendance-summary, finance-summary, and lifecycle-action fields — no national id, address, phone, email, date of birth, health, or family data. Because the surface is already curated, no separate egress redactor is added and no DPA is treated as a blocker for this dataset. An architecture test locks the allowlist so any future addition of a sensitive field fails the build and forces review.

### Hardening

- **Origin/host validation** middleware attached at MCP route registration (not globally), alongside the OAuth guard and the throttle.
- **Rate limits (decision from grilling):** tool calls limited **per user** (~60/min) — the abuse boundary is a person, and per-client could be evaded by registering many clients; the OAuth endpoints limited per IP (~10/min); the DCR endpoint limited per IP (~5/hour). Tool-argument length limits applied.
- **Version pin + review.** The MCP package is pinned to the exact reviewed version, and its OAuth/discovery code path is read and reviewed before production exposure — this review (not editing the package README) is the price of choosing a public endpoint on a pre-1.0 package.
- **Read-only guarantee** enforced by annotation on every tool plus an architecture test.

### Chat UI transition

- **Keep the chat UI live in parallel** for v1 (decision from grilling): the route is **not** neutralized; the synthesis stack keeps running (still not edited). Deprecation is revisited only after MCP adoption is real. This refines the freeze decision: frozen = not developed/edited, but still running and reachable.

### Client list

- **Claude is the committed v1 client.** ChatGPT is validated as a spike that records a documented go/no-go (consent screen, supported scopes, resource indicators, token audience/expiry) without blocking the shipped Claude path.

## Testing Decisions

A good test here asserts **externally observable behavior** — what an MCP client
receives and what evidence is persisted — not internal wiring. Tests must not
assert on private method calls, class shapes, or intermediate objects; they assert
on tool-list contents, tool-call results, safe error codes, persisted audit rows,
and HTTP-level rejections.

**Primary seam — the `/mcp/<server>` HTTP boundary with a real Passport token.**
One feature-test surface drives the entire chain through a single seam: OAuth
guard → tool wrapper → actor resolver (active-status gate) → campus resolver
(campus / all-campus / clarification) → dispatcher → audit recorder. Tests assert:

- the tool list returns the three tools under their canonical names;
- a permitted-campus call returns structured JSON with evidence;
- an out-of-scope campus returns the campus-scope safe error;
- an omitted campus yields: single-campus auto-resolve, multi-campus
  clarification, and all-campus span (for an all-campus holder);
- per-tool permission gating (metric + domain permission; per-entity-type;
  per-section with a hidden-sections list);
- exactly one MCP-channel audit row per call, carrying actor, client id, and the
  evidence fields, with safe-error mapping;
- a non-active account is refused even with a valid token;
- bad origin/host is rejected and exceeding the rate limit returns 429.

Modules tested through this seam: the MCP server and its three tools, the actor
and campus resolvers, the MCP audit recorder, and the dispatcher integration.

**Invariant seam — an architecture test (no HTTP).** Asserts every MCP tool class
is annotated read-only and references only the dispatcher/resolvers/recorder (no
direct model or query use), and that the MCP-exposed field surface stays within
the approved allowlist.

Resolvers and the recorder are intentionally **not** unit-tested in isolation —
they are covered through the HTTP seam. A dedicated unit test is added only for a
branch that cannot be reached cleanly over HTTP.

**Prior art.** Mirror the existing AI feature tests — the query-metrics tool test,
the student-profile sections test, and the audit foundation test — and place MCP
tests under their own feature directory. Note that CSRF is active in feature tests
and OAuth-guarded routes require a Passport token rather than a session.

**Regression guardrails.** The existing AI feature suite stays green; web-chat
campus resolution still works when no campus is supplied in its existing way; the
session and student guards still resolve.

## Out of Scope

- Write/mutating MCP tools (require a separate ADR).
- Deleting or editing the frozen synthesis stack, chat UI, or `laravel/ai`.
- An egress-side redaction layer (deliberately omitted — the tool catalog is the
  allowlist).
- MCP resources and prompts (v1 exposes tools only).
- A BOD-specific MCP mode beyond per-user permission inheritance.
- A token-revocation/offboarding hook (the active-status gate covers offboarding
  in v1).
- Immediate deprecation/neutralization of the chat UI (kept running in parallel).
- A pre-built fallback for the MCP package: the OAuth round-trip spike is
  pass/fail; if it cannot be achieved, work stops and the dependency is
  re-evaluated rather than routed around.

## Further Notes

- **Spike gate.** The whole effort hinges on completing an OAuth round-trip with a
  real MCP client against the pinned package; this is verified first and is a hard
  pass/fail with no plan B in v1.
- **Residual risk — permission cache staleness.** The permission lookup is cached
  for a day, so a mid-employment *permission* downgrade (distinct from account
  locking, which the active-status gate catches immediately) can lag up to ~24h
  over MCP, during which downgraded access could still egress to a third party.
  This matches existing chat behavior. Recommended default: clear the user
  permission cache on role change (or shorten the TTL). To be decided.
- **Residual ops item — public route deploy.** A new publicly reachable route may
  need production entrypoint/TLS/route-cache verification (deploy-script drift).
  Verified at the hardening/deploy stage.
- **Legal framing.** Grades and fee data are treated by the product owner as
  non-blocking for this dataset; note that under local data-protection
  regulation, academic and financial records are commonly classed as protected
  personal data — the decision to proceed without a DPA for this surface is an
  explicit, owner-made product/legal call recorded here for traceability.
- **Relationship to the blueprint.** The execution steps, dependency graph, and
  file-level detail live in `plans/swinx-mcp-server-v1.md`; this PRD captures the
  product intent and the decisions refined during grilling. Where they differ
  (chat UI kept live, active-status gate, `view_ai_all_campus`, allowlist test,
  rate-limit numbers, consent content), this PRD reflects the newer decisions.
