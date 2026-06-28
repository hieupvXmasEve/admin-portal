# PRD: Staff Copilot chat run

Status: ready-for-agent

> Source: `/to-prd` synthesis from the current Swinx AI module, Staff Copilot story set, glossary, and backend/UI tests. Use the glossary vocabulary in `CONTEXT.md`: **Staff Copilot**, **Allowed AI data**, **Connected AI provider**, **AI access layers**, **Free-form staff prompt**, **Safe AI markdown**, **Multi-tool orchestration**, **Bounded metric context**, **Clarification-first AI behavior**, **Query-only AI capability**, and **Campus scoping**.

## Problem Statement

Staff Copilot is now valuable only if a staff member can ask a natural-language
question and trust the whole answer attempt, not just the final text. From the
staff user's perspective, a chat run must feel predictable: I submit a prompt,
see that Swinx accepted it, watch the assistant work, recover if I reload the
page, stop it if needed, retry safely if it fails, and never worry that the AI
is reading data I cannot see or leaking raw provider/tool details into the UI.

From the operator and reviewer perspective, each run must also leave durable,
redacted evidence: who asked, which campus and permissions applied, whether a
connected provider was used, which allowlisted tools ran, which source evidence
supported the answer, and why the run completed, failed, or was cancelled.
Without that first-class run contract, Staff Copilot is hard to trust, hard to
debug, and hard to expand into context, comparison, recommendation, governance,
and evaluation stories.

## Solution

Make a **Staff Copilot chat run** the first-class unit of work for one assistant
response attempt.

When an authorized staff user submits a free-form staff prompt, Swinx queues a
durable run, creates the user message and assistant placeholder, streams
normalized run events to the browser, executes provider/tool work behind the AI
module boundary, and persists redacted audit evidence. The browser shows a
clear run state, reconnects after reload using the active run, lets the owner
cancel an unfinished run, and lets the owner retry a failed run without
duplicating the original user message.

The feature stays query-only. Data access remains governed by AI access layers:
a connected AI provider enables live synthesis, `view_ai_metrics` controls entry
to the Staff Copilot surface, domain permissions control each data group, campus
scope is enforced for every read, and ToolDispatcher remains the execution
boundary for allowed AI data.

## User Stories

**Starting a run**

1. As an authorized staff user, I want to submit a free-form staff prompt, so that I can ask Staff Copilot for academic or finance analysis in natural language.
2. As an authorized staff user, I want my prompt accepted immediately as a queued run, so that I know Swinx is working even if provider/tool execution takes time.
3. As an authorized staff user, I want the prompt stored as my user message, so that the conversation history reflects what I actually asked.
4. As an authorized staff user, I want an assistant placeholder created before execution begins, so that the UI has a stable place to show progress and final output.
5. As an authorized staff user, I want the run tied to my current open Staff Copilot conversation, so that follow-up work remains in the right thread.
6. As an authorized staff user, I want a new conversation created when I have no open Staff Copilot conversation, so that first use feels natural.
7. As an authorized staff user, I want submission to respect CSRF/session behavior, so that the web app remains protected like other Inertia staff pages.
8. As an unauthorized staff user, I want Staff Copilot run creation forbidden, so that AI data access cannot be bypassed from the chat form.
9. As a staff user in one campus, I want the run scoped to my current campus, so that the assistant cannot read another campus by accident.
10. As a staff user without a current campus where campus scope is required, I want Swinx to fail safely or ask for valid context, so that the run does not guess a campus.

**Run state and progress**

11. As a staff user, I want to see whether a run is queued, running, planning, using a tool, streaming, completed, failed, or cancelled, so that I understand what is happening.
12. As a staff user, I want progress states to be human-readable, so that I do not need to understand internal event names.
13. As a staff user, I want Staff Copilot to show that it is using an approved data group, so that I trust the answer is grounded in Swinx.
14. As a staff user, I want progress updates without a full page refresh, so that the chat feels responsive.
15. As a staff user, I want the run to continue if the browser receives several events quickly, so that event ordering does not corrupt the message.
16. As a staff user, I want the run to stop showing a spinner once it reaches a terminal state, so that completed, failed, and cancelled states are visually clear.
17. As a staff user, I want the UI to keep the final answer attached to the correct assistant message, so that past answers do not appear under the wrong prompt.
18. As a staff user, I want partial progress to remain safe if execution fails, so that no raw provider payload or internal exception appears in the chat.

