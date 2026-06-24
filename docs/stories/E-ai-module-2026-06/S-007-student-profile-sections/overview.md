# AI-MOD-007 - Student Profile Sections

## Status

implemented

## Lane

high-risk

## Current Behavior

Swinx already has a safe internal Staff Copilot foundation:

- `AI-MOD-001-ai-governance-provider-settings` provides staff-owned provider
  settings, encrypted credentials, model allowlists, cost limits, and provider
  test evidence.
- `AI-MOD-002-ai-audit-evaluation-foundation` provides AI conversations,
  messages, traces, tool-call audit, provider usage, feedback, redaction, and
  evaluation records.
- `AI-MOD-003-metric-catalog-query-plan` defines the first allowlisted metric
  semantics and validation rules.
- `AI-MOD-004-query-metrics-tool-mvp` provides the internal read-only
  `query_metrics` tool.
- `AI-MOD-005-staff-copilot-chat-mvp` provides `/ai/copilot`, message
  submission, conversation persistence, deterministic planning, and answer UI.
- `AI-MOD-006-entity-catalog-search` provides EntityCatalog v1,
  `search_entities:v1`, current-campus student lookup, opaque `entity_ref`
  values, safe candidate fields, and `student_profile` as a hidden section.
- `AI-MOD-017-live-provider-staff-copilot-agent` lets a live provider choose
  allowlisted tools through ToolDispatcher.
- `AI-MOD-018-provider-agnostic-sse-chat-runtime` persists run lifecycle and
  streams normalized Staff Copilot events through SSE.

Current gap:

- Staff Copilot can find a student candidate but cannot answer a follow-up such
  as "show me this student's academic summary" or "what finance status should I
  know about this student".
- The live provider has no `get_entity_profile` capability.
- Existing student detail data is spread across Academic profile/summary,
  Academic action-history, and Finance Student 360 read models.
- There is no stable AI profile-section contract, section-level permission map,
  hidden-section reporting, or deterministic proof that profile reads remain
  campus-scoped and PII-limited.

## Target Behavior

This story adds the first student profile-section capability for internal Staff
Copilot. It introduces an allowlisted `get_entity_profile` tool that reads
bounded, audited sections for a selected student entity.

Target behavior:

- Add `get_entity_profile:v1` as an internal AI tool exposed only through
  ToolRegistry and ToolDispatcher.
- Accept only an opaque `entity_ref` produced by `search_entities`; do not accept
  raw database ids, arbitrary student codes, SQL, table names, relationship
  names, or free-form includes as profile identifiers.
- Decode and validate the `entity_ref`, then re-check entity type, catalog
  version, actor permission, campus scope, and section permissions before any
  source read.
- Support only the `student` entity type in this story.
- Define a code-owned StudentProfileSectionCatalog v1 with explicit section
  keys, required permissions, source readers, safe fields, source references,
  warnings, and hidden-section behavior.
- Return bounded section summaries with stable keys, source references, campus
  scope, data freshness, confidence, warnings, and hidden sections.
- Let Staff Copilot answer profile-section follow-up questions after an entity
  candidate is resolved, while preserving deterministic fallback and live-agent
  server-side validation.
- Keep portal impact `none`.

Initial accepted sections:

| Section key          | Purpose                                             | Required permission             | Source boundary                | Safe output shape                                                                                                                                                                       |
| -------------------- | --------------------------------------------------- | ------------------------------- | ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `identity`           | Basic student identity and enrollment context.      | `view_student`                  | Academic student reader        | Student code, name, status, academic status, campus, program, specialization, curriculum version, intake terms, admission date, expected graduation date, GC placement summary.         |
| `academic_summary`   | Academic progress summary.                          | `view_student_summary`          | Academic summary reader        | Active registrations, completed courses, credits attempted/earned, current GPA, cumulative GPA, academic standing, active holds count, retake count, current semester enrollment count. |
| `enrollments`        | Recent/current registration snapshot.               | `view_student_summary`          | Academic registration reader   | Bounded course/section/semester/status rows with grade/completion status only when already available through the summary source.                                                        |
| `attendance_summary` | Attendance aggregate overview.                      | `view_student_summary`          | Academic attendance reader     | Course-level or overall attendance aggregates, warning flags, and source scope. No raw session rows in v1.                                                                              |
| `finance_summary`    | Finance Student 360 summary.                        | `view_finance_student_overview` | Finance reader contract        | Balance, unapplied credit, net charges, total paid, collection status, DNG/installment/exception indicators. No raw ledger timeline in v1.                                              |
| `lifecycle_actions`  | Student lifecycle and defer/action history summary. | `view_student_action`           | Academic action-history reader | Latest action metadata, counts by action type, recent defer/dropout/transition rows, decision number/name when linked. No attachments, notes, or free-text private comments in v1.      |

## Affected Users

