# Design

## Domain Model

This story adds a comparison layer above the existing metric execution layer.
The comparison layer is read-only, allowlisted, and deterministic.

Primary concepts:

- `CompareMetricsTool`
    - Internal AI tool registered as `compare_metrics`.
    - Accepts a validated comparison request and returns a bounded aggregate
      comparison result.
    - It does not read business tables directly. It delegates source reads to
      the same metric resolver path used by `query_metrics`.
- `MetricComparisonPlan`
    - Parsed representation of one comparison request.
    - Includes metric key, comparison mode, base filters, comparison filters,
      group_by, options, and optional context source ids.
    - Treat all input as untrusted, including live-provider planner output and
      conversation context.
- `MetricComparisonValidator`
    - Validates comparison-level rules before execution.
    - Builds two QueryPlan-shaped payloads and validates both through
      QueryPlanValidator.
    - Enforces same metric, same campus scope, compatible filters, compatible
      groupings, bounded limits, supported mode, and compare-capable fields.
- `MetricComparisonPeriodResolver`
    - Resolves `current` and `previous` terms through Swinx-owned semester
      logic or accepted `AI-MOD-008` context/source metadata.
    - Produces explicit base and comparison semester ids before QueryPlan
      validation.
    - Returns safe errors for missing, ambiguous, stale, unsupported, or
      cross-campus period context.
- `MetricComparisonCatalog`
    - May be implemented as an extension of MetricCatalog or a small AI module
      service.
    - Declares which MetricCatalog v1 metrics are comparison-capable, which
      aggregate fields are numeric, and which comparison modes are supported.
- `MetricDeltaCalculator`
    - Computes absolute delta, percentage delta, direction, and trend status
      for numeric fields.
    - Handles zero baseline, missing base, missing comparison, null values, and
      partial source results with deterministic warnings.
- `GroupedComparisonAligner`
    - Aligns grouped metric rows by stable key and label.
    - Marks groups that appear only on one side instead of silently inventing
      values.
- `CompareMetricsResult`
    - Stable result contract for the tool, live agent final-answer synthesis,
      evaluation fixtures, and audit summaries.

Business rules:

- Comparison is convenience, not a new permission boundary. Existing
  permissions, campus scope, and metric allowlists remain authoritative.
- Provider credentials and provider mode do not affect data scope.
- All source numbers come from existing Academic/Finance source queries through
  the query_metrics resolver path.
- The backend computes every delta. The provider may only turn a validated
  comparison result into language.
- Each compared scope must preserve source references, normalized filters,
  freshness, warnings, and confidence.
- The tool must fail closed when periods, filters, groups, or permissions are
  ambiguous.

## Application Flow

Current-vs-previous term comparison:

```text
staff asks "compare current term defer count with previous term"
  -> Staff Copilot planner proposes compare_metrics
  -> ToolDispatcher resolves compare_metrics from ToolRegistry
  -> MetricComparisonPlan parses the untrusted payload
  -> MetricComparisonPeriodResolver resolves current and previous semesters
  -> MetricComparisonValidator builds base and comparison QueryPlans
  -> QueryPlanValidator validates both plans for actor, campus, filters, group_by, limits
  -> CompareMetricsTool executes both validated metric scopes through the query_metrics resolver path
  -> MetricDeltaCalculator computes aggregate and grouped deltas
  -> CompareMetricsTool records product audit evidence
  -> live or deterministic answer cites both compared scopes and warnings
```

Same-filter follow-up using context:

```text
staff asks "compare that with last term"
  -> AI-MOD-008 context provides one accepted current filter set
  -> comparison planner proposes base filters from context and comparison mode previous_term
  -> server revalidates all context-derived filters and periods
  -> ambiguous or stale context returns clarification before any source read
```

Denied comparison:

```text
model proposes compare_metrics with raw SQL or unsupported DNG lifecycle metric
  -> MetricComparisonPlan or MetricComparisonValidator rejects the request
  -> no source metric resolver runs
  -> audit records denied compare_metrics with safe_error_code
  -> answer returns unsupported/clarification without hidden facts
```

Partial group alignment:

```text
base result has program IT and Business
comparison result has program IT only
  -> align IT normally
  -> mark Business as missing_comparison_group
  -> comparison status is partial with visible warning
```

## Interface Contract

No student, lecturer, public API, or portal route is expected. Existing internal
Staff Copilot routes remain the only browser surface:

