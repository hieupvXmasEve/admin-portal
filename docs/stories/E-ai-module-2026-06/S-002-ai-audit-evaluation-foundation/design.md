# Design

## Domain Model

This story defines the required audit and evaluation model for future AI runtime
implementation.

Primary entities:

- `AiConversation`
  - Owns one staff-facing AI interaction thread or evaluation conversation.
  - Stores actor, role/campus context, origin, title or summary metadata,
    status, and optional Laravel AI SDK conversation id.
  - Does not grant data access by itself.
- `AiMessage`
  - Stores a redacted user, assistant, system, or tool-visible message linked to
    a conversation.
  - Stores role, content classification, redacted content, source/answer linkage,
    and safety flags.
  - Never stores API keys, authorization headers, or raw provider payloads.
- `AiToolCall`
  - Stores one planned or executed tool call with tool name, schema version,
    redacted arguments, permission result, scope snapshot, status, duration,
    record counts, hidden sections, source references, and deterministic error
    code.
  - May store a redacted result summary, but must not store broad unredacted
    source-record dumps.
- `AiAgentTrace`
  - Stores orchestration metadata for one agent turn, including selected
    provider/model, prompt/tool versions, step count, final status, and answer
    linkage.
- `AiProviderUsage`
  - Stores provider/model usage metadata for one provider interaction, including
    duration, status, token counts when available, estimated cost when available,
    safe provider error code, and correlation ids.
- `AiFeedback`
  - Stores staff feedback on an answer after chat surfaces exist, with a redacted
    reason and answer linkage.
- `AiEvaluationCase`
  - Stores or references a deterministic test question, permission context,
    fixture setup, expected tool calls, expected source parity, and expected
    answer properties.
- `AiEvaluationRun`
  - Groups evaluation execution under a code version, prompt/tool/catalog
    version set, provider fake, and dataset version.
- `AiEvaluationResult`
  - Stores per-case result status, assertions, diff summary, tool-call evidence,
    source parity outcome, permission outcome, and redacted failure reason.

Supporting concepts:

- `AiRedactionPolicy`: schema-aware rules for stripping secrets, auth headers,
  raw provider payloads, sensitive fields, and disallowed source data before
  persistence.
- `AiAuditCorrelation`: shared ids that connect request id, conversation id,
  message id, tool call id, provider usage id, evaluation run id, and final
  answer id.
- `AiSourceReference`: safe reference to a source query/report/filter snapshot,
  record count, freshness timestamp, and optional row identifiers that are
  allowed by the current permission scope.
- `AiPermissionSnapshot`: immutable record of actor id, role/campus context,
  evaluated permission, allowed scope, hidden sections, and denial reason.

Initial business rules:

- Product audit records are separate from generic application logs.
- BusinessActionLogger may record high-level AI events, but AI runtime evidence
  must live in AI-owned audit/evaluation records when implementation begins.
- Laravel AI SDK conversation tables may be used for SDK conversation mechanics,
  but they do not replace Swinx product audit records.
- Every AI answer that includes numbers or record facts must be linked to tool
  calls and source references.
- Every tool call must be audited before and after execution, including denied
  calls.
- Provider usage must be recorded even when a provider call fails after the
  request is attempted.
- Raw API keys, encrypted API keys, authorization headers, provider request
  bodies, provider response bodies, and unredacted source-record dumps are
  forbidden in AI audit tables.
- The default retention rule for product audit metadata is to retain it as
  business audit evidence until a later governance story changes retention. Raw
  provider payloads and unredacted sensitive data have a retention of zero
  because they are never persisted.
- Evaluation fixtures must use deterministic seed data, fake providers, SDK
  fakes/assertions where available, and existing source-of-truth reports or
  query classes for parity.

## Laravel AI SDK Boundary

The implementation should use `docs/features/ai/laravel-ai-sdk.md` as the
primary SDK reference for conversation mechanics, provider usage metadata,
streaming completion callbacks, events, tools, structured output, and
testing fakes/assertions.

Swinx owns:

- product audit records and retention policy;
- redaction rules and redaction tests;
- staff actor, campus, role, and permission snapshots;
- tool-call input/output schemas and audit policy;
- source references, hidden-section reporting, and dashboard parity evidence;
- provider usage cost policy and safe error taxonomy;
- evaluation datasets, fixtures, assertions, and run records;
- answer linkage and support/debug surfaces accepted by later stories.

The Laravel AI SDK may own, where accepted by implementation stories:

