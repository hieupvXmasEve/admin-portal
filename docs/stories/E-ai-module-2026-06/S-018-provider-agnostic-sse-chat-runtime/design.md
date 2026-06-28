# Design

## Domain Model

This story adds a runtime layer between Staff Copilot UI and provider execution.
The runtime owns message/run state and downstream event delivery; providers only
generate model output.

Primary concepts:

- `AiChatRun`
    - Durable record for one assistant response attempt.
    - Belongs to a conversation, user message, assistant placeholder, provider
      setting, and agent trace.
    - Tracks lifecycle status, runtime mode, stream mode, cancellation, duration,
      safe error, and completion metadata.
- `AiRunEvent`
    - Append-only normalized event emitted by the runtime.
    - Examples:
        - `run.started`;
        - `message.created`;
        - `message.delta`;
        - `message.completed`;
        - `tool.started`;
        - `tool.completed`;
        - `tool.denied`;
        - `provider.failed`;
        - `run.failed`;
        - `run.cancelled`.
    - Stores redacted payload only.
- `AssistantPlaceholderMessage`
    - Existing `AiMessage` with role `assistant`, created before provider
      execution starts.
    - It holds the currently accumulated redacted answer and final answer id
      when complete.
- `ProviderStreamAdapter`
    - Swinx-owned contract that converts upstream provider events into internal
      runtime events.
    - The preferred implementation uses Laravel AI SDK streaming primitives.
    - Custom provider-specific glue is allowed only if SDK discovery proves the
      needed stream shape is missing.
- `SseChatRuntime`
    - Application service that opens the downstream SSE stream, replays persisted
      events from a cursor, emits new events, and closes safely.
- `RunLifecycle`
    - State machine for one assistant run:

```text
queued
  -> running
  -> planning
  -> tool_running
  -> streaming
  -> completed

queued/running/planning/tool_running/streaming
  -> failed

queued/running/planning/tool_running/streaming
  -> cancelled
```

Business rules:

- Staff Copilot still requires `view_ai_metrics`.
- Provider credentials and model choice come from the authenticated staff user's
  provider setting.
- API keys decide provider/model/cost behavior only; data access follows the
  authenticated Swinx user, permissions, and campus scope.
- The browser receives only normalized Swinx events, never provider-native
  payloads containing secrets, raw tool arguments, raw rows, SQL, or internal
  exception details.
- Tool execution remains server-side through ToolDispatcher.
- Every event that includes metric/entity facts must cite tool/source evidence
  or reference a tool-call event that has source evidence.
- The runtime must be idempotent enough that a retry after a network failure does
  not create duplicate user messages or duplicate active assistant runs.

## Application Flow

Initial send:

```text
POST /ai/copilot/messages
  -> authorize view_ai_metrics
  -> validate question and conversation_id
  -> create or reuse open ai_conversation
  -> persist user AiMessage
  -> persist assistant placeholder AiMessage with status metadata
  -> persist AiChatRun with status queued
  -> persist run.created / message.created events
  -> dispatch runtime execution synchronously for streaming or via queue if the
     accepted implementation needs a worker
  -> return run id / stream URL to the browser
```

Downstream SSE stream:

```text
GET /ai/copilot/runs/{run}/events?cursor=<event-id>
  -> authorize owner + view_ai_metrics
  -> replay persisted events after cursor
  -> stream new normalized events as they are persisted
  -> close on run.completed, run.failed, or run.cancelled
```

Provider execution:

```text
AiChatRun status queued/running
  -> resolve provider setting
  -> configure SDK provider key server-side
  -> build live planner prompt/context from AI-MOD-017 contracts
  -> stream or prompt upstream provider through Laravel AI SDK where supported
  -> normalize upstream status/delta/tool events into AiRunEvent records
  -> validate and execute model-proposed tools through ToolDispatcher
  -> stream final answer deltas or full final answer events
  -> update assistant message and trace
  -> mark run completed
```

Reload recovery:

```text
GET /ai/copilot
  -> returns conversation, messages, active_run, last_event_id, replay_cursor, capabilities
  -> UI renders persisted messages immediately
  -> if active_run status is not terminal, UI reconnects to SSE using replay_cursor
  -> after a dropped connection, UI reconnects with the latest applied event_id
  -> replayed event ids are ignored so assistant deltas are not duplicated
```

Cancellation:

```text
POST /ai/copilot/runs/{run}/cancel
  -> authorize owner + view_ai_metrics
  -> mark cancellation requested
  -> close downstream stream with run.cancelled when provider loop observes it
  -> persist partial assistant content with cancelled status
```

## Interface Contract

Existing staff page route remains:

```text
GET /ai/copilot
```

Message submission must stop waiting for the full provider response:

```text
POST /ai/copilot/messages
```

Expected response shape may be an Inertia redirect or JSON/Inertia-compatible
payload, but it must expose enough data for the browser to start or reconnect a
stream:

```json
{
    "conversation_id": 101,
    "user_message_id": 501,
    "assistant_message_id": 502,
    "run_id": 301,
    "stream_url": "/ai/copilot/runs/301/events"
}
```

SSE endpoint:

```text
GET /ai/copilot/runs/{run}/events?cursor={event_id}

Invalid cursor:
422 ApiResponse::error(... code=invalid_run_event_cursor ...)
```

