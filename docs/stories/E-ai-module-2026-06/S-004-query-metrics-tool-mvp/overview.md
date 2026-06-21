# AI-MOD-004 - Query Metrics Tool MVP

## Status

implemented

## Lane

high-risk

## Current Behavior

Swinx now has the prerequisite AI foundations needed before a metric execution
tool can be designed:

- `AI-MOD-001-ai-governance-provider-settings` provides the internal AI module
  shell, permissions, staff-owned provider settings, provider/model allowlists,
  encrypted API keys, and provider test audit.
- `AI-MOD-002-ai-audit-evaluation-foundation` provides AI conversation,
  message, trace, tool-call, provider-usage, feedback, and evaluation records,
  plus redaction and deterministic evaluation support.
- `AI-MOD-003-metric-catalog-query-plan` provides `BusinessGlossary`,
  `MetricCatalog` v1, `QueryPlan`, `QueryPlanValidationResult`,
  `QueryPlanValidator`, `StaffMetricQuestionDataset`, the `view_ai_metrics`
  permission, and deterministic staff metric evaluation cases.

Current gaps before staff copilot chat can answer metric questions:

- No `ToolRegistry` or `ToolDispatcher` exists under the AI module boundary.
- No executable `query_metrics` tool exists for the `query_metrics:v1` schema
  already advertised by `MetricCatalog`.
- `QueryPlanValidator` can allow or deny plans, but no backend code executes an
  allowed plan against the source Academic or Finance reports.
- Existing source reports expose different shapes: Academic status reporting
  returns student-level mapped rows, while Finance reporting returns summary,
  breakdown, row, and metadata arrays. AI needs bounded aggregate adapters
  instead of direct row exposure.
- `AiAuditRecorder` can record tool calls, but no metric tool currently records
  execution, denial, source references, result summaries, duration, truncation,
  or safe error codes.
- Existing AI evaluation cases expect `query_metrics` tool calls, but they do
  not yet prove metric execution or source-report parity.
- No staff chat UI, AgentRunner loop, provider prompt runtime, Laravel AI SDK
  Tool adapter, MCP exposure, write/action mode, student API, lecturer API, or
  portal behavior is exposed for metric execution.

## Target Behavior

This story creates the first backend-only AI tool execution slice: a read-only,
allowlisted, audited `query_metrics` MVP backed by existing Academic and Finance
query/report code.

Target behavior:

- Swinx has a small internal `ToolRegistry` that exposes only accepted AI tools.
  In this story, the only registered executable tool is `query_metrics`.
- Swinx has a deterministic `ToolDispatcher` that rejects unknown tools,
  validates tool input, calls the matching tool, catches source-query failures,
  and returns safe error contracts.
- `query_metrics` accepts an untrusted QueryPlan-shaped payload, authenticated
  staff actor, current campus context, and optional trace/evaluation context.
- Every `query_metrics` request is validated through the existing
  `QueryPlanValidator` before any Academic or Finance source query runs.
- Denied plans return deterministic safe error codes and are audit-recorded
  without executing source reports.
- Allowed plans dispatch only to an allowlisted resolver for the validated
  metric key in `MetricCatalog` v1.
- Metric resolvers delegate business calculations to existing Academic and
  Finance query/report classes. They may adapt, aggregate, and normalize the
  source result, but they must not duplicate report business rules or invent new
  SQL.
- Tool output is aggregate-only and bounded. It may include counts, totals,
  rates, grouped summaries, source references, filters, campus scope, freshness,
  truncation warnings, hidden sections, and confidence inputs. It must not
  expose broad source rows or student-level PII.
- Tool-call audit records permission result, campus scope, redacted arguments,
  hidden sections, source references, record count, result summary, status,
  duration, tool schema version, and safe error code.
- Existing staff metric evaluation cases can execute `query_metrics` and compare
  the tool result with the existing source report output for source parity.
- Portal impact remains `none`.

## Implementation Result

Implemented as a backend-only AI tool execution slice:

- `app/Modules/AI/Support/Tools` now contains `ToolRegistry`,
  `ToolDispatcher`, `QueryMetricsTool`, `QueryMetricsResult`,
  `AiToolDefinition`, and `QueryMetricsExecutionContext`.
- `ToolRegistry` exposes only `query_metrics` for this slice.
- `ToolDispatcher` rejects unknown tools and delegates registered tool calls to
  the internal tool implementation without adding web routes, API routes, or UI.
- `QueryMetricsTool` parses untrusted arguments through `QueryPlan`, validates
  every plan with `QueryPlanValidator`, denies unsafe plans before source
  execution, and records tool-call audit when trace context exists.
- Metric resolvers under `app/Modules/AI/Support/Tools/Metrics` execute the
  accepted MetricCatalog v1 keys:
    - `academic_student_status_count`
    - `academic_defer_count`
    - `finance_collection_summary`
    - `finance_fee_monitor_summary`
    - `finance_dng_lifecycle_attention`
- AI source-report access crosses module boundaries through shared contracts:
  `App\Shared\Contracts\Academic\AiAcademicMetricReader` and
  `App\Shared\Contracts\Finance\AiFinanceMetricReader`, with thin adapters in
  the owning Academic and Finance modules.