- provider/model invocation;
- SDK conversation storage mechanics;
- agent prompt execution and structured output;
- provider usage metadata exposed by responses, stream callbacks, or events;
- testing fakes and prompt assertions;
- SDK events that can feed Swinx usage/audit records.

Do not expose SDK capabilities just because the SDK supports them. MCP tools,
provider tools, sub-agents, streaming UI, files, embeddings, vector stores,
images, audio, transcription, failover, and write/action mode remain out of
scope unless a later child story accepts those capabilities with permission,
audit, cost, and validation requirements.

## Application Flow

Future conversation audit flow:

```text
Staff request or evaluation case
  -> resolve authenticated user and role/campus context
  -> create or load AiConversation
  -> store redacted AiMessage for the user input
  -> create AiAgentTrace for this turn
  -> resolve provider setting and prompt/tool/catalog versions
  -> run accepted agent/tool flow
  -> record provider usage from SDK response/event metadata
  -> store redacted assistant message and answer linkage
  -> close trace with status, duration, sources, hidden sections, and errors
```

Future tool-call audit flow:

```text
Agent proposes tool call
  -> validate tool name and schema version
  -> redact and normalize arguments
  -> evaluate permission, campus scope, limits, and hidden sections
  -> persist AiToolCall with permission result before execution
  -> if denied, return deterministic denial result and close audit row
  -> if allowed, execute domain Query/Action read contract
  -> persist record counts, source references, result summary, duration, status
  -> return tool result to agent without exposing hidden sections
```

Future provider usage flow:

```text
Provider interaction starts
  -> correlate with conversation/message/trace
  -> call provider through Laravel AI SDK or accepted adapter
  -> collect usage from response, stream completion callback, or SDK event
  -> map provider/network/auth/model errors to safe codes
  -> persist AiProviderUsage without raw request/response bodies
```

Future evaluation flow:

```text
Evaluation run starts
  -> select dataset version and deterministic fixture scope
  -> fake provider/agent behavior or use SDK fakes/assertions
  -> execute each AiEvaluationCase
  -> assert tool sequence, permissions, hidden sections, source parity, answer shape
  -> store AiEvaluationResult with redacted diff and evidence
  -> fail the run when required safety or parity assertions fail
```

Implementation should keep controllers thin when UI/API surfaces appear later:

- FormRequests own authorization and input validation.
- Actions own audit writes, provider usage capture, and evaluation execution.
- Query classes or existing domain contracts remain the source of business data.
- Resources or Inertia props own safe serialization when audit/evaluation
  records become visible in later admin screens.

## Interface Contract

This requirements story does not add user-facing routes.

Future internal contracts should be introduced under `app/Modules/AI`:

- `AiAuditRecorder`
  - records conversations, messages, traces, tool calls, and answer linkage.
- `AiRedactor`
  - accepts schema-aware payloads and returns safe persisted payloads.
- `AiProviderUsageRecorder`
  - records provider/model usage from SDK responses, stream callbacks, events, or
    accepted provider adapters.
- `AiEvaluationRunner`
  - executes deterministic evaluation cases with fake provider behavior and
    stores evaluation results.
- `AiSourceReferenceBuilder`
  - converts domain query/report evidence into safe source references.

Required safe audit fields:

```text
actor_user_id
actor_role_snapshot
campus_scope_snapshot
conversation_id
message_id
trace_id
tool_call_id
provider_usage_id
evaluation_run_id
request_id
provider
model
prompt_version
tool_schema_version
catalog_version
permission_result
hidden_sections
record_count
source_references
duration_ms
tokens_in
tokens_out
estimated_cost_minor
status
safe_error_code
final_answer_id
```

Forbidden persisted fields:

```text
api_key
encrypted_api_key
authorization_header
raw_provider_request
raw_provider_response
raw_business_prompt_with_secrets
unredacted_tool_arguments
unredacted_tool_result
unbounded_source_rows
cross_campus_records
student_data_without_permission_snapshot
lecturer_data_without_permission_snapshot
finance_data_without_permission_snapshot
academic_data_without_permission_snapshot
```

Expected safe error categories:

- unauthorized;
- forbidden by permission;
- forbidden by campus scope;
- unsupported tool;
- invalid tool schema;
- max record limit exceeded;
- hidden section omitted;
- source parity mismatch;
- missing source data;
- provider authentication failed;
- provider model unavailable;
- provider timeout;
- provider rate limited;
- provider temporarily unavailable;
- provider response invalid;
- redaction failed;
- evaluation assertion failed.

## Data Model

Planned tables for future implementation:

