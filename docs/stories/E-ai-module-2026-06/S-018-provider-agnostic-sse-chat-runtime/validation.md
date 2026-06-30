# Validation

## Proof Strategy

Validation must prove that Staff Copilot has a real chat runtime, not just a
blocking provider call:

- user messages and assistant placeholders are persisted before provider work;
- each assistant response has a durable run lifecycle;
- browser SSE receives normalized Swinx events;
- reload can recover the transcript and active run status;
- provider-specific upstream events are hidden behind backend adapters;
- multiple providers remain supported through provider settings;
- streaming never bypasses ToolDispatcher, permission, campus, redaction, audit,
  or source-reference rules;
- tests do not need live provider credentials or network calls.

## Test Plan

| Layer       | Cases                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | Run lifecycle allows valid transitions and rejects invalid ones; normalized event payloads redact secrets/raw SQL/table names/raw rows; provider stream adapters convert fake OpenAI/OpenRouter/Anthropic/Gemini-like chunks into Swinx `message.delta`, `tool.*`, `run.*` events; cursor replay ordering is stable; idempotency key prevents duplicate active runs.                                                            |
| Integration | Authorized staff submits a message and receives run/stream metadata before provider completion; user and assistant messages are persisted immediately; SSE endpoint authorizes owner and `view_ai_metrics`; SSE replays events after cursor; fake provider stream updates assistant content and marks run completed; provider failure marks run failed with safe error; deterministic/non-streaming fallback remains available. |
| E2E         | Staff Copilot shows optimistic user message, assistant pending row, streamed answer deltas, tool/status progress, final sources/confidence, failed state, retry, and stop/cancel; refreshing the page while a run is active reconnects or shows documented fallback state.                                                                                                                                                      |
| Platform    | No WebSocket/Reverb/Pusher dependency is required; automated tests run without live provider credentials, provider network calls, queue worker, MCP server, vector store, or portal repos.                                                                                                                                                                                                                                      |
| Performance | Per-run event count and payload size are bounded; provider timeout is enforced; SSE connection closes on terminal state; inactive/stale runs are recoverable or safely failed; reconnect replay does not scan unbounded history.                                                                                                                                                                                                |
| Logs/Audit  | Run status, stream mode, provider/model ids, prompt/tool versions, tool events, provider usage, source references, safe errors, cancellation, duration, and reconnect/cursor errors are recorded without secrets or raw data leakage.                                                                                                                                                                                           |

## Fixtures

Implementation tests should define:

- staff user with `view_ai_metrics`;
- staff user without `view_ai_metrics`;
- staff provider setting for OpenAI;
- staff provider setting for OpenRouter;
- provider setting that is disabled or untested;
- current campus and another campus;
- open Staff Copilot conversation;
- existing deterministic catalog prompt;
- fake upstream text stream with deltas;
- fake upstream stream that emits a tool request;
- fake upstream stream that fails mid-response;
- fake provider that does not support streaming and must use fallback;
- active run with persisted events for cursor replay;
- active run that is cancelled before completion;
- malformed cursor or unauthorized run id;
- tool result fixtures from `query_metrics` and `search_entities`.

## Commands

Story packet validation:

```text
./scripts/harness query matrix --numeric | rg -F "AI-MOD-018-provider-agnostic-sse-chat-runtime"
test -f docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime/overview.md
test -f docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime/design.md
test -f docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime/execplan.md
test -f docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime/validation.md
rg -n "TB[D]|TO[D]O|FIX[M]E" docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime docs/stories/E-ai-module-2026-06/README.md docs/features/ai/ai.md
git diff --check -- docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime docs/stories/E-ai-module-2026-06/README.md docs/features/ai/ai.md
```

