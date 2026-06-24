# AI-MOD-006 - Entity Catalog Search

## Status

implemented

## Lane

high-risk

## Current Behavior

Swinx now has the first internal AI runtime foundation:

- `AI-MOD-001-ai-governance-provider-settings` provides provider settings,
  encrypted staff-owned credentials, model allowlists, and provider test audit.
- `AI-MOD-002-ai-audit-evaluation-foundation` provides AI conversations,
  messages, traces, tool calls, provider usage, feedback, redaction, and
  deterministic evaluation records.
- `AI-MOD-003-metric-catalog-query-plan` provides BusinessGlossary,
  MetricCatalog v1, QueryPlan, QueryPlanValidator, the `view_ai_metrics`
  permission, and deterministic staff metric questions.
- `AI-MOD-004-query-metrics-tool-mvp` provides ToolRegistry, ToolDispatcher,
  QueryMetricsTool, bounded aggregate QueryMetricsResult, metric resolvers,
  source references, safe errors, and tool-call audit.
- `AI-MOD-005-staff-copilot-chat-mvp` provides the internal staff copilot page
  and deterministic AgentRunner over the existing `query_metrics` tool.

Gaps this story closes:

- No EntityCatalog exists for staff questions about concrete Swinx entities.
- ToolRegistry intentionally registers only `query_metrics`; `search_entities`
  is still absent.
- Staff copilot cannot resolve a keyword such as a student code, program code,
  semester code, or class section into safe, scoped candidate entities.
- There is no shared result shape for entity candidates, scoped entity
  references, redacted labels, match reasons, source references, hidden
  sections, warnings, confidence, or safe entity-search errors.
- There is no deterministic proof that AI entity lookup respects current campus,
  domain permissions, result limits, and PII-safe field allowlists.

## Target Behavior

This story creates the Phase 2.1 entity-search foundation for the internal staff
copilot. It adds an allowlisted EntityCatalog and a read-only `search_entities`
tool that can find candidate Swinx entities without exposing profiles, raw
source rows, arbitrary schema access, or write behavior.

Target behavior:

- Define EntityCatalog v1 with the first accepted entity types:
  `student`, `program`, `semester`, and `course_offering` with `class` as a
  user-facing alias for course offering / section.
- Define `search_entities:v1` as an internal AI tool schema.
- Register `search_entities` alongside `query_metrics` in ToolRegistry.
- Search only through allowlisted entity resolvers backed by domain-owned query
  contracts or existing module queries.
- Gate every search by the existing AI staff surface permission plus the
  entity-specific domain permission:
    - `student` requires `view_student`;
    - `program` requires `view_program`;
    - `semester` requires `view_semester`;
    - `course_offering` requires `view_course_offering`.
- Enforce current-campus scope for campus-bound entities such as students and
  course offerings.
- Return only bounded candidate results with opaque scoped entity references,
  safe display labels, safe identifiers, match reasons, source references,
  hidden sections, warnings, confidence, and safe error codes.
- Let staff copilot answer entity-search questions with candidate lists and
  clear next-step messaging, without reading a profile section.
- Keep portal impact `none`.

## Affected Users

- Internal staff users who need the copilot to find a student, program,
  semester, or class before asking follow-up questions.
- Academic staff who search by student code, student name, program code,
  semester code, or course-offering section code.
- Finance staff who need to locate a student or term context before later
  profile/finance questions.
- Administrators and security reviewers who need deterministic proof that AI
  entity lookup is permission-aware, campus-scoped, audited, and PII-limited.
- Engineers implementing later `get_entity_profile`, conversation context, and
  resolved-entity stories.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

Portal impact: none.

This story must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, Identity
student/lecturer auth or context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Create this high-risk story packet and register
  `AI-MOD-006-entity-catalog-search` in Harness before runtime code starts.
- Confirm `AI-MOD-005-staff-copilot-chat-mvp` is implemented before executing
  runtime work.
- Add EntityCatalog v1 under the AI module boundary with explicit definitions
  for `student`, `program`, `semester`, and `course_offering`.
- Treat `class` as an alias of `course_offering`; do not invent a separate
  `Class` model.
- Add a `search_entities:v1` schema with bounded input: query string,
  allowlisted entity types, allowlisted filters, and max result limit.
- Add `SearchEntitiesTool` or equivalent under the AI tool layer.
- Update ToolRegistry and ToolDispatcher so both `query_metrics` and
  `search_entities` are registered and dispatched through a shared internal tool
  contract.
- Add entity-search result objects with stable keys for status, entity catalog
  version, tool schema version, normalized query, entity types, result count,
  result limit, results, source references, campus scope, hidden sections,
  warnings, confidence, and safe error code.
- Return scoped opaque entity references rather than raw database primary keys
  in browser-visible props.
- Enforce current-campus scope for student and course-offering search results.
- Enforce entity-specific permissions in addition to the AI staff surface
  permission.
- Do not return student email, phone, national id, date of birth, address,
  parent/emergency-contact fields, notes, finance profile data, attendance
  details, assessment results, or advisor notes in search results.
- Limit result counts and add a truncation warning when more matches exist.
- Add deterministic staff-copilot planning for entity-search questions and
  answer with candidate results only.
- Record AI conversation, message, trace, and tool-call audit evidence for
  completed, denied, failed, and partial entity-search attempts.
- Prove unauthorized staff cannot search entities.
- Prove staff with AI permission but without the entity-specific permission get
  a denied result with hidden-section evidence.
- Prove cross-campus students and course offerings are not returned.
- Prove unsupported entity types, too-short queries, and over-limit requests
  fail safely without source execution.
- Update current-state docs after code lands.
- Record Harness trace with implementation and validation evidence.

## Non-Goals

- Do not add student or lecturer portal behavior.
- Do not add public API routes.
- Do not add `get_entity_profile`, student profile sections, finance/defer
  profile reads, conversation state, resolved-entity memory, current-filter
  memory, or follow-up entity context. Those belong to later stories.
- Do not add compare metrics, risk/recommendation rules, feedback controls,
  admin governance dashboards, quota enforcement, prompt/tool versioning, MCP
  exposure, or write/action mode.
- Do not expose raw SQL, table names, column names, arbitrary joins, arbitrary
  includes, schema exploration, source-row dumps, hidden fields, or broad
  student-level PII through prompts, tool arguments, answers, audit summaries,
  or UI props.
- Do not use embeddings, vector stores, web search, provider-native tools,
  sub-agents, streaming UI, failover, or live provider calls as part of this
  entity-search slice.
- Do not add a migration unless implementation discovery proves current AI
  audit tables cannot preserve the required evidence and the user approves the
  storage change.
- Do not add any Composer or NPM dependency without explicit approval.

## Implementation Result

AI-MOD-006 is implemented as a read-only internal staff-copilot entity-search
slice. It adds EntityCatalog v1, registers `search_entities:v1` beside
`query_metrics`, executes searches through the Academic shared reader contract,
returns opaque scoped `entity_ref` values, and renders safe candidate lists in
the staff copilot answer UI.

Implemented entity types:

- `student` with current-campus scope and `view_student`.
- `program` with global reference scope and `view_program`.
- `semester` with global reference scope and `view_semester`.
- `course_offering` with current-campus scope, `view_course_offering`, and
  `class` as an alias.

The deterministic runner now maps explicit lookup prompts such as
`Find student AUS24001` to `search_entities`. Metric prompts continue to use
`query_metrics`; generic phrases such as `current semester` are not treated as
entity lookup unless a lookup verb is present.
