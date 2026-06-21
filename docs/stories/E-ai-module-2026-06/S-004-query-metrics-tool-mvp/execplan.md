# Exec Plan

## Goal

Create the requirements and future implementation plan for a backend-only
`query_metrics` MVP that executes allowlisted MetricCatalog v1 queries through a
small ToolRegistry/ToolDispatcher foundation, while preserving permission,
campus scope, source-report parity, bounded aggregate output, and AI audit
evidence.

## Scope

In scope for this requirements step:

- Create the `AI-MOD-004-query-metrics-tool-mvp` high-risk story packet.
- Register the story in Harness.
- Define ToolRegistry, ToolDispatcher, QueryMetricsTool, metric resolver,
  result, source-reference, audit, and validation contracts.
- Define which existing Academic and Finance source reports own each accepted
  MetricCatalog v1 metric.
- Define validation expectations and stop conditions.

In scope for the future implementation of this story after approval:

- Add backend-only AI tool support classes under `app/Modules/AI`.
- Add an allowlisted ToolRegistry with only `query_metrics` registered.
- Add a deterministic ToolDispatcher with safe error contracts.
- Add QueryMetricsTool using existing QueryPlan and QueryPlanValidator.
- Add metric resolver adapters for the accepted MetricCatalog v1 metrics or an
  explicitly narrowed subset that still satisfies source-parity proof.
- Add normalized QueryMetricsResult and SourceReference output contracts.
- Integrate tool-call audit through existing AiAuditRecorder records.
- Extend deterministic AI evaluation coverage so staff metric cases execute
  `query_metrics` and compare results with existing source reports.
- Add targeted tests for allowed execution, denied validation, missing resolver,
  source failure, truncation/partial result, audit evidence, and no row/PII
  leakage.

Out of scope:

- Staff chat UI, AgentRunner loop, answer generation, or visible answer cards.
- Live provider calls, live LLM calls, provider credentials, or Laravel AI SDK
  Agent execution.
- User-facing web/API routes or Inertia pages.
- Student API, lecturer API, Identity portal auth/context behavior, or nested
  Nuxt portal changes.
- New database tables, migrations, backfills, or retention-policy changes.
- EntityCatalog, entity search, student profile sections, conversation context,
  prompt versioning, admin governance dashboards, audit viewers, MCP exposure,
  embeddings, vector stores, provider tools, sub-agents, streaming, failover, or
  write/action mode.
- AI-generated SQL, raw schema exploration, arbitrary report execution, broad
  source-row dumps, or student-level PII metric output.

## Risk Classification

Risk flags:

- Authorization: tool execution must respect `view_ai_metrics`, staff
  permissions, and current campus scope.
- Audit/security: AI tool arguments, results, hidden sections, source
  references, denials, failures, and truncation must be redacted and auditable.
- Public contracts: later staff chat depends on stable tool input, output, and
  error contracts.
- Existing behavior: metric numbers must match existing Academic and Finance
  reports rather than replacing their business rules.
- Weak proof: source parity and no-fabrication checks need deterministic tests.
- Multi-domain: the first tool spans Academic and Finance reporting sources.
- External provider boundary: the tool is designed for future Laravel AI SDK
  Agent use, even though this story must not call live providers.

Hard gates:

- Authorization.
- Audit/security.
- Removing or weakening validation requirements.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Requirements packet
    - Record Harness intake.
    - Create high-risk story files.
    - Register Harness story row.
    - Validate packet presence and formatting.

2. Implementation discovery
    - Re-read `MetricCatalog`, `QueryPlan`, `QueryPlanValidator`, and AI audit
      support classes.
    - Inspect Academic and Finance source query signatures and output shapes.
    - Confirm each accepted metric can produce deterministic aggregate output
      without copying report business rules into AI code.
    - Narrow the MVP metric set if a metric cannot meet source parity or privacy
      rules.

3. Tool registry and dispatcher
    - Add code-defined ToolRegistry.
    - Add ToolDispatcher with safe unknown-tool, invalid-input, denied,
      failed, and partial-result handling.
    - Keep the contract internal to the AI module.

4. Query metrics execution
    - Add QueryMetricsTool.
    - Parse untrusted arguments through QueryPlan.
    - Validate every plan with QueryPlanValidator before source execution.
    - Add metric resolvers that delegate to existing source queries and normalize
      aggregate result shapes.
    - Return deterministic QueryMetricsResult output.

5. Audit and evaluation
    - Record tool-call audit for completed, denied, failed, and partial results.
    - Extend existing staff metric evaluation cases to execute the tool and
      assert source references, source parity, permission behavior, hidden
      sections, and no fabricated numbers.

6. Verification and Harness update
    - Run targeted backend tests and formatting.
    - Run existing AI foundation regression tests.
    - Run path-limited `git diff --check`.
    - Update story validation evidence.
    - Record Harness trace.
    - Update `docs/stories/E-ai-module-2026-06/README.md` status only when
      implementation state changes.

## Stop Conditions

Pause for human confirmation if:

- A candidate metric requires duplicating large Academic or Finance report
  business rules inside AI code.
- A source report cannot provide deterministic parity proof.
- A metric requires student-level rows, PII, hidden sections, or broad source
  row dumps instead of aggregate output.
- A plan requires cross-campus expansion or admin override behavior.
- A resolver needs arbitrary SQL, raw table names, raw column names, joins,
  includes, or schema exploration.
- Existing AI audit tables cannot capture the minimum tool-call evidence.
- A database migration or new retention policy becomes necessary.
- Runtime execution needs provider calls, AgentRunner, chat UI, MCP exposure,
  vector search, streaming, failover, or write/action behavior.
- Student or lecturer API/portal behavior becomes impacted.
- Validation requirements need to be weakened or source parity cannot be
  proven.