**Streaming and reload recovery**

19. As a staff user, I want the browser to receive normalized run events, so that the UI does not depend on provider-specific stream formats.
20. As a staff user, I want the browser to reconnect to an active run after reload, so that I do not lose a response attempt by refreshing.
21. As a staff user, I want reload recovery to resume from the last known event, so that repeated events do not duplicate the assistant text.
22. As a staff user, I want persisted run events replayed when I reconnect, so that I can recover from a dropped connection.
23. As a staff user, I want the final answer available from persisted messages after a run completes, so that I can return to it later.
24. As a staff user, I want a failed stream connection to produce a safe reconnect/retry state, so that a network issue does not look like an AI answer.
25. As a platform operator, I want the stream transport to avoid adding a WebSocket dependency for the MVP, so that deployment stays simple.
26. As a platform operator, I want downstream events to be Swinx-owned, so that provider-specific streaming differences stay behind the backend boundary.

**Provider and tool execution**

27. As a staff user with a connected AI provider, I want Staff Copilot to use live provider planning and final-answer synthesis when available, so that it can interpret natural language beyond deterministic prompts.
28. As a staff user without a connected AI provider, I want Staff Copilot to fall back to deterministic behavior where supported, so that allowed MVP questions still work.
29. As a staff user, I want provider failure to fall back or fail safely, so that the assistant never invents data because a provider call failed.
30. As a staff user, I want every data read to go through allowlisted tools, so that the AI cannot query the database directly.
31. As a staff user, I want tool calls validated by permission, campus scope, schema, limits, and catalog version, so that the run only uses allowed AI data.
32. As a staff user, I want denied or unsupported tool requests to produce a clear safe response, so that the assistant does not silently skip important safety rules.
33. As a staff user, I want Staff Copilot to ask for clarification when a request is ambiguous, so that it does not guess the metric, entity, filter, or term.
34. As a staff user, I want tool progress hidden behind staff-friendly language, so that I am not forced to understand internal tool schema names.
35. As a reviewer, I want the backend to retain tool/source evidence even when the UI is natural-language, so that I can audit how an answer was produced.

**Answer rendering**

36. As a staff user, I want completed answers rendered as safe AI markdown, so that the response can include concise sections, lists, and tables without unsafe HTML.
37. As a staff user, I want answer numbers to be grounded in source references and normalized filters, so that I can compare them with existing reports.
38. As a staff user, I want hidden-section notices when data was withheld, so that I understand limitations without seeing restricted facts.
39. As a staff user, I want failed answers to show a staff-friendly message, so that I know what to try next.
40. As a staff user, I want unsupported prompts to be identified safely, so that write/action or out-of-catalog requests do not look like normal failures.
41. As a staff user, I want cancelled runs to show that no complete answer was produced, so that the conversation history remains honest.
42. As a staff user, I want previous completed answers to remain readable after later runs fail, so that one bad run does not damage the conversation.

**Cancellation**

43. As a staff user, I want to cancel my own active run, so that I can stop a prompt I no longer need.
44. As a staff user, I want cancellation to be unavailable once a run is terminal, so that I cannot change completed history by mistake.
45. As another authorized staff user, I want to be forbidden from cancelling someone else's run, so that run ownership is enforced.
46. As a staff user, I want a cancelled run to stop provider/tool work as soon as the runtime can do so safely, so that Swinx avoids unnecessary cost and data access.
47. As a staff user, I want the cancellation state persisted, so that reloads show that the run was cancelled.
48. As a reviewer, I want cancellation recorded with safe error metadata, so that the audit trail explains why no final answer exists.

**Retry**

49. As a staff user, I want to retry a failed run, so that temporary provider or runtime failures do not force me to rewrite the prompt.
50. As a staff user, I want retry to reuse the original user message, so that the conversation does not duplicate my question.
51. As a staff user, I want retry to create a fresh assistant attempt, so that old failed output and new output are clearly separated.
52. As a staff user, I want retry available only for failed runs, so that completed or cancelled history is not accidentally replayed.
53. As a reviewer, I want retry attempts linked back to the failed run, so that repeated failures can be traced.
54. As a platform operator, I want retry to rebuild any needed context from Swinx records, so that stale provider memory is not reused as authority.

