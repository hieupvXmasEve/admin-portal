# Design

## Domain Model

This story introduces the first product-facing staff AI conversation flow. It
does not add new tables by default; it uses the AI audit/evaluation tables
created by earlier stories.

Primary concepts:

- `StaffCopilotController`
    - Web adapter for the staff copilot page and message submission.
    - Validates through FormRequests, calls queries/actions/orchestration, and
      returns Inertia responses or redirects.
- `SubmitStaffCopilotMessageRequest`
    - Validates bounded staff question input.
    - Authorizes with the same accepted AI metric access permission used by the
      tool layer.
- `StaffCopilotPageQuery`
    - Builds page props for the latest current-campus staff conversation,
      visible messages, suggested prompts, capabilities, and permission flags.
- `RunStaffCopilotMessageAction`
    - Orchestrates one submitted message inside a transaction-safe application
      flow where possible.
    - Creates or reuses the current staff conversation, records the user message,
      runs the agent, records the assistant answer, and updates trace status.
- `StaffCopilotAgentRunner`
    - MVP deterministic AgentRunner.
    - Converts a natural-language staff question into one allowed
      `query_metrics` request using BusinessGlossary and deterministic prompt
      cases.
    - Calls ToolDispatcher with authenticated actor, current campus, and trace.
    - Converts QueryMetricsResult into structured answer output.
- `StaffCopilotAnswer`
    - Value object returned by the runner.
    - Owns final answer text, tool result payload, source references, normalized
      filters, campus scope, freshness, hidden sections, warnings, confidence,
      and safe error code.
- `StaffCopilotPromptCatalog`
    - Exposes the accepted canonical staff prompts from
      StaffMetricQuestionDataset as UI suggestions.

Initial business rules:

- Access is allowlist-first. The page and submission require AI metric access;
  ToolDispatcher still re-checks the specific metric permission and campus
  scope before any source report executes.
- The browser submits only a question string and optional conversation id. It
  cannot submit tool names, QueryPlan payloads, raw SQL, report ids, campus ids,
  source-row requests, or provider options.
- The first AgentRunner can call only `query_metrics`.
- If no supported metric can be inferred, the runner returns a safe unsupported
  answer without executing a source query.
- Campus scope is resolved from the authenticated staff session/current campus;
  the question cannot expand campus scope.
- The final visible answer is generated from QueryMetricsResult, not from raw
  source rows or model free text.
- Provider settings do not grant data access. Provider/API-key state may be
  displayed as readiness metadata only.
- If Laravel AI SDK is later accepted for live provider invocation, the SDK
  agent must wrap this same guarded ToolDispatcher contract rather than bypass
  it.

## Application Flow

Page load:

```text
GET /ai/copilot
  -> can:view_ai_metrics middleware
  -> StaffCopilotController@index
  -> StaffCopilotPageQuery resolves actor + current campus
  -> load latest open ai_conversations row for actor/campus/origin=staff_chat
  -> load redacted messages for display
  -> load suggested prompts from StaffMetricQuestionDataset
  -> render AI/StaffCopilot/Index
```

Message submission:

```text
POST /ai/copilot/messages
  -> SubmitStaffCopilotMessageRequest authorizes view_ai_metrics
  -> RunStaffCopilotMessageAction
      -> resolve or create current staff conversation
      -> record user message through AiAuditRecorder
      -> start AiAgentTrace with prompt/catalog/tool versions
      -> StaffCopilotAgentRunner plans one query_metrics call
      -> ToolDispatcher dispatches query_metrics
      -> QueryMetricsTool validates QueryPlan before source execution
      -> Metric resolver delegates to source Academic/Finance report
      -> Tool audit is recorded by QueryMetricsTool
      -> StaffCopilotAnswer is built from QueryMetricsResult
      -> assistant message is recorded with final_answer_id and hidden sections
      -> trace is marked completed, denied, or failed with safe error
  -> redirect back to ai.copilot.index with flash status
```

Unsupported question flow:

```text
question cannot map to MetricCatalog v1
  -> no ToolDispatcher call
  -> trace status failed or denied
  -> safe_error_code unsupported_staff_question
  -> assistant answer tells staff the MVP supports only the listed prompts
  -> no source query, no raw provider request, no PII exposure
```

## Interface Contract

Web routes:

```text
GET  /ai/copilot          name: ai.copilot.index
POST /ai/copilot/messages name: ai.copilot.messages.store
```

Both routes stay in the existing AI module `web` + `auth` route group and add
authorization for the accepted staff metric access permission.

Submit request:

```json
{
    "conversation_id": 123,
    "question": "Current semester outstanding tuition by program là bao nhiêu?"
}
```

Validation:

- `conversation_id`: nullable integer, must belong to the current user and
  current campus when provided.
