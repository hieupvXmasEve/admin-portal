# Exec Plan

## Goal

Create a high-risk requirements packet for the AI audit and evaluation
foundation so future AI runtime slices can prove permission, redaction,
source-parity, usage/cost, and evaluation behavior before exposing tools or
chat to business data.

## Scope

In scope for this implementation step:

- Add AI-owned audit/evaluation models and migrations under the AI module
  boundary.
- Add redaction services and tests for prompts, messages, tool arguments, tool
  results, source references, provider metadata, errors, and SDK events.
- Add provider usage recording from Laravel AI SDK responses, stream callbacks,
  events, or accepted provider adapters.
- Add deterministic evaluation fixtures and runner contracts.
- Add tests proving tool-call audit, denied-call audit, redaction, source
  references, provider usage, and evaluation results.
- Keep BusinessActionLogger for high-level AI events while storing runtime AI
  evidence in AI-owned audit/evaluation records.

Out of scope:

- UI, routes, provider calls, and live provider credentials.
- Chat UI, AgentRunner, ToolRegistry, ToolDispatcher, MetricCatalog,
  EntityCatalog, QueryPlanValidator, `query_metrics`, answer generation, or
  business-data AI access.
- Admin audit viewer, quota dashboard, data-access preview, or governance UI.
- Student or lecturer portal contract changes.
- MCP server exposure, MCP-ready mappings, provider tools, sub-agents,
  embeddings, vector stores, files, images, audio, transcription, streaming UI,
  failover, or write/action mode.
- Live provider credentials in automated tests.

## Risk Classification

Risk flags:

- Authorization: future audit records must prove role, permission, campus scope,
  hidden-section, and denial behavior.
- Data model: future implementation may add conversation, message, tool-call,
  provider-usage, feedback, and evaluation tables.
- Audit/security: prompts, messages, tool arguments, source references, provider
  metadata, and evaluation failures can expose sensitive data if not redacted.
- External systems: provider usage may come from Laravel AI SDK responses,
  events, stream callbacks, or provider adapters.
- Public contracts: future AI answers, source citations, hidden-section notices,
  and evaluation evidence become staff-visible behavior.
- Existing behavior: AI source parity must match existing dashboards, reports,
  Query classes, and authorization behavior.
- Weak proof: AI correctness needs deterministic evaluation cases, tool-call
  assertions, and source parity checks that do not exist yet.
- Multi-domain: later AI tools may read Academic, Finance, Identity,
  Notification, student, lecturer, and portal-adjacent data through controlled
  contracts.

Hard gates:

- Authorization.
- Data model.
- Audit/security.
- External provider behavior.
- Removing or weakening validation requirements.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Implementation discovery
   - Inspect current `app/Modules/AI` structure from S-001.
   - Inspect existing BusinessActionLogger and activity-log patterns.
   - Inspect existing Query classes and report tests that can become source
     parity fixtures.
   - Confirm Laravel AI SDK installation status, conversation migrations,
     response usage metadata, SDK events, and fakes/assertions available in the
     project version.
   - Decide whether implementation starts with backend-only audit/evaluation
     services or includes a developer-facing evaluation command.

2. Data foundation
   - Add AI audit/evaluation migrations and models.
   - Add redaction policy service and schema-aware payload sanitizer.
   - Add audit correlation ids across conversations, messages, traces, tool
     calls, provider usage, and evaluation runs.

3. Runtime recording contracts
   - Add recorder interfaces/actions for conversations, messages, traces, tool
     calls, provider usage, and source references.
   - Add safe error taxonomy.
   - Add denied-call and hidden-section recording behavior.
   - Add usage/cost capture from SDK response/event surfaces where available.

4. Evaluation foundation
   - Add evaluation case fixtures for the first staff-analysis questions.
   - Add fake provider/agent behavior using Laravel AI SDK fakes/assertions where
     available.
   - Add assertions for tool sequence, permissions, hidden sections, source
     parity, no fabricated numbers, and missing-data behavior.

5. Verification and Harness update
   - Run targeted backend tests and formatting.
   - Run any added evaluation command against deterministic fixtures.
   - Update story validation evidence.
   - Record Harness trace with completed, partial, failed, or blocked outcome.
   - Update `docs/stories/E-ai-module-2026-06/README.md` status only when
     implementation state changes.

## Stop Conditions

Pause for human confirmation if:

- A future implementation needs to retain raw provider request/response bodies.
- A tool result requires storing broad unredacted source rows instead of safe
  source references and summaries.
- Evaluation cannot be deterministic without live provider credentials.
- Laravel AI SDK conversation storage conflicts with Swinx audit requirements
  and a custom storage boundary must be chosen.
- The implementation would expose AI reads to Academic, Finance, Identity,
  Notification, student, lecturer, or portal data before audit/evaluation proof
  exists.
- Student or lecturer API/portal behavior becomes impacted.
- Admin audit viewer or quota governance expands into `AI-MOD-012` scope.
- MCP-first, vector-search-first, or write/action behavior is requested before
  the deferred stories are accepted.
- Validation requirements need to be weakened or cannot prove redaction and
  source parity.
