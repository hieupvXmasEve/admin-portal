# Normalized SSE replay and progress UI

Status: done

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Make the queued Staff Copilot run observable and recoverable through Swinx-owned SSE events. The browser should receive provider-neutral run events, render progress in staff-friendly terms, append assistant deltas to the correct placeholder, stop cleanly on terminal events, and reconnect from the last event id after a reload or dropped connection without duplicating content.

The completed slice should be demoable with a fake-backed run: submit a prompt, connect to the run stream, watch queued/running/planning/tool/streaming/completed progress, reload while active, and resume from persisted events.

User stories covered: 11-26, 64.

## Acceptance criteria

- [x] The run stream emits only normalized Swinx events, not provider-native event names or raw provider payloads.
- [x] Persisted events are append-only, ordered, redacted, and replayable from a cursor.
- [x] A valid cursor resumes after the last seen event without duplicating assistant text or progress events in the UI.
- [x] An invalid cursor fails safely with a stable error response and does not execute provider/tool work.
- [x] The Staff Copilot UI renders non-terminal progress states clearly and stops showing loading state when a run completes, fails, or is cancelled.
- [x] Assistant deltas and completion metadata are attached to the correct assistant placeholder even when multiple historical messages exist.
- [x] Streamed content and page props exclude raw provider requests, raw provider responses, SQL, raw rows, secrets, hidden-section data, and internal exception details.
- [x] Feature tests cover stream replay, cursor behavior, terminal-state handling, UI/page-prop recovery data, and stream-safety exclusions.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/01-run-submission-and-active-run-recovery-contract.md`

## Comments

- 2026-06-29: Implemented replay/progress hardening in the existing Staff
  Copilot SSE runtime. Validation covered `AiStaffCopilotSseRuntimeTest`,
  Staff Copilot AI regression bundle, Pint, targeted ESLint, and targeted
  Prettier. Repo-wide `vue-tsc` was attempted but killed with exit 137.