- Academic resolvers aggregate mapped status-report rows and omit student codes,
  names, and broad source rows from tool output.
- Finance resolvers adapt existing source report summaries, breakdowns, rows,
  and DNG lifecycle truncation metadata into bounded aggregate tool output.
- Tool output includes normalized filters, groupings, campus scope, source
  references, freshness, hidden sections, warnings, confidence, safe errors, and
  record counts.
- Completed, denied, failed, and partial tool calls can be audited through the
  existing `ai_tool_calls` table via `AiAuditRecorder`.
- The implementation adds no migrations, no frontend, no provider prompt
  runtime, no live LLM call, no MCP exposure, no write/action behavior, and no
  student/lecturer portal changes.

## Candidate Metric Execution Set

The MVP should implement execution for the existing MetricCatalog v1 keys:

| Metric key                        | Source truth                                                             | Expected output grain                                                                                      |
| --------------------------------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------- |
| `academic_student_status_count`   | `App\Modules\Academic\Queries\Reporting\GetStudentStatusBySemesterQuery` | Aggregate student counts by accepted groupings.                                                            |
| `academic_defer_count`            | `App\Modules\Academic\Queries\Reporting\GetStudentStatusBySemesterQuery` | Aggregate deferred student counts by accepted groupings.                                                   |
| `finance_collection_summary`      | `App\Modules\Finance\Queries\Reporting\ListCollectionProgressQuery`      | Finance collection totals, rates, and accepted breakdowns.                                                 |
| `finance_fee_monitor_summary`     | `App\Modules\Finance\Queries\Reporting\ListFeeMonitorQuery`              | Expected-fee generated/missing/blocked/paid summaries.                                                     |
| `finance_dng_lifecycle_attention` | `App\Modules\Finance\Queries\Reporting\ListDngLifecycleQuery`            | DNG/payment attention counts and accepted breakdowns, with truncation surfaced when source scan is capped. |

The implementation may narrow execution to a smaller subset only if discovery
proves a metric cannot meet source-parity, privacy, or bounded-output rules.
It must not silently add new metric keys outside MetricCatalog v1.

## Affected Users

- Internal staff users who will later ask AI questions about Academic and
  Finance metrics.
- Finance staff who need collection, fee-monitor, and DNG lifecycle summaries.
- Academic staff who need semester/status/defer aggregate summaries.
- Administrators and security reviewers who need permission, campus scope, and
  audit evidence for AI data access.
- Engineers implementing staff copilot chat, answer generation, evaluation, and
  governance stories.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md` when runtime behavior lands.
- `docs/system-architecture.md` when code-verified AI tool execution behavior
  exists.
- `docs/codebase-summary.md` when code-verified AI tool execution behavior
  exists.

## Portal Impact

Portal impact: none.

This story must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, Identity
student/lecturer auth or context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Create a high-risk story packet for
  `AI-MOD-004-query-metrics-tool-mvp`.
- Register the story in Harness before runtime implementation begins.
- Confirm `AI-MOD-003-metric-catalog-query-plan` is implemented before executing
  this story.
- Define an internal `ToolRegistry` contract for allowlisted AI tools.
- Define a deterministic `ToolDispatcher` contract for resolving, validating,
  executing, and safely failing tool calls.
- Define the `query_metrics` input contract using `query_metrics:v1`,
  QueryPlan-shaped arguments, authenticated actor, current campus scope, and
  optional trace/evaluation context.
- Define the `query_metrics` output contract with aggregate data, grouped
  summaries, normalized filters, campus scope, source references, freshness,
  hidden sections, record counts, warnings, confidence inputs, and safe errors.
- Define metric resolver responsibilities for every accepted MetricCatalog v1
  metric, including which existing source query/report owns the numbers.
- Define source-parity expectations for Academic status/defer metrics, Finance
  collection summaries, fee-monitor summaries, and DNG lifecycle attention
  summaries.
- Define audit requirements for accepted, denied, failed, and truncated tool
  calls through existing AI audit records.
- Define evaluation expectations that execute the existing staff metric cases
  without live provider credentials or live LLM calls.
- Keep staff chat UI, AgentRunner, provider prompt runtime, Laravel AI SDK Agent
  integration, frontend routes, MCP exposure, student/lecturer portals,
  provider tools, vector search, embeddings, and write/action behavior out of
  this story.

## Non-Goals

- Do not implement staff chat UI or visible answer generation; that belongs to
  `AI-MOD-005-staff-copilot-chat-mvp`.
- Do not allow AI-generated production SQL, raw table names, raw column names,
  arbitrary joins, arbitrary includes, or schema exploration.
- Do not expose row-level student profiles, broad source rows, hidden fields, or
  student-level PII through metric output.
- Do not add EntityCatalog, `search_entities`, `get_entity_profile`,
  conversation context, profile sections, or current-filter memory.
- Do not create a public API, student API, lecturer API, or nested Nuxt portal
  contract.
- Do not add MCP server exposure, MCP-ready mapping, provider-native tools,
  web search, embeddings, vector stores, sub-agents, streaming UI, failover, or
  write/action mode.
- Do not add new migrations unless implementation discovery proves an audit or
  evaluation gap that cannot be satisfied by existing AI tables; pause first if
  that happens.
