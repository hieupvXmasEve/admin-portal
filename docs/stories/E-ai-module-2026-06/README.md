# AI Module 2026-06 Story Set

Source blueprint: `docs/features/ai/ai.md`

SDK baseline: `docs/features/ai/laravel-ai-sdk.md`

This epic decomposes the AI data-analysis blueprint into high-risk Harness
stories. The goal is to keep one durable place that says what has been done,
what is next, and which slice owns each part of the AI module.

The operating rule is deliberately conservative: AI must not access the database
freely, generate production SQL, infer hidden fields, or bypass Swinx
permissions. The first runtime slices are internal, read-only, campus-scoped,
allowlisted, and audited.

## Master Story

| Story id                       | Story packet               | Status      | Purpose                                                                                           |
| ------------------------------ | -------------------------- | ----------- | ------------------------------------------------------------------------------------------------- |
| `AI-MOD-000-ai-module-roadmap` | `S-000-ai-module-roadmap/` | implemented | Master roadmap, phase tracker, shared guardrails, and split criteria for future AI child stories. |

## Phase And Story Order

Child story packets are created only when a phase is selected for execution.
Until then, this table is the source of truth for planned AI work.

| Order | Story id                                       | Planned packet                             | Status      | Main scope                                                                                                                                | Depends on         |
| ----- | ---------------------------------------------- | ------------------------------------------ | ----------- | ----------------------------------------------------------------------------------------------------------------------------------------- | ------------------ |
| 0     | `AI-MOD-000-ai-module-roadmap`                 | `S-000-ai-module-roadmap/`                 | implemented | Initiative tracker, phase map, non-goals, validation expectations, and risk guardrails                                                    | None               |
| 1.1   | `AI-MOD-001-ai-governance-provider-settings`   | `S-001-ai-governance-provider-settings/`   | implemented | AI module shell, permissions, provider config boundary, encrypted API keys, model selection, cost limits, and provider test flow          | 000                |
| 1.2   | `AI-MOD-002-ai-audit-evaluation-foundation`    | `S-002-ai-audit-evaluation-foundation/`    | implemented | Conversation/message/tool-call/provider-usage audit records, redaction rules, and baseline evaluation dataset                             | 000                |
| 1.3   | `AI-MOD-003-metric-catalog-query-plan`         | `S-003-metric-catalog-query-plan/`         | implemented | Business glossary subset, MetricCatalog, filter/group-by allowlists, QueryPlanValidator, and 3-5 real staff questions                     | 001, 002           |
| 1.4   | `AI-MOD-004-query-metrics-tool-mvp`            | `S-004-query-metrics-tool-mvp/`            | implemented | ToolRegistry/ToolDispatcher foundation and read-only `query_metrics` tool backed by existing Academic/Finance queries                     | 003                |
| 1.5   | `AI-MOD-005-staff-copilot-chat-mvp`            | `S-005-staff-copilot-chat-mvp/`            | implemented | Internal staff chat surface, AgentRunner loop, structured output, answer sources, hidden-section notices, and dashboard parity proof      | 004                |
| 2.1   | `AI-MOD-006-entity-catalog-search`             | `S-006-entity-catalog-search/`             | implemented | EntityCatalog for student/class/program/semester, `search_entities`, scoped identifiers, and PII-safe search result limits                | 005                |
| 2.2   | `AI-MOD-017-live-provider-staff-copilot-agent` | `S-017-live-provider-staff-copilot-agent/` | implemented | Live provider Staff Copilot agent that interprets natural-language prompts and chooses allowlisted tools through ToolDispatcher           | 001-006            |
| 2.3   | `AI-MOD-007-student-profile-sections`          | `S-007-student-profile-sections/`          | planned     | `get_entity_profile` as a Live Copilot capability for student profile sections with section-level permission and hidden-section reporting | 006, 017           |
| 2.4   | `AI-MOD-008-conversation-context`              | `S-008-conversation-context/`              | planned     | Live Copilot context: resolved entities, current filters, current term, conversation summary, tool history, and bounded context rebuild   | 007, 017           |
| 3.1   | `AI-MOD-009-compare-metrics-tool`              | `S-009-compare-metrics-tool/`              | planned     | `compare_metrics` for current-vs-previous term and scoped trend explanations with source filters                                          | 004, 008           |
| 3.2   | `AI-MOD-010-risk-and-recommendation-rules`     | `S-010-risk-and-recommendation-rules/`     | planned     | Rule-backed Live Copilot recommendations with missing-data handling, confidence levels, and no-ML default                                 | 007, 009, 017      |
| 3.3   | `AI-MOD-011-feedback-and-evaluation-loop`      | `S-011-feedback-and-evaluation-loop/`      | planned     | Feedback controls, evaluation runner, expected answer fixtures, tool-call assertions, and Live Copilot regression tracking                | 002, 005, 010, 017 |
| 4.1   | `AI-MOD-012-admin-governance-quota`            | `S-012-admin-governance-quota/`            | planned     | Admin tool/model/provider toggles, quota enforcement, rate limits, data-access preview, and audit viewer for live/deterministic modes     | 001, 002, 005, 017 |
| 4.2   | `AI-MOD-013-prompt-tool-versioning`            | `S-013-prompt-tool-versioning/`            | planned     | Prompt versions, tool schema versions, catalog version stamps, answer traceability, and rollback strategy for Live Copilot                | 002, 004, 017      |
| 4.3   | `AI-MOD-014-mcp-ready-resources-tools`         | `S-014-mcp-ready-resources-tools/`         | planned     | MCP-ready mapping for tools/resources/prompts after internal core is stable; no MCP server before this story                              | 012, 013           |
| 5.1   | `AI-MOD-015-student-portal-assistant-spike`    | `S-015-student-portal-assistant-spike/`    | planned     | Separate student-portal feasibility spike only after internal staff assistant proves permission, audit, and evaluation gates              | 011, 012           |
| 5.2   | `AI-MOD-016-write-approval-mode`               | `S-016-write-approval-mode/`               | planned     | Explicit human-approved write/action mode; mutations remain out of scope until a separate high-risk story accepts it                      | 011, 012           |

