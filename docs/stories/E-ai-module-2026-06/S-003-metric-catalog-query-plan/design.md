# Design

## Domain Model

This story defines the semantic and validation model required before a future
`query_metrics` tool can execute aggregate business-data reads.

Primary concepts:

- `BusinessGlossary`
  - Maps staff-facing phrases to canonical semantic keys.
  - Example mappings: "current term" to `current_semester`, "defer" to
    `academic_defer_count`, "outstanding tuition" to
    `finance_collection_summary.outstanding_total`.
  - Helps interpret intent only. It must not contain executable business logic
    or direct database details.
- `MetricCatalog`
  - Versioned allowlist of metric definitions.
  - Each definition owns a metric key, domain, label, description, source query
    or report, output grain, aggregate fields, freshness rule, source-reference
    type, required permission, campus-scope rule, allowed filters, allowed
    groupings, max record/result limits, hidden-section behavior, and evaluation
    cases.
- `MetricDefinition`
  - Immutable contract for one metric key in one catalog version.
  - Points to an existing Academic or Finance query/report as source truth.
  - Never points to raw SQL supplied by the AI.
- `MetricFilterDefinition`
  - Defines allowed filter key, value type, allowed values or resolver, default
    behavior, required/optional state, and whether the filter is scope-sensitive.
- `MetricGroupByDefinition`
  - Defines allowed grouping key and resulting label/value shape.
  - Grouping must be aggregate-only and must not reveal hidden entities.
- `QueryPlan`
  - Structured AI-proposed object containing metric key, filters, groupings, and
    bounded options.
  - Treats the AI proposal as untrusted input until validated.
- `ValidatedQueryPlan`
  - Normalized, permission-checked, campus-scoped, catalog-versioned plan that a
    later tool may execute.
- `QueryPlanValidationResult`
  - Deterministic result containing allow/deny status, normalized plan when
    allowed, permission result, campus scope, hidden sections, estimated record
    limit, safe error code, and validation messages safe for audit.
- `StaffMetricQuestion`
  - Deterministic evaluation fixture containing a staff question, actor fixture,
    expected query plan, expected permission outcome, source report, expected
    answer properties, and parity assertions.

Initial business rules:

- The catalog is allowlist-first. Unsupported metrics, filters, groupings, and
  options are denied.
- The first catalog is aggregate-only. Student-level profile details, row dumps,
  and broad search are deferred to later EntityCatalog/profile stories.
- Campus scope is always resolved from the authenticated staff context or
  current campus/session context. The AI cannot expand campus scope.
- Provider API keys choose provider/model/cost behavior only. They do not
  affect data access.
- Existing Academic and Finance queries remain the source of business truth.
  The AI module validates and delegates; it does not duplicate business rules.
- Query plans must never include raw SQL, raw table names, raw column names,
  relationship traversal, arbitrary sort columns, arbitrary includes, or
  unbounded limits.
- Every metric that can produce numbers must define source references and
  parity proof before it is exposed to later AgentRunner flows.
- Metric output must include data freshness, filters, scope, source report, and
  confidence inputs for later answer generation.
- Missing data must be represented explicitly. The agent must not fabricate a
  number when a metric cannot be validated or executed.

## Query Plan Schema

Query-plan schema v1:

```json
{
  "metric": "finance_collection_summary",
  "filters": {
    "semester": "current",
    "program_id": 12,
    "fee_type": "tuition_term"
  },
  "group_by": ["program"],
  "options": {
    "include_rows": false,
    "limit": 100
  }
}
```

Required validation:

- `metric` must exist in the active MetricCatalog version.
- `filters` may contain only keys accepted by that metric.
- Filter values must be parsed into typed values before use.
- Relative filters such as `current` must be normalized to a concrete semester
  id or date range by backend code.
- `group_by` may contain only keys accepted by that metric.
- `options.include_rows` defaults to `false` and must remain `false` for
  catalog v1 unless a metric explicitly supports safe aggregate rows.
