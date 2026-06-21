# Design

## Domain Model

This story introduces the first internal AI tool execution model. The model is
backend-only and read-only.

Primary concepts:

- `AiToolDefinition`
    - Immutable description of one registered AI tool.
    - Owns tool name, schema version, description, input shape, output shape,
      permission rule, rate-limit policy, audit policy, and deterministic error
      contract.
- `ToolRegistry`
    - Code-defined allowlist of tools accepted by the current Swinx AI runtime.
    - In this story it registers only `query_metrics`.
    - Unknown tools are denied before any source query or provider call.
- `ToolDispatcher`
    - Executes a registered tool for an authenticated actor and campus context.
    - Parses untrusted arguments, applies validation, records audit evidence, and
      returns a safe tool result.
- `QueryMetricsTool`
    - The read-only tool that executes validated MetricCatalog v1 metrics.
    - It owns the `query_metrics:v1` execution contract and delegates metric work
      to metric resolvers.
- `MetricResolver`
    - Per-metric or per-domain adapter that maps a validated metric plan to an
      existing source query/report call.
    - It may normalize filters, select source summary/breakdown values, aggregate
      already-mapped source rows, and build source references.
    - It must not duplicate source report business rules or use AI-generated SQL.
- `QueryMetricsResult`
    - Normalized aggregate result returned by `query_metrics`.
    - Contains allowed status, metric key, catalog version, tool schema version,
      normalized filters, groupings, campus scope, aggregate values, grouped
      summaries, source references, freshness metadata, hidden sections,
      record count, warnings, confidence inputs, safe error code, and redacted
      audit summary.
- `SourceReference`
    - Safe reference to the existing report/query that produced the result.
    - Includes source report key, metric key, catalog/tool-schema versions,
      filters, campus scope, grouping, generated-at timestamp, and parity policy.
- `QueryMetricsExecutionContext`
    - Carries the authenticated user, current campus, optional conversation,
      optional trace, optional evaluation run/case, request id, and whether the
      call is from deterministic evaluation or future AgentRunner flow.

Initial business rules:

- Tool execution is allowlist-first. Only registered tool names and registered
  metric keys can run.
- The first tool is aggregate-only and read-only.
- `QueryPlanValidator` remains the gate before execution. A denied validation
  result cannot be overridden by the tool layer.
- Campus scope is resolved from the authenticated staff context or current
  campus binding/session. The tool cannot expand campus scope.
- Provider API keys do not affect tool permission or data scope.
- Existing Academic and Finance source reports remain the source of truth.
- The AI module may adapt source results into a common output contract, but it
  must not fork report business logic into a second AI-specific truth.
- Source rows are internal implementation detail. Tool output exposes bounded
  aggregates and safe source references only.
- Missing data, truncated scans, unavailable source reports, and resolver errors
  must be represented explicitly through warnings or safe error codes. The tool
  must not fabricate numbers.

## Application Flow

Backend-only `query_metrics` execution flow:

```text
Tool request from deterministic evaluation or future AgentRunner
  -> ToolDispatcher receives tool name, arguments, actor, campus, trace context
  -> ToolRegistry resolves query_metrics
  -> QueryPlan::fromArray parses arguments as untrusted input
  -> QueryPlanValidator validates metric, filters, groupings, permission, campus scope, limits
  -> denied validation result is audit-recorded and returned without source execution
  -> allowed validation result is passed to QueryMetricsTool
  -> QueryMetricsTool selects the resolver for the validated metric key
  -> resolver calls the existing Academic or Finance source query/report
  -> resolver normalizes aggregate summary, breakdowns, source references, warnings
  -> QueryMetricsTool records tool-call audit through AiAuditRecorder
  -> ToolDispatcher returns QueryMetricsResult
```

Metric resolver flows:

```text
academic_student_status_count / academic_defer_count
  -> GetStudentStatusBySemesterQuery
  -> aggregate mapped source rows by accepted grouping
  -> omit student identifiers and names from output
  -> emit source report academic.reporting.student-status-by-semester

finance_collection_summary
  -> ListCollectionProgressQuery
  -> use source summary and accepted breakdowns
  -> emit source report finance.reporting.collection-progress

finance_fee_monitor_summary
  -> ListFeeMonitorQuery
  -> use source summary and accepted breakdowns derived from report rows
  -> emit source report finance.reporting.fee-monitor

finance_dng_lifecycle_attention
  -> ListDngLifecycleQuery
  -> use source summary, accepted breakdowns, and meta.truncated/scan_cap
  -> emit source report finance.reporting.dng-lifecycle
```

Denied and failed flows:

- `unsupported_tool`: the registry cannot resolve the requested tool.
- `invalid_query_plan_schema`: QueryPlan rejects top-level or nested forbidden
  keys such as SQL, tables, columns, joins, or row identifiers.
- `unsupported_metric`, `unsupported_filter`, `unsupported_group_by`,
  `invalid_filter_value`, `missing_required_filter`,
  `unsupported_query_plan_option`, `forbidden_by_permission`,
  `forbidden_by_campus_scope`, and `max_record_limit_exceeded`: propagated from
  the existing QueryPlanValidator.
- `metric_resolver_missing`: catalog metric exists but no resolver is registered.
- `source_query_failed`: source query/report throws or cannot produce a safe
  aggregate result.
- `source_result_truncated`: source report deliberately caps scanning or result
  size; the tool may still return an allowed result when the source report marks
  the result as partial and the warning is visible.
- `source_parity_not_defined`: a metric lacks a deterministic parity assertion
  and must not be exposed beyond tests.

## Interface Contract