**Authorization, scope, and privacy**

55. As a staff user, I want Staff Copilot access gated by AI entry permission, so that only authorized staff can create or view runs.
56. As a staff user, I want domain-specific data permissions enforced inside each tool call, so that AI entry permission never grants finance or academic data by itself.
57. As a staff user, I want all-campus AI scope granted only by explicit permission, so that broad data reads are never implied.
58. As a campus operator, I want run streams, cancellation, retry, messages, and audit records scoped to owner and campus, so that cross-user/cross-campus access is blocked.
59. As a security reviewer, I want provider API keys never sent to the browser or recorded in run events, so that provider credentials remain protected.
60. As a security reviewer, I want raw rows, SQL, provider request payloads, provider response payloads, internal exception traces, and hidden section data excluded from stream events, so that the chat surface stays safe.
61. As a security reviewer, I want the run to record redacted input and output, so that debugging is possible without exposing sensitive data unnecessarily.
62. As a security reviewer, I want every terminal state to have a stable safe error code where appropriate, so that UI and audit behavior can be tested.

**Operations and expansion**

63. As an AI module maintainer, I want each run to record provider, model, runtime mode, stream transport, stream mode, duration, and terminal status, so that runtime health can be inspected.
64. As an AI module maintainer, I want run events to be append-only and sequence-aware, so that replay and debugging are deterministic.
65. As an AI module maintainer, I want the run contract versioned through prompt/tool/catalog metadata, so that later prompt-tool versioning can trace behavior.
66. As an AI module maintainer, I want this run lifecycle to support future bounded conversation context, so that follow-up prompts can reuse safe Swinx-owned context.
67. As an AI module maintainer, I want this run lifecycle to support future compare-metrics and recommendation capabilities, so that new tools can plug into the same execution boundary.
68. As an AI module maintainer, I want automated tests to use fakes and deterministic fixtures, so that CI does not require live provider credentials or network calls.
69. As a product owner, I want the Staff Copilot chat run to stay internal to the staff web app, so that student and lecturer portals remain untouched until a separate story accepts that scope.
70. As a product owner, I want the feature to stay query-only, so that AI cannot send emails, mutate records, trigger notifications, or change workflow state without a separate approval story.

## Implementation Decisions

- The AI module owns the Staff Copilot chat run lifecycle. Controllers stay orchestration-only: validate, authorize, call the run action/runtime, return an Inertia response or stream.
- A run is the durable unit for one assistant response attempt. It belongs to one Staff Copilot conversation, one user message, one assistant placeholder, one authenticated staff user, one current campus scope, and one audit trace.
- Message submission must queue the run and return control to the browser instead of waiting for the full answer. The browser then connects to the run's stream.
- Run status values are explicit and stable: queued, running, planning, tool-running, streaming, completed, failed, and cancelled. Completed, failed, and cancelled are terminal.
- Run events are append-only, redacted, ordered, and provider-neutral. The browser receives only Swinx-normalized events such as run status, message delta/completion, tool progress, provider failure, and terminal run events.
- The downstream browser transport is SSE for this feature. WebSocket, Reverb, Pusher, and provider-native browser streams remain out of scope.
- The runtime must support replay from a cursor so page reload and connection recovery can resume without duplicating assistant output.
- The page contract exposes the active run, last event id, stream URL, cancellation availability, stream transport, runtime mode, and whether WebSocket is required.
- Cancellation is owner- and campus-checked. Cancelling an active run records a terminal cancelled state, updates the assistant placeholder with a safe cancelled message, and writes a safe cancellation event.
- Retry is owner- and campus-checked and applies only to failed runs. Retry reuses the original user message, creates a new assistant placeholder and run attempt, and links the new attempt back to the failed run through redacted event metadata.
- Connected AI provider state controls whether live provider planning/final synthesis is available. It does not grant data access.
- Deterministic fallback remains available for supported prompts and safe fallback behavior. Provider failures must never cause the assistant to fabricate an answer.
- ToolDispatcher remains the only business-data execution boundary for AI tools. Provider-planned tool calls are suggestions until Swinx validates permissions, campus scope, schema, catalog/tool version, result limits, and audit requirements.
- Staff-facing chat content follows the natural-language UI decision: main chat text avoids internal tool/source/schema jargon, while backend audit persists the technical evidence.
- Answers use safe AI markdown only. Raw HTML, images, external links, code blocks, provider payloads, SQL, raw rows, and copy actions are not part of this run surface.
- Every answer that includes facts or numbers must retain source references, normalized filters, campus scope, freshness, hidden sections, warnings, and confidence evidence in backend payloads and page props where appropriate.
- Stable safe error codes are part of the contract for unsupported prompts, provider invocation failure, runtime execution failure, invalid cursor, run ownership denial, run cancellation, missing context, and unavailable stream behavior.
- Portal impact is none. The feature must not alter student API routes, lecturer API routes, Identity student/lecturer auth/context routes, or nested Nuxt portal code.
- Documentation updates are required only when the implementation changes route contracts, auth behavior, current-state AI docs, or story tracker status.