- `options.limit` must be bounded by the metric definition and never override
  max-record safeguards.
- Query plans must not contain SQL, table names, raw column names, arbitrary
  joins, arbitrary sorting, arbitrary includes, or nested payloads outside the
  schema.

Expected safe error categories:

- `unsupported_metric`
- `unsupported_filter`
- `invalid_filter_value`
- `missing_required_filter`
- `unsupported_group_by`
- `unsupported_query_plan_option`
- `forbidden_by_permission`
- `forbidden_by_campus_scope`
- `max_record_limit_exceeded`
- `hidden_section_omitted`
- `source_parity_not_defined`
- `catalog_version_inactive`
- `invalid_query_plan_schema`

## MetricCatalog V1

Each metric definition should include:

```text
key
catalog_version
domain
label
description
source_query
source_report_route_or_page
output_shape
aggregate_fields
allowed_filters
allowed_group_by
required_permission
campus_scope_rule
max_record_limit
source_reference_builder
freshness_rule
hidden_sections
evaluation_cases
```

Candidate filters:

- `semester`: current, selected semester id, or explicit allowed semester id.
- `program_id`: program visible within current campus scope.
- `intake_semester_id`: intake semester visible within current campus scope.
- `cohort`: allowed cohort/intake value.
- `student_status`: allowlisted status values from existing student status
  contracts.
- `fee_type`: allowlisted Finance charge or expected fee type.
- `balance_state`: values from Collection Progress report.
- `aging_bucket`: values from Collection Progress report.
- `expected_fee_type`: values from Fee Monitor expected-fee catalog.
- `generation_state`: values from Fee Monitor report.
- `payment_state`: values from Fee Monitor report.
- `attention_bucket`: values from DNG lifecycle report.
- `flow_state`: values from DNG lifecycle report.

Candidate groupings:

- `status`
- `program`
- `intake_semester`
- `fee_type`
- `expected_fee_type`
- `generation_state`
- `payment_state`
- `balance_state`
- `aging_bucket`
- `attention_bucket`
- `flow_state`

The implementation must confirm each value against the current source query or
catalog class before accepting it.

## Application Flow

Future query-plan validation flow:

```text
Staff question or evaluation case
  -> agent or deterministic test proposes QueryPlan v1
  -> parse plan as untrusted input
  -> load active MetricCatalog version
  -> verify metric exists and is active
  -> normalize relative filters using backend resolvers
  -> validate filter keys and typed values
  -> validate grouping keys and aggregate output shape
  -> resolve authenticated actor permission and campus scope
  -> estimate record/result bounds from metric definition
  -> deny unsafe or unsupported plans with deterministic safe error code
  -> return ValidatedQueryPlan for future tool execution
  -> audit validation result through existing AI trace/tool-call records when run inside an agent turn
```

Future evaluation flow:

```text
Metric evaluation dataset
  -> create StaffMetricQuestion cases
  -> run deterministic plan parser/validator
  -> assert expected metric key, filters, groupings, permission, hidden sections
  -> compare expected source report and source references
  -> record evaluation run with catalog_version and tool_schema_version
```

Future `query_metrics` flow in `AI-MOD-004`:

```text
ValidatedQueryPlan
  -> ToolRegistry resolves query_metrics
  -> query_metrics dispatches only to the metric resolver for the validated key
  -> existing Academic or Finance query/report computes the aggregate result
  -> result summary and source references are audited
  -> agent receives bounded aggregate output only
```

This story must stop before the final `query_metrics` execution step.

## Interface Contract

This requirements story does not add user-facing routes.

Future internal contracts should live under the existing AI module boundary,
for example under `app/Modules/AI/Support` and focused subdirectories:

- `BusinessGlossary`
  - returns canonical terms, synonyms, and explanation metadata for accepted
    metric/filter/grouping keys.
