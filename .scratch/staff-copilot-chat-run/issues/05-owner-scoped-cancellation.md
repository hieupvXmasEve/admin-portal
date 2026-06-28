# Owner-scoped cancellation

Status: ready-for-agent

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Add the complete cancel path for active Staff Copilot chat runs. The run owner should be able to stop a queued or in-progress run from the UI, Swinx should persist a terminal cancelled state and safe assistant message, and other staff or wrong-campus sessions must be forbidden from cancelling it.

The completed slice should be demoable by submitting a prompt, cancelling it before completion, reloading the Staff Copilot page, and seeing the cancelled run preserved honestly in the conversation history.

User stories covered: 43-48, 58, 62.

## Acceptance criteria

- [ ] The Staff Copilot UI shows cancel only while the active run is non-terminal and cancellable.
- [ ] The run owner in the correct campus can cancel a queued or in-progress run.
- [ ] A different staff user, wrong-campus session, or user without Staff Copilot entry permission cannot cancel the run.
- [ ] Cancelling a run records terminal cancelled state, cancellation timestamp, safe error code, assistant placeholder content, and a normalized cancelled event.
- [ ] Cancelled runs do not execute provider/tool work after cancellation when cancellation happens before execution begins.
- [ ] If provider-level cancellation cannot interrupt already-started work safely, the behavior is documented and tests assert the safe partial-cancellation outcome.
- [ ] Reloading after cancellation shows the run as cancelled and does not reconnect to it as active.
- [ ] Feature tests cover successful cancellation, denied cancellation, terminal-run cancellation no-op/denial behavior, no source execution after pre-execution cancellation, and audit evidence.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/01-run-submission-and-active-run-recovery-contract.md`
- `.scratch/staff-copilot-chat-run/issues/02-normalized-sse-replay-and-progress-ui.md`