Optional cancellation endpoint:

```text
POST /ai/copilot/runs/{run}/cancel
```

Normalized SSE event examples:

```text
event: run.started
data: {"run_id":301,"event_id":1,"sequence":4,"status":"running","progress_label":"Starting"}

event: tool.started
data: {"run_id":301,"event_id":2,"sequence":6,"status":"started","data_group":"Finance data","progress_label":"Checking approved data"}

event: message.delta
data: {"run_id":301,"event_id":3,"sequence":8,"message_id":502,"delta":"Outstanding tuition "}

event: message.completed
data: {"run_id":301,"event_id":4,"sequence":9,"message_id":502,"status":"completed","source_references":[...],"progress_label":"Answer ready"}

event: run.completed
data: {"run_id":301,"event_id":5,"sequence":10,"status":"completed","progress_label":"Answer ready"}
```

Page props should include active run state:

```json
{
    "active_run": {
        "id": 301,
        "status": "streaming",
        "assistant_message_id": 502,
        "last_event_id": 3,
        "replay_cursor": 0,
        "stream_url": "/ai/copilot/runs/301/events",
        "can_cancel": true
    },
    "capabilities": {
        "runtime_mode": "live_provider",
        "stream_transport": "sse",
        "streaming_enabled": true,
        "websocket_required": false,
        "supported_provider_stream_modes": {
            "openai": "sdk_stream",
            "openrouter": "sdk_stream_or_adapter",
            "anthropic": "sdk_stream_or_adapter",
            "gemini": "sdk_stream_or_adapter"
        }
    }
}
```

Safe error codes should be stable enough for UI and tests:

```text
stream_unavailable
provider_stream_unsupported
provider_invocation_failed
run_cancelled
run_not_found
run_not_active
run_ownership_denied
invalid_run_event_cursor
```

## Data Model

Existing tables remain the source of truth for conversations, messages, traces,
tool calls, and provider usage:

- `ai_conversations`
- `ai_messages`
- `ai_agent_traces`
- `ai_tool_calls`
- `ai_provider_usages`

This story is expected to add a durable run/event layer. Proposed tables:

```text
ai_chat_runs
- id
- ai_conversation_id
- user_message_id
- assistant_message_id
- ai_agent_trace_id
- user_id
- campus_id
- provider
- model
- runtime_mode
- stream_transport
- stream_mode
- status
- cancellation_requested_at
- started_at
- completed_at
- failed_at
- duration_ms
- safe_error_code
- last_event_id
- idempotency_key
- created_at
- updated_at
```

```text
ai_run_events
- id
- ai_chat_run_id
- ai_conversation_id
- ai_message_id nullable
- ai_tool_call_id nullable
- event_type
- sequence
- redacted_payload
- created_at
```

Indexes should support:

- active run lookup by conversation/user/status;
- SSE replay by run id and sequence/id;
- ownership checks by user id and campus id;
- cleanup/retention of old event records if needed.

The exact migration shape may be adjusted during implementation, but it must
preserve:

- append-only event replay;
- redacted payloads only;
- no provider secrets;
- no raw SQL/table/source-row payloads;
- enough state to recover after browser reload.

## UI / Platform Impact

Staff Copilot UI should shift from full-page wait to AI-chat runtime behavior:

- optimistic user message display;
- assistant placeholder row with status;
- streamed assistant text/deltas;
- status chips for queued/running/planning/tool/generating/completed/failed;
- tool event rendering that matches existing source/evidence UI;
- reconnect after reload;
- cancel/stop action where provider/runtime supports it;
- retry failed run without duplicating the original user message;
- no WebSocket dependency.

Implementation should prefer existing Vue/Inertia patterns. If the Laravel
stream Vue package is added, the dependency change must be explicit in the
story implementation evidence. If not added, native `EventSource` or fetch
stream consumption may be used behind a small local composable.

## Observability

The runtime must persist and expose enough evidence for debugging and audit:

- run lifecycle transitions;
- normalized provider stream mode;
- provider/model ids;
- prompt/tool/catalog versions;
- event counts and byte/delta counts;
- tool-call start/completion/denial events;
- provider failures and safe error codes;
- cancellation requests;
- duration and usage/cost metadata where available;
- SSE reconnect attempts and cursor errors.

Logs and audit must never include provider API keys, raw provider payloads with
hidden data, raw SQL, table names supplied by the model, or raw source rows.

## Alternatives Considered

1. WebSocket/Reverb/Pusher realtime
    - More flexible for bidirectional realtime, but not needed for Staff Copilot
      MVP. It adds infrastructure and operational complexity. Rejected for this
      story.
2. Keep synchronous Inertia form submission
    - Safe and simple, but does not support streaming, reload recovery, or
      modern AI chat UX. Rejected as the target runtime.
3. Let the frontend call provider streaming APIs directly
    - Would expose keys, bypass Swinx permission/audit boundaries, and tie UI to
      provider-specific event formats. Rejected.
4. Provider-specific streaming UI
    - Fast for one provider, but fails Swinx's multi-provider requirement.
      Rejected in favor of normalized backend events.
5. SSE with provider-agnostic backend runtime
    - Fits the current need: one-way streaming from server to browser, lower
      operational cost than WebSockets, backend-owned provider keys, and a stable
      UI event contract. Recommended.
