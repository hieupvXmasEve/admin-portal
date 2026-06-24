# Validation

## Proof Strategy

Validation must prove that live provider mode improves Staff Copilot from fixed
prompt parsing to natural-language planning while preserving Swinx safety rules:

- no direct DB/SQL access;
- all business-data reads go through ToolDispatcher;
- provider calls are gated by staff provider settings and permissions;
- model-proposed tool calls are untrusted until server validation passes;
- prompts, arguments, provider usage, tool calls, and answers are audited and
  redacted;
- automated tests use fakes, not live provider credentials.

Runtime validation must cover live success, deterministic fallback, denied tool
calls, invalid model output, provider failures, and evidence rendering.

## Test Plan

| Layer       | Cases                                                                                                                                                                                                                                                                                                                                                                                                     |
| ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | Prompt context excludes secrets/raw rows/schema dumps; catalog summaries include only allowed tools and versions; planner output validator accepts valid `query_metrics`/`search_entities` calls and rejects unsupported tools, SQL/table/column payloads, unsafe includes, over-limit requests, cross-campus arguments, and malformed JSON; final-answer validator requires source references for facts. |
| Integration | Authorized staff with valid provider setting can submit a natural-language question; fake SDK returns planner output; ToolDispatcher executes accepted calls; fake SDK synthesizes final answer; conversation/message/trace/tool-call/provider-usage records are written. Staff without `view_ai_metrics` is denied. Staff lacking entity permission receives hidden-section evidence.                    |
| E2E         | Staff Copilot page shows live provider mode, accepts a natural-language prompt, renders answer with tool evidence/source references/confidence, and renders safe fallback/clarification states.                                                                                                                                                                                                           |
| Platform    | Automated tests use SDK fakes or local adapter fakes; no live provider credentials, provider network calls, queue worker, MCP server, vector store, or portal code is required.                                                                                                                                                                                                                           |
| Performance | Per-message tool-call count is bounded; provider timeout is enforced; prompt context size is bounded; model output size is bounded; fallback does not block indefinitely.                                                                                                                                                                                                                                 |
| Logs/Audit  | Audit records runtime mode, provider/model ids, prompt/tool versions, redacted planner output, validation results, tool-call evidence, source references, hidden sections, confidence, safe error codes, duration, and usage/cost metadata where available.                                                                                                                                               |

## Fixtures

Implementation tests should define:

- staff user with `view_ai_metrics`;
- staff user without `view_ai_metrics`;
- staff user with `view_ai_metrics` but missing an entity permission;
- current campus and another campus;
- enabled provider setting with safe fake credentials;
- disabled/missing provider setting;
- fake SDK planner response for `query_metrics`;
- fake SDK planner response for `search_entities`;
- fake SDK planner response for `ask_clarification`;
- fake SDK planner response for `unsupported`;
- fake SDK invalid/malformed response;
- fake provider timeout/network error;
- existing metric/entity fixtures from AI-MOD-004 and AI-MOD-006;
- conversation with previous deterministic messages to prove compatibility.

## Commands

Story packet validation:

```text
./scripts/harness query matrix --numeric | rg -F "AI-MOD-017-live-provider-staff-copilot-agent"
test -f docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent/overview.md
test -f docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent/design.md
test -f docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent/execplan.md
test -f docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent/validation.md
rg -n "TB[D]|TO[D]O|FIX[M]E" docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent docs/stories/E-ai-module-2026-06/README.md
git diff --check -- docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent docs/stories/E-ai-module-2026-06/README.md docs/features/ai/ai.md
```

Runtime validation after implementation:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm exec eslint -- resources/js/pages/AI/StaffCopilot/Index.vue
./scripts/dev.sh npm exec -- prettier --check resources/js/pages/AI/StaffCopilot/Index.vue
git diff --check -- app/Modules/AI resources/js/pages/AI tests/Feature/AI docs/stories/E-ai-module-2026-06 docs/system-architecture.md docs/project-overview-pdr.md docs/codebase-summary.md
```

If browser tooling is available after implementation, run a local dev server and
capture browser proof for:

- live mode badge;
- natural-language metric prompt;
- natural-language entity lookup prompt;
- clarification response;
- provider fallback/error state.

## Acceptance Evidence

- Harness intake recorded for this roadmap/story update as Intake #151.
- Story packet path:
  `docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent/`.
- Portal impact remains `none`.
- Runtime implementation adds `LiveStaffCopilotPlannerAgent`,
  `LiveStaffCopilotFinalAnswerAgent`, and `LiveStaffCopilotAgent` above the
  existing deterministic runner.
- Live mode is gated by enabled, tested staff-owned provider settings and
  continues to execute business-data reads only through ToolDispatcher.
- Denied model-proposed unsupported tools are audited as safe denied tool calls
  without persisting raw SQL/table argument values.
- Provider failure records `provider_invocation_failed` usage evidence and
  falls back to deterministic tool execution when the existing MVP catalog can
  answer.
- Detailed Harness trace recorded as Trace #250.
- Validation run:
  `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php`
  passed with 3 tests / 74 assertions.
- Regression run:
  `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiQueryMetricsToolTest.php`
  passed with 19 tests / 504 assertions.
- Full AI regression run:
  `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php`
  passed with 37 tests / 845 assertions.
- Frontend targeted checks:
  `./scripts/dev.sh npm exec eslint -- resources/js/pages/AI/StaffCopilot/Index.vue`
  passed, and
  `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/AI/StaffCopilot/Index.vue docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent/validation.md docs/stories/E-ai-module-2026-06/S-017-live-provider-staff-copilot-agent/overview.md docs/stories/E-ai-module-2026-06/README.md docs/features/ai/ai.md docs/system-architecture.md docs/project-overview-pdr.md docs/codebase-summary.md`
  passed after formatting touched markdown docs.
- Full frontend type check attempted:
  `./scripts/dev.sh npm run type-check` started `vue-tsc --noEmit` and was
  killed with exit 137 in the container.
