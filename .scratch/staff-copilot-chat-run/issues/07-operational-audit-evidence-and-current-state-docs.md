# Operational audit evidence and current-state docs

Status: ready-for-agent

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Tighten the reviewer/operator evidence for completed, failed, cancelled, and retried Staff Copilot chat runs, then update only the current-state docs/story tracker entries whose contracts actually changed. This slice should make it easy to answer: who asked, under which campus and AI access layers, whether a connected provider was used, which allowlisted tools ran, what source evidence supported the answer, and why the run ended.

The completed slice should be verifiable from tests and docs: run histories contain redacted evidence for each terminal state, and documentation accurately describes the current Staff Copilot run contract without claiming portal, WebSocket, write/action, or raw-data behavior.

User stories covered: 35, 48, 53, 61-68.

## Acceptance criteria

- [ ] Completed runs retain redacted evidence for user/campus scope, runtime mode, provider/model metadata, prompt/tool/catalog metadata, duration, source references, confidence, warnings, hidden sections, and final answer linkage.
- [ ] Failed runs retain stable safe error codes and enough redacted evidence to distinguish unsupported prompt, provider failure, runtime failure, permission denial, invalid cursor, and missing context where those cases apply.
- [ ] Cancelled runs retain cancellation metadata and explain why no complete answer exists.
- [ ] Retried runs retain linkage from retry attempt to failed attempt without duplicating user messages or exposing raw sensitive payloads.
- [ ] Audit/page/stream outputs do not expose provider API keys, raw provider requests/responses, SQL, raw rows, hidden-section data, or internal exception traces.
- [ ] Tests assert evidence completeness across completed, failed, cancelled, and retried runs through external behavior rather than private methods.
- [ ] Current-state docs and the AI story tracker are updated only if route contracts, auth behavior, runtime contract, or story status changed.
- [ ] Docs explicitly preserve portal impact `none`, query-only scope, SSE/no-WebSocket transport, ToolDispatcher boundary, and no write/action mode.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/03-provider-tool-execution-safety-and-fallback.md`
- `.scratch/staff-copilot-chat-run/issues/04-safe-answer-rendering-and-terminal-state-copy.md`
- `.scratch/staff-copilot-chat-run/issues/05-owner-scoped-cancellation.md`
- `.scratch/staff-copilot-chat-run/issues/06-failed-run-retry-without-message-duplication.md`
