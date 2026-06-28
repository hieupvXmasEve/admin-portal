# Provider/tool execution safety and fallback

Status: done

## Parent

`.scratch/staff-copilot-chat-run/PRD.md`

## What to build

Prove the execution path behind one Staff Copilot chat run: connected-provider mode can interpret a natural-language prompt and propose allowlisted tool calls, deterministic fallback remains available for supported prompts, and every business-data read still goes through ToolDispatcher validation before Swinx returns an answer.

The completed slice should be demoable with fakes: one run uses live-provider planning over a connected provider setting, one run falls back deterministically when live mode is unavailable or fails, one unsupported prompt fails safely, and one ambiguous prompt asks for clarification instead of guessing.

User stories covered: 27-35, 55-62, 68, 70.

## Acceptance criteria

- [x] A connected AI provider enables live-provider planning/final-answer synthesis but does not grant data access by itself.
- [x] Staff Copilot entry permission, domain permissions, current campus scope, tool schema, catalog version, result limits, and audit requirements are validated before any tool reads business data.
- [x] Provider-planned tool calls are treated as suggestions until ToolDispatcher accepts them.
- [x] Deterministic fallback answers supported prompts when live provider mode is unavailable.
- [x] Provider invocation failure produces a safe fallback or failed run and never fabricates unsupported data.
- [x] Unsupported write/action or out-of-catalog prompts fail safely without executing source reads.
- [x] Ambiguous metric, entity, filter, or term requests ask for clarification instead of guessing.
- [x] Feature tests use fake provider responses and mocked Academic/Finance AI reader contracts; no test requires live provider credentials or network calls.
- [x] Tests assert tool-call audit evidence: permission result, redacted arguments, source references, redacted result summary, safe error code, and no raw sensitive payloads.

## Blocked by

- `.scratch/staff-copilot-chat-run/issues/01-run-submission-and-active-run-recovery-contract.md`

## Implementation notes

- `query_metrics` now keeps `view_ai_metrics` as the Staff Copilot entry permission and adds per-metric domain permissions before resolver execution:
  - Academic metrics require `view_academic_report`.
  - Finance metrics require `view_finance_reporting`.
- Live provider prompt context includes the domain permission requirement, but provider-planned tool calls remain untrusted suggestions until `ToolDispatcher` and `QueryPlanValidator` accept them.
- Missing domain permission returns `forbidden_by_domain_permission`, records a denied `AiToolCall`, and does not call the Academic/Finance reader contract.
- Live-provider clarification planning now has feature coverage proving no tool execution occurs.

## Validation

- Passed: `./scripts/dev.sh composer exec pint -- --dirty --format agent`
- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/AI/AiLiveStaffCopilotAgentTest.php tests/Feature/AI/AiQueryMetricsToolTest.php tests/Feature/AI/AiMetricCatalogQueryPlanTest.php tests/Feature/AI/AiStaffCopilotChatTest.php tests/Feature/AI/AiStaffCopilotSseRuntimeTest.php`
- Passed: `./scripts/dev.sh artisan test --compact tests/Feature/AI`
- Blocked: `./scripts/dev.sh test --compact` exits `255`; direct Pest output shows a pre-existing fatal redeclaration, `Cannot redeclare function failedGradeRecord()`, between `tests/Feature/Academic/ExamResit/ListExamResitEligibleStudentsQueryTest.php` and `tests/Feature/Academic/ExamResitLegacyBackfillTest.php`.
- Blocked: `./scripts/dev.sh npm run type-check` exits `137` after `vue-tsc --noEmit` is killed.
