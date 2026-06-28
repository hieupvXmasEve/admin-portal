# Run submission and active-run recovery contract

Status: ready-for-agent

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Establish the Staff Copilot chat run tracer bullet: an authorized staff user submits a free-form staff prompt, Swinx creates or reuses the current open Staff Copilot conversation, persists the user message, creates an assistant placeholder, queues one durable run, and returns enough page state for the browser to recover the active run after reload.

The completed slice should be demoable without relying on live provider credentials: submit a supported prompt, land back on the Staff Copilot page, and see a recoverable active run with the correct owner, campus scope, assistant message, stream URL, cancellation availability, runtime mode, stream transport, and no WebSocket requirement. Unauthorized users and wrong-campus users must not be able to create or recover the run.

User stories covered: 1-10, 20, 23, 55, 58, 63-65, 69-70.

## Acceptance criteria

- [ ] Staff with Staff Copilot entry permission can submit a free-form staff prompt and receive a queued run instead of a synchronous full answer.
- [ ] Submission persists exactly one user message, exactly one assistant placeholder, exactly one run, and the run is tied to the current open Staff Copilot conversation or a newly created one.
- [ ] The run records the authenticated staff user, current campus scope, runtime mode, stream transport, stream mode, provider/model metadata, prompt/tool/catalog metadata, and initial status.
- [ ] Reloading the Staff Copilot page while the run is non-terminal returns an active-run contract containing run id, status, assistant message id, last event id, stream URL, cancellation availability, stream transport, streaming flag, and no-WebSocket flag.
- [ ] Staff without Staff Copilot entry permission cannot open the page or submit a run.
- [ ] A different staff user or wrong-campus session cannot recover another user's active run state.
- [ ] Feature tests cover successful submission, active-run page props, unauthorized submission denial, owner/campus scoping, and no portal impact.

## Blocked by

None - can start immediately
