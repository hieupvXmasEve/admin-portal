# AI-MOD-009 - Compare Metrics Tool

## Status

planned

## Lane

high-risk

## Current Behavior

Swinx already has the safe foundations needed before metric comparison:

- `AI-MOD-003-metric-catalog-query-plan` defines aggregate-only
  MetricCatalog v1, glossary mapping, QueryPlan, QueryPlanValidator, and the
  `view_ai_metrics` permission.
- `AI-MOD-004-query-metrics-tool-mvp` executes allowlisted `query_metrics`
  requests through ToolRegistry, ToolDispatcher, QueryMetricsTool, and
  Academic/Finance shared reader contracts.
- `AI-MOD-005-staff-copilot-chat-mvp` gives internal staff a copilot surface
  that can answer deterministic metric prompts.
- `AI-MOD-006-entity-catalog-search` and
  `AI-MOD-007-student-profile-sections` add safe entity/profile capabilities.
- `AI-MOD-017-live-provider-staff-copilot-agent` lets a live provider plan
  allowlisted tool calls, while server-side validation remains authoritative.
- `AI-MOD-018-provider-agnostic-sse-chat-runtime` persists run lifecycle and
  streams normalized Staff Copilot events through Swinx-owned SSE.
- `AI-MOD-008-conversation-context` is the planned dependency that gives the
  copilot bounded current filters, current term, resolved entities, recent tool
  history, and context evidence.

Current gap:

- Staff can ask for one metric snapshot, but there is no first-class
  `compare_metrics` tool for current-vs-previous term analysis.
- A model could attempt to call `query_metrics` twice and compare values in the
  final answer, but that would make period resolution, denominator handling,
  missing-data handling, group alignment, rounding, safe error codes, and audit
  evidence inconsistent.
- MetricCatalog v1 does not yet declare which metrics are comparison-capable or
  how their numeric fields should be compared.
- Staff Copilot answers cannot yet cite a single comparison contract with base
  filters, comparison filters, source reports, deltas, warnings, confidence,
  and freshness.
- There is no deterministic evaluation fixture proving trend answers against
  existing Academic and Finance source reports.

## Target Behavior

This story adds a Swinx-owned `compare_metrics` internal tool for scoped,
read-only metric comparisons. The first accepted comparison mode is
current-vs-previous term for metrics that already have safe term semantics.

Target behavior:

- Register `compare_metrics` as an allowlisted Staff Copilot tool with schema
  version `compare_metrics:v1`.
- Keep `query_metrics:v1` as the source execution primitive. The comparison
  layer validates two compatible query plans, executes or reuses the same
  allowlisted metric resolver path, then computes deterministic deltas in
  Swinx code.
- Use `AI-MOD-008` context only to propose base filters, comparison filters, or
  current term. Context never grants permission or bypasses QueryPlanValidator.
- Support initial comparison for these MetricCatalog v1 metrics only:
    - `academic_student_status_count`;
    - `academic_defer_count`;
    - `finance_collection_summary`;
    - `finance_fee_monitor_summary`.
- Exclude `finance_dng_lifecycle_attention` until it has an accepted time or
  term filter that can support source-parity comparison.
- Resolve comparison periods through Swinx-owned semester logic or accepted
  context/source metadata. The model must not invent period ids, date ranges, or
  "previous term" rules.
- Require base and comparison plans to use the same metric, same campus scope,
  compatible filters, and compatible groupings.
- Compute absolute delta, percentage delta, direction, and trend status only
  for numeric aggregate fields declared compare-capable.
- Align grouped results by stable group key and label. Missing groups must be
  represented explicitly instead of being treated as zero unless the metric
  contract says zero is valid.
- Return bounded aggregate comparison output only. Do not expose source rows,
  student identifiers, raw finance ledger rows, SQL, table names, model names,
  or hidden fields.
- Record product audit evidence for the comparison request, both source metric
  scopes, source references, warnings, confidence, and safe error code.
- Let the live provider synthesize trend wording only from the redacted
  comparison result. The backend remains responsible for all numbers and delta
  calculations.
- Keep portal impact `none`.

## Affected Users

- Internal academic staff comparing current-term academic status and defer
  counts with a previous term.
- Internal finance staff comparing current-term collection or fee-monitor
  completeness against a previous term.
- Administrators and security reviewers who need proof that trend analysis is
  permission-aware, campus-scoped, audited, and source-parity checked.
