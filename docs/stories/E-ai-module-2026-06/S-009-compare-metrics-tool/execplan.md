# Exec Plan

## Goal

Create a high-risk requirements packet for `compare_metrics` and, when
approved for implementation, add a read-only Staff Copilot tool that compares
allowlisted aggregate metrics across current and previous term scopes with
deterministic deltas, source evidence, and audit proof.

## Scope

In scope:

- `AI-MOD-009-compare-metrics-tool` high-risk story packet.
- Harness registration for the story.
- Internal `compare_metrics` tool contract with schema version
  `compare_metrics:v1`.
- Comparison validation for same metric, supported comparison mode, compatible
  filters, compatible group_by, current campus scope, permission, record
  limits, and compare-capable fields.
- Current-vs-previous term and explicit semester-vs-semester comparison when
  Swinx can resolve both periods safely.
- Initial compare-capable metrics:
    - `academic_student_status_count`;
    - `academic_defer_count`;
    - `finance_collection_summary`;
    - `finance_fee_monitor_summary`.
- Reuse of QueryPlanValidator and the existing query_metrics resolver path for
  source reads.
- Deterministic delta calculation, group alignment, missing-data handling,
  warnings, confidence, and source references.
- ToolRegistry, ToolDispatcher, Staff Copilot capability, live planner prompt,
  deterministic fallback, SSE/audit, and evaluation updates needed to expose
  the tool safely.
- Current-state doc updates after runtime implementation lands.

Out of scope:

- Student or lecturer portal changes.
- Public API routes.
- New reporting/dashboard UI.
- New source metric keys unless explicitly accepted.
- `finance_dng_lifecycle_attention` comparison before accepted period
  semantics exist.
- Arbitrary date ranges, cohort windows, rolling averages, forecasts, ML risk
  scoring, recommendations, or anomaly detection.
- Raw SQL, raw source rows, source model names, source table names, student
  identifiers, raw finance ledger rows, gateway payloads, attendance session
  rows, score component rows, hidden profile sections, or unrestricted exports.
- Mutations, notifications, emails, write/action mode, approval workflows, MCP,
  provider-native tools, vector stores, embeddings, files, images, audio,
  sub-agents, or new dependencies without explicit approval.

## Risk Classification

Risk flags:

- Authorization: comparison can expose Academic and Finance facts and must
  preserve permission/campus checks for both scopes.
- Audit/security: comparison arguments, source scopes, deltas, and result
  summaries must be redacted, bounded, and traceable.
- External provider behavior: live planner may request the tool, but server
  validation must remain authoritative.
- Public/internal contract: ToolRegistry definitions, planner tool summaries,
  capability props, tool result shape, and answer evidence contract change.
- Existing behavior: existing `query_metrics`, live agent, SSE runtime,
  deterministic fallback, and evaluation flows must keep working.
- Weak proof: trend answers are easy to misstate without deterministic
  fixtures for zero baselines, missing groups, and period resolution.
- Multi-domain: supported comparisons include Academic and Finance metrics.

Hard gates:

- Authorization.
- Audit/security.
- External provider behavior.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Story setup.
    - Create `docs/stories/E-ai-module-2026-06/S-009-compare-metrics-tool/`.
    - Fill `overview.md`, `design.md`, `execplan.md`, and `validation.md`.
    - Register `AI-MOD-009-compare-metrics-tool` in Harness.
    - Confirm roadmap status remains `planned`.
2. Discovery before implementation.
    - Confirm `AI-MOD-004` is implemented.
    - Confirm `AI-MOD-008` is implemented or explicitly waived for a smaller
      non-context comparison slice.
    - Re-read `MetricCatalog`, `QueryPlan`, `QueryPlanValidator`,
      `QueryMetricsTool`, `QueryMetricsResult`, metric resolvers,
      `ToolRegistry`, `ToolDispatcher`, `LiveStaffCopilotAgent`,
      `StaffCopilotAgentRunner`, SSE runtime tests, and current AI migrations.
    - Inspect Academic and Finance source report behavior for current and
      previous semester filters.
3. Red tests.
    - Add `AiCompareMetricsToolTest` covering validation, allowed comparison,
      denied metric, period resolution, permission/campus denial, missing
      baseline, zero baseline, group alignment, partial results, and audit.
    - Extend live-agent/fake tests so the model can propose `compare_metrics`
      and unsafe proposals are denied.
    - Extend deterministic fallback tests for clear current-vs-previous term
      prompts.
    - Extend SSE/runtime tests only if run events or UI evidence changes.
4. Comparison contract foundation.
    - Add schema/version constants and result/value objects under the AI module.
    - Add comparison-capable metric metadata.
    - Add period resolution and safe error codes.
    - Add numeric field and trend-label rules.
5. Tool execution.
    - Register `compare_metrics` in ToolRegistry.
    - Extend ToolDispatcher return handling for compare result shape.
    - Implement comparison validation using QueryPlanValidator for both scopes.
    - Execute source metric reads through the existing query_metrics resolver
      path without duplicating Academic/Finance business logic.
    - Compute aggregate and group deltas deterministically.
6. Runtime integration.
    - Update Staff Copilot capabilities.
    - Update live planner tool summaries and final-answer instructions.
    - Add deterministic fallback mapping for unambiguous comparison prompts.
    - Revalidate any context-derived filters or periods from `AI-MOD-008`.
    - Add audit and SSE evidence for completed, denied, failed, or partial
      comparisons.
7. Verification and docs.
    - Run targeted AI tests and regression tests.
    - Run Pint and targeted frontend checks if Vue/TS files change.
    - Update current-state docs after implementation.
    - Record Harness trace with validation evidence and known gaps.

## Stop Conditions

Pause for human confirmation if:

- `AI-MOD-008` is still unimplemented and the requested implementation depends
  on context-derived "same filters", "current term", or follow-up behavior.
- A new permission is needed instead of reusing `view_ai_metrics` plus existing
  metric permissions.
- A supported metric lacks stable semester/current-vs-previous source parity.
- Product scope expands to arbitrary date ranges, forecasts, recommendations,
  anomaly detection, dashboard UI, or new metric keys.
- Comparison would require copying large business logic from Academic or
  Finance into the AI module instead of using shared contracts/adapters.
- The tool would need raw rows, SQL, table names, model names, hidden sections,
  source ids, raw ledgers, attendance sessions, score component rows, gateway
  payloads, or other sensitive details.
- Tool-call parent-child audit linkage cannot be preserved with existing AI
  audit records and a migration is being considered.
- Laravel AI SDK fakes or project-local fakes cannot prove live-provider
  planner behavior without real credentials or network calls.
- Validation has to be weakened or known existing failures obscure the story's
  behavioral proof.
