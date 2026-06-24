# Exec Plan

## Goal

Create the requirement and future implementation plan for a provider-agnostic
SSE Staff Copilot chat runtime that supports durable assistant runs, streamed
UI updates, reload recovery, cancellation, and multi-provider backend execution
without introducing WebSockets.

## Scope

In scope:

- Discover Laravel 13 streamed response/SSE support and Laravel AI SDK streaming
  support in the installed package.
- Define durable Staff Copilot run lifecycle and normalized runtime events.
- Add or plan `ai_chat_runs` and `ai_run_events` persistence.
- Add downstream SSE endpoint for browser event delivery.
- Add provider stream adapter boundary that hides OpenAI/OpenRouter/Anthropic/
  Gemini-specific event formats from the Vue UI.
- Preserve existing provider settings, permissions, campus scope, audit,
  redaction, ToolDispatcher execution, and deterministic fallback.
- Update Staff Copilot UI to show optimistic user messages, assistant placeholder
  state, streamed deltas, tool/status events, reload recovery, and cancel/retry
  affordances.
- Add fake-backed tests for all provider/runtime behavior.
- Update product/story docs and Harness evidence after implementation lands.

Out of scope:

- WebSocket, Reverb, Pusher, or general realtime broadcasting.
- Frontend direct provider calls.
- New provider credential storage beyond AI-MOD-001.
- New business-data tools beyond the currently accepted tool catalog.
- Student/lecturer portal behavior.
- Public external API clients.
- Long-term memory/conversation summarization beyond active run recovery.
- Profile sections, compare metrics, recommendations, write/action mode, MCP,
  vector stores, embeddings, files, audio, images, sub-agents, and
  provider-native tools.

## Risk Classification

Risk flags:

- Authorization: SSE run access must enforce owner, permission, and campus
  scope.
- Audit/security: streaming events must remain redacted and traceable.
- External systems: upstream provider streaming and failures differ by provider.
- Public contracts: Staff Copilot routes/page props/event contracts change.
- Existing behavior: synchronous deterministic and live provider fallback must
  keep working.
- Weak proof: streaming and reload behavior need fake-backed tests and browser
  proof.
- Multi-domain: streamed answers may include Academic/Finance tool evidence.

Hard gates:

- Authorization.
- Audit/security.
- External provider behavior.

Portal impact: none.

## Work Phases

1. Discovery
    - Confirm installed Laravel, Inertia, Vue, and Laravel AI SDK versions.
    - Read Laravel docs for `response()->eventStream()`, streamed responses, and
      queue timeouts.
    - Read Laravel AI SDK docs for `stream`, streamed events, `queue`, events,
      fakes/assertions, and failover.
    - Inspect AI-MOD-017 live agent, provider resolver, audit recorder,
      provider usage recorder, ToolDispatcher, and current Staff Copilot UI.

2. Runtime contract
    - Define run lifecycle statuses and terminal states.
    - Define normalized event names and payload schemas.
    - Define cursor/replay semantics.
    - Define idempotency behavior for message send and retry.
    - Define cancellation behavior.

3. Persistence
    - Add run and event migrations/models.
    - Link runs to conversation, user message, assistant message, trace, user,
      campus, provider, and model.
    - Keep event payloads redacted and append-only.
    - Add indexes for active run lookup and SSE replay.

4. Backend runtime
    - Refactor Staff Copilot message submission to create durable run state
      before provider execution.
    - Add runtime service that can execute a run and persist events.
    - Add provider stream adapter boundary.
    - Use Laravel AI SDK streaming when supported.
    - Use safe non-streaming fallback for providers or SDK surfaces that cannot
      stream safely.
    - Continue all tool execution through ToolDispatcher.
    - Persist trace/tool/provider usage evidence.

5. SSE routes and controller
    - Add stream endpoint for active run events.
    - Authorize owner, `view_ai_metrics`, and campus scope.
    - Replay events from cursor.
    - Stream new events until terminal state.
    - Return safe errors for invalid cursor, inactive run, or denied access.

6. Frontend runtime UI
    - Replace blocking full-response submission with optimistic local rendering.
    - Add local stream composable or approved Laravel stream Vue package usage.
    - Render run status, tool progress, assistant deltas, completed evidence,
      failed state, cancelled state, retry, and stop/cancel.
    - Reconnect on page load when `active_run` exists.
    - Preserve existing source/reference cards.

7. Validation
    - Add unit tests for lifecycle transitions, event schemas, redaction, and
      provider adapter normalization.
    - Add feature tests for message submission, SSE authorization, event replay,
      completion, failure, cancellation, and reload recovery.
    - Add fake provider stream tests without live network calls.
    - Add frontend source/pattern tests or browser proof for optimistic/streamed
      rendering.
    - Run AI regression suite and targeted frontend checks.

8. Docs and Harness
    - Update AI docs and story evidence.
    - Record implementation trace with detailed high-risk metadata.
    - Update story status after implementation and validation.

## Stop Conditions

Pause for human confirmation if:

- Laravel AI SDK streaming cannot support the required provider-agnostic adapter
  boundary.
- A custom upstream provider stream client is needed for OpenRouter, Anthropic,
  Gemini, or another provider.
- A new frontend dependency such as `@laravel/stream-vue` is preferred over a
  local composable.
- Runtime implementation requires queue infrastructure changes beyond existing
  Swinx assumptions.
- SSE buffering behavior cannot be made reliable under the local/prod web
  server stack.
- Cancellation cannot be implemented safely for a provider; partial cancellation
  semantics must then be documented.
- Event persistence would store raw provider payloads, raw SQL, table names, raw
  rows, hidden fields, or credentials.
- Validation would require live provider credentials or network calls.
- The scope expands into WebSocket/realtime broadcasting, portal assistants,
  memory, profile tools, write actions, MCP, files, vector search, or
  provider-native tools.
