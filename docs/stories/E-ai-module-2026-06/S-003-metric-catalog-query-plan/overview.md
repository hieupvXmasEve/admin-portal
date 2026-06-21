# AI-MOD-003 - Metric Catalog Query Plan

## Status

implemented

## Lane

high-risk

## Current Behavior

Swinx now has the first AI runtime foundations:

- `AI-MOD-001-ai-governance-provider-settings` provides internal provider
  settings, encrypted staff-owned API keys, provider/model allowlists, and
  provider test audit.
- `AI-MOD-002-ai-audit-evaluation-foundation` provides AI conversation,
  message, trace, tool-call, provider-usage, feedback, and evaluation records,
  plus redaction and deterministic evaluation support.

Current gaps before any staff copilot can safely answer business-data questions:

- No business glossary subset defines the natural-language terms that the AI is
  allowed to map into Swinx concepts.
- No `MetricCatalog` defines which aggregate metrics exist, which domain query
  or report owns them, which filters and groupings are allowed, and which
  permission/campus scope applies.
- No query-plan schema exists for AI-proposed metric requests.
- No `QueryPlanValidator` checks metric keys, filter keys, filter values,
  grouping, user permission, campus scope, record limits, or sensitive-data
  exposure before a metric can be executed by a later tool.
- No canonical staff questions are pinned to existing Academic or Finance
  reports for source-parity proof.
- `AI-MOD-004-query-metrics-tool-mvp` cannot be implemented safely until this
  catalog and query-plan contract exists.

The existing AI audit tables already include `catalog_version` and
`tool_schema_version` fields for traces/evaluation runs. This story should use
those fields instead of inventing a second audit layer.

## Target Behavior

This story defines the first safe semantic layer for AI metric questions. After
the future implementation of this story:

- Swinx has a versioned MetricCatalog v1 for a small aggregate metric set backed
  by existing Academic and Finance query/report code.
- Swinx has a business glossary subset that maps common staff wording to
  canonical metric, filter, and grouping keys without treating synonyms as
  executable business logic.
- AI may propose a structured query plan, but backend code must validate the
  plan before any later `query_metrics` tool can execute it.
- The query-plan contract forbids raw SQL, table names, raw column names,
  unbounded row extraction, cross-campus expansion, and hidden-field inference.
- QueryPlanValidator returns deterministic allow/deny results with normalized
  filters, permission result, campus scope, hidden sections, estimated record
  limit, safe error code, and catalog/tool-schema versions.
- The first metric set is aggregate-only and read-only. It may produce counts,
  totals, rates, and grouped summaries, but not student-level profile details or
  broad source-row dumps.
- Each accepted metric points to an existing source-of-truth query or report and
  records how future parity checks will compare AI metric output with that
  report.
- Three to five canonical staff questions are stored as evaluation cases so
  later stories can assert tool selection, plan validation, permission behavior,
  source parity, hidden-section behavior, and no fabricated numbers.
- No staff chat UI, AgentRunner loop, ToolRegistry, ToolDispatcher, or
  `query_metrics` execution is exposed by this story.

## Implementation Result

Implemented as a backend-only AI semantic-layer slice:

- `MetricCatalog` defines catalog version `metric-catalog:v1`, tool schema
  version `query_metrics:v1`, and five aggregate-only metric contracts:
  academic student status count, academic defer count, finance collection
  summary, finance fee monitor summary, and DNG lifecycle attention.
- `BusinessGlossary` maps the first staff-facing phrases and synonyms to
  canonical metric/filter keys without making synonyms executable business
  logic.
- `QueryPlan` parses untrusted AI-proposed metric plans and flags unsupported
  top-level fields plus forbidden SQL/table/column/cross-campus fields before
  permission evaluation.
- `QueryPlanValidator` validates metric keys, filters, groupings, bounded
  options, `view_ai_metrics` permission, campus scope, current-semester
  normalization, record limits, source report identity, and deterministic safe
  error codes.
- `StaffMetricQuestionDataset` seeds five deterministic `ai_evaluation_cases`
  for the first staff metric questions with expected `query_metrics` calls,
  source references, catalog/tool-schema versions, and answer requirements.
- `view_ai_metrics` is registered in `config/permission.php` as the first AI
  metric-read permission.
- Source query references are stored as identity strings only. This story does
  not import or execute Academic/Finance query classes, and it does not expose
  `query_metrics` runtime execution.
- Portal impact remained `none`; no student/lecturer routes or nested Nuxt
  portal files changed.

