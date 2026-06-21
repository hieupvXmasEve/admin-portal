# Validation

## Proof Strategy

Validation must prove that the staff copilot page is internal, read-only,
permission-aware, campus-scoped, source-cited, structured, and audited.

The story packet itself is validated by Harness registration, file presence,
incomplete-marker review, and whitespace checks.

Runtime validation must use deterministic tests. It must not require live
provider credentials, live LLM calls, production data, MCP servers, vector
stores, queue workers, or portal code.

## Test Plan

| Layer       | Cases                                                                                                                                                                                                                                                                                                                                                                                     |
| ----------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | StaffCopilotAgentRunner maps canonical staff questions to the expected MetricCatalog v1 metric and QueryPlan-shaped payload; unsupported questions return `unsupported_staff_question` without ToolDispatcher execution; StaffCopilotAnswer exposes stable answer/evidence keys; prompt suggestions come from StaffMetricQuestionDataset.                                                   |
| Integration | Authorized current-campus staff can load the copilot page and submit a supported question; unauthorized staff are forbidden; submitted questions create conversation, user message, trace, audited tool call, assistant message, and completed trace; invalid/unsupported questions create safe failed traces without source execution; source references and filters come from QueryMetricsResult. |
| E2E         | Browser proof should cover page render, suggested prompt click, message submission, visible answer card, source section, confidence, hidden-section notice, and safe unsupported-question state if the local browser stack is available.                                                                                                                                                    |
| Platform    | No student portal, lecturer portal, public API route, queue worker, MCP server, vector store, live provider credential, or external side-effect tool is required.                                                                                                                                                                                                                         |
| Performance | One message dispatch executes at most one `query_metrics` call in this MVP; tool max-record limits remain enforced by QueryPlanValidator and QueryMetricsTool; no unbounded source row payload is sent to Vue props.                                                                                                                                                                      |
| Logs/Audit  | Conversation, message, trace, and tool-call records are correlated; tool calls record redacted arguments, permission result, campus scope, source references, record count, redacted result summary, status, duration, and safe error code; user/assistant message payloads do not store unredacted secrets.                                                                                 |

## Fixtures

Implementation tests should define:

- internal staff user with `view_ai_metrics`;
- internal staff user without `view_ai_metrics`;
- current campus and another campus;
- active/current semester;
- mocked or fixture-backed `AiFinanceMetricReader` collection summary response;
- mocked or fixture-backed `AiAcademicMetricReader` status/defer response;
- a current-campus open AI conversation for the authorized user;
- canonical staff prompt cases from StaffMetricQuestionDataset;
- unsupported natural-language question with no MetricCatalog v1 match;
- valid and invalid message submission payloads;
- existing `query_metrics` tool audit trace context.

## Commands

Requirements validation for this story packet:

```text
./scripts/harness query matrix | rg -F "AI-MOD-005-staff-copilot-chat-mvp"
test -f docs/stories/E-ai-module-2026-06/S-005-staff-copilot-chat-mvp/overview.md
test -f docs/stories/E-ai-module-2026-06/S-005-staff-copilot-chat-mvp/design.md
test -f docs/stories/E-ai-module-2026-06/S-005-staff-copilot-chat-mvp/execplan.md
test -f docs/stories/E-ai-module-2026-06/S-005-staff-copilot-chat-mvp/validation.md
rg -n "TB[D]|TO[D]O|FIX[M]E" docs/stories/E-ai-module-2026-06/S-005-staff-copilot-chat-mvp docs/stories/E-ai-module-2026-06/README.md
git diff --check -- docs/stories/E-ai-module-2026-06/README.md docs/stories/E-ai-module-2026-06/S-005-staff-copilot-chat-mvp
```

Runtime validation after implementation:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotChatTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
git diff --check -- app/Modules/AI resources/js docs/stories/E-ai-module-2026-06 tests/Feature/AI
```

If browser tooling is available after implementation, run a local dev server and
capture browser proof for the copilot page. If it is unavailable, record the
gap explicitly.

## Acceptance Evidence

- Harness intake recorded for this story as Intake #148.
- Harness story row registered for `AI-MOD-005-staff-copilot-chat-mvp`.
- User approved deterministic MVP and `laravel/ai` dependency addition before
  runtime implementation.
- Story packet path:
  `docs/stories/E-ai-module-2026-06/S-005-staff-copilot-chat-mvp/`.
- Portal impact remains `none`.
- Requirements packet validation passed:
  - `./scripts/harness query matrix --numeric | rg -F "AI-MOD-005-staff-copilot-chat-mvp"`.
  - File presence checks for `overview.md`, `design.md`, `execplan.md`, and
    `validation.md`.
  - `rg -n "TB[D]|TO[D]O|FIX[M]E"` returned no matches for the story packet and
    AI module README.
  - `git diff --check -- docs/stories/E-ai-module-2026-06/README.md docs/stories/E-ai-module-2026-06/S-005-staff-copilot-chat-mvp`.
- Runtime TDD evidence:
  - RED: `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotChatTest.php` failed with `Route [ai.copilot.index] not defined.`
  - GREEN targeted: `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotChatTest.php` passed with 4 tests and 95 assertions.
  - AI regression: `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php` passed with 28 tests and 578 assertions.
  - Harness verify: `./scripts/harness story verify AI-MOD-005-staff-copilot-chat-mvp` passed by running the same AI regression suite with 28 tests and 578 assertions.
- Runtime route/dependency evidence:
  - `./scripts/dev.sh artisan route:list --name=ai --except-vendor` shows `ai.copilot.index` and `ai.copilot.messages.store`.
  - `./scripts/dev.sh composer show laravel/ai --no-ansi` shows `laravel/ai v0.7.2`.
- Static check evidence:
  - `./scripts/dev.sh composer exec pint -- --format agent ...` passed on AI-MOD-005 PHP files.
  - `./scripts/dev.sh npm exec eslint -- resources/js/pages/AI/StaffCopilot/Index.vue resources/js/constants/system-routes.ts resources/js/utils/routes.ts resources/js/constants/menu-sidebar.ts` passed.
  - `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/AI/StaffCopilot/Index.vue resources/js/constants/system-routes.ts resources/js/utils/routes.ts resources/js/constants/menu-sidebar.ts` passed.
  - `git diff --check -- app/Modules/AI resources/js docs/stories/E-ai-module-2026-06 docs/system-architecture.md docs/project-overview-pdr.md docs/codebase-summary.md tests/Feature/AI composer.json composer.lock` passed.
- Known validation gaps:
  - Full `./scripts/dev.sh npm run type-check` was attempted twice and was killed with exit code 137.
  - Direct single-file `vue-tsc` is not valid in this repo setup because it does not load project alias resolution.
  - Full `./scripts/dev.sh npm run format:check` fails on existing unrelated resource formatting and syntax errors in `resources/js/pages/Forms/Admin/Create.vue` and `resources/js/pages/Forms/Admin/Edit.vue`.
  - Full ESLint dry-run fails on existing unrelated lint errors across `.agents`, `.claude`, nested `FE/student-nuxt`, `docs-site`, and many pre-existing `resources/js` files. Targeted AI-MOD-005 ESLint passed.
  - Browser proof was not captured in this work window; feature tests verify the Inertia page contract and message flow.