- `question`: required string, min 3, max 1000.

Page props:

```json
{
    "conversation": {
        "id": 123,
        "status": "open",
        "campus_id": 1,
        "last_message_at": "2026-06-21T12:00:00+07:00"
    },
    "messages": [
        {
            "id": 10,
            "role": "user",
            "content": "Current semester outstanding tuition by program là bao nhiêu?",
            "created_at": "2026-06-21T12:00:00+07:00",
            "hidden_sections": []
        },
        {
            "id": 11,
            "role": "assistant",
            "content": "For the current semester, outstanding tuition is ...",
            "created_at": "2026-06-21T12:00:01+07:00",
            "hidden_sections": [],
            "answer": {
                "status": "completed",
                "summary": {},
                "groups": [],
                "source_references": [],
                "normalized_filters": {},
                "campus_scope_snapshot": {},
                "freshness": {},
                "warnings": [],
                "confidence": {"level": "high", "basis": "source_report_parity"},
                "safe_error_code": null
            }
        }
    ],
    "suggested_prompts": [
        {
            "key": "finance_collection_summary_by_program",
            "question": "Current semester outstanding tuition by program là bao nhiêu?",
            "metric": "finance_collection_summary"
        }
    ],
    "capabilities": {
        "tool_names": ["query_metrics"],
        "catalog_version": "metric-catalog:v1",
        "tool_schema_version": "query_metrics:v1",
        "live_provider_enabled": false
    },
    "permissions": {
        "can_use_copilot": true
    }
}
```

Route names must be added to the existing frontend route helper constants before
the Vue page uses them.

## Data Model

No new table is expected for this MVP.

Existing tables used:

- `ai_conversations`
- `ai_messages`
- `ai_agent_traces`
- `ai_tool_calls`
- `ai_provider_usages` only if a provider attempt is accepted later in this
  story
- `ai_evaluation_cases`
- `ai_evaluation_runs`
- `ai_evaluation_results`

The assistant answer evidence can be stored in `ai_messages.hidden_sections`
and `ai_agent_traces.final_answer_id`, while the complete structured tool
evidence remains in `ai_tool_calls.redacted_result_summary`,
`ai_tool_calls.source_references`, `ai_tool_calls.campus_scope_snapshot`, and
related trace fields. If implementation discovery proves the UI needs a
separate durable answer payload table, pause before adding a migration.

## UI / Platform Impact

Add one internal Inertia page:

```text
resources/js/pages/AI/StaffCopilot/Index.vue
```

UI requirements:

- single root component with `<script setup lang="ts">`;
- use Inertia `useForm` for message submission;
- use named route helpers, not literal URL strings;
- use lucide-vue-next icons and existing `@/components/ui` primitives;
- show suggested prompts as buttons;
- show user and assistant messages in a readable conversation area;
- show answer evidence in compact sections: summary, groups, sources, filters,
  scope, freshness, warnings, hidden sections, confidence;
- show safe error states without exposing internal exception details;
- keep the page utilitarian and work-focused, not a marketing page;
- avoid streaming in this story.

Add sidebar entry only for staff with the accepted copilot permission. The
provider settings page remains separate.

No student portal, lecturer portal, queue worker, broadcast channel, MCP server,
or scheduled job is required.

## Observability

Every submitted message must create correlated product audit records:

- conversation id;
- user id and current campus id;
- user message id;
- trace id;
- prompt version;
- catalog version;
- tool schema version;
- tool name;
- redacted tool arguments;
- permission result;
- campus scope snapshot;
- hidden sections;
- source references;
- record count;
- redacted result summary;
- trace status;
- duration in milliseconds;
- safe error code.

Operational logs may be used for unexpected exceptions, but product audit tables
remain the source of truth for AI answer evidence.

## Alternatives Considered

1. Deterministic AgentRunner over `query_metrics` first.
    - Recommended for this story because it needs no new dependency, is fully
      testable, and proves the chat/audit/source UI contract before live model
      behavior is introduced.
2. Add `laravel/ai` and implement a live SDK Agent immediately.
    - Viable only with explicit dependency approval. It should still call the
      same ToolDispatcher and structured answer contract. Tests must use SDK
      fakes and must not call live providers.
3. Build a custom OpenRouter/OpenAI chat client directly.
    - Rejected unless a later decision proves the SDK cannot support the
      accepted behavior. A custom provider layer would conflict with the shared
      Laravel AI SDK baseline.
4. Let the browser submit full QueryPlan JSON.
    - Rejected because it gives the UI too much control over tool execution and
      weakens the parse-first boundary.
5. Return raw source rows and ask the assistant to summarize them.
    - Rejected because it increases PII risk and undermines deterministic
      source-parity proof.
