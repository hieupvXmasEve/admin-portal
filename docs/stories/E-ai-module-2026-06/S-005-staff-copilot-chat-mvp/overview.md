# AI-MOD-005 - Staff Copilot Chat MVP

## Status

implemented

## Lane

high-risk

## Current Behavior

Swinx now has the AI foundations needed for a first internal staff copilot
surface:

- `AI-MOD-001-ai-governance-provider-settings` provides provider settings,
  encrypted staff-owned credentials, model allowlists, and provider test audit.
- `AI-MOD-002-ai-audit-evaluation-foundation` provides AI conversations,
  messages, traces, tool calls, provider usage, feedback, redaction, and
  deterministic evaluation records.
- `AI-MOD-003-metric-catalog-query-plan` provides BusinessGlossary,
  MetricCatalog v1, QueryPlan, QueryPlanValidator, the `view_ai_metrics`
  permission, and deterministic staff metric questions.
- `AI-MOD-004-query-metrics-tool-mvp` provides internal ToolRegistry,
  ToolDispatcher, QueryMetricsTool, bounded aggregate QueryMetricsResult, metric
  resolvers, source references, safe errors, and tool-call audit.

Gaps this story closes:

- No staff-facing AI chat route or Inertia page exists.
- No AgentRunner or conversation orchestration exists above ToolDispatcher.
- No message submission flow creates user messages, traces, tool calls, final
  assistant messages, and provider-usage records as one correlated flow.
- No visible answer card shows answer text, metric summaries, source reports,
  filters, campus scope, freshness, hidden sections, warnings, and confidence.
- No route exposes the already implemented canonical staff metric questions as
  suggested prompts.
- No dashboard-parity proof exists for visible copilot answers.
- The `laravel/ai` Composer package is not installed in this checkout, so live
  Laravel AI SDK Agent classes cannot be imported unless this story explicitly
  accepts a dependency change.

## Target Behavior

This story creates the first internal staff copilot chat MVP. It turns the
backend-only `query_metrics` foundation into a usable read-only staff page while
preserving the existing permission, campus, source, audit, and evaluation
guardrails.

Target behavior:

- Staff with the accepted AI metric permission can open an internal AI copilot
  page under the existing `/ai` route group.
- Staff without that permission are denied before seeing or submitting chat
  content.
- The page shows the current staff conversation for the current campus, suggested
  canonical prompts, and the latest structured answer evidence.
- Staff can submit one question at a time through an Inertia form submission.
- The backend records a user message, starts an agent trace, plans an allowed
  `query_metrics` call from the staff question, dispatches the tool, records the
  tool audit, creates a structured assistant answer, marks the trace completed
  or safely failed, and redirects back to the chat page.
- The MVP uses only MetricCatalog v1 and the existing `query_metrics` tool. It
  must not expose entity search, student profile sections, raw source rows,
  arbitrary SQL, provider-native tools, MCP, or write actions.
- The visible answer cites source reports, filters, campus scope, freshness,
  hidden-section notices, warnings, and confidence whenever it includes numbers
  or record facts.
- Portal impact remains `none`.

## Implemented Result

- `laravel/ai` is installed at `v0.7.2`; this MVP uses the package as the
  approved SDK readiness boundary and does not make live provider calls.
- `GET /ai/copilot` renders `AI/StaffCopilot/Index` and
  `POST /ai/copilot/messages` submits bounded staff questions.
- Both routes are guarded by `view_ai_metrics`; the FormRequest accepts only
  `question` and optional `conversation_id`.
- `RunStaffCopilotMessageAction` records user message, trace, audited tool call
  evidence, assistant message, final answer id, safe error, and status.
- `StaffCopilotAgentRunner` maps canonical prompts and BusinessGlossary matches
  to MetricCatalog v1 `query_metrics` calls with safe defaults.
- The Vue page renders suggested prompts from StaffMetricQuestionDataset,
  structured summary/group/source evidence, confidence, and safe error state.

## Recommended Implementation Shape

Use a Swinx-owned deterministic AgentRunner for this first slice. It should:

- map the question to the already accepted MetricCatalog v1 metric using
  BusinessGlossary and the canonical staff question dataset;
- build a QueryPlan-shaped payload with safe defaults such as
  `semester=current` when the question asks for current-term metrics;
- call ToolDispatcher with the authenticated user, current campus, and trace;
- generate a stable structured answer from QueryMetricsResult;
- store all messages and audit records through AiAuditRecorder.

The implementation has approval to add `laravel/ai`, but the MVP should still
keep provider invocation deterministic and testable first. SDK integration
should be limited to package installation and adapter/readiness boundaries
unless live provider behavior is explicitly implemented with fakes and no live
test calls.

## Affected Users

- Internal staff users who need a low-friction way to ask aggregate Academic and
  Finance metric questions.
- Finance staff who need collection, fee-monitor, and DNG lifecycle summaries.
- Academic staff who need current-term defer and status aggregate summaries.
- Administrators and security reviewers who need permission, campus scope,
  source, hidden-section, confidence, and audit evidence.
- Engineers extending later AI entity, context, feedback, governance, and SDK
  integration stories.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

Portal impact: none.

This story must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, Identity
student/lecturer auth or context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Create this high-risk story packet and register
  `AI-MOD-005-staff-copilot-chat-mvp` in Harness before runtime code starts.
- Confirm `AI-MOD-004-query-metrics-tool-mvp` is implemented before executing
  runtime work.
- Add a staff copilot web route/page under the AI module route boundary.
- Gate the page and message submission by the accepted metric access
  permission.
- Add a FormRequest for message submission with bounded question length and no
  raw tool payload input from the browser.
- Add an AgentRunner or equivalent orchestration class under `app/Modules/AI`.
- Keep the first runtime read-only and limited to the registered
  `query_metrics` tool.
- Record correlated conversation, user message, trace, tool-call audit, and
  assistant message records for completed, denied, and failed submissions.
- Return structured answer props with answer text, summary metrics, grouped
  metrics, source references, normalized filters, campus scope, freshness,
  warnings, hidden sections, confidence, and safe error code.
- Render the chat MVP in Vue using `<script setup lang="ts">`, Inertia `useForm`,
  named routes, lucide icons, and existing `@/components/ui` primitives.
- Add suggested prompts from deterministic staff metric cases.
- Prove unauthorized staff cannot access or submit.
- Prove invalid/unsupported questions fail safely without source execution.
- Prove visible answers cite sources, filters, scope, freshness, hidden sections,
  and confidence.
- Prove answer numbers come from `query_metrics` output, not fabricated UI text.
- Update current-state docs after code lands.
- Record Harness trace with implementation and validation evidence.

## Non-Goals

- Do not add student or lecturer portal behavior.
- Do not add public API routes.
- Do not add EntityCatalog, `search_entities`, `get_entity_profile`, student
  profile sections, conversation context memory, or current-filter memory.
- Do not add write/action mode, emails, state changes, external side-effect
  tools, or human-approved mutations.
- Do not expose raw SQL, table names, column names, arbitrary joins, arbitrary
  includes, schema exploration, source-row dumps, hidden fields, or student-level
  PII through prompts, tool arguments, answers, or UI props.
- Do not add MCP server exposure, MCP-ready resources, provider-native tools,
  web search, embeddings, vector stores, sub-agents, streaming UI, failover, or
  prompt/tool version administration.
- Do not add any Composer/NPM dependency beyond the approved `laravel/ai`
  package without explicit approval.
- Do not call a live LLM provider in tests.
