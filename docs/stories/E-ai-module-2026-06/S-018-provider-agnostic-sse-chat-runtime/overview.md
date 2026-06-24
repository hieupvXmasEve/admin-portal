# AI-MOD-018 - Provider-Agnostic SSE Chat Runtime

## Status

implemented

## Implemented Slice

`AI-MOD-018` now adds the first provider-agnostic Staff Copilot SSE runtime:

- `ai_chat_runs` and `ai_run_events` persist assistant run lifecycle and
  normalized redacted events.
- `POST /ai/copilot/messages` queues a durable run and assistant placeholder
  before provider/tool execution.
- `GET /ai/copilot/runs/{run}/events` uses Laravel SSE to replay events after a
  cursor, execute queued runs, emit status/tool/message events, and close on a
  terminal run state.
- `POST /ai/copilot/runs/{run}/cancel` cancels queued active runs with
  owner/campus authorization.
- `POST /ai/copilot/runs/{run}/retry` retries failed runs without duplicating
  the original user message.
- The Vue Staff Copilot page renders optimistic local messages, pending
  assistant state, SSE deltas, terminal reload recovery, and a stop action.
- Provider/tool execution still flows through AI-MOD-017
  `LiveStaffCopilotAgent`/`StaffCopilotAgentRunner` and `ToolDispatcher`; the
  first stream mode is `fallback_snapshot`, so upstream provider-native token
  streaming remains a later adapter enhancement.

## Lane

high-risk

## Current Behavior

`AI-MOD-017-live-provider-staff-copilot-agent` added the first live provider
Staff Copilot path, but the browser flow is still request/response oriented:

- staff submits a question through the existing Inertia form;
- the HTTP request waits while Swinx invokes the live provider and tools;
- the page redirects/reloads only after the full answer is ready;
- the UI cannot show provider/tool progress while a message is running;
- if the browser reloads during a long provider call, the user cannot reconnect
  to a durable run and observe the current status;
- the frontend does not receive token/delta events from the assistant;
- there is no normalized event stream that hides provider-specific streaming
  details from the UI.

This behavior is safe, but it does not match modern AI chat UX. The missing
layer is a chat runtime, not another provider call.

## Target Behavior

This story introduces a provider-agnostic SSE chat runtime for internal Staff
Copilot. The runtime must make chat messages durable, observable, resumable, and
streamable while preserving the Swinx AI safety model.

Target behavior:

- Use Server-Sent Events as the browser delivery transport.
- Do not introduce WebSockets in this story.
- Keep provider calls behind the backend. The browser never calls OpenAI,
  OpenRouter, Anthropic, Gemini, or any other provider directly.
- Treat OpenAI, OpenRouter, Anthropic, Gemini, and future providers as upstream
  provider backends selected by staff-owned provider settings.
- Normalize provider-specific stream/tool/status events into a Swinx-owned event
  contract that the Vue UI can render consistently.
- Create a durable run lifecycle for each assistant response:
    - `queued`;
    - `running`;
    - `planning`;
    - `tool_running`;
    - `streaming`;
    - `completed`;
    - `failed`;
    - `cancelled`.
- Persist the user message and assistant placeholder before provider work starts.
- Persist message/run events so a page reload can render the current transcript
  and reconnect or poll the active run.
- Stream assistant deltas and status events to the browser through an SSE
  endpoint.
- Keep tool execution server-side through ToolDispatcher and preserve all
  existing permission, campus, redaction, audit, and source-reference rules.
- Use Laravel `response()->eventStream()` / stream responses and Laravel AI SDK
  streaming APIs where they support the accepted provider behavior.
- If the Laravel AI SDK cannot provide a needed provider stream shape, define a
  Swinx `ProviderStreamAdapter` contract and record the exception before adding
  custom provider glue.
- Keep deterministic and non-streaming fallback behavior when live streaming is
  unavailable, disabled, or unsafe.

## Affected Users

- Internal staff using Staff Copilot for analytical questions.
- Academic and finance staff waiting on long provider/tool responses.
- Administrators managing provider credentials, cost, and audit evidence.
- Engineers adding future AI tools that need progress/status rendering.
- Security/compliance reviewers verifying that streaming does not bypass audit
  or permission rules.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

Portal impact: none.

This story affects the internal staff copilot web UI only. It must not change
`/api/v1/student/*`, `/api/v1/lecturer/*`, Identity student/lecturer auth or
context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Register `AI-MOD-018-provider-agnostic-sse-chat-runtime` in Harness before
  runtime implementation starts.
- Keep `AI-MOD-018` in the `E-ai-module-2026-06` roadmap after `AI-MOD-017` and
  before capability expansion stories that depend on richer chat context.
- Confirm `AI-MOD-001` through `AI-MOD-006` and `AI-MOD-017` are implemented
  before runtime work.
- Add a durable assistant run lifecycle for Staff Copilot responses.
- Persist a user message and an assistant placeholder before provider execution
  begins.
- Add a Swinx-owned normalized event contract for downstream browser events.
- Add an SSE endpoint or stream response for active copilot runs.
- The Vue UI must show the user message immediately, display assistant pending
  state, render status/tool events, append streamed answer deltas, and finish in
  completed/failed/cancelled state.
- Page reload must recover existing conversation messages and active run status.
- If the run is still active after reload, the UI must reconnect to SSE or use a
  documented fallback polling path.
- Provider-specific upstream streaming must be hidden behind backend adapters or
  Laravel AI SDK abstractions.
- Support multiple providers through existing provider settings; do not hardcode
  an OpenAI-only streaming path.
- Use SSE only for this story. Do not introduce WebSocket, Reverb, Pusher, or
  broadcasting as a required dependency.
- Do not let streaming expose raw SQL, table names, hidden sections, credentials,
  raw rows, or provider exception details.
- Continue to execute all business-data reads through ToolDispatcher.
- Persist redacted run events, tool events, provider usage, safe errors, source
  references, duration, and final answer metadata.
- Add fake-backed tests for stream event normalization, run lifecycle, reload
  recovery, provider failure, cancellation, and fallback behavior.
- Do not require live provider credentials or network calls in automated tests.
- Update current-state docs after runtime implementation lands.
- Record Harness trace with implementation and validation evidence.

## Non-Goals

- Do not implement WebSocket, Reverb, Pusher, or general realtime broadcasting.
- Do not expose provider APIs or provider keys to the frontend.
- Do not add student or lecturer portal assistant behavior.
- Do not add public API routes for external clients.
- Do not add new business-data tools beyond the currently accepted tools.
- Do not add `get_entity_profile`; that remains `AI-MOD-007`.
- Do not add long-term conversation memory or conversation summary; that remains
  `AI-MOD-008` unless a minimal active-run resume summary is required for this
  runtime.
- Do not add compare metrics, recommendation rules, write/action mode, MCP,
  vector stores, embeddings, files, images, audio, sub-agents, or provider-native
  tools.
- Do not require the first implementation to stream every upstream provider if
  the SDK does not support it; unsupported providers may safely fall back to
  non-streaming completion while preserving the normalized run lifecycle.
