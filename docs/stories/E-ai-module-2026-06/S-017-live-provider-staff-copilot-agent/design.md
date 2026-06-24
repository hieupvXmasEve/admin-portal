# Design

## Domain Model

This story adds a live agent layer above the existing internal tool layer. The
agent may reason over staff questions, but it does not own data access.

Primary concepts:

- `LiveStaffCopilotAgent`
    - AI-module service responsible for building the live provider prompt,
      invoking the Laravel AI SDK, receiving structured planner/final-answer
      output, and coordinating tool execution.
    - It is a planner and answer synthesizer, not a data reader.
- `StaffCopilotRuntimeMode`
    - Runtime decision for one message:
        - `deterministic`: existing runner only;
        - `live_provider`: provider setting is valid and live mode is enabled;
        - `fallback`: attempted live mode failed safely and deterministic path
          is used or a safe error is returned.
- `LiveCopilotPromptContext`
    - Redacted prompt context passed to the model:
        - actor role summary and current campus id/code;
        - allowed tool names and schema versions;
        - compact MetricCatalog and EntityCatalog summaries;
        - answer citation rules;
        - hidden-section and safe-error policies.
    - It must exclude provider secrets, raw source rows, broad schema metadata,
      unredacted student PII, and arbitrary DB access instructions.
- `LiveCopilotPlannerOutput`
    - Structured model output for planning.
    - Accepted actions:
        - `tool_calls`;
        - `ask_clarification`;
        - `unsupported`.
- `LiveCopilotToolCall`
    - One proposed model tool call.
    - Contains `tool_name`, `arguments`, and optional `reason`.
    - It is untrusted until server validation and ToolDispatcher execution.
- `LiveCopilotFinalAnswer`
    - Structured model output for the final response after tool results are
      supplied.
    - Contains answer text, referenced tool calls, confidence, limitations, and
      any clarification request.
- `AiProviderUsageRecorder`
    - Existing provider usage recorder should persist provider/model/token/cost
      metadata when the SDK exposes it.

Business rules:

- The staff AI surface still requires `view_ai_metrics`.
- Provider credentials and model settings come from staff-owned provider
  settings from `AI-MOD-001`.
- The model can only request registered internal tools. At story start those are
  `query_metrics` and `search_entities`.
- The server must validate model-proposed arguments exactly like browser or
  deterministic arguments.
- The model never receives raw database credentials, SQL permissions, or direct
  model classes as runnable access.
- Final answers must cite source reports/tool calls for any metric or record
  fact.
- If the model asks for data not supported by the current tool catalog, the
  system must ask for clarification or return `unsupported`, not fabricate an
  answer.

## Application Flow

Live provider path:

```text
POST /ai/copilot/messages
  -> SubmitStaffCopilotMessageRequest authorizes view_ai_metrics
  -> RunStaffCopilotMessageAction records user message and starts trace
  -> StaffCopilotAgentRunner chooses runtime mode
      -> live mode enabled + valid provider setting
      -> LiveStaffCopilotAgent builds LiveCopilotPromptContext
      -> Laravel AI SDK call #1 requests structured planner output
      -> server validates planner output
      -> ToolDispatcher executes accepted tool calls
      -> audit records each tool call
      -> Laravel AI SDK call #2 synthesizes final answer from redacted tool results
      -> final answer is server-validated and persisted
  -> redirect back to ai.copilot.index
```

Fallback path:

```text
live mode disabled, missing credentials, unsupported provider, timeout, network
error, invalid model output, or denied provider setting
  -> record safe error / provider usage where available
  -> if deterministic runner can answer, use deterministic output
  -> otherwise persist unsupported/failed answer with safe error
```

Denied tool-call path:

```text
model proposes query_metrics/search_entities with unsafe arguments
  -> server validation denies before source read
  -> AiToolCall records failed/denied safe_error_code
  -> final answer receives only safe denial summary
  -> answer must not reveal hidden data or internal exception details
```

Clarification path:

```text
question is ambiguous and current tools require missing details
  -> model returns ask_clarification
  -> no business-data tool executes
  -> assistant asks one concise clarifying question
  -> trace records status waiting_for_clarification or completed_no_tool
```

## Interface Contract

No new public or portal route is required. Existing staff routes remain:

```text
GET  /ai/copilot
POST /ai/copilot/messages
```

Page capability props should distinguish deterministic and live modes:

