# Exec Plan

## Goal

Implement the first internal staff copilot chat MVP on top of the existing
`query_metrics` tool, with permission-safe message submission, structured
answers, source citations, hidden-section notices, confidence, and audit
evidence.

## Scope

In scope for this requirements step:

- Record Harness intake for `AI-MOD-005-staff-copilot-chat-mvp`.
- Create this high-risk story packet.
- Register the story in Harness.
- Define the runtime route, orchestration, answer, UI, audit, and validation
  contracts.
- Define dependency stop conditions for Laravel AI SDK adoption.

In scope for runtime implementation after approval:

- Add `GET /ai/copilot` and `POST /ai/copilot/messages` under the existing AI
  web route group.
- Add the approved `laravel/ai` Composer dependency and keep runtime code behind
  the Swinx-owned AgentRunner/ToolDispatcher boundary.
- Add `StaffCopilotController` and `SubmitStaffCopilotMessageRequest`.
- Add page prop query and message action classes under the AI module.
- Add deterministic StaffCopilotAgentRunner and answer value object under the
  AI module; use StaffMetricQuestionDataset as the prompt catalog.
- Use BusinessGlossary, StaffMetricQuestionDataset, ToolDispatcher, and
  QueryMetricsResult to answer the accepted MetricCatalog v1 staff questions.
- Persist conversation/user-message/trace/tool-call/assistant-message evidence
  through existing AI audit models and recorder.
- Add `resources/js/pages/AI/StaffCopilot/Index.vue`.
- Add route helper constants and sidebar entry for the copilot page.
- Update current-state docs after runtime behavior lands.
- Add targeted feature tests and frontend static checks.

Out of scope:

- Adding any dependency other than the now-approved `laravel/ai` package without
  explicit approval.
- Live LLM/provider calls unless they use SDK fakes in tests and do not require
  real provider credentials.
- Streaming.
- EntityCatalog, entity search, student profile sections, conversation context
  memory, compare metrics, risk recommendation rules, feedback controls, admin
  governance dashboards, quota enforcement, prompt/tool versioning, MCP
  exposure, and write/action mode.
- Student API, lecturer API, Identity portal routes, nested Nuxt portal code, or
  public API routes.
- Database migrations unless implementation discovery proves an unavoidable
  storage gap and the user approves that change.
- Raw SQL, schema exploration, broad source-row exposure, hidden fields, or
  student-level PII.

## Risk Classification

Risk flags:

- Authorization: route/page/message submission and tool execution must respect
  staff AI metric permission and current campus scope.
- Audit/security: prompts, tool arguments, result summaries, source references,
  hidden sections, and safe errors must be redacted and auditable.
- External provider behavior: the story touches the provider/Agent boundary,
  even though the recommended MVP avoids live provider calls.
- Public contracts: new staff-visible route, page props, form contract, answer
  evidence contract, and sidebar entry.
- Existing behavior: visible numbers must match existing `query_metrics` and
  source reports.
- Weak proof: AI answer quality requires deterministic source-parity and
  no-fabrication tests.
- Multi-domain: the first prompts span Academic and Finance metrics.

Hard gates:

- Authorization.
- Audit/security.
- External provider behavior.
- Removing or weakening validation requirements.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Requirements packet
    - Record Harness intake.
    - Create high-risk story files.
    - Register Harness story row.
    - Validate packet presence and incomplete markers.
    - Present implementation approach for approval before runtime code.

2. Runtime discovery
    - Re-read AI routes, provider settings controller, AI audit recorder,
      conversation/message/trace models, StaffMetricQuestionDataset,
      BusinessGlossary, ToolDispatcher, QueryMetricsResult, and sidebar route
      helpers.
    - Confirm `AI-MOD-004-query-metrics-tool-mvp` is implemented in Harness.
    - Confirm `laravel/ai` installation and available SDK classes.
    - Keep the deterministic AgentRunner and no live provider calls for the MVP
      unless implementation discovery proves the SDK fake path is low-risk.

3. RED tests
    - Add `tests/Feature/AI/AiStaffCopilotChatTest.php`.
    - Test authorized page render and suggested prompts.
    - Test unauthorized page/submission denial.
    - Test supported finance prompt creates conversation, messages, trace,
      audited tool call, and visible answer evidence.
    - Test unsupported prompt fails safely without source reader execution.
    - Run the test and verify expected failures before production code.

4. Backend implementation
    - Add `SubmitStaffCopilotMessageRequest`.
    - Add `StaffCopilotController`.
    - Add `StaffCopilotPageQuery`.
    - Add `RunStaffCopilotMessageAction`.
    - Add `StaffCopilotAgentRunner`.
    - Add `StaffCopilotAnswer`.
    - Add or update route definitions.
    - Bind any new support classes only if container autowiring is insufficient.

5. Frontend implementation
    - Add route helper constants for `ai.copilot.index` and
      `ai.copilot.messages.store`.
    - Add `resources/js/pages/AI/StaffCopilot/Index.vue`.
    - Add sidebar item guarded by the accepted copilot permission.
    - Use Inertia `useForm`, named routes, lucide icons, existing UI
      primitives, and stable answer-evidence layout.

6. Documentation and story state
    - Update `docs/stories/E-ai-module-2026-06/README.md` from `planned` to
      `in_progress` when runtime implementation starts and to `implemented`
      only after validation passes.
    - Update `docs/system-architecture.md`, `docs/project-overview-pdr.md`, and
      `docs/codebase-summary.md` after code-verified behavior lands.
    - Add validation evidence to this packet.

7. Verification
    - Run targeted staff copilot feature test.
    - Run AI regression suite.
    - Run Pint dirty formatting after PHP edits.
    - Run frontend type-check, lint, and format checks after Vue/TS edits.
    - Run pattern checks.
    - Run `git diff --check`.
    - Record Harness trace with outcome and friction.

## Stop Conditions

Pause for human confirmation if:

- The user wants live provider/LLM behavior beyond deterministic MVP evidence,
  because provider invocation behavior must not rely on real credentials in
  tests.
- The implementation needs a custom OpenRouter/OpenAI client instead of the
  accepted Laravel AI SDK boundary.
- The UI needs browser-submitted tool payloads, QueryPlan JSON, provider
  options, campus ids, or source-row include flags.
- A supported staff question cannot map deterministically to MetricCatalog v1.
- A visible answer would need source rows, student-level PII, hidden sections,
  or schema details.
- Current AI audit tables cannot preserve the minimum required evidence.
- A migration, retention-policy change, queue, broadcast channel, MCP server, or
  portal contract becomes necessary.
- Validation has to be weakened or dashboard/source parity cannot be proven.
