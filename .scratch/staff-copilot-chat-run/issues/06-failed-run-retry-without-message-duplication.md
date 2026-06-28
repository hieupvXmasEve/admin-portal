# Failed-run retry without message duplication

Status: ready-for-agent

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Add the complete retry path for failed Staff Copilot chat runs. The owner should be able to retry a failed run without retyping the prompt; Swinx should reuse the original user message, create a fresh assistant placeholder and run attempt, link the retry to the failed attempt, and preserve both attempts in audit/history.

The completed slice should be demoable by forcing a safe failed run, clicking retry, streaming the new attempt, and confirming the conversation has one user message with two assistant attempts.

User stories covered: 49-54, 58, 62.

## Acceptance criteria

- [ ] Retry is available only for failed runs and not for queued, running, completed, or cancelled runs.
- [ ] The run owner in the correct campus can retry a failed run.
- [ ] A different staff user, wrong-campus session, or user without Staff Copilot entry permission cannot retry the run.
- [ ] Retry reuses the original user message and creates a fresh assistant placeholder, fresh trace, fresh run, and fresh run events.
- [ ] Retry metadata links the new attempt back to the failed run without exposing raw sensitive payloads.
- [ ] Retrying does not duplicate the original user message in the conversation.
- [ ] Retry rebuilds required context from Swinx-owned records and does not rely on provider-native memory as authority.
- [ ] Feature tests cover successful retry, denied retry, retry status restrictions, no message duplication, fresh assistant attempt creation, linked audit evidence, and successful streaming of the retry attempt.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/01-run-submission-and-active-run-recovery-contract.md`
- `.scratch/staff-copilot-chat-run/issues/02-normalized-sse-replay-and-progress-ui.md`
- `.scratch/staff-copilot-chat-run/issues/03-provider-tool-execution-safety-and-fallback.md`