## Candidate Metric Set

MetricCatalog v1 should stay intentionally small. Candidate metrics:

| Metric key | Staff question | Source truth | Allowed groupings |
| --- | --- | --- | --- |
| `academic_student_status_count` | How many students are active, deferred, pending, or dropped out in the selected semester? | `App\Modules\Academic\Queries\Reporting\GetStudentStatusBySemesterQuery` | `status`, `program`, `intake_semester` |
| `academic_defer_count` | How many students are deferred in the current term, by program or intake? | Student status/action report via `GetStudentStatusBySemesterQuery` | `program`, `intake_semester` |
| `finance_collection_summary` | For the selected Finance semester, how much is billed, paid, outstanding, overdue, overpaid, and unapplied? | `App\Modules\Finance\Queries\Reporting\ListCollectionProgressQuery` | `program`, `fee_type`, `balance_state`, `aging_bucket` |
| `finance_fee_monitor_summary` | Which expected fee types are missing, generated, blocked, or paid this semester? | `App\Modules\Finance\Queries\Reporting\ListFeeMonitorQuery` | `expected_fee_type`, `generation_state`, `payment_state`, `program` |
| `finance_dng_lifecycle_attention` | Which DNG/payment lifecycle states need staff attention? | `App\Modules\Finance\Queries\Reporting\ListDngLifecycleQuery` | `attention_bucket`, `flow_state`, `fee_type` |

The implementation may narrow this set if a source report cannot provide
deterministic parity proof without expanding scope. It must not silently add new
metrics outside the accepted story.

## Affected Users

- Internal staff users who will later ask AI questions about Academic and
  Finance metrics.
- Finance staff who need collection, fee-monitor, and DNG lifecycle summaries.
- Academic staff who need semester/status/defer aggregate summaries.
- Administrators and security reviewers who need allowlisted data access,
  campus scope, and audit evidence.
- Engineers implementing `query_metrics`, staff copilot, evaluation, and later
  governance stories.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md` when runtime behavior lands.
- `docs/system-architecture.md` when code-verified AI semantic-layer behavior
  exists.
- `docs/codebase-summary.md` when code-verified AI metric/query-plan behavior
  exists.

## Portal Impact

Portal impact: none.

This story must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, Identity
student/lecturer auth or context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Create a high-risk story packet for
  `AI-MOD-003-metric-catalog-query-plan`.
- Register the story in Harness before runtime implementation begins.
- Define MetricCatalog v1 with a small aggregate-only set of Academic and
  Finance metrics backed by existing query/report classes.
- Define the business glossary subset and synonym rules needed for the selected
  staff questions.
- Define the query-plan schema for `metric`, `filters`, `group_by`, and bounded
  options.
- Define allowed filters, filter value types, required scopes, groupings,
  output shape, max result/record limits, source references, permission rules,
  and hidden-section behavior per metric.
- Define QueryPlanValidator responsibilities and deterministic safe error
  categories.
- Define catalog versioning and tool-schema versioning so AI traces and
  evaluation runs can identify which catalog validated a plan.
- Define three to five canonical staff questions with expected query plans,
  permission contexts, source reports, and parity expectations.
- Define validation expectations for unsupported metrics, invalid filters,
  invalid groupings, cross-campus attempts, permission denials, record-limit
  denials, hidden sections, and source-parity checks.
- Keep runtime execution, ToolRegistry, ToolDispatcher, AgentRunner, chat UI,
  provider calls, MCP exposure, student/lecturer portal behavior, and write
  actions out of this requirements step.

## Non-Goals

- Do not implement `query_metrics` execution; that belongs to
  `AI-MOD-004-query-metrics-tool-mvp`.
- Do not implement staff chat UI, AgentRunner, ToolRegistry, ToolDispatcher, or
  provider prompt runtime.
- Do not expose AI access to live Academic, Finance, Identity, Notification,
  student, lecturer, or portal data.
- Do not allow AI-generated production SQL, raw table names, raw column names,
  or schema exploration as user-visible output.
- Do not build EntityCatalog, `search_entities`, `get_entity_profile`,
  student profile sections, or conversation context.
- Do not return student-level PII or broad source rows from metric plans.
- Do not add MCP server exposure, vector search, embeddings, provider tools,
  sub-agents, streaming UI, failover, or write/action mode.
- Do not create admin governance dashboards, audit viewers, or quota controls;
  those belong to later governance stories.
