# Exec Plan

## Goal

Create the first safe entity-search foundation for the internal staff copilot by
adding EntityCatalog v1 and the `search_entities` tool for bounded,
permission-aware, campus-scoped candidate lookup across student, program,
semester, and course-offering entities.

## Scope

In scope for this requirements step:

- Record Harness intake for `AI-MOD-006-entity-catalog-search`.
- Create this high-risk story packet.
- Register the story in Harness.
- Define the runtime entity catalog, tool schema, resolver, result, audit, UI,
  and validation contracts.
- Keep the story as planned until the user explicitly approves runtime
  implementation.

In scope for runtime implementation after approval:

- Add EntityCatalog v1 under `app/Modules/AI/Support`.
- Add entity definition/result/reference value objects or typed array contracts
  under the AI module.
- Add `SearchEntitiesTool` under `app/Modules/AI/Support/Tools`.
- Refactor ToolRegistry and ToolDispatcher enough to support both
  `query_metrics` and `search_entities` without weakening existing
  `query_metrics` behavior.
- Add `AiAcademicEntitySearchReader` under
  `App\Shared\Contracts\Academic` and a thin Academic-module adapter/query for
  student, program, semester, and course-offering candidate search.
- Extend StaffCopilotAgentRunner to plan `search_entities` for entity lookup
  questions while keeping runtime deterministic and provider-free.
- Extend StaffCopilotAnswer and the existing Vue page to render entity-search
  candidates and evidence.
- Update current-state docs after runtime behavior lands.
- Add targeted unit/feature tests and frontend static checks.

Out of scope:

- Student API, lecturer API, Identity portal routes, nested Nuxt portal code, or
  public API routes.
- `get_entity_profile`, section-level profile reads, finance/defer profile
  details, attendance details, assessment results, advisor notes, and
  conversation resolved-entity memory.
- Live LLM/provider calls, embeddings, vector stores, web search,
  provider-native tools, MCP exposure, streaming, failover, sub-agents, or
  prompt/tool version administration.
- Write/action mode, emails, state changes, side-effect tools, or human-approved
  mutations.
- Database migrations unless current AI audit/reference mechanisms cannot
  preserve required evidence and the user approves that change.
- New Composer or NPM dependencies without explicit approval.

## Risk Classification

Risk flags:

- Authorization: entity search must respect staff AI access, entity-specific
  domain permissions, and current campus scope.
- Audit/security: search arguments, labels, identifiers, hidden sections,
  source references, and safe errors must be redacted and auditable.
- Public contracts: the staff-visible answer evidence shape and internal tool
  schema become product contracts for later profile/context stories.
- Existing behavior: ToolRegistry/ToolDispatcher currently support
  `query_metrics`; adding a second tool must not regress metric chat behavior.
- Weak proof: PII-safe search requires deterministic field allowlist and
  negative tests.
- Multi-domain: the first entity catalog spans Academic reference data,
  students, and course offerings, and is later consumed by Finance questions.

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
    - Validate packet presence and incomplete markers.
    - Present implementation approach for approval before runtime code.

2. Runtime discovery
    - Re-read AI routes, StaffCopilotAgentRunner, ToolRegistry,
      ToolDispatcher, QueryMetricsTool, QueryMetricsResult, AiAuditRecorder,
      StaffCopilotAnswer, StaffCopilot page props, and current AI feature tests.
    - Re-read Academic routes/permissions and existing student/program/semester/
      course-offering queries.
    - Confirm `AI-MOD-005-staff-copilot-chat-mvp` is implemented in Harness.
    - Confirm no student/lecturer portal API contract is touched.

3. RED tests
    - Add `tests/Feature/AI/AiEntityCatalogSearchTest.php`.
    - Test catalog definitions and tool registration.
    - Test authorized student/program/semester/course-offering search.
    - Test denied entity-specific permission.
    - Test current-campus hiding.
    - Test forbidden PII fields are absent.
    - Test unsupported type, too-short query, forbidden filters/options, and
      limit truncation.
    - Test staff copilot search prompt creates a `search_entities` tool call and
      visible candidate answer evidence.
    - Run tests and verify expected failures before production code.

4. Backend implementation
    - Add EntityCatalog and entity definition/result/reference support classes.
    - Add `AiAcademicEntitySearchReader` contract and Academic implementation.
    - Add `SearchEntitiesTool`.
    - Generalize ToolRegistry/ToolDispatcher to return tool definitions and
      dispatch both accepted tools.
    - Extend StaffCopilotAgentRunner planning for entity-search intents.
    - Extend StaffCopilotAnswer to carry entity-search evidence.
    - Ensure all tool attempts record AiToolCall audit through AiAuditRecorder.

5. Frontend implementation
    - Update `resources/js/pages/AI/StaffCopilot/Index.vue` types and rendering
      for entity-search answer evidence.
    - Show compact candidate rows, source/scope/confidence evidence, hidden
      sections, warnings, and safe errors.
    - Keep the page utilitarian and work-focused.
    - Use existing route names and UI primitives.

6. Documentation and story state
    - Update `docs/stories/E-ai-module-2026-06/README.md` from `planned` to
      `in_progress` when runtime implementation starts and to `implemented`
      only after validation passes.
    - Update `docs/system-architecture.md`, `docs/project-overview-pdr.md`, and
      `docs/codebase-summary.md` after code-verified behavior lands.
    - Add validation evidence to this packet.

7. Verification
    - Run targeted entity-search feature test.
    - Run AI regression suite.
    - Run Pint dirty formatting after PHP edits.
    - Run targeted frontend ESLint and Prettier after Vue/TS edits.
    - Run broader frontend gates if feasible and record existing baseline gaps
      separately.
    - Run `git diff --check`.
    - Record Harness trace with outcome and friction.

## Stop Conditions

Pause for human confirmation if:

- The implementation needs profile/detail sections instead of search candidates.
- The user wants the copilot to remember resolved entities across turns in this
  story.
- A supported entity would require exposing email, phone, national id, address,
  date of birth, parent/emergency contact, finance details, attendance details,
  assessment results, advisor notes, hidden fields, or raw source rows.
- Current AI audit tables cannot preserve the minimum required search evidence.
- Scoped entity references require durable storage rather than signed/encrypted
  stateless references.
- Search needs all-campus scope or a new permission model beyond current-campus
  v1 rules.
- A migration, queue, broadcast channel, MCP server, embedding/vector store,
  provider call, or portal contract becomes necessary.
- Validation has to be weakened or PII/campus/permission boundaries cannot be
  proven deterministically.
