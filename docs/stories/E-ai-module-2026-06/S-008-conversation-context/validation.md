# Validation

## Proof Strategy

This story is done only when conversation context is proven as a bounded,
redacted, auditable input to Staff Copilot planning. The proof must show
follow-up convenience without weakening permission, campus, catalog, tool, or
redaction boundaries.

Automated tests must not require live provider credentials or network calls.
Use Laravel AI SDK fakes or project-local fakes for live-provider behavior.

## Test Plan

| Layer       | Cases                                                                                                                                                                                                                                                                                                                               |
| ----------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Unit        | ConversationContextBuilder selects only current actor/campus/open `staff_chat` records, respects item/token budgets, redacts messages, and rejects closed/evaluation/other-user/other-campus conversations.                                                                                                                         |
| Unit        | ResolvedEntityContext accepts valid recent `entity_ref` evidence from `search_entities` or `get_entity_profile`, revalidates through EntityReferenceResolver, and invalidates forged, stale, catalog-mismatched, unsupported, denied, or cross-campus refs.                                                                         |
| Unit        | FilterContext selects latest accepted normalized filters/groupings, applies explicit staff overrides, and rejects hidden fields, raw rows, SQL/table payloads, denied tool output, and unsupported filters.                                                                                                                         |
| Unit        | TermContext resolves current term only from Swinx code or accepted source metadata and returns `context_term_unresolved` when no safe term exists.                                                                                                                                                                                  |
| Unit        | ConversationSummaryUpdater produces bounded redacted summaries with source message/tool ids, schema versions, hidden-section limitations, and no unsupported PII.                                                                                                                                                                   |
| Integration | Staff asks a profile follow-up after one resolved student. Planner maps "this student" to `get_entity_profile` with the revalidated opaque `entity_ref`; ToolDispatcher executes normally; answer cites context source and profile source.                                                                                          |
| Integration | Staff asks "same filters" after a successful `query_metrics` result. Planner reuses accepted filters, applies explicit group-by or metric changes, validates through QueryPlanValidator, and cites reused filters.                                                                                                                  |
| Integration | Conversation has multiple recent students or filter sets. Copilot asks clarification and performs no source read.                                                                                                                                                                                                                   |
| Integration | Permission changes after context was built. Later tool execution hides or denies affected sections and does not include hidden facts in prompts, context snapshots, answers, or audit.                                                                                                                                              |
| Integration | Cross-campus or stale context is invalidated before source execution and does not leak the remote entity label/code.                                                                                                                                                                                                                |
| Integration | LiveStaffCopilotAgent receives bounded context in fake planner prompts, validates model-proposed context-derived tool calls server-side, and synthesizes a final answer from redacted tool results. Unsafe model-proposed context use is denied.                                                                                    |
| Integration | Deterministic fallback handles unambiguous "this student", "same filters", and "current term" follow-ups; unsupported or ambiguous follow-ups return clarification/unsupported safely.                                                                                                                                              |
| Integration | SSE runtime persists context-building events or audit evidence, recovers active runs on reload, and retry rebuilds context without duplicating stale or denied context.                                                                                                                                                             |
| Integration | Context snapshot or rebuild audit records include schema version, source message/tool ids, campus scope, hidden sections, warnings, confidence, safe errors, and redacted summary only.                                                                                                                                             |
| E2E         | Optional browser smoke for `/ai/copilot`: search one student, ask a profile follow-up using "this student", then ask a same-filter metric follow-up. Verify visible answer evidence and hidden-section display. If browser auth/setup is not available, record the gap explicitly.                                                  |
| Platform    | No portal files changed. `./scripts/portal-status.sh` only if implementation discovery touches portal-impacting API behavior, which this story should not do.                                                                                                                                                                       |
| Performance | Context build is bounded by message/tool-count and token budgets; it should not scan all historical conversations or load raw source rows. Add query-count or result-limit proof if implementation introduces persisted context snapshots or heavier history selection.                                                             |
| Logs/Audit  | AI audit/context records do not contain raw provider bodies, API keys, auth headers, SQL, table names, source model names, hidden facts, raw ledger rows, contact/address/parent/emergency-contact data, private notes, gateway payloads, attachments, raw attendance sessions, raw score component rows, or arbitrary source rows. |

## Fixtures

Use deterministic fixtures for:

- staff user with `view_ai_metrics`, `view_student`, `view_student_summary`,
  `view_finance_student_overview`, and `view_student_action`;
- staff user with `view_ai_metrics` and limited section permissions;
- staff user with `view_ai_metrics` but without `view_student`;
- staff user without `view_ai_metrics`;
- current campus and another campus;
- current-campus student with safe entity/profile evidence;
- other-campus student with similar student code/name;
- successful `search_entities`, `get_entity_profile`, and `query_metrics`
  tool-call audit rows;
- denied, failed, partial, and completed tool-call audit rows;
- open and closed Staff Copilot conversations;
- evaluation conversation records that must not become staff context;
- valid, forged, stale, catalog-mismatched, and cross-campus `entity_ref`
  values;
- current-term resolver/source metadata;
- fake live-provider planner output for accepted and unsafe context-derived tool
  requests;
- active SSE run with persisted run events for reload/retry proof.

## Commands

Expected commands after implementation:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiConversationContextTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStudentProfileSectionsTest.php tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiQueryMetricsToolTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiAuditFoundationTest.php
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh npm run type-check
```

If repo-wide frontend checks hit known baseline or memory limits, record the
exact failure and run targeted checks for touched Vue/TS files.

## Acceptance Evidence

No runtime implementation has landed yet. Add commands, outputs, screenshots,
or audit snippets here after validation exists.
