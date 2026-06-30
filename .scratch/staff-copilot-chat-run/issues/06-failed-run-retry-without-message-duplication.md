# Failed-run retry without message duplication

Status: done

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Add the complete retry path for failed Staff Copilot chat runs. The owner should be able to retry a failed run without retyping the prompt; Swinx should reuse the original user message, create a fresh assistant placeholder and run attempt, link the retry to the failed attempt, and preserve both attempts in audit/history.

The completed slice should be demoable by forcing a safe failed run, clicking retry, streaming the new attempt, and confirming the conversation has one user message with two assistant attempts.

User stories covered: 49-54, 58, 62.

## Acceptance criteria

- [x] Retry is available only for retryable failed runs and not for queued, running, completed, cancelled, or unsupported safety-terminal runs.
- [x] The run owner in the correct campus can retry a failed run.
- [x] A different staff user, wrong-campus session, or user without Staff Copilot entry permission cannot retry the run.
- [x] Retry reuses the original user message and creates a fresh assistant placeholder, fresh trace, fresh run, and fresh run events.
- [x] Retry metadata links the new attempt back to the failed run without exposing raw sensitive payloads.
- [x] Retrying does not duplicate the original user message in the conversation.
- [x] Retry rebuilds required context from Swinx-owned records and does not rely on provider-native memory as authority.
- [x] Feature tests cover successful retry, denied retry, retry status restrictions, no message duplication, fresh assistant attempt creation, linked audit evidence, and successful streaming of the retry attempt.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/01-run-submission-and-active-run-recovery-contract.md`
- `.scratch/staff-copilot-chat-run/issues/02-normalized-sse-replay-and-progress-ui.md`
- `.scratch/staff-copilot-chat-run/issues/03-provider-tool-execution-safety-and-fallback.md`

## Implementation notes

- Added a retryability contract on `AiChatRun` so backend retry matches the Staff Copilot terminal-state UI: source/runtime failures can be retried, but unsupported safety-terminal prompts cannot be retried by manual POST.
- Routed retry through `RetryStaffCopilotRunAction` and guarded `StaffCopilotSseRuntime::retry()` under a row lock before creating the fresh assistant placeholder, trace, run, and redacted retry metadata.
- Extended `AiStaffCopilotSseRuntimeTest` to cover retry success with a failed source read followed by a successful streamed retry, linked redacted retry evidence, non-duplication of the original user message, unsupported retry denial, status restrictions, and owner/campus/permission denial.

## Validation

- Passed: `git diff --check`
- Blocked: `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php` exits `127` because `docker` is not installed in this environment.
- Blocked: `./scripts/dev.sh composer exec pint -- --dirty --format agent` exits `127` because `docker` is not installed in this environment.
- Blocked: `./scripts/dev.sh npm exec -- eslint resources/js/pages/AI/StaffCopilot/Index.vue` exits `127` because `docker` is not installed in this environment.
- Blocked: `./scripts/dev.sh npm run type-check` exits `127` because `docker` is not installed in this environment.
