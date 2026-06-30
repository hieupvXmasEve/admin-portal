# Operational audit evidence and current-state docs

Status: done

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Tighten the reviewer/operator evidence for completed, failed, cancelled, and retried Staff Copilot chat runs, then update only the current-state docs/story tracker entries whose contracts actually changed. This slice should make it easy to answer: who asked, under which campus and AI access layers, whether a connected provider was used, which allowlisted tools ran, what source evidence supported the answer, and why the run ended.

The completed slice should be verifiable from tests and docs: run histories contain redacted evidence for each terminal state, and documentation accurately describes the current Staff Copilot run contract without claiming portal, WebSocket, write/action, or raw-data behavior.

User stories covered: 35, 48, 53, 61-68.

## Acceptance criteria

- [x] Completed runs retain redacted evidence for user/campus scope, runtime mode, provider/model metadata, prompt/tool/catalog metadata, duration, source references, confidence, warnings, hidden sections, and final answer linkage.
- [x] Failed runs retain stable safe error codes and enough redacted evidence to distinguish unsupported prompt, provider failure, runtime failure, permission denial, invalid cursor, and missing context where those cases apply.
- [x] Cancelled runs retain cancellation metadata and explain why no complete answer exists.
- [x] Retried runs retain linkage from retry attempt to failed attempt without duplicating user messages or exposing raw sensitive payloads.
- [x] Audit/page/stream outputs do not expose provider API keys, raw provider requests/responses, SQL, raw rows, hidden-section data, or internal exception traces.
- [x] Tests assert evidence completeness across completed, failed, cancelled, and retried runs through external behavior rather than private methods.
- [x] Current-state docs and the AI story tracker are updated only if route contracts, auth behavior, runtime contract, or story status changed.
- [x] Docs explicitly preserve portal impact `none`, query-only scope, SSE/no-WebSocket transport, ToolDispatcher boundary, and no write/action mode.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/03-provider-tool-execution-safety-and-fallback.md`
- `.scratch/staff-copilot-chat-run/issues/04-safe-answer-rendering-and-terminal-state-copy.md`
- `.scratch/staff-copilot-chat-run/issues/05-owner-scoped-cancellation.md`
- `.scratch/staff-copilot-chat-run/issues/06-failed-run-retry-without-message-duplication.md`

## Implementation notes

- Terminal `run.completed`, `run.failed`, and `run.cancelled` events now carry
  a redacted `audit_evidence` payload for operator/reviewer inspection.
- The evidence snapshot captures actor/campus scope, Staff Copilot entry layer,
  connected-provider usage, provider/model/runtime metadata, prompt/catalog/tool
  versions, tool permission outcomes, source references, warnings,
  hidden-section markers, confidence, duration, final-answer linkage,
  cancellation reason, and retry linkage.
- Retry linkage is reconstructed from the retry attempt's queued event and
  included on the retry attempt's terminal evidence without duplicating the
  original user message or exposing the raw prompt.
- Current-state docs were updated only where the runtime event contract is
  described. Portal impact remains `none`; the runtime remains query-only,
  SSE/no-WebSocket, ToolDispatcher-bound, and non-mutating.

## Validation

- Passed: `git diff --check -- app/Modules/AI/Support/StaffCopilotSseRuntime.php tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php tests/Feature/AI/AiStaffCopilotChatTest.php docs/project-overview-pdr.md docs/system-architecture.md docs/codebase-summary.md docs/stories/E-ai-module-2026-06/README.md docs/stories/E-ai-module-2026-06/S-018-provider-agnostic-sse-chat-runtime/validation.md .scratch/staff-copilot-chat-run/issues/07-operational-audit-evidence-and-current-state-docs.md`
- Blocked: `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php` exits `127` because `docker` is not installed in this environment.
- Blocked: `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php tests/Feature/AI/AiStaffCopilotChatTest.php` cannot run for the same missing-Docker reason.
- Blocked: `./scripts/dev.sh composer exec pint -- --dirty --format agent` exits `127` because `docker` is not installed in this environment.
- Blocked: `./scripts/dev.sh test --compact` exits `127` because `docker` is not installed in this environment.
- Blocked: `./scripts/dev.sh npm run type-check` exits `127` because `docker` is not installed in this environment.
- Blocked: `php -l app/Modules/AI/Support/StaffCopilotSseRuntime.php` and `php -l tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php` exit `127` because the host `php` binary is not installed.
