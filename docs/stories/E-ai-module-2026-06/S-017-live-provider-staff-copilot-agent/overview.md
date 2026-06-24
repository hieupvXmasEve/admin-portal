# AI-MOD-017 - Live Provider Staff Copilot Agent

## Status

implemented

## Lane

high-risk

## Baseline Before This Story

Before AI-MOD-017, the AI module had a safe internal foundation, but Staff
Copilot was still mostly deterministic:

- `AI-MOD-001-ai-governance-provider-settings` provides staff-owned provider
  settings, encrypted credentials, model allowlists, cost limits, and provider
  test evidence.
- `AI-MOD-002-ai-audit-evaluation-foundation` provides conversations, messages,
  traces, tool-call audit, provider usage, feedback, redaction, and evaluation
  records.
- `AI-MOD-003-metric-catalog-query-plan` defines the first allowlisted metric
  semantics and validation rules.
- `AI-MOD-004-query-metrics-tool-mvp` provides the internal read-only
  `query_metrics` tool.
- `AI-MOD-005-staff-copilot-chat-mvp` provides `/ai/copilot`, message
  submission, conversation persistence, deterministic planning, and answer UI.
- `AI-MOD-006-entity-catalog-search` provides EntityCatalog v1 and
  `search_entities:v1` candidate lookup.

The current copilot can answer only prompt shapes encoded in deterministic
rules. That is useful for proving guardrails, but it does not meet the product
goal of letting staff ask natural-language analytical questions.

## Target Behavior

This story introduces the first live-provider Staff Copilot agent while keeping
the existing safety model intact.

The live agent may interpret natural-language staff questions and choose
allowlisted tool calls, but it must not read the database directly, generate
SQL, bypass permissions, or invent hidden data access. All business data access
continues through ToolRegistry/ToolDispatcher and module-owned readers.

Target behavior:

- Add a Live Staff Copilot agent path for `/ai/copilot` when a valid provider
  setting is enabled for the staff user.
- Use the Laravel AI SDK as the provider/agent abstraction where supported by
  the installed package.
- Provide the agent with a bounded system prompt containing:
    - current AI guardrails;
    - current campus and actor context summary;
    - MetricCatalog and EntityCatalog summaries;
    - available tool names, schema versions, safe error codes, and source
      citation rules.
- Require the agent to produce structured planner output:
    - `tool_calls` for `query_metrics` and/or `search_entities`;
    - `ask_clarification` when required information is missing;
    - `unsupported` when the request is outside the allowlisted AI surface.
- Validate every model-proposed tool call server-side before execution.
- Execute tools only through ToolDispatcher.
- Feed redacted tool results back to the model for final answer synthesis.
- Persist provider usage, prompt/model/tool versions, tool calls, final answer,
  safe errors, hidden sections, source references, and confidence in existing
  AI audit records.
- Keep deterministic fallback behavior when live provider mode is disabled,
  unsupported, misconfigured, timed out, or denied.
- Reframe later stories as capability expansion for the live copilot:
    - `AI-MOD-007` adds profile-section tools;
    - `AI-MOD-008` adds conversation context;
    - `AI-MOD-009` adds comparison tools;
    - `AI-MOD-010` adds rule-backed recommendations;
    - `AI-MOD-011` validates live agent behavior over time.

## Affected Users

- Internal staff who expect the copilot to understand natural-language
  analytical questions instead of fixed prompt templates.
- Academic and finance staff asking for metrics, entity lookup, and follow-up
  analysis.
- Administrators who manage provider settings, model choice, cost, audit, and
  safety controls.
- Engineers implementing future AI tools and evaluation fixtures.
- Security/compliance reviewers who need evidence that live AI remains
  permission-aware, audited, and bounded.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

Portal impact: none.

This story affects the internal staff copilot only. It must not change
`/api/v1/student/*`, `/api/v1/lecturer/*`, Identity student/lecturer auth or
context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Register `AI-MOD-017-live-provider-staff-copilot-agent` in Harness before
  runtime implementation starts.
- Keep `AI-MOD-017` in the `E-ai-module-2026-06` roadmap after `AI-MOD-006` and
  before profile/context expansion stories.
- Confirm `AI-MOD-001` through `AI-MOD-006` are implemented before runtime work.
- Add a live-provider Staff Copilot agent path that is feature-gated by provider
  settings and permission.
- Use Laravel AI SDK provider/agent primitives where the installed SDK supports
  the required behavior.
- Do not add a parallel custom provider client unless SDK discovery proves it is
  necessary and an ADR records the exception.
- Give the live agent only catalog/tool summaries and redacted context, not raw
  schema, arbitrary table names, source rows, hidden fields, or credentials.
- Require model output to be structured and server-validated before any tool
  execution.
- Allow only registered internal tools at this stage: `query_metrics` and
  `search_entities`.
- Reject model-proposed SQL, arbitrary tables, unsupported tools, unsupported
  filters, unsafe includes, over-limit requests, and cross-campus scope.
- Execute all accepted tool calls through ToolDispatcher.
- Preserve existing deterministic fallback when live mode is unavailable or
  disabled.
- Record provider usage and final answer evidence with redacted prompts,
  arguments, source references, campus scope, hidden sections, confidence, safe
  errors, model/provider identifiers, and duration/cost metadata where
  available.
- Add fake-backed tests for live provider planning, tool-call validation, final
  answer synthesis, fallback behavior, denied tool calls, and provider errors.
- Do not require live provider credentials or network calls in automated tests.
- Update current-state docs after runtime implementation lands.
- Record Harness trace with implementation and validation evidence.

## Non-Goals

- Do not allow live AI to query the database directly.
- Do not add AI-generated production SQL, schema exploration, source-row dumps,
  arbitrary joins, arbitrary includes, or hidden field access.
- Do not add student/lecturer portal behavior.
- Do not add public API routes.
- Do not add `get_entity_profile`; that belongs to `AI-MOD-007`.
- Do not add conversation memory/current entity tracking; that belongs to
  `AI-MOD-008`.
- Do not add compare metrics; that belongs to `AI-MOD-009`.
- Do not add rule-backed recommendations; that belongs to `AI-MOD-010`.
- Do not add write/action mode, mutation tools, email sending, state changes, or
  approval workflows.
- Do not add MCP exposure, vector stores, embeddings, files, images, audio,
  sub-agents, or streaming unless a later child story explicitly accepts them.