## Live Copilot Capability Model

`AI-MOD-017-live-provider-staff-copilot-agent` is the bridge from the current
deterministic Staff Copilot to a live provider-backed agent. It should not make
the remaining planned stories obsolete. Instead, it turns the remaining stories
into safe capabilities that the live agent can use.

The intended layering is:

```text
Live provider Staff Copilot agent
  -> chooses allowlisted tool calls from natural-language prompts
  -> ToolDispatcher validates and executes Swinx-owned tools
  -> domain modules read source-of-truth data through shared contracts
  -> agent synthesizes answer with source/campus/permission/audit evidence
```

Capability expansion after `AI-MOD-017`:

- `AI-MOD-007`: adds safe profile-section reads for selected entities.
- `AI-MOD-008`: adds resolved entity/current filter/current term context for
  follow-up questions.
- `AI-MOD-009`: adds comparison and trend tools.
- `AI-MOD-010`: adds rule-backed recommendations.
- `AI-MOD-011`: adds live-agent evaluation and feedback regression proof.
- `AI-MOD-012` and `AI-MOD-013`: add governance, quota, prompt/tool versioning,
  and rollback control for live and deterministic modes.

## Shared Rules

- Portal impact is `none` until a future story explicitly changes
  `/api/v1/student/*`, `/api/v1/lecturer/*`, or nested Nuxt portal behavior.
- Every AI child story must use `docs/features/ai/laravel-ai-sdk.md` as the
  official SDK baseline before designing provider calls, agents, tools,
  structured output, conversation storage, streaming, testing, events, failover,
  files, embeddings, vector stores, MCP, or provider-specific options.
- Use the Laravel AI SDK as the provider/agent abstraction where it supports the
  accepted capability. Do not create a parallel custom provider/client layer
  unless a child story proves the SDK cannot support the required behavior and
  records that exception.
- New runtime code should live under a dedicated AI module boundary, not as
  scattered controller logic. Existing Academic/Finance/Notification queries
  remain the business-data source.
- The AI layer plans and validates tool calls; domain modules execute the
  allowlisted reads through their existing Query/Action patterns.
- API keys choose provider/model/cost behavior only. Data access always follows
  the authenticated user, Gate/policy permissions, campus scope, field/section
  allowlists, and audit policy.
- Swinx owns permission resolution, campus/user context, encrypted staff-owned
  settings, quota/cost policy, redaction, audit, metric/entity catalogs, domain
  tool contracts, and source-of-truth report parity. The Laravel AI SDK owns
  provider/model invocation, agent interfaces, SDK fakes/assertions, and SDK
  events where those surfaces are accepted by the current child story.
- SDK capabilities are not automatically product capabilities. Images, audio,
  transcription, embeddings, vector stores, MCP tools, provider tools,
  sub-agents, streaming, failover, and write/action behavior require explicit
  child-story acceptance with permission, audit, cost, and validation rules.
- The first implementation phase is read-only. AI may read, analyze, summarize,
  and recommend; it must not mutate records, trigger emails, change state, or
  call external side-effect tools.
- Every tool must have an input schema, output schema, permission rule, rate
  limit, max-record guard, audit policy, and deterministic error contract before
  it is exposed to the AgentRunner.
- Every answer that includes numbers or record facts must cite tool/source,
  filters, scope, data freshness, hidden sections, and confidence.
- Do not implement AI-generated production SQL, broad vector indexing of
  sensitive records, fine-tuning on live student data, multi-agent automation,
  MCP exposure, or write actions before the corresponding planned story is
  accepted.
- Each child story must update this README status and register its Harness story
  row before implementation starts.

## Recommended First Slice

Start with `AI-MOD-001` through `AI-MOD-005` as Phase 1. The MVP should answer a
small set of staff questions that can be checked against existing Swinx reports,
for example current-term defer counts, tuition collection/outstanding summaries,
or finance data-health findings. The exact questions belong in
`AI-MOD-003-metric-catalog-query-plan`.