```text
ai_conversations
- id
- user_id
- campus_id
- origin
- title
- status
- sdk_conversation_id
- last_message_at
- created_at
- updated_at

ai_messages
- id
- ai_conversation_id
- role
- redacted_content
- content_hash
- content_classification
- final_answer_id
- hidden_sections
- created_at
- updated_at

ai_agent_traces
- id
- ai_conversation_id
- ai_message_id
- provider
- model
- prompt_version
- catalog_version
- tool_schema_version
- status
- step_count
- duration_ms
- safe_error_code
- created_at
- updated_at

ai_tool_calls
- id
- ai_conversation_id
- ai_agent_trace_id
- tool_name
- tool_schema_version
- redacted_arguments
- permission_result
- campus_scope_snapshot
- hidden_sections
- record_count
- source_references
- redacted_result_summary
- status
- duration_ms
- safe_error_code
- created_at
- updated_at

ai_provider_usages
- id
- ai_conversation_id
- ai_agent_trace_id
- ai_message_id
- provider
- model
- status
- tokens_in
- tokens_out
- estimated_cost_minor
- duration_ms
- safe_error_code
- provider_request_id
- created_at
- updated_at

ai_feedback
- id
- ai_conversation_id
- ai_message_id
- user_id
- rating
- redacted_reason
- created_at
- updated_at

ai_evaluation_cases
- id
- key
- dataset_version
- question
- actor_fixture
- permission_context
- expected_tool_calls
- expected_source_references
- expected_answer_properties
- status
- created_at
- updated_at

ai_evaluation_runs
- id
- dataset_version
- code_version
- prompt_version
- catalog_version
- tool_schema_version
- provider_fake
- status
- started_at
- finished_at
- created_at
- updated_at

ai_evaluation_results
- id
- ai_evaluation_run_id
- ai_evaluation_case_id
- status
- assertions
- redacted_diff_summary
- tool_call_evidence
- source_parity_status
- safe_error_code
- duration_ms
- created_at
- updated_at
```

Expected constraints and indexes:

- All runtime audit rows must be scoped by conversation or evaluation run.
- Conversation, message, trace, tool-call, and usage rows should be indexed by
  user/campus, created timestamp, and status where query patterns require it.
- Evaluation rows should be indexed by dataset version, run status, case key,
  and created timestamp.
- `sdk_conversation_id` is nullable and must not be the only Swinx audit key.
- JSON columns that store redacted payloads must be validated by application
  rules before persistence.
- Raw provider payloads and unredacted source rows must not have table columns.
- Deleting or anonymizing user data in a future privacy workflow must preserve
  enough audit metadata to explain AI access while removing personal content as
  required by accepted governance policy.

## UI / Platform Impact

No user-facing UI is required for this requirements story.

Future implementation may add internal admin/support audit viewers only through
`AI-MOD-012-admin-governance-quota` or another accepted governance story. The
first audit/evaluation implementation can be backend-only with tests and
developer-facing evaluation commands.

Portal impact remains none. Student and lecturer portal surfaces are deferred
until internal staff AI has proven permission, audit, cost, and evaluation
behavior.

## Observability

AI audit records are product records. Application logs remain operational
records and must not substitute for AI audit tables.

Minimum observability requirements for later implementation:

- Every agent turn has a trace id.
- Every provider interaction has usage metadata or a safe explanation for why
  usage was unavailable.
- Every tool call records permission result, scope, record count, hidden
  sections, duration, and safe error code.
- Every answer with factual claims can link back to tool/source evidence.
- Every evaluation failure stores a redacted diff summary that is safe to share
  in engineering review.
- Redaction failures fail closed and do not persist unsafe payloads.

## Alternatives Considered

1. Use Laravel AI SDK conversation tables as the only audit source.
   - Rejected because SDK tables are conversation mechanics, while Swinx needs
     product audit evidence for permissions, source parity, hidden sections,
     usage/cost, redaction, and evaluation.
2. Add audit only after chat MVP.
   - Rejected because chat/tool behavior would be difficult to trust or debug if
     early answers are not traceable from the first runtime slice.
3. Store raw provider payloads for debugging.
   - Rejected because prompts, provider responses, source rows, and secrets can
     contain sensitive data. Debuggability must come from redacted evidence,
     source references, deterministic fixtures, and safe error codes.
4. Evaluate AI with live provider calls only.
   - Rejected because automated validation must be deterministic, cheap, and
     safe. Live provider smoke tests can be optional manual checks later, not the
     required proof for correctness.
