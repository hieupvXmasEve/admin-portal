# Exec Plan

## Goal

Create a high-risk requirements packet for the AI metric catalog and query-plan
validation layer so the later `query_metrics` tool can be implemented against a
small, allowlisted, aggregate-only, permission-aware, campus-scoped, auditable
metric contract.

## Scope

In scope for this requirements step:

- Create the `AI-MOD-003-metric-catalog-query-plan` high-risk story packet.
- Define MetricCatalog v1 scope, candidate metrics, source reports, filters,
  groupings, output shape, source references, permission rules, campus scope,
  record limits, and hidden-section behavior.
- Define business glossary subset requirements for the first staff questions.
- Define QueryPlan v1 schema and QueryPlanValidator responsibilities.
- Define canonical staff questions and future evaluation fixture shape.
- Define validation expectations and stop conditions.
- Register the story in Harness.

In scope for the future implementation of this story after approval:

- Add code-defined MetricCatalog v1 under the existing AI module boundary.
- Add business glossary/synonym mapping for the selected metrics and filters.
- Add typed query-plan parsing and validation objects.
- Add QueryPlanValidator with deterministic allow/deny results.
- Add evaluation cases for three to five staff questions using existing AI
  evaluation records.
- Add targeted tests for catalog lookup, glossary mapping, query-plan parsing,
  validation, permission/campus denial, record-limit denial, and evaluation case
  persistence.

Out of scope:

- Runtime execution of `query_metrics`.
- ToolRegistry, ToolDispatcher, AgentRunner, provider prompt runtime, or chat UI.
- Live provider calls, live LLM calls, or provider credentials in automated
  tests.
- New student/lecturer API behavior or nested Nuxt portal changes.
- EntityCatalog, entity search, student profile sections, or conversation
  context.
- AI-generated SQL, raw table/column/schema exposure, broad source-row dumps, or
  student-level PII metric output.
- MCP server exposure, MCP-ready mappings, vector search, embeddings, provider
  tools, sub-agents, streaming UI, failover, or write/action mode.
- Admin governance dashboards, audit viewers, prompt/tool versioning, or
  database-backed catalog administration.

## Risk Classification

Risk flags:

- Authorization: metric access must respect staff permissions and campus scope.
- Audit/security: query plans, source references, hidden sections, and denied
  plans must be redacted and auditable.
- Public contracts: future staff-facing AI answers depend on stable metric,
  filter, grouping, source, and safe error contracts.
- Existing behavior: metric output must match existing Academic and Finance
  reports rather than duplicating or changing business rules.
- Weak proof: AI metric correctness needs deterministic validation and source
  parity fixtures that do not exist yet.
- Multi-domain: the first catalog spans Academic and Finance source reports.

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
   - Inspect current AI module support classes and audit/evaluation models.
   - Inspect Academic and Finance source reports for the candidate metric set.
   - Confirm each candidate metric can be proven against an existing query or
     report without copying business logic into AI code.
   - Narrow the catalog if a metric cannot meet source-parity proof.

3. Catalog and glossary
   - Add code-defined MetricCatalog v1.
   - Add business glossary and synonym mapping for selected metric/filter/group
     keys.
   - Add catalog version and tool-schema version constants.

4. Query-plan parsing and validation
   - Add typed query-plan DTO/value objects.
   - Add QueryPlanValidator.
   - Normalize relative filters such as current semester through backend
     resolvers.
   - Resolve staff permission and current campus scope.
   - Deny unsafe, unsupported, hidden, or excessive plans with safe error codes.

5. Evaluation fixtures
   - Add three to five StaffMetricQuestion cases.
   - Store expected query plans, permission contexts, source references, and
     answer properties through existing AI evaluation records.
   - Ensure every metric has at least one accepted and one denied/invalid test
     path.

6. Verification and Harness update
   - Run targeted backend tests and formatting.
   - Run existing AI audit foundation regression tests.
   - Update story validation evidence.
   - Record Harness trace with completed, partial, failed, or blocked outcome.
   - Update `docs/stories/E-ai-module-2026-06/README.md` status only when
     implementation state changes.

## Stop Conditions

Pause for human confirmation if:

- A candidate metric requires copying large business rules from Academic or
  Finance queries into AI code.
- A source report cannot provide deterministic source-parity proof.
- A metric needs student-level records, PII, or hidden sections instead of
  aggregate output.
- A plan requires cross-campus scope expansion or admin-only override behavior.
- A filter or grouping cannot be allowlisted safely.
- A database-backed catalog or migration becomes necessary before
  `AI-MOD-013-prompt-tool-versioning`.
- Runtime execution of `query_metrics`, chat UI, AgentRunner, ToolRegistry,
  ToolDispatcher, EntityCatalog, MCP exposure, provider calls, or write/action
  behavior is requested as part of this story.
- Student or lecturer API/portal behavior becomes impacted.
- Validation requirements need to be weakened or cannot prove permission,
  campus scope, source parity, and no fabricated numbers.