This story does not add user-facing routes, API routes, or frontend pages.

Internal tool request shape:

```json
{
    "tool": "query_metrics",
    "arguments": {
        "metric": "finance_collection_summary",
        "filters": {
            "semester": "current",
            "fee_type": "tuition_term"
        },
        "group_by": ["program"],
        "options": {
            "include_rows": false,
            "limit": 100
        }
    }
}
```

The `arguments` object must remain compatible with the existing QueryPlan v1
schema. It must not include raw SQL, table names, column names, joins,
relationship traversal, arbitrary sorting, arbitrary includes, source-row
requests, student ids, cross-campus expansion, or unbounded limits.

Internal result shape:

```json
{
    "allowed": true,
    "tool": "query_metrics",
    "tool_schema_version": "query_metrics:v1",
    "catalog_version": "metric-catalog:v1",
    "metric": "finance_collection_summary",
    "normalized_filters": {
        "semester_id": 123,
        "fee_type": "tuition_term"
    },
    "group_by": ["program"],
    "campus_scope_snapshot": {
        "campus_ids": [1]
    },
    "summary": {
        "student_count": 42,
        "billed_total": 1000000,
        "paid_total": 750000,
        "outstanding_total": 250000
    },
    "groups": [
        {
            "key": "program",
            "value": "IT",
            "metrics": {
                "student_count": 10,
                "outstanding_total": 50000
            }
        }
    ],
    "source_references": [
        {
            "source_report": "finance.reporting.collection-progress",
            "source_reference_policy": "report_summary_with_filters"
        }
    ],
    "record_count": 42,
    "freshness": {
        "computed_at": "2026-06-21T00:00:00+07:00",
        "rule": "computed_at_request_time"
    },
    "hidden_sections": [],
    "warnings": [],
    "confidence": {
        "level": "high",
        "basis": "source_report_parity"
    },
    "safe_error_code": null
}
```

Denied result shape:

```json
{
    "allowed": false,
    "tool": "query_metrics",
    "tool_schema_version": "query_metrics:v1",
    "catalog_version": "metric-catalog:v1",
    "metric": "finance_collection_summary",
    "summary": null,
    "groups": [],
    "source_references": [],
    "record_count": 0,
    "hidden_sections": [],
    "warnings": [],
    "confidence": {
        "level": "none",
        "basis": "not_executed"
    },
    "safe_error_code": "forbidden_by_permission"
}
```

The output shape may be represented by PHP value objects or arrays, but tests
must assert the stable public keys expected by later AgentRunner and evaluation
flows.

Laravel AI SDK boundary:

- This story may define an adapter shape that can later implement
  `Laravel\Ai\Contracts\Tool` when `AI-MOD-005` accepts Agent integration.
- This story must not expose the tool to an LLM provider directly, add an SDK
  Agent, stream responses, or execute provider-native tools.
- If the SDK package is unavailable or its generated `app/Ai/Tools` convention
  conflicts with the Swinx module boundary, keep the Swinx-owned internal tool
  contract and record the adapter decision in the implementation notes.

## Data Model

No new database table is expected.

The implementation should use existing AI audit/evaluation tables:

- `ai_agent_traces` stores `catalog_version`, `tool_schema_version`, trace
  status, duration, and safe error codes.
- `ai_tool_calls` stores tool name, tool schema version, redacted arguments,
  permission result, campus scope snapshot, hidden sections, record count,
  source references, redacted result summary, status, duration, and safe error.
- `ai_evaluation_cases`, `ai_evaluation_runs`, and `ai_evaluation_results`
  store deterministic source-parity evidence.

Pause before implementation if a new table, retention policy, index, or
backfill becomes necessary.

## UI / Platform Impact

No user-facing UI or route is added in this story.

No student or lecturer portal behavior is changed. No `FE/student-nuxt` or
`FE/lecturer-nuxt` files should be touched.

No queue worker, scheduled job, broadcast channel, MCP server, external provider
tool, or live LLM call is required for the MVP.

## Observability

Every tool dispatch must record a product audit entry when trace context is
available. Deterministic evaluation execution must also preserve enough
evidence to replay why a result passed or failed.

Minimum audit fields:

- user id and current campus context via conversation/trace linkage;
- tool name `query_metrics`;
- tool schema version `query_metrics:v1`;
- catalog version `metric-catalog:v1`;
- redacted arguments;
- permission result;
- campus scope snapshot;
- hidden sections;
- record count or estimated record count;
- source references;
- redacted result summary;
- status: `completed`, `denied`, `failed`, or `partial`;
- duration in milliseconds;
- safe error code when present;
- truncation warning when source result is partial.

Operational logs may be used for unexpected source-query exceptions, but product
audit records remain the source of truth for AI tool-call evidence.

## Alternatives Considered

1. Expose `query_metrics` directly as a Laravel AI SDK Tool in this story.
    - Deferred because no staff AgentRunner/chat loop is accepted yet. The safe
      slice is an internal deterministic tool contract that S-005 can adapt to
      the SDK agent surface.
2. Let the AI generate SQL for metric questions.
    - Rejected because it bypasses MetricCatalog, permissions, campus scope,
      source-report parity, and audit guardrails.
3. Use one generic reflection-based resolver over source query classes.
    - Rejected because existing Academic and Finance reports have different
      filter, summary, row, and meta shapes. Each metric needs an explicit
      adapter so output stays bounded and testable.
4. Return source report rows directly and let the Agent summarize them.
    - Rejected because row dumps increase PII exposure and make answer quality
      dependent on LLM summarization. The tool should return deterministic
      aggregates and safe references.