```text
GET  /ai/copilot
POST /ai/copilot/messages
GET  /ai/copilot/runs/{run}/events
POST /ai/copilot/runs/{run}/cancel
POST /ai/copilot/runs/{run}/retry
```

Tool definition:

```json
{
    "name": "compare_metrics",
    "schema_version": "compare_metrics:v1",
    "permission": "view_ai_metrics",
    "description": "Compare two allowlisted aggregate metric scopes and return deterministic deltas."
}
```

Input shape:

```json
{
    "metric": "finance_collection_summary",
    "comparison_mode": "current_vs_previous_term",
    "base": {
        "filters": {
            "semester": "current",
            "fee_type": "tuition_term"
        }
    },
    "comparison": {
        "filters": {
            "semester": "previous",
            "fee_type": "tuition_term"
        }
    },
    "group_by": ["program"],
    "options": {
        "include_rows": false,
        "limit": 100
    },
    "context_source": {
        "conversation_context_schema_version": "conversation-context:v1",
        "source_tool_call_ids": [123]
    }
}
```

Input restrictions:

- `metric` must be a compare-capable MetricCatalog v1 metric.
- `comparison_mode` initially supports only `current_vs_previous_term` and
  explicit `semester_vs_semester` when both semesters are safely resolvable.
- `base.filters` and `comparison.filters` must be compatible except for the
  accepted period filter.
- `group_by` must be allowed by the underlying metric.
- `options.include_rows` must be false.
- The request must not contain raw SQL, table names, column names, joins,
  relationship paths, source-row requests, arbitrary includes, raw ids outside
  accepted filters, cross-campus expansion, or unbounded limits.

Output shape:

```json
{
    "allowed": true,
    "tool": "compare_metrics",
    "tool_schema_version": "compare_metrics:v1",
    "metric_tool_schema_version": "query_metrics:v1",
    "catalog_version": "metric-catalog:v1",
    "metric": "finance_collection_summary",
    "comparison_mode": "current_vs_previous_term",
    "base_scope": {
        "label": "Current term",
        "normalized_filters": {
            "semester_id": 12,
            "fee_type": "tuition_term"
        },
        "source_references": [
            {
                "source_report": "finance.reporting.collection-progress",
                "source_reference_policy": "report_summary_with_filters"
            }
        ],
        "freshness": {
            "computed_at": "2026-06-25T09:00:00+07:00",
            "rule": "computed_at_request_time"
        }
    },
    "comparison_scope": {
        "label": "Previous term",
        "normalized_filters": {
            "semester_id": 11,
            "fee_type": "tuition_term"
        },
        "source_references": [
            {
                "source_report": "finance.reporting.collection-progress",
                "source_reference_policy": "report_summary_with_filters"
            }
        ],
        "freshness": {
            "computed_at": "2026-06-25T09:00:00+07:00",
            "rule": "computed_at_request_time"
        }
    },
    "deltas": [
        {
            "field": "outstanding_total",
            "base_value": 250000,
            "comparison_value": 300000,
            "absolute_delta": -50000,
            "percentage_delta": -16.67,
            "direction": "down",
            "trend": "improved",
            "calculation_note": "lower_is_better"
        }
    ],
    "group_deltas": [
        {
            "group_key": "program",
            "group_value": "IT",
            "deltas": [
                {
                    "field": "student_count",
                    "base_value": 10,
                    "comparison_value": 8,
                    "absolute_delta": 2,
                    "percentage_delta": 25,
                    "direction": "up",
                    "trend": "changed"
                }
            ]
        }
    ],
    "hidden_sections": [],
    "warnings": [],
    "confidence": {
        "level": "high",
        "basis": "both_scopes_source_report_parity"
    },
    "safe_error_code": null
}
```

Denied result shape:

```json
{
    "allowed": false,
    "tool": "compare_metrics",
    "tool_schema_version": "compare_metrics:v1",
    "catalog_version": "metric-catalog:v1",
    "metric": "finance_dng_lifecycle_attention",
    "comparison_mode": "current_vs_previous_term",
    "deltas": [],
    "group_deltas": [],
    "source_references": [],
    "hidden_sections": [],
    "warnings": [],
    "confidence": {
        "level": "none",
        "basis": "not_executed"
    },
    "safe_error_code": "unsupported_comparison_metric"
}
```

