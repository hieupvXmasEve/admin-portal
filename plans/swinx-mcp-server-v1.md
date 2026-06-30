# Blueprint — Swinx Controlled MCP Server v1

**Objective:** Expose Swinx's three existing read-only AI tools (`query_metrics`, `search_entities`, `get_entity_profile`) as a public, OAuth-protected MCP server that authorized staff reach from external agent clients (Claude first, ChatGPT after spike), with per-user impersonation, campus-by-permission, full audit, and hardening — and freeze (not delete) the in-house Staff Copilot chat UI.

**Owner:** Platform Team · **Created:** 2026-06-30 · **Status:** Draft (ready to execute)

---

## Decision basis (locked)

| ADR / term | What it fixes for this plan |
|---|---|
| [ADR-0008](../docs/adr/0008-swinx-tooldispatcher-is-ai-execution-boundary.md) | `ToolDispatcher` stays the enforcement boundary; MCP only maps its tools outward. |
| [ADR-0009](../docs/adr/0009-mcp-server-replaces-staff-copilot-chat-ui.md) | MCP replaces chat UI; synthesis stack (planner/final-answer/`laravel/ai`/`AiProviderSetting`) **frozen, not deleted**. Claude = priority v1 client. Pin + wrap `laravel/mcp`. |
| [ADR-0010](../docs/adr/0010-mcp-access-is-per-user-oauth-impersonation.md) | Per-user OAuth (Laravel **Passport**, approved) coexisting with Sanctum via `api` guard. Client inherits user's **full** permissions. Campus = validated tool argument, all-campus holders span. |
| [ADR-0011](../docs/adr/0011-mcp-server-is-public-and-accepts-redacted-data-egress.md) | Public OAuth HTTPS endpoint; egress accepted; **no egress-redactor** added; hard read-only; Host/Origin + rate-limit; DPA = production precondition. |
| [ADR-0006](../docs/adr/0006-staff-ai-uses-natural-language-ui-with-backend-audit.md) | Persist redacted technical evidence for every tool call. MCP returns structured JSON + evidence (consumer is a model, not the non-technical chat UI). |
| Glossary: `Controlled MCP server`, `MCP client authorization`, `All-campus AI scope`, `Query-only AI capability`, `Clarification-first AI behavior` | Canonical terms — use verbatim in code/test/PR titles. |

## Global invariants (verify after EVERY step)

1. **Sanctum unbroken** — existing SPA/API auth still works; the AI feature tests in `tests/Feature/AI/` stay green.
2. **ToolDispatcher is the only data path** — MCP tools never touch models/queries directly; they call `ToolDispatcher::dispatch()`.
3. **Read-only** — no MCP tool mutates state; all annotated `#[IsReadOnly]`; no write path reachable.
4. **Every MCP tool call writes one audit record** (per [ADR-0006](../docs/adr/0006-staff-ai-uses-natural-language-ui-with-backend-audit.md)) — recorded by the **MCP tool wrapper itself** after `dispatch()` returns, NOT by the inner tools (their `recordAudit()` early-returns when `trace === null`, which is always the case over MCP — verified `QueryMetricsTool.php:76`, `SearchEntitiesTool.php:426`, `GetEntityProfileTool.php:383`, `AiAuditRecorder.php:77`).
5. **Permission + campus are enforced server-side** — a connected client never widens a staff member's access; campus is validated against permitted campuses.
6. **Frozen stack untouched** — do not edit planner/final-answer/`laravel/ai`/`AiProviderSetting`/chat UI except to neutralize routes if a step says so.

## Workflow conventions

