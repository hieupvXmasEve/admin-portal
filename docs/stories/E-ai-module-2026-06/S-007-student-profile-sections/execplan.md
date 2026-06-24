# Exec Plan

## Goal

Create the first safe student profile-section capability for internal Staff
Copilot. Staff should be able to ask profile follow-up questions about a
resolved student entity, and the answer should include only allowed, bounded,
audited sections with hidden-section reporting.

## Scope

In scope:

- `get_entity_profile:v1` internal AI tool for `student` entities.
- StudentProfileSectionCatalog v1.
- Entity reference decode/validation reused from `search_entities` output.
- Academic profile-section reader contract and Academic-module implementation
  for identity, academic summary, enrollments, attendance summary, and lifecycle
  actions.
- Finance profile-section reader contract and Finance-module implementation for
  Student 360 finance summary.
- ToolRegistry, ToolDispatcher, Staff Copilot deterministic/live planner, answer
  rendering, SSE event, and audit updates needed for the new tool.
- Feature tests and fake-backed live-provider tests proving permission, campus,
  redaction, safe errors, and audit behavior.
- Current-state doc updates after runtime implementation lands.

Out of scope:

- Student or lecturer portal changes.
- Public API routes.
- Conversation memory/current entity context persistence.
- Raw student profile exports, raw source rows, raw ledger timelines, raw
  attendance sessions, raw score component rows, attachments, notes, hidden
  fields, or arbitrary includes.
- Mutations, notifications, write/action mode, or external side effects.
- MCP exposure, vector stores, embeddings, files, images, audio, provider-native
  tools, sub-agents, WebSocket transport, or new dependencies.

## Risk Classification

Risk flags:

- Authorization.
- Audit/security.
- Public/internal AI contract.
- External provider behavior.
- Weak proof around PII and section permissions.
- Multi-domain reads across AI, Academic, and Finance.

Hard gates:

- Authorization.
- Audit/security.
- External provider behavior.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Discovery and baseline proof.
    - Confirm Harness matrix status for `AI-MOD-001` through `AI-MOD-006`,
      `AI-MOD-017`, and `AI-MOD-018`.
    - Re-read current `ToolRegistry`, `ToolDispatcher`, `SearchEntitiesTool`,
      `EntityCatalog`, `LiveStaffCopilotAgent`, and SSE runtime files.
    - Re-read Academic student profile/summary/action sources and Finance
      Student 360 readers.
2. Red tests.
    - Add `AiStudentProfileSectionsTest` for catalog/tool registration,
      valid section reads, denied/mixed permissions, invalid references,
      cross-campus blocking, safe argument rejection, audit, and PII redaction.
    - Extend live-agent/SSE tests for fake model profile tool calls and run
      events.
3. Profile section catalog and reference resolver.
    - Add catalog and result objects under the AI module.
    - Extract reusable entity reference validation from `SearchEntitiesTool`
      without weakening existing search behavior.
4. Domain reader contracts.
    - Add shared Academic and Finance contracts.
    - Implement thin module-owned readers that reuse existing queries/services
      and return section-specific safe payloads.
5. Tool execution.
    - Add `GetEntityProfileTool`.
    - Register in ToolRegistry and dispatch through ToolDispatcher.
    - Ensure failed/denied/partial/completed results always audit safely.
6. Staff Copilot runtime.
    - Update deterministic planner only for clear profile-section prompts.
    - Update live planner context/schema validation to allow
      `get_entity_profile` with accepted sections.
    - Update final-answer synthesis and UI answer data as needed.
    - Keep deterministic fallback unchanged for unsupported questions.
7. Verification and docs.
    - Run targeted AI regression tests and formatting.
    - Run targeted frontend checks if Vue/TS changes occur.
    - Update current-state docs and this story evidence.
    - Record Harness trace with validation evidence.

## Stop Conditions

Pause for human confirmation if:

- A new permission is needed instead of reusing existing section permissions.
- A migration is needed for audit/runtime evidence.
- Implementation discovery suggests exposing contact, address, parent,
  emergency-contact, notes, attachments, raw attendance, raw score, raw finance
  ledger, payment method, or gateway payload data.
- Current-campus behavior needs to use `view_finance_all_campus` or another
  all-campus policy in a way not already accepted by source surfaces.
- The Laravel AI SDK cannot support the fake-backed planner/final-answer proof
  and custom provider glue is being considered.
- Validation has to be weakened or browser/provider proof cannot be reproduced.
