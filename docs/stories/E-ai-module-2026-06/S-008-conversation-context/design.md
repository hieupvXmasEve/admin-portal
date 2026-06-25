# Design

## Domain Model

This story introduces a context layer between Staff Copilot conversation
history and the planner. The context layer is not a permission system. It is a
bounded, redacted, versioned input packet that helps the planner understand
follow-up references.

Primary concepts:

- `ConversationContextBuilder`
    - Builds the context packet for one Staff Copilot run.
    - Reads only the current actor's open `staff_chat` conversation for the
      current campus.
    - Selects recent messages, recent tool calls, latest safe entity refs,
      latest filter sets, current term evidence, hidden-section notices, and a
      short summary.
    - Enforces token and item budgets before provider or deterministic planning.
- `ConversationContextSnapshot`
    - Redacted context record tied to a conversation, run, and trace.
    - May be persisted in a dedicated AI table or equivalent audit record when
      implementation needs durable reload/retry/audit proof.
    - Stores schema version, built-at timestamp, selected message ids, selected
      tool-call ids, resolved entities, filters, term, tool history, summary,
      hidden sections, warnings, confidence, and safe error code.
- `ResolvedEntityContext`
    - One current entity candidate accepted for follow-up use.
    - Carries `entity_type`, opaque `entity_ref`, safe label, safe identifiers,
      source tool call, catalog version, campus scope, freshness, and validation
      state.
    - Must be revalidated through `EntityReferenceResolver` before use.
- `FilterContext`
    - One reusable filter/grouping set accepted from a prior tool result or
      explicit staff follow-up text.
    - Carries tool name, metric or profile section, normalized filters,
      group-by values, source report, source tool call, catalog/schema version,
      freshness, and confidence.
- `TermContext`
    - Current term or semester evidence resolved by Swinx code.
    - Carries semester identifier/ref, code/label, campus scope, source rule,
      source tool call or resolver, and freshness.
- `ToolHistorySelector`
    - Selects recent completed/partial tool calls and safe denied/failed
      metadata.
    - Never includes raw arguments/results beyond the existing redacted audit
      summaries.
- `ConversationSummaryUpdater`
    - Produces a compact summary from redacted messages and tool metadata.
    - The summary is an index of safe context, not a hidden memory store.
    - It is deterministic by default. Provider-assisted summary is allowed only
      if fake-backed tests prove redaction and deterministic fallback.
- `ContextPromptRenderer`
    - Renders context for `LiveStaffCopilotAgent` planner instructions.
    - Presents structured JSON-like context, not prose-only memory.
    - Marks each context item with source ids, freshness, and confidence.

Business rules:

- Context can make a follow-up more convenient, but it never expands data
  access.
- ToolDispatcher remains the only business-data execution path.
- All tool calls still re-check actor permission, campus scope, catalog version,
  tool schema, safe options, and source limits.
- Context entries expire or become invalid when their source entity ref, catalog
  version, campus scope, permission, or conversation scope no longer matches.
- Hidden sections are preserved as limitations only. Hidden data is not stored,
  summarized, inferred, or sent to the provider.
- If a staff message explicitly changes entity, filter, or term, the explicit
  message wins over previous context after validation.
- If multiple entities or filters are equally plausible, the planner must ask a
  clarification question.

## Application Flow

Follow-up with resolved student:

```text
POST /ai/copilot/messages
  -> SubmitStaffCopilotMessageRequest authorizes view_ai_metrics
  -> RunStaffCopilotMessageAction queues a durable run
  -> ConversationContextBuilder loads current actor/campus conversation
  -> selects latest valid student entity_ref from prior search/profile tool
  -> EntityReferenceResolver revalidates entity_ref for current actor/campus
  -> context packet records current student entity with source evidence
  -> live or deterministic planner maps "this student" to get_entity_profile
  -> ToolDispatcher validates and executes get_entity_profile
  -> answer cites previous context source and current profile source
```

Same-filter follow-up:

```text
staff asks "use the same filters, group by program"
  -> context builder selects latest completed query_metrics filter set
  -> explicit text updates only group_by
  -> QueryPlanValidator validates the merged plan
  -> ToolDispatcher executes query_metrics
  -> answer cites reused filters, changed group_by, source report, and campus
```

Ambiguous context:

```text
conversation has two recent valid student entities
staff asks "what about this student's finance?"
  -> context builder marks current_entity ambiguous
  -> planner returns ask_clarification
  -> no profile source read occurs
```

Permission drift:

```text
prior run had finance_summary visible
staff loses view_finance_student_overview before next run
  -> context may remember finance_summary existed as a limitation/source id
  -> EntityReferenceResolver and section permission checks run again
  -> finance_summary is hidden or denied
  -> no hidden finance facts are sent to the provider
```

Retry or reload:

```text
GET /ai/copilot
  -> returns messages and active_run
  -> active run can stream or finish through SSE
  -> context snapshot metadata is available to audit the run
  -> if no snapshot exists, builder rebuilds from canonical records
```

## Interface Contract

No student/lecturer API route is expected. Existing Staff Copilot routes remain:

```text
GET  /ai/copilot
POST /ai/copilot/messages
GET  /ai/copilot/runs/{run}/events
POST /ai/copilot/runs/{run}/cancel
POST /ai/copilot/runs/{run}/retry
```

Context packet shape:

