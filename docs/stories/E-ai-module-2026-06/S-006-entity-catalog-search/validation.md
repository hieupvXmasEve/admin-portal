# Validation

## Proof Strategy

Validation must prove that entity search is internal, read-only,
permission-aware, campus-scoped, PII-limited, bounded, deterministic, and
audited.

The story packet itself is validated by Harness registration, file presence,
incomplete-marker review, and whitespace checks.

Runtime validation must use deterministic tests. It must not require live
provider credentials, live LLM calls, production data, MCP servers, vector
stores, embeddings, queue workers, or portal code.

## Test Plan

| Layer       | Cases                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | EntityCatalog exposes `entity-catalog:v1`, `search_entities:v1`, accepted entity keys, aliases, permissions, search fields, safe result fields, max limits, and hidden sections; entity-search argument validator rejects unsupported types, unsupported filters, too-short queries, raw SQL/include options, browser-provided `campus_id`, and over-limit requests; scoped entity references do not expose raw primary keys.                                                      |
| Integration | ToolRegistry registers `query_metrics` and `search_entities`; authorized staff can search student/program/semester/course-offering candidates; staff without `view_ai_metrics` cannot submit; staff with AI access but missing entity permission receives a denied result; cross-campus students/course offerings are hidden; results omit forbidden PII fields; truncation warnings appear at max limit; tool-call audit is recorded for completed/denied/failed/partial results. |
| E2E         | Browser proof should cover the staff copilot page, an entity-search prompt, visible candidate list, source/scope/confidence evidence, hidden-section notice, and safe denied/unsupported state if local browser tooling is available.                                                                                                                                                                                                                                              |
| Platform    | No student portal, lecturer portal, public API route, queue worker, MCP server, vector store, embedding index, live provider credential, or external side-effect tool is required.                                                                                                                                                                                                                                                                                                 |
| Performance | Default result limit is 5 and hard max is 10; broad search requires a safe minimum query length; source readers select only allowlisted columns; no unbounded roster/profile/source-row payload is sent to Vue props or audit summaries.                                                                                                                                                                                                                                           |
| Logs/Audit  | Conversation, message, trace, and tool-call records are correlated; tool calls record redacted arguments, requested entity types, permission result, campus scope, hidden sections, source references, result count, result limit, redacted result summary, status, duration, and safe error code.                                                                                                                                                                                 |

## Fixtures

Implementation tests should define:

- internal staff user with `view_ai_metrics`, `view_student`, `view_program`,
  `view_semester`, and `view_course_offering`;
- internal staff user with `view_ai_metrics` but without `view_student`;
- internal staff user without `view_ai_metrics`;
- current campus and another campus;
- active/current semester and another semester;
- current-campus student with `student_id`, `full_name`, `program_id`,
  `campus_id`, `intake_semester_id`, and `status`;
- other-campus student with the same or similar keyword to prove cross-campus
  hiding;
- program with code/name;
- semester with code/name and `is_active`;
- course offering with `section_code`, `unit`, `semester_id`, `campus_id`, and
  `course_status`;
- enough matching students or programs to trigger the result-limit truncation
  warning;
- unsupported entity type request;
- too-short broad query;
- request with forbidden `campus_id`, `include_profiles`, `include_rows`, raw
  table/column, or relationship include argument;
- existing staff copilot conversation for the authorized user.

## Commands

Requirements validation for this story packet:

```text
./scripts/harness query matrix --numeric | rg -F "AI-MOD-006-entity-catalog-search"
test -f docs/stories/E-ai-module-2026-06/S-006-entity-catalog-search/overview.md
test -f docs/stories/E-ai-module-2026-06/S-006-entity-catalog-search/design.md
test -f docs/stories/E-ai-module-2026-06/S-006-entity-catalog-search/execplan.md
test -f docs/stories/E-ai-module-2026-06/S-006-entity-catalog-search/validation.md
rg -n "TB[D]|TO[D]O|FIX[M]E" docs/stories/E-ai-module-2026-06/S-006-entity-catalog-search docs/stories/E-ai-module-2026-06/README.md
git diff --check -- docs/stories/E-ai-module-2026-06/S-006-entity-catalog-search
```

Runtime validation after implementation:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiEntityCatalogSearchTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm exec eslint -- resources/js/pages/AI/StaffCopilot/Index.vue
./scripts/dev.sh npm exec -- prettier --check resources/js/pages/AI/StaffCopilot/Index.vue
git diff --check -- app/Modules/AI app/Modules/Academic app/Shared/Contracts resources/js/pages/AI tests/Feature/AI docs/stories/E-ai-module-2026-06
```

If browser tooling is available after implementation, run a local dev server and
capture browser proof for the copilot entity-search answer. If it is
unavailable, record the gap explicitly.

## Acceptance Evidence

- Harness intake recorded for this story as Intake #149.
- Runtime implementation intake recorded as Intake #150.
- Harness story row registered for `AI-MOD-006-entity-catalog-search`.
- Story packet path:
  `docs/stories/E-ai-module-2026-06/S-006-entity-catalog-search/`.
- Portal impact remains `none`.
- Runtime implementation adds EntityCatalog v1, `search_entities:v1`,
  Academic shared entity-search reader, tool registry/dispatcher support,
  deterministic copilot entity lookup planning, and Vue candidate-list rendering.
- Deterministic entity-search test passed:
  `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiEntityCatalogSearchTest.php`
  with 6 tests and 187 assertions.
- AI regression suite passed:
  `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php`
  with 34 tests and 771 assertions.
- Browser validation was not run in this work window; Inertia page props are
  covered by feature tests and the Vue candidate-list rendering is covered by
  targeted frontend checks.
