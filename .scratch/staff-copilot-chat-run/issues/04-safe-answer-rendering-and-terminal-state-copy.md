# Safe answer rendering and terminal-state copy

Status: ready-for-agent

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Finish the staff-facing answer surface for completed, failed, unsupported, and cancelled Staff Copilot runs. Answers should render as safe AI markdown, keep internal tool/provider details out of the main chat, preserve hidden-section and evidence limitations, and make terminal states understandable without exposing raw backend details.

The completed slice should be demoable from the Staff Copilot page: a completed run renders readable answer content, a failed run renders a safe failure message, an unsupported prompt is distinguishable from a provider/runtime failure, and a cancelled run clearly states that no complete answer was produced.

User stories covered: 36-42, 59-62.

## Acceptance criteria

- [ ] Completed answers render safe AI markdown only, with no raw HTML, code blocks, images, external links, provider payloads, SQL, raw rows, or copy actions introduced by this slice.
- [ ] Answer payloads preserve source references, normalized filters, campus scope, freshness, hidden sections, warnings, and confidence where available.
- [ ] The main chat uses natural staff-facing language and does not expose internal tool names, schema versions, trace identifiers, raw filters, or provider internals as primary UI copy.
- [ ] Hidden-section notices explain limitations without revealing hidden facts.
- [ ] Failed, unsupported, and cancelled runs each render distinct safe staff-facing messages and stable safe error metadata.
- [ ] A failed or cancelled later run does not damage or overwrite previous completed answers in the conversation.
- [ ] Tests cover rendered answer payload shape, terminal-state messages, hidden-section behavior, safe markdown restrictions, and absence of raw sensitive data in page props.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/02-normalized-sse-replay-and-progress-ui.md`
- `.scratch/staff-copilot-chat-run/issues/03-provider-tool-execution-safety-and-fallback.md`
