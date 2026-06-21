# AI-MOD-002 - AI Audit Evaluation Foundation

## Status

implemented

## Lane

high-risk

## Current Behavior

Swinx now has the first AI runtime foundation through
`AI-MOD-001-ai-governance-provider-settings`, including staff-owned provider
settings, encrypted API keys, provider/model allowlists, provider tests, and
redacted provider-settings audit records.

Current gaps before any AI tool or chat runtime can safely read business data:

- There is no Swinx-owned AI conversation, message, tool-call, provider-usage,
  feedback, or evaluation storage contract.
- Provider-settings audit exists, but it does not prove future AI answers,
  tool calls, source records, hidden sections, token usage, or cost estimates.
- No redaction policy defines what can be stored from prompts, messages, tool
  arguments, tool results, source records, provider responses, errors, or SDK
  events.
- No baseline evaluation dataset exists to prove that AI answers use the right
  tools, match existing reports, respect permissions, cite sources, and admit
  missing data.
- `docs/features/ai/laravel-ai-sdk.md` describes SDK conversation storage,
  usage metadata, events, and testing fakes/assertions, but Swinx has not yet
  bound those SDK surfaces to product audit and evaluation requirements.
- Later stories such as MetricCatalog, `query_metrics`, staff copilot chat,
  feedback loops, admin governance, prompt/tool versioning, MCP readiness, and
  student portal feasibility depend on this audit/evaluation boundary.

## Target Behavior

This story defines the audit and evaluation foundation that must exist before
AI tools or chat can access Academic, Finance, Identity, Notification, student,
lecturer, or portal-adjacent data.

Target behavior:

- Swinx defines product audit records for AI conversations, messages, tool
  calls, tool results, agent traces, provider usage, evaluation cases, evaluation
  runs, evaluation results, and feedback.
- Every AI runtime event can be correlated by conversation, message, tool call,
  provider usage, evaluation run, authenticated user, role/campus context, and
  request or trace id.
- All stored prompts, messages, tool arguments, tool results, provider metadata,
  and errors pass through an explicit redaction policy before persistence.
- Raw API keys, authorization headers, unredacted provider payloads, raw
  provider request/response bodies, and broad source-record dumps are never
  stored in AI audit records.
- Tool-call audit stores permission decisions, campus scope, filter scope,
  record counts, hidden sections, source references, duration, error codes, and
  answer linkage so answers can be debugged and compared with source-of-truth
  dashboards.
- Provider usage audit stores provider, model, success/failure status,
  duration, token counts when available, estimated cost when available, safe
  provider error code, and correlation metadata.
- Evaluation fixtures prove tool selection, permission behavior, source parity,
  hidden-section reporting, no fabricated numbers, missing-data behavior, and
  safe error handling without live provider credentials or production data.
- Laravel AI SDK conversation, usage, event, and testing features may be used
  where they fit, but Swinx remains responsible for product audit records,
  redaction, permissions, source parity, usage/cost policy, and evaluation
  evidence.
- No business-data AI access is exposed by this story. The story creates the
  audit/evaluation contract that later runtime stories must satisfy.

## Implementation Result

Implemented as the first backend-only AI audit/evaluation runtime slice:

- `ai_conversations`, `ai_messages`, `ai_agent_traces`, `ai_tool_calls`,
  `ai_provider_usages`, `ai_feedback`, `ai_evaluation_cases`,
  `ai_evaluation_runs`, and `ai_evaluation_results` provide the Swinx-owned
  product audit/evaluation record set.
- AI audit/evaluation Eloquent models live under `app/Modules/AI/Models`.
- `AiRedactor` redacts API keys, encrypted keys, auth headers, raw provider
  payloads, sensitive permission-snapshot fields, token-like strings, and
  unredacted source/tool payloads before persistence.
- `AiAuditRecorder` records correlated conversations, messages, traces, and
  tool calls with actor/campus context, permission results, hidden sections,
  source references, duration, status, and safe error codes.
- `AiProviderUsageRecorder` stores provider/model usage metadata from accepted
  provider or SDK response surfaces without retaining raw provider payloads.
- `AiEvaluationRunner` creates deterministic evaluation cases, runs, and
  redacted result evidence without live provider credentials.
- The implementation is backend-only: no chat UI, tool dispatcher,
  MetricCatalog, provider prompt runtime, MCP exposure, write/action behavior,
  or student/lecturer portal surface was added.

## Affected Users

- Internal staff users who will later ask AI analysis questions.
- Administrators and platform operators responsible for AI governance.
- Engineers implementing metric tools, entity tools, and staff copilot chat.
- Security/compliance reviewers who need replayable evidence for AI data
  access.
- Support operators who need to debug incorrect AI answers without exposing
  secrets or sensitive records.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md` when runtime audit behavior lands.
- `docs/system-architecture.md` when the AI audit boundary lands.
- `docs/codebase-summary.md` when code-verified AI behavior exists.

## Portal Impact

Portal impact: none.

This story must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, Identity
student/lecturer auth or context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Create a high-risk story packet for
  `AI-MOD-002-ai-audit-evaluation-foundation`.
- Register the story in Harness before implementation begins.
- Define AI audit records for conversations, messages, tool calls, tool results,
  agent traces, provider usage, feedback, evaluation cases, evaluation runs, and
  evaluation results.
- Define the redaction boundary for prompts, messages, tool arguments, tool
  results, source references, provider metadata, errors, and SDK events.
- Define the minimum fields each future AI tool call must audit before it can be
  exposed to AgentRunner.
- Define provider usage and cost metadata without requiring live provider
  credentials in automated tests.
- Define a baseline evaluation dataset shape with canonical staff questions,
  expected tool calls, expected source parity, permission contexts, and
  deterministic fixtures.
- Define how Laravel AI SDK conversation storage, usage metadata, events, and
  testing fakes/assertions may be used without replacing Swinx-owned product
  audit records.
- Define validation expectations for redaction, source parity, permission
  enforcement, usage capture, and evaluation evidence.
- Add the backend-only runtime migration, models, redaction service, audit
  recorder, usage recorder, evaluation runner, and deterministic tests without
  adding chat UI, tool dispatcher, metric catalogs, provider prompts, live
  provider calls, or business-data AI access.

## Non-Goals

- Do not implement chat UI, AgentRunner, ToolRegistry, ToolDispatcher,
  MetricCatalog, EntityCatalog, QueryPlanValidator, or `query_metrics`.
- Do not expose AI access to Academic, Finance, Identity, Notification, student,
  lecturer, or portal data.
- Do not create MCP server exposure or MCP-ready mappings.
- Do not add student or lecturer assistant behavior.
- Do not add admin governance dashboards, quota enforcement UI, or audit viewers;
  those belong to later governance stories.
- Do not retain raw provider request/response bodies or unredacted source-record
  dumps.
- Do not allow AI-generated production SQL.
- Do not implement write/action mode or any side-effectful AI action.
