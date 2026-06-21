# Validation

## Proof Strategy

Validation must prove that the metric semantic layer is allowlisted,
aggregate-only, permission-aware, campus-scoped, auditable, and tied to existing
Swinx reports before any future `query_metrics` tool can execute a read.

The requirements packet itself is validated by Harness registration, file
presence, incomplete-marker review, and whitespace checks.

Future implementation validation must use deterministic tests and fixtures.
It must not require live provider credentials, live LLM calls, production data,
or unbounded source-row dumps.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | MetricCatalog returns only accepted metric keys; glossary maps synonyms to canonical keys without executable logic; QueryPlan parser rejects unknown fields, SQL/table/column payloads, malformed filters, unsupported options, and unbounded limits; QueryPlanValidator accepts valid plans and denies unsupported metric/filter/grouping/value combinations with deterministic safe error codes. |
| Integration | Validator resolves current/selected semester filters, current campus scope, and staff permission context; denied permission and denied campus scope produce audited-safe validation results; each selected metric maps to an existing source query/report; evaluation cases are stored with expected tool calls, source references, catalog version, and tool-schema version. |
| E2E | No user-facing E2E is required for this backend-only catalog/query-plan story. Later staff chat must prove source citations, filters, scope, hidden sections, and confidence in the visible answer. |
| Platform | No live provider credentials, provider network calls, student portal changes, lecturer portal changes, queue workers, or external systems are required. |
| Performance | Validator rejects plans that exceed metric max-record/max-result limits; catalog lookup remains in-memory or code-defined for v1; metric parity tests use small deterministic fixture scopes. |
| Logs/Audit | Validation within agent/evaluation flows records catalog version, tool schema version, redacted arguments, permission result, campus scope, hidden sections, safe error code, and source references through existing AI audit/evaluation records. |

## Fixtures

Future implementation tests should define:

- internal staff user with AI metric permission for one campus;
- internal staff user without AI metric permission;
- optional admin actor for future governance-only assertions;
- campus-scoped Academic records for semester/status/defer count cases;
- campus-scoped Finance records for collection summary, fee monitor, and DNG
  lifecycle cases;
- selected/current semester fixtures;
- program, intake semester, cohort, fee type, balance state, generation state,
  payment state, attention bucket, and flow state filter samples;
- valid query plans for each accepted metric;
- invalid query plans containing unsupported metric, unsupported filter,
  unsupported group_by, invalid value type, raw SQL, table/column fields,
  cross-campus attempt, and unbounded limit;
- evaluation cases for:
  - current-term deferred student count by program;
  - selected-semester student status count;
  - finance collection outstanding summary by program or fee type;
  - fee monitor missing/generated/blocked summary by expected fee type;
  - DNG/payment lifecycle attention summary by attention bucket.

## Commands

Requirements validation for this story packet:

```text
./scripts/harness query matrix | rg -F "AI-MOD-003-metric-catalog-query-plan"
test -f docs/stories/E-ai-module-2026-06/S-003-metric-catalog-query-plan/overview.md
test -f docs/stories/E-ai-module-2026-06/S-003-metric-catalog-query-plan/design.md
test -f docs/stories/E-ai-module-2026-06/S-003-metric-catalog-query-plan/execplan.md
test -f docs/stories/E-ai-module-2026-06/S-003-metric-catalog-query-plan/validation.md
git diff --check
```

Future implementation validation:

```text
docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev exec app sh -lc './vendor/bin/pest tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php --colors=never'
./scripts/dev.sh composer exec pint -- --format agent app/Modules/AI/Support/BusinessGlossary.php app/Modules/AI/Support/MetricCatalog.php app/Modules/AI/Support/QueryPlan.php app/Modules/AI/Support/QueryPlanValidationResult.php app/Modules/AI/Support/QueryPlanValidator.php app/Modules/AI/Support/StaffMetricQuestionDataset.php config/permission.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php
git diff --check
```

If implementation changes frontend files, also run the targeted frontend lint,
format, and type checks required by the changed surface. If no frontend files are
touched, frontend validation is not applicable for this story.

## Acceptance Evidence

- Harness intake recorded for this requirements step as Intake #138.
- Story packet path:
  `docs/stories/E-ai-module-2026-06/S-003-metric-catalog-query-plan/`.
- Harness story row is registered as
  `AI-MOD-003-metric-catalog-query-plan` with high-risk lane.
- Portal impact remains `none`.
- Backend-only runtime implementation added `BusinessGlossary`, `MetricCatalog`,
  `QueryPlan`, `QueryPlanValidationResult`, `QueryPlanValidator`,
  `StaffMetricQuestionDataset`, `view_ai_metrics`, and
  `AiMetricCatalogQueryPlanTest`.
- `AiMetricCatalogQueryPlanTest`, `AiAuditFoundationTest`, and
  `AiProviderSettingsTest` passed together: 18 tests / 341 assertions.
- Targeted Pint passed for the touched AI support, permission config, and AI
  test files.
- Path-limited `git diff --check` passed for the touched AI/story files.
- No frontend, route, migration, provider-call, live-LLM, `query_metrics`
  execution, MCP, write/action, student API, lecturer API, or portal code was
  changed.
