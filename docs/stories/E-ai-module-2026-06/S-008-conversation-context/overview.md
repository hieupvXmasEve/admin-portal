# AI-MOD-008 - Conversation Context

## Status

planned

## Lane

high-risk

## Current Behavior

Swinx already has a safe internal Staff Copilot foundation:

- `AI-MOD-001-ai-governance-provider-settings` provides staff-owned provider
  settings, encrypted credentials, model allowlists, cost limits, and provider
  test evidence.
- `AI-MOD-002-ai-audit-evaluation-foundation` provides AI conversations,
  messages, traces, tool-call audit, provider usage, feedback, redaction, and
  evaluation records.
- `AI-MOD-003-metric-catalog-query-plan` defines allowlisted metric semantics
  and validation rules.
- `AI-MOD-004-query-metrics-tool-mvp` provides the internal read-only
  `query_metrics` tool.
- `AI-MOD-005-staff-copilot-chat-mvp` provides `/ai/copilot`, message
  submission, conversation persistence, deterministic planning, and answer UI.
- `AI-MOD-006-entity-catalog-search` provides EntityCatalog v1,
  `search_entities:v1`, current-campus lookup, opaque `entity_ref` values, and
  bounded candidate results.
- `AI-MOD-017-live-provider-staff-copilot-agent` lets a live provider choose
  allowlisted tools through ToolDispatcher.
- `AI-MOD-018-provider-agnostic-sse-chat-runtime` persists run lifecycle and
  streams normalized Staff Copilot events through SSE.
- `AI-MOD-007-student-profile-sections` provides `get_entity_profile:v1` for
  bounded, audited student profile sections from opaque `entity_ref` values.

Current gap:

- Each run still plans mostly from the latest staff question plus static
  catalog/tool summaries.
- Follow-up prompts such as "show me this student's finance summary", "use the
  same filters", "what about the current term", or "continue from the previous
  result" are ambiguous unless the staff member repeats the entity, filters, or
  term.
- Prior messages, tool calls, run events, and redacted answer payloads contain
  useful evidence, but there is no Swinx-owned context builder that selects a
  safe subset for the next turn.
- There is no canonical resolved-entity memory, current-filter memory,
  current-term snapshot, conversation summary, or bounded tool-history packet.
- Letting a provider infer context from the full transcript would be unsafe:
  hidden sections, stale entity references, cross-campus data, and failed or
  denied tool calls must not become implicit authority for later turns.

## Target Behavior

This story adds bounded conversation context for the internal Staff Copilot. The
context layer helps the copilot answer follow-up questions while preserving the
existing Swinx safety model.

Target behavior:

- Build a Swinx-owned context packet before each Staff Copilot run.
- The context packet is scoped to the authenticated staff user, current campus,
  current open Staff Copilot conversation, current tool/catalog versions, and
  the selected conversation history window.
- Context includes only redacted, bounded, auditable state:
    - recent redacted messages;
    - current resolved entities from successful entity/profile tool calls;
    - current filters from successful metric/profile tool calls;
    - current term or semester when it is resolved by Swinx code;
    - recent tool-call history with safe status, source references, hidden
      sections, warnings, and confidence;
    - a short conversation summary that is derived from redacted evidence.
- Rebuild or refresh context from canonical AI records: conversations, messages,
  traces, tool calls, chat runs, and run events. Persist a redacted context
  snapshot only when implementation needs durable reload/retry/audit evidence.
- Feed the live-provider planner a compact context packet rather than the full
  raw transcript.
- Extend deterministic fallback enough to handle clear follow-ups for the
  current resolved student, current filters, and current term.
- Ask for clarification when context is missing, ambiguous, stale, denied, or
  cross-campus.
- Keep every business-data read behind ToolDispatcher. Context may suggest
  arguments, but it never grants permission or bypasses tool validation.
- Preserve existing hidden-section behavior: context may remember that a section
  was hidden, but it must not reveal hidden facts or use hidden data in later
  prompts.
- Keep portal impact `none`.

## Affected Users

- Internal staff using Staff Copilot for multi-turn academic and finance
  analysis.
- Academic staff asking profile follow-ups about a resolved student.
- Finance staff asking follow-ups that reuse prior filters or current-term
  context.
- Administrators and security reviewers who need proof that AI context remains
  permission-aware, campus-scoped, redacted, and auditable.
- Engineers implementing comparison, recommendation, feedback, governance, and
  prompt-versioning stories that depend on stable context evidence.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

