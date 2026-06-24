# Validation

## Proof Strategy

This story is done only when profile-section reads are proven as a bounded,
audited AI tool. The proof must show happy-path section reads, mixed permission
results, denied sections, invalid references, cross-campus blocking, live
provider planner validation, SSE event preservation, and redaction of hidden
student fields.

Automated tests must not require live provider credentials or network calls.
Use Laravel AI SDK fakes or project-local fakes for live-provider behavior.

## Test Plan

| Layer       | Cases                                                                                                                                                                                                                                                                                                                                                                                                                                |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Unit        | StudentProfileSectionCatalog exposes accepted section definitions, permissions, aliases, safe fields, max rows, and safe error codes. EntityReferenceResolver rejects forged, stale, mismatched, unsupported, and cross-campus references.                                                                                                                                                                                           |
| Integration | ToolRegistry registers `get_entity_profile`. ToolDispatcher executes it with a valid student `entity_ref`. Allowed sections return safe summaries. Mixed section permissions return partial status with hidden sections. Missing `view_student` denies before source execution. Missing finance/action permissions hide only those sections. Raw `student_id`, SQL, includes, and hidden-field options fail before source execution. |
| Integration | Academic reader returns identity, academic summary, enrollments, attendance summary, and lifecycle-action summaries through bounded source readers. Finance reader returns Finance Student 360 summary through Finance-owned read models and keeps ledger/payment/gateway details hidden.                                                                                                                                            |
| Integration | Current-campus student references work. Cross-campus references fail or deny without leaking the remote student's code/name. Existing `search_entities` candidate output remains PII-safe.                                                                                                                                                                                                                                           |
| Integration | AiToolCall audit records completed, partial, denied, and failed profile reads with redacted arguments/results, source references, hidden sections, campus scope, safe error code, confidence, and record counts.                                                                                                                                                                                                                     |
| Integration | LiveStaffCopilotAgent accepts fake structured planner output for `get_entity_profile`, validates tool arguments server-side, executes through ToolDispatcher, and synthesizes a final answer from redacted section results. Unsafe model-proposed profile calls are denied.                                                                                                                                                          |
| Integration | SSE run lifecycle emits normalized status/tool/message events for a profile-section run and persists enough `ai_run_events` for reload recovery.                                                                                                                                                                                                                                                                                     |
| E2E         | Optional browser smoke for `/ai/copilot`: search a student, ask for allowed sections, observe section answer with source/hidden-section display. If browser auth/setup is not available, record the gap explicitly.                                                                                                                                                                                                                  |
| Platform    | No portal files changed. `./scripts/portal-status.sh` only if implementation discovery touches portal-impacting API behavior, which this story should not do.                                                                                                                                                                                                                                                                        |
| Performance | Profile reads are bounded by section max rows and do not execute raw source-row exports. Add query-count or result-limit proof if implementation introduces heavier readers.                                                                                                                                                                                                                                                         |
| Logs/Audit  | AI audit tables contain redacted profile-section evidence and do not contain email, phone, national id, address, CCCD address, emergency-contact data, parent data, private notes, raw attendance rows, raw finance ledger, gateway payloads, or attachments.                                                                                                                                                                        |

## Fixtures

Use deterministic fixtures for:

- staff user with `view_ai_metrics`, `view_student`, `view_student_summary`,
  `view_finance_student_overview`, and `view_student_action`;
- staff user with `view_ai_metrics` and `view_student` only;
- staff user with `view_ai_metrics` but without `view_student`;
- staff user without `view_ai_metrics`;
- current campus and another campus;
- current-campus student with program, specialization, curriculum version,
  intake semesters, GC fields, academic summary rows, attendance rows, finance
  balances, DNG/installment status, and lifecycle action logs;
- other-campus student with a matching or similar student code/name;
- valid `entity_ref` from `search_entities`;
- forged, stale, catalog-mismatched, and cross-campus `entity_ref` values;
- fake live-provider planner output for accepted and unsafe profile requests.

## Commands

Expected commands after implementation:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStudentProfileSectionsTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiAuditFoundationTest.php
./scripts/dev.sh composer exec pint -- --dirty --format agent
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh npm run type-check
```

If repo-wide frontend checks hit known baseline or memory limits, record the
exact failure and run targeted checks for touched Vue/TS files.

## Acceptance Evidence

Validated on 2026-06-24:

```text
./scripts/dev.sh artisan test --compact tests/Feature/AI/AiStudentProfileSectionsTest.php
PASS: 5 passed, 228 assertions

./scripts/dev.sh artisan test --compact tests/Feature/AI/AiEntityCatalogSearchTest.php tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php tests/Feature/AI/AiQueryMetricsToolTest.php
PASS: 20 passed, 516 assertions

./scripts/dev.sh artisan test --compact tests/Feature/AI/AiQueryMetricsToolTest.php
PASS: 6 passed, 155 assertions

./scripts/dev.sh artisan test --compact tests/Feature/AI/AiAuditFoundationTest.php
PASS: 4 passed, 118 assertions

./scripts/dev.sh composer exec pint -- --dirty --format agent
PASS: fixed dirty PHP files

./scripts/dev.sh npm exec -- prettier --write resources/js/pages/AI/StaffCopilot/Index.vue
PASS: unchanged

./scripts/dev.sh npm exec -- eslint resources/js/pages/AI/StaffCopilot/Index.vue
PASS
```

Frontend gap:

```text
./scripts/dev.sh npm run type-check
FAIL: vue-tsc --noEmit was killed with exit code 137
```

Testing environment note:

- Running several RefreshDatabase-heavy files together intermittently left
  `db_test` in a half-migrated state (`migrations` table missing or tables
  already existing). A targeted `./scripts/dev.sh artisan migrate:fresh
  --env=testing --no-interaction` restored the testing database, and the
  story/regression files passed when run in the smaller command groups above.
