# Exec Plan

## Goal

Create a durable high-risk Harness story set for the AI module so future work
can be implemented phase by phase without losing product, architecture,
security, validation, or status context.

## Scope

In scope:

- Create `docs/stories/E-ai-module-2026-06/README.md`.
- Create master story packet `S-000-ai-module-roadmap/`.
- Register `AI-MOD-000-ai-module-roadmap` in Harness.
- Capture phase order, child story IDs, dependencies, shared rules, non-goals,
  validation expectations, and first-slice recommendation.

Out of scope:

- Runtime AI module implementation.
- Database migrations.
- Provider/API-key UI.
- Chat UI.
- Tool dispatcher code.
- Metric/entity catalog implementation.
- MCP server or MCP exposure.
- Student/lecturer portal changes.
- AI write/action mode.

## Risk Classification

Risk flags:

- Auth: future provider/user settings may intersect authenticated user context.
- Authorization: every AI data read must respect roles, permissions, and campus
  scope.
- Data model: future stories may add provider settings, conversations, tool
  audit, usage, feedback, and versioning tables.
- Audit/security: AI prompts and tool calls can expose sensitive operational or
  student data if not audited and redacted.
- External systems: future provider calls depend on model APIs and credentials.
- Public contracts: staff-visible answers, structured outputs, and future portal
  surfaces are product contracts.
- Existing behavior: AI answers must match existing dashboards and reports.
- Weak proof: AI correctness requires evaluation sets, tool-call assertions, and
  source parity checks not yet present.
- Multi-domain: the module may read Academic, Finance, Notification, Identity,
  and future portal data.

Hard gates:

- Auth.
- Authorization.
- Audit/security.
- External provider behavior.
- Potential data model changes.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Initiative setup
   - Record Harness intake.
   - Create AI module epic README.
   - Create master high-risk story packet.
   - Register the master story.

2. Phase 1 child-story creation
   - When selected, create child packets for provider/governance, audit,
     MetricCatalog/query plan, `query_metrics`, and staff copilot MVP.
   - Keep each child story implementation-bounded and independently verifiable.

3. Phase 2 child-story creation
   - Add entity search, student profile sections, and conversation context only
     after the metric MVP has proven permission and audit behavior.

4. Phase 3 child-story creation
   - Add metric comparison, rule-based risk/recommendation, and feedback/eval
     loops after entity/context behavior is stable.

5. Phase 4 child-story creation
   - Add admin governance, quotas, prompt/tool versioning, and MCP-ready mapping
     after the internal core is stable.

6. Phase 5 deferred surfaces
   - Evaluate student portal assistant and write/action mode only after internal
     staff usage has evidence for permission, audit, cost, and accuracy.

## Stop Conditions

Pause for human confirmation if:

- A child story requires AI write actions, state mutation, email sending, or
  other side effects.
- A child story needs student or lecturer portal contract changes.
- A provider integration requires secrets handling that is not covered by an
  accepted design.
- A tool would expose broad PII, unbounded records, raw SQL, or cross-campus
  data.
- The implementation cannot produce deterministic validation against existing
  dashboard/report truth.
- Architecture direction changes from internal module/tool registry to MCP-first
  or vector-search-first.
