# Provider/tool execution safety and fallback

Status: ready-for-agent

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Prove the execution path behind one Staff Copilot chat run: connected-provider mode can interpret a natural-language prompt and propose allowlisted tool calls, deterministic fallback remains available for supported prompts, and every business-data read still goes through ToolDispatcher validation before Swinx returns an answer.

The completed slice should be demoable with fakes: one run uses live-provider planning over a connected provider setting, one run falls back deterministically when live mode is unavailable or fails, one unsupported prompt fails safely, and one ambiguous prompt asks for clarification instead of guessing.

User stories covered: 27-35, 55-62, 68, 70.

## Acceptance criteria

- [ ] A connected AI provider enables live-provider planning/final-answer synthesis but does not grant data access by itself.
- [ ] Staff Copilot entry permission, domain permissions, current campus scope, tool schema, catalog version, result limits, and audit requirements are validated before any tool reads business data.
- [ ] Provider-planned tool calls are treated as suggestions until ToolDispatcher accepts them.
- [ ] Deterministic fallback answers supported prompts when live provider mode is unavailable.
- [ ] Provider invocation failure produces a safe fallback or failed run and never fabricates unsupported data.
- [ ] Unsupported write/action or out-of-catalog prompts fail safely without executing source reads.
- [ ] Ambiguous metric, entity, filter, or term requests ask for clarification instead of guessing.
- [ ] Feature tests use fake provider responses and mocked Academic/Finance AI reader contracts; no test requires live provider credentials or network calls.
- [ ] Tests assert tool-call audit evidence: permission result, redacted arguments, source references, redacted result summary, safe error code, and no raw sensitive payloads.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/01-run-submission-and-active-run-recovery-contract.md`