## Testing Decisions

- A good test asserts external behavior through the highest seam: staff web request in, streamed events/page props/database records/audit evidence out. Do not test private runtime methods or provider implementation details.
- Primary seam: Staff Copilot web run lifecycle. Exercise message submission, active-run page props, SSE stream replay/execution, cancellation, retry, owner denial, campus denial, invalid cursor, provider failure, unsupported prompts, and completed answer rendering through the web routes.
- Secondary seam only when needed: page-query contract for active run and message payloads. Use it to prove reload recovery data is present when a full browser test would add too much noise.
- Tool execution tests should fake or mock Academic/Finance AI reader contracts and Laravel AI/provider calls. Automated tests must not require live provider credentials or network access.
- Tests must assert that raw provider requests/responses, SQL, raw rows, secrets, and internal exception details do not appear in streamed content or page props.
- Tests must assert audit-side outcomes: conversation origin/status, user and campus scope, assistant placeholder, trace status, tool-call status, permission result, source references, redacted arguments, redacted result summary, run duration/terminal state, and safe error code.
- Tests must cover deterministic fallback and live-provider mode using fakes. The live-provider fake should prove that model-proposed tool calls still pass through ToolDispatcher.
- Tests must cover cancellation before execution and cancellation after progress has begun where implementation supports it. If provider-level cancellation cannot interrupt an in-flight provider call safely, the test should assert the documented partial-cancellation behavior.
- Tests must cover retry without duplicating the original user message and with a fresh assistant attempt.
- Frontend correctness is primarily covered through Inertia prop contract tests plus targeted Vue/TypeScript checks. Add manual/browser verification only if UI behavior changes materially.
- Prior art: existing Staff Copilot chat, SSE runtime, live-provider agent, entity-search, and student-profile-section feature tests already exercise the relevant shape. Extend those patterns instead of creating a parallel harness.

## Out of Scope

- Student, lecturer, or parent portal assistant behavior.
- Public API exposure for Staff Copilot.
- Write/action mode, email sending, notification dispatch, workflow mutation, approval routing, or any side-effect tool.
- WebSocket/Reverb/Pusher transport.
- Provider-native browser streaming, provider-native memory, provider-native retrieval, provider-native tools, sub-agents, files, images, audio, embeddings, vector stores, fine-tuning, or MCP exposure.
- Free-form database access, AI-generated production SQL, raw row exports, raw profile dumps, hidden-section disclosure, or unrestricted transcript memory.
- Long-term user preference memory across conversations.
- Compare-metrics, risk/recommendation rules, feedback controls, admin quota dashboards, prompt/tool versioning, or audit viewer UI. Those remain separate planned capabilities.
- Adding Composer or NPM dependencies without explicit approval.

## Further Notes

- This PRD treats the existing Staff Copilot and AI-MOD story set as baseline; agents should harden and extend the current run contract rather than rebuild a new chat stack.
- The preferred test seam is intentionally one high-level seam: the Staff Copilot web run lifecycle. This keeps behavior tests close to what staff and reviewers actually observe.
- Natural future issue seams: run lifecycle contract hardening; SSE replay/reconnect UI polish; cancellation/retry edge cases; safe error taxonomy; audit evidence completeness; provider-failure fallback; and documentation/story-tracker refresh.