- `MetricCatalog`
  - returns active metric definitions and catalog version metadata.
- `MetricDefinition`
  - immutable description of one metric contract.
- `QueryPlan`
  - parsed DTO/value object for untrusted AI-proposed plans.
- `QueryPlanValidator`
  - validates and normalizes query plans for the current actor/campus context.
- `ValidatedQueryPlan`
  - safe object that a later `query_metrics` tool may execute.
- `MetricSourceReferenceBuilder`
  - converts source report/query metadata into safe source references.
- `StaffMetricQuestionDataset`
  - creates deterministic AI evaluation cases for selected staff questions.

Required QueryPlanValidator output fields:

```text
allowed
catalog_version
tool_schema_version
metric
normalized_filters
group_by
permission_result
campus_scope_snapshot
hidden_sections
max_record_limit
estimated_record_count
source_report
source_reference_policy
safe_error_code
messages
```

Forbidden query-plan fields:

```text
sql
raw_sql
table
tables
column
columns
join
joins
relationship
relationships
include
includes
select
where
order_by
having
raw_filter
student_ids
cross_campus
unbounded_limit
```

## Data Model

No new database table is required for the requirements step.

Runtime implementation should first prefer code-defined catalog definitions and
deterministic evaluation cases because the catalog is small and versioned with
code. A later governance/versioning story may move catalog definitions into
database-backed admin controls only after the internal metric MVP is stable.

Existing AI audit/evaluation tables from `AI-MOD-002` should be reused:

- `ai_agent_traces.catalog_version`
- `ai_agent_traces.tool_schema_version`
- `ai_tool_calls.tool_schema_version`
- `ai_tool_calls.redacted_arguments`
- `ai_tool_calls.permission_result`
- `ai_tool_calls.campus_scope_snapshot`
- `ai_tool_calls.hidden_sections`
- `ai_tool_calls.source_references`
- `ai_evaluation_cases.expected_tool_calls`
- `ai_evaluation_cases.expected_source_references`
- `ai_evaluation_runs.catalog_version`
- `ai_evaluation_runs.tool_schema_version`
- `ai_evaluation_results.tool_call_evidence`
- `ai_evaluation_results.source_parity_status`

If implementation discovers that catalog definitions need persistence before
`AI-MOD-013-prompt-tool-versioning`, pause and record an architecture decision
before adding migrations.

## UI / Platform Impact

No user-facing UI is in scope.

Later staff copilot UI must show metric answers with source report, filters,
scope, freshness, hidden sections, and confidence, but that belongs to
`AI-MOD-005-staff-copilot-chat-mvp`.

Portal impact is none. Do not touch student or lecturer Nuxt portals.

## Observability

Implementation should use the existing AI audit/evaluation foundation:

- Record `catalog_version` and `tool_schema_version` for validation runs inside
  agent traces and evaluation runs.
- Record denied plans as tool-call audit rows when validation happens during a
  future agent turn.
- Redact rejected plan payloads before persistence.
- Store safe error codes for unsupported metric/filter/grouping, permission
  denial, campus denial, limit denial, and missing source-parity definition.
- Store source references as report/query identity plus filter and freshness
  metadata, not raw source rows.

## Alternatives Considered

1. Let the AI generate SQL directly.
   - Rejected. This bypasses Swinx permissions, campus scope, source-of-truth
     query code, audit policy, and deterministic validation.
2. Build `query_metrics` first and infer the catalog from tool behavior.
   - Rejected. Tool execution without a prior catalog makes permission, scope,
     and parity errors harder to prove.
3. Store the first catalog in database tables immediately.
   - Deferred. Code-defined catalog v1 is simpler, easier to version with tests,
     and enough for the small first metric set. Database-backed governance
     belongs to a later admin/versioning story.
4. Start with student profile/entity search questions.
   - Deferred. Entity lookup and profile sections have higher PII exposure and
     are already planned for later EntityCatalog stories.
