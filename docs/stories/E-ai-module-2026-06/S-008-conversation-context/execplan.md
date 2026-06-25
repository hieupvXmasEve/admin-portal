# Exec Plan

## Goal

Add bounded conversation context for internal Staff Copilot so staff can ask
safe follow-up questions that reuse the current resolved entity, current
filters, current term, recent tool history, and short conversation summary.

## Scope

In scope:

- ConversationContextBuilder for current actor/campus/open Staff Copilot
  conversations.
- Redacted context packet with schema version, selected messages, selected tool
  calls, resolved entities, filters, current term, tool history, hidden
  sections, warnings, confidence, and summary.
- Optional narrow context snapshot persistence if rebuild-only context cannot
  satisfy reload/retry/audit requirements.
- Entity ref revalidation through existing `EntityReferenceResolver`.
- Filter and term context derived from accepted tool output and Swinx-owned
  resolver/source metadata.
- LiveStaffCopilotAgent planner prompt updates to include bounded context.
- Deterministic fallback support for unambiguous "this student", "same
  filters", and "current term" follow-ups.
- SSE/run audit updates needed to preserve context evidence.
- Feature tests and fake-backed live-provider tests proving permission, campus,
  stale-context, redaction, clarification, and audit behavior.
- Current-state doc updates after runtime implementation lands.

Out of scope:

- Student or lecturer portal changes.
- Public API routes.
- Long-term staff preference memory across conversations.
- Provider-native memory as product source of truth.
- Full raw transcript prompting.
- Raw SQL, schema exploration, source-row dumps, arbitrary joins, hidden field
  access, raw profile exports, raw finance ledger timelines, raw attendance
  sessions, raw score component rows, private notes, attachments, gateway
  payloads, contact/address/parent data, or broad PII extraction.
- Mutations, notifications, write/action mode, approval workflows, MCP, vector
  stores, embeddings, files, images, audio, provider-native tools, sub-agents,
  WebSocket transport, compare metrics, recommendation rules, feedback
  controls, admin quota dashboards, or prompt/tool versioning.
- New dependencies without explicit approval.

## Risk Classification

Risk flags:

- Authorization: context can point to entity/profile data and must re-check
  current permissions.
- Audit/security: context summaries and snapshots must remain redacted and
  traceable.
- External provider behavior: live planner receives context and may misuse it
  unless server validation remains strict.
- Public/internal contract: Staff Copilot planner prompts, answer evidence, page
  props, and run events may change.
- Existing behavior: deterministic fallback, SSE reload, retry, and current
  tools must keep working.
- Weak proof: context bugs can be subtle and require explicit stale/ambiguous
  tests.
- Multi-domain: context may reference Academic and Finance evidence through AI
  tools.

Hard gates:

- Authorization.
- Audit/security.
- External provider behavior.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Discovery and baseline proof.
    - Confirm Harness status for `AI-MOD-001` through `AI-MOD-007`,
      `AI-MOD-017`, and `AI-MOD-018`.
    - Re-read `StaffCopilotSseRuntime`, `StaffCopilotAgentRunner`,
      `LiveStaffCopilotAgent`, `StaffCopilotPageQuery`, `ToolRegistry`,
      `ToolDispatcher`, `EntityReferenceResolver`, `SearchEntitiesTool`,
      `GetEntityProfileTool`, `QueryMetricsTool`, and current AI migrations.
    - Confirm whether context can be rebuilt from existing records or needs a
      narrow `ai_conversation_context_snapshots` table.
2. Red tests.
    - Add `AiConversationContextTest` for context builder scope, redaction,
      entity ref revalidation, filter reuse, term resolution, summary rules,
      ambiguity, stale references, permission drift, cross-campus blocking, and
      audit.
    - Extend live-agent and SSE tests for fake context-aware planner output,
      context events/evidence, reload recovery, retry, and unsafe context use.
3. Context packet foundation.
    - Add context DTOs/value objects under the AI module.
    - Implement message/tool-call selectors with count, age, and token budgets.
    - Add safe error codes and schema/version constants.
4. Entity, filter, and term resolution.
    - Reuse `EntityReferenceResolver` for all current entity context.
    - Build filter context from accepted result summaries and explicit staff
      overrides.
    - Resolve current term through Swinx-owned source metadata or resolver
      logic.
5. Summary and persistence.
    - Add deterministic summary update/rebuild path.
    - Persist context snapshot only if needed for reload/retry/audit evidence.
    - Keep stored context redacted and source-linked.
6. Runtime integration.
    - Build context before live/deterministic planning.
    - Add context to live planner instructions/prompt without full transcript.
    - Extend deterministic fallback for unambiguous follow-ups.
    - Ensure ToolDispatcher remains the enforcement point.
    - Add SSE/audit evidence for context built, ambiguous, invalidated, or used.
7. UI evidence.
    - Reuse existing Staff Copilot answer evidence rendering for context-based
      source references, hidden sections, warnings, and confidence.
    - Add compact context props only if needed for transparent reload/recovery.
8. Verification and docs.
    - Run targeted AI feature/regression tests and formatting.
    - Run targeted frontend checks if Vue/TS changes occur.
    - Update current-state docs after runtime lands.
    - Record Harness trace with validation evidence.

## Stop Conditions

Pause for human confirmation if:

- A new permission is needed instead of reusing existing Staff Copilot, entity,
  profile, or metric permissions.
- Context persistence requires a migration broader than a narrow AI-owned
  snapshot table.
- Context would need to store hidden facts, raw rows, raw provider payloads,
  source model names, SQL, table names, private notes, attachments, contact
  data, gateway payloads, raw finance ledgers, raw attendance, or raw score
  rows.
- Current-campus behavior needs an all-campus policy not already accepted by
  source surfaces.
- Laravel AI SDK cannot support fake-backed context-aware planner/final-answer
  tests and custom provider glue is being considered.
- Validation has to be weakened or provider/browser proof cannot be reproduced.
- Product scope expands into long-term memory, write actions, portal
  assistants, compare metrics, recommendation rules, MCP, vector search,
  provider-native retrieval, or provider-native memory.