- **Runtime:** all PHP/artisan/composer/test commands go through `./scripts/dev.sh` (Docker). Never run host `php artisan`.
- **Branching:** branch from `dev`; PR into `dev`. Remote is **`hieupv`** (not `origin`) → `git push -u hieupv <branch>`.
- **Per-step verify (baseline):** `./scripts/dev.sh composer exec pint -- --dirty --format agent` → `./scripts/dev.sh test --compact` → targeted feature test. Type-check FE only if FE touched (whole-project `type-check` OOMs in the dev container — per-file/eslint instead).
- **Commit style:** conventional commits (`feat(mcp): …`, `fix(mcp): …`).
- **Tests:** Pest feature tests under `tests/Feature/Mcp/`, mirroring `tests/Feature/AI/` patterns (CSRF is active in feature tests; OAuth-guarded routes need a Passport token, not a session).

## Frozen contracts (defined in Step 1, consumed by Steps 2–4)

To keep Step 2 ∥ Step 3 safe and prevent emergent conflicts in Step 4, Step 1 must commit two stable interfaces before parallel work starts:

1. **Result-object → audit-row mapping.** The MCP audit recorder reads these accessors off the dispatcher's result objects (`QueryMetricsResult|EntitySearchResult|EntityProfileResult`): `permissionResult()`, `recordCount()`, `sourceReferences()`, `safeErrorCode()`, `status()`, and the array shape for `campus_scope_snapshot` / `hidden_sections` (via `toArray()` / existing audit-summary accessor used by `AiAuditRecorder`). Step 2's `campus_scope_snapshot` changes and Step 3's recorder both code against this frozen shape.
2. **Actor + campus acquisition.** Actor = `auth('api')->user()` resolved **inside** each MCP tool's `handle()` (the package injects only `array $arguments` — `Tool.php:43`, `CallTool.php:39`). Campus = the validated `campus_id` argument via `McpCampusResolver`. No session, no DI of the user.

## Step 0 — Commit the design docs (prerequisite)

Before any branch cites them, commit this grilling session's output so PR descriptions can reference committed ADRs: `CONTEXT.md` (glossary edits), `docs/adr/0006`–`0008` (currently untracked), the new `docs/adr/0009`–`0011`, and `plans/swinx-mcp-server-v1.md`. One `docs:`/`chore:` commit on `dev`. (No code; pure docs.)

## Dependency graph

```
Step 1 (spike: Passport + Mcp::web skeleton + Claude e2e)   [strongest]
   ├─► Step 2 (identity: OAuth user→actor + campus-as-arg)   [strongest]
   └─► Step 3 (audit schema: nullable FK + channel/actor/client) [default]
            Step 2 + Step 3 ─► Step 4 (map 3 tools via ToolDispatcher) [strongest]
                                   └─► Step 5 (hardening: Host/Origin + rate-limit + pin) [default]
                                          └─► Step 6 (ChatGPT spike + finalize v1 client list) [default]
```

**Parallelizable:** Step 2 ∥ Step 3 (disjoint files). All others serial.

---

## Step 1 — Spike: Passport + `Mcp::web` skeleton + Claude Desktop OAuth e2e

**Model tier:** strongest · **Depends on:** none · **Branch:** `feat/mcp-step1-oauth-spike`