```json
{
    "capabilities": {
        "tool_names": ["query_metrics", "search_entities"],
        "catalog_version": "metric-catalog:v1",
        "entity_catalog_version": "entity-catalog:v1",
        "tool_schema_versions": {
            "query_metrics": "query_metrics:v1",
            "search_entities": "search_entities:v1"
        },
        "sdk_installed": true,
        "live_provider_enabled": true,
        "runtime_mode": "live_provider"
    }
}
```

Planner output shape:

```json
{
    "action": "tool_calls",
    "tool_calls": [
        {
            "tool_name": "search_entities",
            "arguments": {
                "query": "AUS24001",
                "entity_types": ["student"],
                "options": { "limit": 5 }
            },
            "reason": "Find the student candidate before answering follow-up questions."
        }
    ],
    "answer_intent": "entity_lookup"
}
```

Clarification output shape:

```json
{
    "action": "ask_clarification",
    "question": "Bạn muốn phân tích học phí, trạng thái học tập, hay hồ sơ tổng quan của sinh viên này?",
    "missing_fields": ["analysis_type"]
}
```

Unsupported output shape:

```json
{
    "action": "unsupported",
    "safe_error_code": "unsupported_staff_question",
    "reason": "The requested action is outside the allowlisted copilot tools."
}
```

Final answer output shape:

```json
{
    "status": "completed",
    "answer": "Có 1 sinh viên khớp mã AUS24001 trong campus hiện tại...",
    "referenced_tool_call_ids": ["tool-call-1"],
    "source_references": [
        {
            "source_report": "academic.entity-search.student",
            "source_reference_policy": "entity_candidate_list"
        }
    ],
    "confidence": { "level": "high", "basis": "tool_result_exact_match" },
    "limitations": []
}
```

Validation rules:

- `tool_name` must exist in ToolRegistry.
- Tool arguments must pass the existing tool-specific validators.
- The model cannot set campus scope directly.
- The model cannot request raw rows, profiles, hidden sections, SQL, tables,
  columns, arbitrary relationships, or unsupported includes.
- `tool_calls` must be bounded. Initial max is 3 tool calls per message.
- If multiple tool calls are needed, the system must execute them sequentially
  and stop on denied/unsafe calls unless the answer can safely continue with
  partial evidence.

## Data Model

No migration is expected for the first implementation because existing tables
already cover:

- `ai_conversations`
- `ai_messages`
- `ai_agent_traces`
- `ai_tool_calls`
- `ai_provider_usages`
- `ai_evaluation_cases`
- `ai_evaluation_runs`
- `ai_evaluation_results`

Implementation discovery may add columns only if existing audit records cannot
preserve required runtime mode, provider usage, or structured output metadata.
Any migration must be explicitly justified in the implementation story trace.

## UI / Platform Impact

The staff copilot page should show live-mode state without becoming a provider
admin screen.

Expected UI changes:

- Capability badge for deterministic vs live provider mode.
- Clear fallback/failed provider state.
- Existing answer cards continue to show source references, hidden sections,
  confidence, warnings, and candidate lists.
- Clarification answers are rendered as assistant messages without tool results.

No student portal, lecturer portal, public API route, queue worker, streaming UI,
or external side-effect platform is required.

## Observability

Product audit must show:

- runtime mode (`live_provider`, `deterministic`, `fallback`);
- provider/model ids;
- prompt version and tool schema versions;
- redacted planner output;
- server validation result for each proposed tool call;
- executed tool calls and source references;
- provider usage/cost metadata where available;
- final answer status, safe error code, hidden sections, confidence, and
  duration.

Operational logs may contain exception ids and safe diagnostics, but product
audit tables remain the source of truth.

## Alternatives Considered

1. Keep deterministic parser until all tools are complete.
    - Rejected as the primary roadmap because it delays the product value the
      user expects: natural-language analysis.
2. Add live provider now but allow direct DB/SQL access.
    - Rejected. This bypasses Swinx permissions, campus scope, audit, and PII
      controls.
3. Make `AI-MOD-017` replace profile/context/compare/recommendation stories.
    - Rejected. Live AI is the reasoning layer; the remaining stories add safe
      capabilities the agent needs.
4. Add a custom OpenAI/OpenRouter client directly.
    - Rejected unless SDK discovery proves Laravel AI SDK cannot support the
      accepted behavior.
5. Use provider-native tools directly.
    - Deferred. Provider tools would create a second tool boundary; the first
      live implementation must use Swinx-owned ToolDispatcher only.