- Internal staff using Staff Copilot to inspect a specific student after entity
  lookup.
- Academic staff who need a quick academic, attendance, enrollment, or lifecycle
  summary.
- Finance staff who need a safe finance summary for a selected student.
- Administrators and security reviewers who need proof that AI profile reads are
  permission-aware, campus-scoped, audited, and PII-limited.
- Engineers implementing `AI-MOD-008-conversation-context`, which will reuse
  resolved entities and hidden-section evidence.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

Portal impact: none.

This story affects only the internal web Staff Copilot. It must not change
`/api/v1/student/*`, `/api/v1/lecturer/*`, Identity student/lecturer auth or
context routes, or nested Nuxt portal code.

## Implementation Result

Implemented on 2026-06-24.

- Added `student-profile-sections:v1` and internal `get_entity_profile:v1`.
- Reused opaque `entity_ref` values from `search_entities` through a shared
  resolver with catalog, age, entity-type, and current-campus validation.
- Added Academic and Finance shared profile-reader contracts plus owning-module
  adapters for identity, academic summary, enrollments, attendance aggregates,
  lifecycle actions, and Finance Student 360 summary.
- Registered `get_entity_profile` in `ToolRegistry` and `ToolDispatcher`.
- Extended live Staff Copilot planner context/final answer payloads and the
  Staff Copilot UI answer renderer for profile section summaries.
- Added feature tests proving happy path, mixed permission partial results,
  denied base permission, invalid/raw arguments, invalid references,
  cross-campus blocking, audit evidence, live-provider fake execution, and PII
  redaction.

## Acceptance Criteria

- Register `AI-MOD-007-student-profile-sections` in Harness before runtime
  implementation starts.
- Keep `AI-MOD-007` in the `E-ai-module-2026-06` roadmap after entity search
  and live-provider Staff Copilot.
- Confirm `AI-MOD-001` through `AI-MOD-006`, `AI-MOD-017`, and `AI-MOD-018`
  are implemented before runtime work.
- Add `get_entity_profile:v1` under the AI module tool layer.
- Register `get_entity_profile` in ToolRegistry and route execution through
  ToolDispatcher.
- Support only `student` profile sections in this story.
- Require a valid opaque `entity_ref`; reject raw `student_id`, raw DB id,
  arbitrary student code, table names, columns, relationship names, SQL,
  source-row requests, and free-form includes.
- Re-check `view_ai_metrics`, `view_student`, current campus scope, and
  section-specific permissions server-side before source reads.
- Apply current-campus scope to the decoded student reference unless the future
  implementation explicitly reuses an accepted all-campus permission boundary.
- Return a denied result with `hidden_sections` when the actor can use Staff
  Copilot but lacks one or more requested section permissions.
- Return allowed sections and hidden-section notices together when a mixed
  request contains both allowed and denied sections.
- Return no student email, phone, national id, date of birth, address, CCCD
  address, parent/emergency-contact fields, admission notes, private comments,
  raw attendance sessions, raw assessment component rows, raw finance ledger
  timeline, payment method details, gateway payloads, attachments, or arbitrary
  source rows in v1.
- Use Academic and Finance module-owned readers or shared contracts for source
  reads; the AI module must not own broad cross-domain Eloquent queries.
- Preserve existing AI audit evidence for completed, denied, failed, and partial
  profile reads.
- Update live-provider planner validation so the model may propose
  `get_entity_profile` only with a decoded entity reference and accepted section
  keys.
- Render Staff Copilot answers with section summaries, source references,
  confidence, warnings, and hidden sections using the existing chat answer
  patterns.
- Add fake-backed live-provider tests for a model-proposed profile-section tool
  call, server validation, final answer synthesis, denied sections, and fallback
  behavior.
- Do not require live provider credentials or network calls in automated tests.
- Update current-state docs after runtime implementation lands.
- Record Harness trace with implementation and validation evidence.

## Non-Goals

- Do not add student or lecturer portal behavior.
- Do not add public API routes or expose this tool outside internal Staff
  Copilot.
- Do not add write/action mode, mutation tools, email sending, notifications,
  state changes, or approval workflows.
- Do not add conversation memory, current entity context, current-filter memory,
  or follow-up context persistence; that belongs to `AI-MOD-008`.
- Do not add compare metrics, recommendations, feedback controls, admin quota
  dashboards, prompt/tool versioning, MCP exposure, vector stores, embeddings,
  files, images, audio, provider-native tools, sub-agents, or WebSocket
  transport.
- Do not add raw SQL generation, schema exploration, source-row dumps,
  arbitrary joins, arbitrary includes, hidden field access, or broad PII
  extraction.
- Do not add a migration unless implementation discovery proves current AI
  audit tables cannot preserve required evidence and the user approves the
  storage change.
- Do not add Composer or NPM dependencies without explicit approval.