### Context brief
`laravel/ai ^0.7.2` is a direct dep. **`laravel/mcp` is present only transitively** (pulled by `laravel/boost`, lock-only — NOT in `composer.json` `require`); it must be promoted to a direct dependency or a future `composer update` can drop it. **Passport is absent** from lock and json. No `app/Mcp/`, no `routes/ai.php`, no MCP server exists. `config/auth.php:38-47` defines only `web` (session) + `student` (sanctum) guards — no `api` guard exists yet (no collision). `oauthRoutes()` ([vendor/laravel/mcp/src/Server/Registrar.php:59](../vendor/laravel/mcp/src/Server/Registrar.php)) **only advertises** `.well-known` discovery + a `POST /oauth/register` DCR shim that delegates to Passport's `ClientRepository`; the real `/oauth/authorize`, `/oauth/token`, consent, and PKCE enforcement are **Passport's** and must be installed and verified first (the shim fatals until Passport's container bindings exist). Biggest unknown: whether a real remote MCP client completes OAuth end-to-end. This step de-risks that and freezes the two contracts above. Skeleton is kept as the foundation.

### Tasks
1. Promote the package: `./scripts/dev.sh composer require "laravel/mcp:^0.7"` (move from transitive to direct `require`) and `./scripts/dev.sh composer require laravel/passport`; run `passport:install`; migrate.
2. Install + register **Passport's** auth routes (Passport v12+ needs explicit registration); confirm `/oauth/authorize` and `/oauth/token` resolve.
3. Add the `api` guard **exactly** as `'api' => ['driver' => 'passport', 'provider' => 'users']` in `config/auth.php`; leave `auth.defaults.guard = web` and the `student` guard untouched.
4. `./scripts/dev.sh artisan vendor:publish --tag=ai-routes` → `routes/ai.php`.
5. `make:mcp-server SwinxMcpServer`; `make:mcp-tool PingTool` (`#[IsReadOnly]`, returns `ToolResult::text`, **override `name()` → `'ping'`**).
6. Register `Mcp::web('swinx', SwinxMcpServer::class)->middleware('auth:api')` in `routes/ai.php`; add `Mcp::oauthRoutes()`. **Carve `oauth/register` + `.well-known/*` out of CSRF** (they are POST/GET from unauthenticated external clients — under the `web` group `POST /oauth/register` would 419). Add them to the CSRF except list.
7. Add/confirm a minimal Passport authorization/consent screen renders.
8. **Freeze the two contracts** from the "Frozen contracts" section into `app/Mcp/Support/` stubs (`McpActorResolver`, `McpCampusResolver` interfaces) + a documented result→audit field map, so Steps 2/3 code against them.
9. Test with `./scripts/dev.sh artisan mcp:inspector swinx` (automated OAuth round-trip), then optionally connect a **real Claude Desktop** connector and call `ping`.
10. Record findings (consent, scope metadata, token audience, expiry) in `plans/swinx-mcp-server-v1.notes.md`.

### Files
`composer.json`, `config/auth.php`, `config/passport.php`, `bootstrap/app.php` (CSRF except), `routes/ai.php`, `routes/web.php`, `app/Mcp/Servers/SwinxMcpServer.php`, `app/Mcp/Tools/PingTool.php`, `app/Mcp/Support/McpActorResolver.php`, `app/Mcp/Support/McpCampusResolver.php`, `plans/swinx-mcp-server-v1.notes.md`, Passport migrations.

### Verification
- `./scripts/dev.sh artisan test --compact` green (existing Sanctum/AI tests unaffected; `auth('web')` + `auth('student')` still resolve — add a guard regression test).
- `curl` GET `/.well-known/oauth-authorization-server` returns metadata with `authorization_endpoint`/`token_endpoint`/`registration_endpoint`, **and** `POST /oauth/register` returns a `client_id` (not 419).
- **Automated gate (required):** `mcp:inspector` completes the OAuth round-trip and calls `ping`.
- **Human-in-the-loop gate (optional, needs an Anthropic account):** Claude Desktop connects and invokes `ping`.

### Exit criteria
The `mcp:inspector` OAuth round-trip succeeds and calls a read-only stub tool, and `composer.json` now directly requires `laravel/mcp` + `laravel/passport`. **If the OAuth round-trip cannot be achieved, STOP and re-evaluate `laravel/mcp` viability.**

### Rollback
`composer remove laravel/passport`; revert the `laravel/mcp` promotion; revert `config/auth.php` + CSRF except; drop Passport migrations; delete `app/Mcp/` + `routes/ai.php`.

---

## Step 2 — Identity resolution: OAuth user → Swinx actor + campus-as-argument

**Model tier:** strongest · **Depends on:** Step 1 · **Branch:** `feat/mcp-step2-identity-campus`

### Context brief
Every tool today is keyed off a real `User $actor` + `Campus` via `QueryPlanValidator` ([app/Modules/AI/Support/QueryPlanValidator.php](../app/Modules/AI/Support/QueryPlanValidator.php)), which currently reads campus from `app('campus')`/session and **denies** any `campus_id` filter that mismatches the single session campus (line ~68). MCP has no session. Per [ADR-0010](../docs/adr/0010-mcp-access-is-per-user-oauth-impersonation.md) campus becomes an explicit, permission-validated argument; an **All-campus AI scope** holder may span/omit it; ambiguity triggers **Clarification-first** behavior. The authenticated Passport user resolves to a Swinx `User`; `PermissionService::getUserPermissions($actor, $campusId)` is the gate.

### Tasks
1. Build an identity resolver: `auth('api')->user()` → Swinx `User`; expose actor to MCP tool context.
2. Add a campus-resolution helper: accept explicit `campus_id` arg; validate ∈ the user's permitted campuses; if the user holds the all-campus AI permission, allow span/omit; if no single campus resolves and none provided, return a `clarification_required` safe result (not a deny).
3. **Before refactoring:** grep every `QueryPlanValidator::validate(` caller and every `app('campus')` / `app()->bound('campus')` read in `app/Modules/AI` (the validator currently falls back to `app('campus')` at `QueryPlanValidator.php:21` and denies a `campus_id` filter ≠ session campus at lines 66-70). Confirm whether web chat relies on the fallback when `$context->campus` is null.
4. Refactor so campus is supplied explicitly; **add a regression test** asserting web-chat resolution still works with `$context->campus = null` (or prove no caller relies on the fallback before removing it).
5. Keep changes behind existing result/error contracts (reuse `forbidden_by_campus_scope`; add a `clarification_required` safe code for the no-single-campus case — a clarification result, not a deny). Conform to the frozen `campus_scope_snapshot` shape so Step 3's recorder stays valid.

### Files
`app/Modules/AI/Support/QueryPlanValidator.php`, new `app/Mcp/Support/McpActorResolver.php`, new `app/Mcp/Support/McpCampusResolver.php`, possibly `app/Modules/AI/Support/Tools/QueryMetricsExecutionContext.php` (no behavior change), tests under `tests/Feature/Mcp/`.

### Verification
- New Pest tests: permitted campus passes; forbidden campus → `forbidden_by_campus_scope`; all-campus holder spans; missing/ambiguous campus → clarification.
- **Regression:** `./scripts/dev.sh test --compact --filter=Ai` stays green (web chat still resolves session campus).

### Exit criteria
Given a Passport-authenticated user, a tool call resolves the correct actor and a permission-validated campus, with web-chat campus behavior unchanged.

### Rollback
Revert validator to session-only campus; remove resolvers. (No schema changes to undo.)

---

## Step 3 — Audit schema: nullable FK + channel/actor/client on `AiToolCall`

**Model tier:** default · **Depends on:** Step 1 · **Parallel with:** Step 2 · **Branch:** `feat/mcp-step3-audit-schema`

### Context brief
`AiToolCall` ([app/Modules/AI/Models/AiToolCall.php](../app/Modules/AI/Models/AiToolCall.php)) records the [ADR-0006](../docs/adr/0006-staff-ai-uses-natural-language-ui-with-backend-audit.md) evidence but its `ai_conversation_id` / `ai_agent_trace_id` FKs are **NOT NULL today** (confirmed: migration `2026_06_21_135115_create_ai_audit_evaluation_tables.php:70-71` uses `->constrained()->cascadeOnDelete()` with no `->nullable()`). **Critical (C1):** the existing `AiAuditRecorder::recordToolCall(AiAgentTrace $trace, …)` ([AiAuditRecorder.php:77](../app/Modules/AI/Support/AiAuditRecorder.php)) *requires* a non-null trace and the inner tools' `recordAudit()` early-returns when `trace === null` — so over MCP (no trace) **reusing that recorder produces zero audit rows**. MCP audit MUST be written by a new recorder called from the MCP tool wrapper, reading the result object's accessors. There is **no "run"** concept for MCP — each call is a standalone row.

### Tasks
1. Migration: make `ai_conversation_id`, `ai_agent_trace_id` **nullable**; add `channel` (string, default `chat`), `actor_user_id` (FK users, nullable), `mcp_client_id` (string nullable — OAuth client id).
2. Update `AiToolCall` `$fillable`/`casts`; add `actor()` relation + `scopeMcp()`.
3. **Add a new `McpAuditRecorder::record(User $actor, string $mcpClientId, QueryMetricsResult|EntitySearchResult|EntityProfileResult $result, array $redactedArgs, float $startedAtMs): AiToolCall`** that writes a standalone row (`channel='mcp'`, null conversation/trace FKs, `actor_user_id`, `mcp_client_id`) by reading the frozen result accessors (`permissionResult()`, `recordCount()`, `sourceReferences()`, `safeErrorCode()`, `status()`, `campus_scope_snapshot`/`hidden_sections`). Reuse `AiRedactor` for the audit fields only — **no egress redaction** ([ADR-0011](../docs/adr/0011-mcp-server-is-public-and-accepts-redacted-data-egress.md)).
4. Explicitly leave the inner tools' `recordAudit()` dormant (trace stays null by design); the MCP wrapper (Step 4) is the sole MCP audit source.
5. Backfill nothing; existing chat rows keep `channel='chat'`.

### Files
new migration under `database/migrations/`, `app/Modules/AI/Models/AiToolCall.php`, new `app/Mcp/Support/McpAuditRecorder.php` (or edit `app/Modules/AI/Support/AiAuditRecorder.php`), tests under `tests/Feature/Mcp/`.

### Verification
- `./scripts/dev.sh artisan migrate` succeeds; `database-schema` shows nullable FKs + new columns.
- Pest test: recorder writes a row with `channel='mcp'`, actor, client id, and the evidence fields.
- **Regression:** existing `AiAuditFoundationTest` green.

### Exit criteria
An MCP tool call can be persisted as a standalone `AiToolCall` with full evidence + MCP identity, without a conversation/run.

### Rollback
`migrate:rollback` the new migration; revert model + recorder.

---

## Step 4 — Map the 3 tools as MCP tools through `ToolDispatcher`

**Model tier:** strongest · **Depends on:** Step 2 + Step 3 · **Branch:** `feat/mcp-step4-tools`

### Context brief
`ToolDispatcher::dispatch($toolName, $args, $actor, $campus, $trace)` ([ToolDispatcher.php](../app/Modules/AI/Support/Tools/ToolDispatcher.php)) returns `QueryMetricsResult|EntitySearchResult|EntityProfileResult`. The `laravel/mcp` `Tool` base injects **only `array $arguments` into `handle()`** (`Tool.php:43`, `CallTool.php:39`) — there is no DI of the authenticated user, so actor MUST be pulled via `auth('api')->user()` inside `handle()`. The base also auto-names tools `Str::kebab(class_basename())` (`Tool.php:20`), so `QueryMetricsMcpTool` would wrongly become `query-metrics-mcp-tool`; each tool MUST override `name()` to the canonical `query_metrics` / `search_entities` / `get_entity_profile`. `ToolResult` offers only `text()`/`error()`/`items()` — **no `json()` helper** — so structured output is `ToolResult::text(json_encode($shape))`. Per-tool gating is finer than the registry's declared `view_ai_metrics` (see verification). Pass `$trace = null`; audit is written by this wrapper via `McpAuditRecorder` (Step 3), not the inner tool.

### Tasks
1. Create `app/Mcp/Tools/QueryMetricsMcpTool.php`, `SearchEntitiesMcpTool.php`, `GetEntityProfileMcpTool.php`. Each: `schema()` mirrors the catalog tool input; **override `name()`** to the canonical name; `handle(array $arguments)` → `$actor = auth('api')->user()` → `$campus = McpCampusResolver::resolve($actor, $arguments['campus_id'] ?? null)` → `ToolDispatcher::dispatch(name(), $arguments, $actor, $campus, null)` → `McpAuditRecorder::record(...)` → return.
2. Annotate all `#[IsReadOnly]` + `#[Title]`.
3. Register the three tools on `SwinxMcpServer::$tools`; remove the `PingTool` stub.
4. Map each result object to a stable JSON shape (value + evidence: source, scope, freshness, permission_result, hidden_sections) via `ToolResult::text(json_encode($shape))`; map a failed `permissionResult`/`safeErrorCode()` to `ToolResult::error(...)` carrying the safe code.
5. `entity_ref` reality: the resolver ([EntityReferenceResolver.php:23](../app/Modules/AI/Support/EntityReferenceResolver.php)) binds a ref to `campus_id` + 24h expiry + catalog version (NOT to the issuing user); `get_entity_profile` re-checks `view_student` + campus on resolve, so a ref is still permission-gated. Confirm it works session-free; do NOT claim per-user binding.

### Files
`app/Mcp/Tools/QueryMetricsMcpTool.php`, `app/Mcp/Tools/SearchEntitiesMcpTool.php`, `app/Mcp/Tools/GetEntityProfileMcpTool.php`, `app/Mcp/Servers/SwinxMcpServer.php`, `app/Mcp/Support/McpAuditRecorder.php` (from Step 3), tests under `tests/Feature/Mcp/`.

### Verification
- Per-tool Pest feature tests with **tool-specific** gating:
  - `query_metrics` — metric `required_permission` (default `view_ai_metrics`) **+** optional `required_domain_permission` ([QueryPlanValidator.php:43-64](../app/Modules/AI/Support/QueryPlanValidator.php)).
  - `search_entities` — **per-entity-type** permission (e.g. `view_student`), not a flat `view_ai_metrics`.
  - `get_entity_profile` — `view_ai_metrics` **+** `view_student` **+** per-section perms (`view_student_summary`, `view_finance_student_overview`, …) → partial sections / `hidden_sections`.
- Each test asserts: campus in-scope + out-of-scope; structured JSON shape with evidence; **exactly one `AiToolCall` row with `channel='mcp'` + actor + client per call**; safe-error mapping.
- `./scripts/dev.sh artisan mcp:inspector swinx` lists 3 tools with the **canonical names** and executes each.
- **Invariant check:** grep that MCP tools reference only `ToolDispatcher`/resolvers/recorder (no direct model/query use).

### Exit criteria
All three tools are callable over MCP, enforce permission + campus per-user, return structured evidence JSON, write audit, and never mutate.

### Rollback
Remove the three tool classes; restore `PingTool` registration.

---

## Step 5 — Hardening: Host/Origin + rate-limit + version pin + read-only guard

**Model tier:** default · **Depends on:** Step 4 · **Branch:** `feat/mcp-step5-hardening`

### Context brief
The server is public ([ADR-0011](../docs/adr/0011-mcp-server-is-public-and-accepts-redacted-data-egress.md)). `laravel/mcp`'s own README warns about DNS-rebinding on `Mcp::web()` and that the package is not yet meant for public use. This step adds the wrap the ADR promised: validate `Host`/`Origin`, rate-limit the `/mcp` + `/oauth` routes, pin the package, and add an architectural test that no MCP tool is destructive.

### Tasks
1. `ValidateMcpOrigin` middleware validating `Host`/`Origin` against an allowlist. **Attach at the registration**, not globally: `Mcp::web('swinx', …)->middleware(['auth:api', 'throttle:mcp', ValidateMcpOrigin::class])` in `routes/ai.php` (there is no controller to decorate; the route is `/mcp/swinx` per `McpServiceProvider.php:76`). Do NOT add to `bootstrap/app.php` global stack (would hit the whole app).
2. Define a named `throttle:mcp` limiter (per-user/per-client) + length limits on tool args; also throttle the `/oauth/*` endpoints.
3. Pin `laravel/mcp` to the exact reviewed version in `composer.json` (possible only because Step 1 promoted it to a direct dep).
4. Architecture test (Pest) asserting every class in `app/Mcp/Tools` is `#[IsReadOnly]` and exposes no write path.
5. Confirm the frozen chat UI route/nav is neutralized or left dark per product call (no new exposure).

### Files
`app/Http/Middleware/ValidateMcpOrigin.php`, `routes/ai.php` (middleware chained on registration), `app/Providers/AppServiceProvider.php` or a `RouteServiceProvider` (the `throttle:mcp` limiter), `composer.json`, tests under `tests/Feature/Mcp/` + `tests/Unit/`.

### Verification
- Pest: bad `Origin`/`Host` rejected; rate-limit returns 429 past threshold; architecture test passes.
- `composer.json` shows pinned `laravel/mcp` version.

### Exit criteria
Public endpoint is origin-validated, rate-limited, version-pinned, and provably read-only.

### Rollback
Remove middleware + rate limiters; unpin (not recommended).

---

## Step 6 — ChatGPT connector validation (spike) + finalize v1 client list

**Model tier:** default · **Depends on:** Step 5 · **Branch:** `feat/mcp-step6-chatgpt-spike`

### Context brief
Claude is the committed v1 client ([ADR-0009](../docs/adr/0009-mcp-server-replaces-staff-copilot-chat-ui.md)). ChatGPT custom connectors require OAuth + dynamic client registration + specific discovery metadata and are gated to Business/Enterprise plans. This step measures the real gap and records a go/no-go, without blocking the Claude path already shipped in Steps 1–5.

### Tasks
1. Configure a ChatGPT custom MCP connector against the public endpoint; attempt the OAuth + tool-call flow.
2. Record gaps (consent screen, `scopes_supported`, resource indicators RFC-8707, token audience/expiry) in the notes file.
3. If gaps are small, add the metadata/fixes; if large, record deferral.
4. Update [ADR-0009](../docs/adr/0009-mcp-server-replaces-staff-copilot-chat-ui.md)/[ADR-0011](../docs/adr/0011-mcp-server-is-public-and-accepts-redacted-data-egress.md) and CONTEXT.md with the finalized v1 client list and the DPA precondition status.

### Files
`plans/swinx-mcp-server-v1.notes.md`, ADR-0009/0011, `CONTEXT.md`, possibly `routes/web.php` (metadata tweaks).

### Verification
- Documented, reproducible outcome (ChatGPT connects, or a precise list of blocking gaps).
- Docs reflect the decision.

### Exit criteria
A recorded go/no-go for ChatGPT in v1; Claude path unaffected.

### Rollback
Revert any metadata tweaks; ChatGPT remains deferred.

---

## Out of scope for v1 (explicit)
- Write tools (separate ADR required).
- Deleting the frozen synthesis stack / chat UI / `laravel/ai`.
- An egress-side redaction layer (deliberately omitted, [ADR-0011](../docs/adr/0011-mcp-server-is-public-and-accepts-redacted-data-egress.md)).
- MCP resources/prompts (only tools in v1).
- BOD-specific MCP mode beyond per-user permission inheritance.

## External / non-engineering preconditions
- **DPA + zero-retention** with the chosen provider before production exposure ([ADR-0011](../docs/adr/0011-mcp-server-is-public-and-accepts-redacted-data-egress.md)).
- Production network/TLS for a publicly reachable endpoint (FrankenPHP prod).

## Unresolved questions
1. Exact Passport consent-screen UX for staff (branded vs default)?
2. Per-client vs per-user rate-limit thresholds (need a usage estimate)?
3. Does any production deploy entrypoint need updating for the new public route (deploy-script drift, CONTEXT §6 D2)?