- Engineers implementing later recommendation, evaluation, governance, and
  prompt-versioning stories that need a stable comparison contract.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`

## Portal Impact

Portal impact: none.

This story affects only the internal web Staff Copilot and AI module runtime.
It must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, Identity
student/lecturer auth or context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Register `AI-MOD-009-compare-metrics-tool` in Harness before runtime
  implementation starts.
- Keep `AI-MOD-009` in the `E-ai-module-2026-06` roadmap after
  `AI-MOD-004-query-metrics-tool-mvp` and
  `AI-MOD-008-conversation-context`.
- Confirm `AI-MOD-004` is implemented and `AI-MOD-008` is implemented or
  deliberately waived before runtime work begins.
- Add an internal `compare_metrics` tool definition to ToolRegistry with
  schema version `compare_metrics:v1`, permission `view_ai_metrics`, safe
  error codes, and bounded input/output contracts.
- Add a comparison contract that accepts one metric plus a base scope and a
  comparison scope. The first supported mode is current-vs-previous term.
- Validate base and comparison scopes as untrusted input before any source read.
- Reuse QueryPlanValidator, campus scope rules, permission checks, filter
  allowlists, group-by allowlists, max-record limits, source-reference policy,
  and existing metric resolvers.
- Deny unsupported metrics, unsupported comparison modes, incompatible filters,
  incompatible groupings, missing periods, ambiguous periods, cross-campus
  scopes, raw rows, SQL/table/column payloads, arbitrary joins, source ids, and
  unbounded limits.
- Declare compare-capable aggregate fields per supported metric. Do not compare
  non-numeric fields, labels, hidden sections, warnings, confidence strings, or
  source metadata as metric values.
- Use deterministic rounding rules for percentage deltas and explicitly handle
  divide-by-zero or missing-baseline cases.
- Keep group comparison bounded and aligned by stable group keys. Surface
  missing base or comparison groups as warnings or partial results.
- Return source references for both base and comparison scopes, including
  metric key, catalog version, tool schema versions, source reports, filters,
  groupings, campus scope, freshness, and parity policy.
- Record audit evidence for completed, denied, failed, and partial
  `compare_metrics` calls.
- If the implementation records child `query_metrics` tool calls, link or cite
  them in the parent comparison audit summary without exposing raw child
  payloads.
- Add deterministic evaluation cases for at least one academic comparison and
  one finance comparison.
- Update Staff Copilot capability props and live planner prompt/tool summaries
  so the model can request `compare_metrics` only through ToolDispatcher.
- Ensure deterministic fallback can handle clear comparison prompts such as
  "compare current term defer count with previous term" when all required
  filters can be resolved safely.
- If context supplies "current term", "previous term", or "same filters",
  revalidate those values before executing the comparison.
- If comparison context is missing, stale, ambiguous, denied, or cross-campus,
  the copilot asks for clarification or returns a safe unsupported response
  without source reads.
- Staff Copilot answers that use `compare_metrics` must cite both compared
  scopes, source reports, filters, freshness, warnings, hidden sections, and
  confidence.
- Automated tests must use deterministic fixtures and Laravel AI SDK or
  project-local fakes. They must not require live provider credentials or
  network calls.
- Update current-state docs after runtime implementation lands.
- Record Harness trace with implementation and validation evidence.

## Non-Goals

- Do not add student or lecturer portal behavior.
- Do not add public API routes.
- Do not add write/action mode, mutations, notifications, emails, state changes,
  approval workflows, or external side-effect tools.
- Do not add AI-generated SQL, raw schema exploration, source-row dumps,
  unrestricted report joins, or raw dashboard exports.
- Do not add new metric keys unless the implementation proves they are required
  and explicitly updates MetricCatalog, source parity, evaluation fixtures, and
  this story's accepted scope.
- Do not compare `finance_dng_lifecycle_attention` until a separate story or
  accepted change defines its time/term comparison semantics.
- Do not add arbitrary date-range comparison in the first slice. Keep the first
  mode to current-vs-previous term plus explicit semester ids when Swinx can
  resolve them safely.
- Do not let the provider compute deltas from prose, transcript history, or
  unstructured prior answers.
- Do not add long-term memory, embeddings, vector stores, files, MCP exposure,
  provider-native tools, sub-agents, images, audio, or new dependencies without
  explicit approval.