Safe error codes:

- `invalid_comparison_schema`
- `unsupported_comparison_metric`
- `unsupported_comparison_mode`
- `comparison_period_unresolved`
- `comparison_period_ambiguous`
- `comparison_period_forbidden`
- `comparison_filters_incompatible`
- `comparison_group_by_incompatible`
- `comparison_field_not_numeric`
- `comparison_field_not_supported`
- `comparison_divide_by_zero`
- `comparison_missing_base_value`
- `comparison_missing_comparison_value`
- `comparison_missing_base_group`
- `comparison_missing_comparison_group`
- `comparison_context_invalid`
- `comparison_context_stale`
- `comparison_context_ambiguous`
- `forbidden_by_permission`
- `forbidden_by_campus_scope`
- `max_record_limit_exceeded`
- `source_query_failed`
- `source_result_truncated`

Staff Copilot capability props should add the new tool without removing the
existing tools:

```json
{
    "capabilities": {
        "tool_names": ["query_metrics", "search_entities", "get_entity_profile", "compare_metrics"],
        "tool_schema_versions": {
            "query_metrics": "query_metrics:v1",
            "search_entities": "search_entities:v1",
            "get_entity_profile": "get_entity_profile:v1",
            "compare_metrics": "compare_metrics:v1"
        }
    }
}
```

## Data Model

No new database table is expected for the first slice.

Use existing AI audit/runtime records:

- `ai_conversations`
- `ai_messages`
- `ai_agent_traces`
- `ai_tool_calls`
- `ai_provider_usages`
- `ai_evaluation_cases`
- `ai_evaluation_runs`
- `ai_evaluation_results`
- `ai_chat_runs`
- `ai_run_events`

Audit requirements:

- Record `compare_metrics` tool-call status as `completed`, `denied`,
  `failed`, or `partial`.
- Store redacted arguments, permission result, campus scope snapshot, base
  filters, comparison filters, group_by, source references for both scopes,
  compared fields, warnings, confidence, record counts where available, duration
  in milliseconds, and safe error code.
- If implementation executes child `query_metrics` calls that also record audit
  rows, keep the parent `compare_metrics` audit summary clear enough to connect
  the comparison result with those source scopes.
- Pause before adding a migration for parent-child tool call linkage unless
  existing JSON audit summaries cannot preserve the evidence needed for replay.

## UI / Platform Impact

The Staff Copilot browser surface remains chat-first.

Expected UI impact:

- Capability metadata can include `compare_metrics`.
- Existing answer evidence rendering should show compared scopes, reused
  filters, source reports, freshness, warnings, confidence, and hidden sections.
- SSE run events may include normal tool started/completed/denied events for
  `compare_metrics`.
- No new navigation item, public page, portal page, WebSocket transport, queue
  infrastructure, or provider-native browser integration is required.

## Observability

Product audit must show:

- comparison tool schema version and MetricCatalog version;
- actor, conversation, trace, run, and current campus scope;
- comparison mode and period resolver source;
- base and comparison normalized filters;
- group_by and compared fields;
- source reports and source-reference policies for both scopes;
- freshness for both scopes;
- hidden sections, warnings, confidence, and safe error code;
- redacted final result summary;
- whether the result was completed, denied, failed, or partial;
- provider runtime mode when a live planner requested the tool.

Operational logs may include exception ids and safe diagnostics for unexpected
source-query errors, but product audit records remain the source of truth.

## Alternatives Considered

1. Let the live provider call `query_metrics` twice and compare in prose.
    - Rejected because period resolution, missing-data handling, group
      alignment, rounding, trend labels, and audit evidence would be
      inconsistent and hard to test.
2. Add a dashboard page for metric comparison.
    - Deferred. The current roadmap asks for a Staff Copilot tool capability,
      not a new reporting UI.
3. Allow arbitrary date-range comparisons in the first slice.
    - Deferred because the accepted roadmap says current-vs-previous term and
      source filters. Arbitrary ranges need separate source-parity and
      performance proof.
4. Compare all MetricCatalog v1 metrics immediately.
    - Rejected because DNG lifecycle currently lacks accepted term semantics.
      The first slice should compare only metrics with safe period filters.
5. Store comparison snapshots in a new table.
    - Deferred. Existing `ai_tool_calls` and trace records should be enough
      unless implementation proves replay or audit linkage cannot be preserved.
