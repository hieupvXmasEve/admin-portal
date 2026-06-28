# Safe answer rendering and terminal-state copy

Status: done

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Finish the staff-facing answer surface for completed, failed, unsupported, and cancelled Staff Copilot runs. Answers should render as safe AI markdown, keep internal tool/provider details out of the main chat, preserve hidden-section and evidence limitations, and make terminal states understandable without exposing raw backend details.

The completed slice should be demoable from the Staff Copilot page: a completed run renders readable answer content, a failed run renders a safe failure message, an unsupported prompt is distinguishable from a provider/runtime failure, and a cancelled run clearly states that no complete answer was produced.

User stories covered: 36-42, 59-62.

## Acceptance criteria

- [x] Completed answers render safe AI markdown only, with no raw HTML, code blocks, images, external links, provider payloads, SQL, raw rows, or copy actions introduced by this slice.
- [x] Answer payloads preserve source references, normalized filters, campus scope, freshness, hidden sections, warnings, and confidence where available.
- [x] The main chat uses natural staff-facing language and does not expose internal tool names, schema versions, trace identifiers, raw filters, or provider internals as primary UI copy.
- [x] Hidden-section notices explain limitations without revealing hidden facts.
- [x] Failed, unsupported, and cancelled runs each render distinct safe staff-facing messages and stable safe error metadata.
- [x] A failed or cancelled later run does not damage or overwrite previous completed answers in the conversation.
- [x] Tests cover rendered answer payload shape, terminal-state messages, hidden-section behavior, safe markdown restrictions, and absence of raw sensitive data in page props.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/02-normalized-sse-replay-and-progress-ui.md`
- `.scratch/staff-copilot-chat-run/issues/03-provider-tool-execution-safety-and-fallback.md`

## Implementation notes

- Added a shared Staff Copilot answer presenter for safe markdown stripping,
  terminal-state copy, stable safe error metadata, and hidden-section notices.
- The SSE runtime now stores and streams sanitized answer content, and assigns
  final answer ids for failed and cancelled assistant placeholders so reloads
  can render stable terminal answer payloads.
- The Staff Copilot page renders a safe markdown subset through Vue text nodes
  and keeps source/campus/freshness/confidence evidence in structured payloads
  instead of primary chat copy.

## Validation

- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php`
- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/AI`
- Passed: `./scripts/dev.sh composer exec pint -- --dirty --format agent`
- Passed: `./scripts/dev.sh npm exec -- eslint resources/js/pages/AI/StaffCopilot/Index.vue`
- Passed: `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/AI/StaffCopilot/Index.vue`
- Blocked: `./scripts/dev.sh npm run type-check` exits `137` after `vue-tsc --noEmit` is killed.
- Blocked: `./scripts/dev.sh test --compact`, `./scripts/dev.sh artisan test --compact`, and `./scripts/dev.sh artisan test` exit `255` without diagnostic output in this checkout; `./scripts/dev.sh composer exec pest -- --compact` only reports Pest returned error code `255`.