Runtime validation after implementation:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm exec eslint -- resources/js/pages/AI/StaffCopilot/Index.vue
./scripts/dev.sh npm exec -- prettier --check resources/js/pages/AI/StaffCopilot/Index.vue
git diff --check -- app/Modules/AI resources/js/pages/AI tests/Feature/AI docs/stories/E-ai-module-2026-06 docs/features/ai/ai.md docs/system-architecture.md docs/project-overview-pdr.md docs/codebase-summary.md
```

If browser tooling is available after implementation, run a local dev server and
capture browser proof for:

- optimistic user message display;
- assistant placeholder/pending state;
- streamed answer delta rendering;
- tool progress events;
- reload recovery while run is active;
- cancellation/stop state;
- provider failure state;
- non-streaming fallback provider state.

## Acceptance Evidence

- Harness intake recorded as Intake #152.
- Story packet path:
  `docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime/`.
- Portal impact remains `none`.
- Requirement scope explicitly chooses SSE and excludes WebSocket/Reverb/Pusher.
- Requirement scope is provider-agnostic and does not hardcode OpenAI-only
  behavior.
- Runtime implementation adds `ai_chat_runs`, `ai_run_events`,
  `StaffCopilotSseRuntime`, owner/campus-checked SSE and cancel routes, and Vue
  `EventSource` handling with fallback snapshot message deltas.
- Detailed requirement Harness trace recorded as Trace #252; implementation
  trace is recorded separately after validation.

## Implementation Validation Evidence

- RED observed for `AiStaffCopilotSseRuntimeTest`: old POST still generated the
  answer and `AiChatRun`/retry routes did not exist before implementation.
- `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php`
  passed: 5 tests / 85 assertions.
- AI regression passed:
  `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiAuditFoundationTest.php tests/Feature/AI/AiProviderSettingsTest.php`
  => 43 tests / 945 assertions.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed.
- `./scripts/dev.sh npm exec eslint -- resources/js/pages/AI/StaffCopilot/Index.vue`
  passed.
- `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/AI/StaffCopilot/Index.vue docs/features/ai/ai.md docs/stories/E-ai-module-2026-06/README.md docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime/overview.md docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime/validation.md docs/codebase-summary.md docs/system-architecture.md docs/project-overview-pdr.md`
  passed.
- `./scripts/dev.sh artisan route:list --name=ai.copilot --except-vendor`
  shows `index`, `messages.store`, `runs.events`, `runs.cancel`, and
  `runs.retry`.
- `git diff --check -- app/Modules/AI resources/js/pages/AI tests/Feature/AI database/migrations docs/stories/E-ai-module-2026-06 docs/features/ai/ai.md docs/system-architecture.md docs/project-overview-pdr.md docs/codebase-summary.md`
  passed.
- `./scripts/dev.sh npm run type-check` was attempted and was killed with exit
  137, matching the known Swinx repo-wide frontend memory baseline; targeted
  ESLint/Prettier checks passed for the touched Staff Copilot page.
- Browser E2E proof was not run in this slice.

## Replay/Progress Hardening Evidence - 2026-06-29

- `AiStaffCopilotSseRuntimeTest` now covers replay after a valid cursor,
  invalid cursor rejection before provider/tool execution, active-run
  `replay_cursor`, append-only sequence ordering, terminal stream behavior, and
  stream safety exclusions.
- AI Staff Copilot regression bundle passed:
  `AiStaffCopilotChatTest`, `AiLiveStaffCopilotAgentTest`,
  `AiStudentProfileSectionsTest`, `AiEntityCatalogSearchTest`, and
  `AiStaffCopilotSseRuntimeTest` => 26 tests / 770 assertions.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed.
- `./scripts/dev.sh npm exec -- eslint resources/js/pages/AI/StaffCopilot/Index.vue`
  passed.
- `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/AI/StaffCopilot/Index.vue`
  passed.
- `./scripts/dev.sh npm run type-check` was attempted again and was killed with
  exit 137. A single-file `vue-tsc` attempt is not a valid project check here:
  it does not resolve the repo's `@` aliases and reports an existing
  `router.reload({ preserveScroll })` type mismatch on this page.

## Operational Audit Evidence Hardening - 2026-06-30

- Terminal `ai_run_events` now include redacted `audit_evidence` for completed,
  failed, cancelled, and retried Staff Copilot runs.
- The evidence snapshot records actor/campus scope, `view_ai_metrics` entry
  layer, connected-provider usage, provider/model/runtime metadata,
  prompt/catalog/tool schema versions, tool permission outcomes, source
  references, warnings, hidden-section markers, confidence, duration, final
  answer linkage, cancellation metadata, and retry linkage.
- Evidence payloads are normalized run-event metadata only. They do not expose
  provider API keys, raw provider request/response bodies, SQL, raw rows,
  hidden-section data, internal exception traces, portal behavior, WebSocket
  transport, or write/action mode.
- `AiStaffCopilotSseRuntimeTest` and `AiStaffCopilotChatTest` were extended to
  assert terminal evidence through persisted SSE/run-event behavior for
  completed, provider-failed, permission-denied, unsupported, cancelled, failed,
  and retried runs.
- Validation in this environment was blocked before execution because
  `./scripts/dev.sh` cannot find Docker and the host does not provide `php`.
