# Validation

## Proof Strategy

This story is done only when `compare_metrics` is proven as a read-only,
allowlisted, permission-aware, campus-scoped, source-parity comparison tool.
The proof must show that Swinx computes all comparison numbers and deltas
deterministically, while the live provider can only request the tool and
synthesize wording from redacted results.

Automated tests must not require live provider credentials or network calls.
Use Laravel AI SDK fakes or project-local fakes for live-provider behavior.

## Test Plan

| Layer       | Cases                                                                                                                                                                                                                                                                                                                       |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | MetricComparisonPlan rejects SQL/table/column/join/raw-row payloads, unsupported top-level keys, invalid options, unsupported comparison modes, and malformed base/comparison scopes.                                                                                                                                       |
| Unit        | MetricComparisonPeriodResolver resolves current and previous semesters through Swinx-owned logic and returns safe errors for missing, ambiguous, stale, unsupported, or cross-campus period context.                                                                                                                        |
| Unit        | MetricComparisonValidator allows only compare-capable MetricCatalog v1 metrics and denies `finance_dng_lifecycle_attention` until accepted period semantics exist.                                                                                                                                                          |
| Unit        | MetricComparisonValidator builds two QueryPlan-shaped payloads and validates both through QueryPlanValidator before any source read.                                                                                                                                                                                        |
| Unit        | MetricDeltaCalculator computes absolute delta, percentage delta, direction, trend status, zero-baseline behavior, null/missing-value behavior, and deterministic rounding.                                                                                                                                                  |
| Unit        | GroupedComparisonAligner aligns groups by stable key/label and surfaces missing base or comparison groups as warnings or partial results.                                                                                                                                                                                   |
| Integration | Authorized staff with `view_ai_metrics` can compare `academic_defer_count` current term versus previous term and receive bounded aggregate deltas with source references for both scopes.                                                                                                                                   |
| Integration | Authorized finance staff can compare `finance_collection_summary` or `finance_fee_monitor_summary` current term versus previous term and receive deterministic numeric field deltas.                                                                                                                                        |
| Integration | Staff without `view_ai_metrics`, cross-campus filters, unsupported filters, unsupported groupings, unbounded limits, and raw-row options are denied before source execution and audited safely.                                                                                                                             |
| Integration | Missing previous term, unsupported metric, incompatible filters, incompatible groupings, divide-by-zero baseline, missing groups, and truncated source results return stable safe error/warning contracts.                                                                                                                  |
| Integration | `compare_metrics` records audit evidence for completed, denied, failed, and partial results, including compared scopes, source reports, freshness, warnings, confidence, and safe errors.                                                                                                                                   |
| Integration | If child `query_metrics` calls are audited, the parent comparison audit summary cites or links those source scopes without exposing raw child payloads.                                                                                                                                                                     |
| Integration | LiveStaffCopilotAgent fake planner can request `compare_metrics`; unsafe planner proposals are denied server-side; final answer uses only redacted comparison result facts.                                                                                                                                                 |
| Integration | Deterministic fallback handles clear comparison prompts and asks clarification or returns unsupported when periods, filters, context, or metric intent are ambiguous.                                                                                                                                                       |
| Integration | AI-MOD-008 context-derived "same filters", "current term", or "previous term" inputs are revalidated before execution; stale, ambiguous, denied, or cross-campus context does not trigger source reads.                                                                                                                     |
| E2E         | Optional browser smoke for `/ai/copilot`: ask a current-vs-previous academic or finance comparison and verify visible compared scopes, source evidence, warnings, and confidence. If browser auth/setup is not available, record the gap explicitly.                                                                        |
| Platform    | No portal files changed. Run `./scripts/portal-status.sh` only if implementation discovery unexpectedly touches student/lecturer API behavior, which this story should not do.                                                                                                                                              |
| Performance | Comparison performs at most the bounded number of source metric reads per request, honors existing metric max-record limits, and does not scan all conversations or source rows.                                                                                                                                            |
| Logs/Audit  | AI audit records do not contain raw provider bodies, API keys, auth headers, SQL, table names, source model names, hidden facts, raw ledger rows, contact/address/parent/emergency-contact data, private notes, gateway payloads, attachments, raw attendance sessions, raw score component rows, or arbitrary source rows. |

## Fixtures

Use deterministic fixtures for:

- staff user with `view_ai_metrics`;
- staff user without `view_ai_metrics`;
- current campus and another campus;
- current active semester and a previous semester;
- a campus with no resolvable previous semester;
- Academic status/defer source rows for current and previous terms;
- Finance collection source summaries for current and previous terms;
- Finance fee-monitor source summaries for current and previous terms;
- supported groupings such as program, intake semester, fee type, balance
  state, and generation/payment state;
- group values that exist on both sides;
- group values that exist only in base or only in comparison;
- numeric fields with zero, null, increased, decreased, unchanged, and missing
  values;
- invalid payloads with raw SQL, table names, column names, joins, raw rows,
  unsupported filters, unsupported group_by, and unbounded limits;
- denied, failed, partial, and completed source metric outcomes;
- fake live-provider planner output for accepted and unsafe `compare_metrics`
  requests;
- conversation context records from `AI-MOD-008` for same-filter/current-term
  follow-ups when that dependency is implemented.

## Commands

Story-packet validation commands:

```text
test -f docs/stories/E-ai-module-2026-06/S-009-compare-metrics-tool/overview.md
test -f docs/stories/E-ai-module-2026-06/S-009-compare-metrics-tool/design.md
test -f docs/stories/E-ai-module-2026-06/S-009-compare-metrics-tool/execplan.md
test -f docs/stories/E-ai-module-2026-06/S-009-compare-metrics-tool/validation.md
./scripts/harness query matrix | rg -F "AI-MOD-009-compare-metrics-tool"
rg -n "TB[D]|TO[D]O|FIX[M]E" docs/stories/E-ai-module-2026-06/S-009-compare-metrics-tool
git diff --check -- docs/stories/E-ai-module-2026-06/S-009-compare-metrics-tool
```

Expected commands after runtime implementation:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiCompareMetricsToolTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh npm run type-check
```

If repo-wide frontend checks hit known baseline or memory limits, record the
exact failure and run targeted checks for touched Vue/TS files.

## Acceptance Evidence

No runtime implementation has landed yet. Add commands, outputs, screenshots,
or audit snippets here after validation exists.
