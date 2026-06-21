# Validation

## Proof Strategy

Validation must prove that `query_metrics` is read-only, allowlisted,
permission-aware, campus-scoped, aggregate-only, source-parity checked, and
audited before a later staff copilot can call it.

The requirements packet itself is validated by Harness registration, file
presence, incomplete-marker review, and whitespace checks.

Implementation validation uses deterministic tests and fixtures. It does not
require live provider credentials, live LLM calls, production data, MCP servers,
vector stores, or frontend/browser flows.

## Test Plan

| Layer       | Cases                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | ToolRegistry exposes only `query_metrics`; unknown tool names return `unsupported_tool`; ToolDispatcher parses arguments and returns deterministic safe errors; QueryMetricsTool validates through QueryPlanValidator before execution; denied plans never call source queries; missing metric resolver returns `metric_resolver_missing`; result objects expose stable allowed/denied keys.                                                   |
| Integration | Authorized current-campus staff can execute accepted MetricCatalog v1 plans; unauthorized staff and cross-campus plans are denied and audited; each metric resolver delegates to the existing source query/report; Academic status/defer resolvers aggregate mapped source rows without student identifiers; Finance resolvers use source summaries/breakdowns/meta; DNG truncation is surfaced as partial/warning when source scan is capped. |
| E2E         | No user-facing E2E is required for this backend-only tool story. Later staff chat must prove visible answer sources, filters, scope, hidden-section notices, confidence, and dashboard parity.                                                                                                                                                                                                                                                 |
| Platform    | No live provider credentials, provider network calls, queue workers, MCP server, student portal changes, lecturer portal changes, or external systems are required.                                                                                                                                                                                                                                                                            |
| Performance | Tool execution respects metric max-record/max-result limits; DNG lifecycle scan cap warnings are surfaced; resolvers avoid unbounded row dumps and do not increase source-report scan caps without explicit acceptance.                                                                                                                                                                                                                        |
| Logs/Audit  | Accepted, denied, failed, and partial tool calls record redacted arguments, permission result, campus scope, hidden sections, source references, record count, redacted result summary, status, duration, tool schema version, catalog version, and safe error code through existing AI audit records.                                                                                                                                         |

## Fixtures

Future implementation tests should define:

- internal staff user with `view_ai_metrics` permission for one campus;
- internal staff user without `view_ai_metrics`;
- current campus and another campus;
- active/current semester and selected semester fixtures;
- Academic student/status/action fixtures for:
    - current-term deferred count by program;
    - selected-semester student status count by status;
    - cross-campus denial;
- Finance collection fixtures with invoice lines, payments, discounts,
  outstanding, overdue, overpaid, unapplied, fee type, program, and balance
  state samples;
- Fee monitor fixtures for missing, generated, blocked, paid, voided, and
  skipped expected fee states;
- DNG lifecycle fixtures for attention bucket, flow state, paid uninvoiced,
  failed request, cancelled, and truncated/scan-cap scenarios;
- valid `query_metrics` plans for each accepted metric;
- invalid plans containing unsupported tool, unsupported metric, unsupported
  filter, unsupported grouping, invalid filter value, missing required filter,
  raw SQL, table/column fields, include/source-row attempts, cross-campus
  attempts, and unbounded limits;
- audit trace/conversation fixtures when testing `AiAuditRecorder` integration;
- deterministic evaluation cases from `StaffMetricQuestionDataset`.

## Commands

Requirements validation for this story packet:

```text
./scripts/harness query matrix | rg -F "AI-MOD-004-query-metrics-tool-mvp"
test -f docs/stories/E-ai-module-2026-06/S-004-query-metrics-tool-mvp/overview.md
test -f docs/stories/E-ai-module-2026-06/S-004-query-metrics-tool-mvp/design.md
test -f docs/stories/E-ai-module-2026-06/S-004-query-metrics-tool-mvp/execplan.md
test -f docs/stories/E-ai-module-2026-06/S-004-query-metrics-tool-mvp/validation.md
rg -n "TB[D]|TO[D]O|FIX[M]E" docs/stories/E-ai-module-2026-06/S-004-query-metrics-tool-mvp docs/stories/E-ai-module-2026-06/README.md
git diff --check -- docs/stories/E-ai-module-2026-06/README.md docs/stories/E-ai-module-2026-06/S-004-query-metrics-tool-mvp
```

Implementation validation:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php
./scripts/dev.sh composer exec pint -- --format agent app/Modules/AI/Support/Tools tests/Feature/AI/AiQueryMetricsToolTest.php
git diff --check -- app/Modules/AI tests/Feature/AI docs/stories/E-ai-module-2026-06
```

If implementation changes frontend files, also run the targeted frontend lint,
format, and type checks required by the changed surface. If no frontend files
are touched, frontend validation is not applicable for this story.

## Acceptance Evidence

- Harness intake recorded for this requirements step as Intake #140.
- Story packet path:
  `docs/stories/E-ai-module-2026-06/S-004-query-metrics-tool-mvp/`.
- Portal impact remains `none`.
- Backend-only implementation added `ToolRegistry`, `ToolDispatcher`,
  `QueryMetricsTool`, `QueryMetricsResult`, `AiToolDefinition`,
  `QueryMetricsExecutionContext`, metric resolver contracts, and resolvers for
  all MetricCatalog v1 metrics.
- `AiQueryMetricsToolTest` passed: 6 tests / 142 assertions.
- AI regression suite passed with `AiQueryMetricsToolTest`,
  `AiMetricCatalogQueryPlanTest`, `AiAuditFoundationTest`, and
  `AiProviderSettingsTest`: 24 tests / 483 assertions.
- Targeted Pint passed after formatting AI tool classes and
  `AiQueryMetricsToolTest`.
- No frontend, route, migration, provider-call, live-LLM, MCP, write/action,
  student API, lecturer API, or portal code was changed.
