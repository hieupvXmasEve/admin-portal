# Design

## Domain Model

This story defines planning artifacts, not runtime entities.

Planning entities:

- AI module epic: `E-ai-module-2026-06`.
- Master story: `AI-MOD-000-ai-module-roadmap`.
- Child story: a future bounded implementation slice with its own story packet,
  status, dependencies, Portal impact, and validation proof.
- Phase: an ordered group of child stories that builds a coherent capability.

Future runtime entities are intentionally deferred to child stories. The
blueprint suggests provider settings, conversations, messages, tool calls,
tool results, traces, usages, feedback, catalogs, and prompt/tool versions, but
none are accepted as schema in this master story.

## Application Flow

The planning flow is:

```text
AI blueprint or user request
  -> AI module README phase tracker
  -> selected child story
  -> child high-risk packet
  -> Harness story registration
  -> implementation only after the child story is accepted
  -> validation evidence
  -> README status update
```

Runtime flow is only a target architecture for future stories:

```text
Staff question
  -> AI controller/chat surface
  -> Laravel AI SDK-backed AgentRunner
  -> ContextBuilder
  -> ToolRegistry/ToolDispatcher
  -> QueryPlanValidator + PermissionResolver
  -> existing Swinx Query/Action classes
  -> audited tool result
  -> structured answer with sources
```

## Shared Laravel AI SDK Boundary

All future AI child stories must treat `docs/features/ai/laravel-ai-sdk.md` as
the official SDK reference before designing AI provider calls or agent behavior.

The boundary is:

- Laravel AI SDK should provide provider/model invocation, agent interfaces,
  structured output support, tool integration surfaces, testing fakes/assertions,
  SDK events, provider options, and failover only when the child story accepts
  those capabilities.
- Swinx must still own authenticated user context, Gate/policy permission,
  campus scope, encrypted staff-owned provider settings, quota/cost policy,
  redaction, product audit records, metric/entity catalogs, Query/Action
  delegation, answer-source requirements, and report parity proof.
- A child story must not create a parallel custom provider/client abstraction if
  the Laravel AI SDK supports the needed provider behavior. If the SDK cannot
  support an accepted provider/model/tool behavior, the child story must record
  the exception, risk, and validation strategy before implementation.
- SDK features are not automatically enabled by dependency availability. Images,
  audio, transcription, embeddings, vector stores, files, MCP tools, provider
  tools, sub-agents, streaming, failover, and write/action mode each need their
  own accepted story scope before product exposure.

## Interface Contract

This story introduces documentation contracts only:

- `README.md` tracks phase order, child story IDs, status, scope, and
  dependencies.
- `overview.md` explains current behavior, target behavior, impacted users,
  Portal impact, acceptance criteria, and non-goals.
- `design.md` records the planning and target runtime boundaries.
- `validation.md` records proof expectations for this master story and future
  child stories.
- `execplan.md` records risk classification, work phases, and stop conditions.

Future child stories own any web routes, APIs, request DTOs, response DTOs,
database tables, background jobs, or provider clients.

## Data Model

No data model change in this story.

Future child stories must separately decide and validate storage for:

- provider settings and encrypted secrets;
- conversations and messages;
- tool calls, results, traces, and provider usage;
- feedback and evaluation fixtures;
- prompt, catalog, and tool versions.

Any table that can contain prompts, user messages, tool arguments, source
records, tokens, provider responses, or PII must define retention, redaction,
masking, indexing, and audit behavior before implementation.

## UI / Platform Impact

No UI or platform impact in this story.

Future UI stories should prefer an internal staff surface first. Student portal,
lecturer portal, and write/action mode are deferred until internal permission,
audit, and evaluation gates are proven.

## Observability

No runtime observability is added in this story.

Future AI runtime stories must treat audit logs as product records, not generic
application logs. At minimum, tool-call audit should capture user, role/campus
context, conversation, tool name, arguments after redaction, permission result,
record counts, hidden sections, duration, provider/model, usage/cost estimate,
errors, and answer linkage.

## Alternatives Considered

1. Monolithic AI story only.
   - Rejected because provider settings, permission, metric tools, entity
     profiles, chat UI, evaluation, governance, MCP, and write actions have
     different risks and validation needs.
2. Create every child story packet immediately.
   - Deferred because many child details depend on decisions from earlier
     phases. The README table gives enough tracking without manufacturing empty
     packets.
3. Implement AI runtime directly from `docs/features/ai/ai.md`.
   - Rejected because the blueprint is too broad for one implementation pass and
     touches high-risk security, data, provider, and multi-domain surfaces.