```json
{
    "context_schema_version": "conversation-context:v1",
    "conversation_id": 123,
    "campus_scope_snapshot": {
        "campus_ids": [1],
        "scope_rule": "current_campus_only"
    },
    "current_entities": [
        {
            "entity_type": "student",
            "entity_ref": "opaque-search-entities-token",
            "label": "AUS24001 - Nguyen Van A",
            "safe_identifiers": {
                "student_code": "AUS24001"
            },
            "source_tool_call_id": 456,
            "source_report": "ai.search_entities",
            "catalog_version": "entity-catalog:v1",
            "validated_at": "2026-06-25T09:00:00Z",
            "confidence": {
                "level": "high",
                "basis": "single_recent_resolved_entity"
            }
        }
    ],
    "current_filters": [
        {
            "tool_name": "query_metrics",
            "metric": "finance.collection",
            "normalized_filters": {
                "semester": "current"
            },
            "group_by": [],
            "source_tool_call_id": 455,
            "source_report": "finance.collection",
            "freshness": "read_at_request_time"
        }
    ],
    "current_term": {
        "semester_ref": "current",
        "label": "Current term",
        "source": "metric_filter_default",
        "campus_scope": "current_campus"
    },
    "recent_tool_history": [
        {
            "tool_call_id": 456,
            "tool_name": "search_entities",
            "status": "completed",
            "source_references": [
                {
                    "source_report": "ai.search_entities",
                    "source_reference_policy": "bounded_candidates"
                }
            ],
            "hidden_sections": [],
            "safe_error_code": null
        }
    ],
    "summary": {
        "text": "Staff searched for one current-campus student and asked for academic profile sections.",
        "source_message_ids": [101, 102],
        "source_tool_call_ids": [456],
        "summary_version": "conversation-summary:v1"
    },
    "warnings": [],
    "token_budget": {
        "max_context_items": 12,
        "max_recent_messages": 8
    }
}
```

Planner behavior:

- The planner may use `current_entities` to fill `entity_ref` only when one
  valid entity is unambiguous.
- The planner may use `current_filters` only when the staff message says to
  reuse them or does not override them.
- The planner may use `current_term` only when it was resolved by Swinx code.
- The planner must include the context source in final answer evidence when a
  context item influenced a tool call or answer.

Safe error codes:

- `context_missing`
- `context_ambiguous_entity`
- `context_ambiguous_filters`
- `context_entity_reference_invalid`
- `context_entity_reference_expired`
- `context_entity_reference_forbidden`
- `context_term_unresolved`
- `context_permission_changed`
- `context_catalog_mismatch`
- `context_summary_unavailable`
- `context_budget_exceeded`

## Data Model

Implementation may use canonical records only if they are enough for reliable
rebuild. If durable context evidence is needed, add one narrow AI-owned table
instead of spreading context state across unrelated records.

Expected table if persistence is needed:

- `ai_conversation_context_snapshots`
    - `id`
    - `ai_conversation_id`
    - `ai_chat_run_id` nullable
    - `ai_agent_trace_id` nullable
    - `context_schema_version`
    - `summary_version`
    - `redacted_summary`
    - `resolved_entities` JSON
    - `current_filters` JSON
    - `current_term` JSON nullable
    - `recent_tool_history` JSON
    - `source_message_ids` JSON
    - `source_tool_call_ids` JSON
    - `campus_scope_snapshot` JSON
    - `hidden_sections` JSON nullable
    - `warnings` JSON nullable
    - `confidence` JSON nullable
    - `token_budget` JSON nullable
    - `safe_error_code` nullable
    - timestamps

Indexes should support conversation/run lookup without creating a broad search
surface:

- `ai_conversation_id`, latest first;
- `ai_chat_run_id`;
- `ai_agent_trace_id`;
- `context_schema_version`.

Storage rules:

- Store redacted context only.
- Store opaque refs and safe labels, not raw source ids as public contract.
- Do not store provider request/response bodies.
- Do not store hidden facts, raw tool results, raw source rows, or credentials.
- Add migration only after implementation discovery confirms rebuild-only
  context cannot satisfy reload/retry/audit requirements.

## UI / Platform Impact

The Staff Copilot browser surface should keep the existing chat-first flow.

Expected UI impact:

- Page props may include a compact current context payload for reload recovery
  and transparent answer rendering.
- Answers that use context should show source references, reused filters,
  current entity labels, hidden sections, warnings, and confidence using the
  existing answer evidence patterns.
- SSE run events may include context-building events such as:
    - `context.built`;
    - `context.ambiguous`;
    - `context.invalidated`.

No portal, public API, WebSocket, queue infrastructure, or provider-native
browser integration is required.

## Observability

Product audit must show:

- context schema version and summary version;
- conversation id, run id, trace id, and actor/campus scope;
- selected message ids and tool-call ids;
- resolved entity refs and validation result;
- current filters and term source;
- recent tool history selection;
- hidden sections and limitations;
- context item count and budget decisions;
- safe error code, warnings, confidence, and duration.

Audit must not store or display hidden facts, raw rows, raw provider payloads,
credentials, SQL, table names, source model names, private notes, contact data,
gateway payloads, attachments, or broad PII.

## Alternatives Considered

1. Pass the full conversation transcript to the provider.
    - Rejected. It risks leaking stale, hidden, denied, or overly broad context
      and makes permission/campus evidence hard to audit.
2. Use Laravel AI SDK conversation memory tables as the product context source.
    - Rejected. SDK conversation storage may support provider mechanics, but
      Swinx needs product-owned permission, campus, source, and audit evidence.
3. Store long-term staff preferences as memory.
    - Rejected for this story. The accepted need is short-term conversation
      context inside one Staff Copilot conversation.
4. Infer current entity from the last label shown in the browser only.
    - Rejected. Server-side tool calls need source ids, catalog versions,
      campus validation, and audit evidence.
5. Let context bypass tool validation for convenience.
    - Rejected. Context is input to planning only. ToolDispatcher remains the
      enforcement boundary.