Portal impact: none.

This story affects only the internal web Staff Copilot. It must not change
`/api/v1/student/*`, `/api/v1/lecturer/*`, Identity student/lecturer auth or
context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Register `AI-MOD-008-conversation-context` in Harness before runtime
  implementation starts.
- Keep `AI-MOD-008` in the `E-ai-module-2026-06` roadmap after
  `AI-MOD-007`, `AI-MOD-017`, and `AI-MOD-018`.
- Confirm `AI-MOD-001` through `AI-MOD-007`, `AI-MOD-017`, and `AI-MOD-018`
  are implemented before runtime work.
- Add a Swinx-owned conversation context builder for Staff Copilot.
- Context must be scoped by `ai_conversation_id`, actor user, current campus,
  origin `staff_chat`, and open conversation status.
- Context must not read or reuse another user's conversation, another campus'
  conversation, closed conversations, evaluation conversations, or provider SDK
  conversation state as authoritative product context.
- Context must include only redacted message content and redacted tool/result
  metadata.
- Build current resolved-entity context only from successful or partial
  `search_entities` and `get_entity_profile` results.
- Store or carry resolved entities as opaque `entity_ref` values plus safe
  labels/source metadata; do not store raw database ids as the context contract.
- Re-validate every `entity_ref` before using it in a later tool call.
- Drop or invalidate stale, forged, catalog-mismatched, unsupported, denied, or
  cross-campus entity references before planner execution.
- Build current filters only from accepted tool output, normalized filters,
  groupings, source references, and explicit staff follow-up text.
- Never use hidden sections, denied tool output, raw rows, SQL, table names,
  source model names, or provider-only memory as filter context.
- Resolve current term through Swinx-owned term/semester logic or accepted
  metric/profile source metadata; do not let the model invent the current term.
- Keep recent tool history bounded by count, age, token budget, and redaction.
- Include failed/denied tool calls only as safe error context when it helps avoid
  repeating an unsafe request; never include denied facts.
- Create a conversation summary that records only redacted intent, resolved
  entities, filters, term, decisions, and safe limitations.
- Version context schema and summary prompt/rules so later prompt-tool
  versioning can trace which context contract was used.
- Attach context snapshot metadata to the active trace/run audit evidence.
- Live provider planner prompts must receive only the bounded context packet,
  tool/catalog summaries, and current question.
- Deterministic fallback must handle unambiguous follow-ups for:
    - "this student" after one valid current student entity exists;
    - "same filters" after one valid current filter set exists;
    - "current term" when Swinx can resolve it for the current campus.
- If context has multiple possible entities, multiple filter sets, missing term,
  stale references, or permission conflict, the copilot asks a clarification
  question instead of guessing.
- Existing ToolDispatcher validation remains authoritative for every proposed
  `query_metrics`, `search_entities`, and `get_entity_profile` call.
- Staff Copilot answers that rely on context must cite the context source:
  previous tool call, source report, filters, campus scope, freshness, hidden
  sections, and confidence.
- SSE run events and page reload recovery must preserve enough redacted context
  evidence for an active run to complete or fail safely.
- Retry must rebuild context from the original user message and current
  conversation state without duplicating stale or denied context.
- Automated tests must use deterministic fixtures and Laravel AI SDK or
  project-local fakes. They must not require live provider credentials or
  network calls.
- Update current-state docs after runtime implementation lands.
- Record Harness trace with implementation and validation evidence.

## Non-Goals

- Do not add student or lecturer portal behavior.
- Do not add public API routes or expose context outside internal Staff
  Copilot.
- Do not add long-term user preference memory across conversations.
- Do not add free-form memory, embeddings, vector stores, files, provider-native
  memory, provider-native retrieval, or SDK conversation tables as the product
  source of truth.
- Do not pass the full raw transcript to the provider.
- Do not use hidden sections, denied results, raw rows, contact details,
  addresses, parent/emergency-contact data, private notes, attachments, gateway
  payloads, raw finance ledgers, raw attendance sessions, raw score component
  rows, SQL, table names, or source model names as context.
- Do not add write/action mode, mutation tools, email sending, notifications,
  state changes, or approval workflows.
- Do not add compare metrics, recommendation rules, feedback controls, admin
  quota dashboards, prompt/tool versioning, MCP exposure, WebSocket transport,
  files, images, audio, provider-native tools, or sub-agents.
- Do not add Composer or NPM dependencies without explicit approval.
