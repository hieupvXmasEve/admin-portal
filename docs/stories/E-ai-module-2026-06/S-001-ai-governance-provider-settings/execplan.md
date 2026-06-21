# Exec Plan

## Goal

Create a high-risk requirements packet for the first AI module runtime slice so
future implementation can add provider settings, encrypted secrets, permissions,
cost limits, and Laravel AI SDK-backed provider testing without accidentally
exposing business data or expanding into chat/tool/MCP behavior.

## Scope

In scope for this requirements step:

- Create `S-001-ai-governance-provider-settings/`.
- Define current behavior, target behavior, acceptance criteria, non-goals, and
  Portal impact.
- Define the planned domain model, data model, interface contract, application
  flow, UI expectations, observability, and alternatives.
- Define validation expectations and deterministic fixtures.
- Bind implementation requirements to `docs/features/ai/laravel-ai-sdk.md` for
  provider/model/timeout options, testing fakes, SDK events, and future agent
  surfaces.
- Record Harness intake and register the Harness story row.

In scope for the future implementation story:

- Add a dedicated AI module boundary for provider settings.
- Install/configure the Laravel AI SDK only when implementation begins and only
  through Swinx wrapper boundaries that enforce permission, encrypted
  staff-owned settings, audit, redaction, and quota.
- Add AI provider-settings permissions.
- Add staff-owned provider settings storage.
- Encrypt API keys at rest.
- Add provider/model allowlist validation mapped to Laravel AI SDK provider
  primitives where supported.
- Add daily/monthly cost-limit validation.
- Add a redacted Laravel AI SDK-backed provider test flow.
- Add safe Inertia/internal UI for viewing/updating/testing/clearing provider
  settings.
- Add audit records for settings changes and provider test attempts.
- Add tests proving permission, encryption, masking, redaction, SDK fake usage,
  and provider test behavior.

Out of scope:

- Runtime implementation during this requirements step.
- Chat UI, AgentRunner, MetricCatalog, EntityCatalog, QueryPlanValidator,
  ToolRegistry, ToolDispatcher, conversation storage, provider usage metering,
  answer sources, evaluation datasets, MCP exposure, and AI write/action mode.
- Admin-wide provider governance, quota dashboards, tool/model toggles,
  data-access preview, and audit viewer; those remain later governance stories.
- Student or lecturer portal contract changes.
- Live provider credentials in automated tests.
- Direct use of Laravel AI SDK images, audio, transcription, embeddings, vector
  stores, MCP tools, provider tools, sub-agents, streaming, or failover without a
  later story accepting those capabilities.

## Risk Classification

Risk flags:

- Auth: provider settings belong to an authenticated internal staff user.
- Authorization: only users with explicit AI settings permissions may view,
  manage, test, or clear settings.
- Data model: future implementation may add provider settings and audit storage.
- Audit/security: API keys are secrets, provider tests need redaction, and
  settings changes must be audited.
- External systems: provider test behavior touches external AI provider APIs,
  though automated tests must use Laravel AI SDK fakes, adapter fakes, or HTTP
  fakes.
- Public contracts: internal staff UI and response props become product
  contracts for later AI slices.
- Existing behavior: navigation, permissions, audit/logging, and Inertia
  patterns must stay consistent with the current monolith.
- Weak proof: provider correctness must be proven with deterministic fakes, not
  live network behavior.
- Multi-domain: the provider boundary is shared by later Academic, Finance,
  Identity, Notification, and portal-adjacent AI slices.

Hard gates:

- Auth.
- Authorization.
- Data model.
- Audit/security.
- External provider behavior.
- Potential credential exposure.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Requirements packet
   - Record Harness intake.
   - Create high-risk story files.
   - Register the Harness story row.
   - Confirm the matrix can query the story.

2. Implementation discovery
   - Inspect current permission seeding/assignment patterns.
   - Inspect current audit/logging facilities and decide where AI provider
     setting events belong.
   - Inspect current settings/sidebar/navigation patterns.
   - Confirm the Laravel AI SDK package/version available to the project, how
     its config/migrations are published, and which provider surfaces are
     needed for S-001.
   - Confirm whether the implementation should use `app/Modules/AI` or another
     namespace consistent with the repo.

3. Data and permission foundation
   - Add migration/model for staff-owned provider settings.
   - Add AI permissions and route authorization.
   - Add encrypted secret handling and safe resource serialization.

4. Backend flows
   - Add FormRequests and Actions for update, rotate, disable/clear, and test.
   - Add provider/model allowlist config.
   - Add a Swinx provider-settings resolver that feeds provider/model/timeout
     options into the Laravel AI SDK without leaking secrets.
   - Add provider tester interface and Laravel AI SDK fake-backed tests.
   - Add redacted audit events.

5. Internal UI
   - Add permission-aware route/page/sidebar entry.
   - Add Inertia v3 page form using existing UI primitives.
   - Add provider/model selects, masked key state, cost inputs, enable toggle,
     test action, and clear-key confirmation.

6. Verification and Harness update
   - Run targeted backend, frontend, formatting, linting, and route checks.
   - Update story validation evidence.
   - Record Harness trace with completed/partial/blocked outcome.
   - Update `docs/stories/E-ai-module-2026-06/README.md` status only when
     implementation state changes.

## Stop Conditions

Pause for human confirmation if:

- The implementation needs tenant-wide or campus-wide provider defaults instead
  of current-user provider settings.
- Provider credentials would need to be stored anywhere except encrypted
  application storage.
- A provider test requires sending Swinx business data.
- A later implementation needs live provider credentials to pass automated
  tests.
- The Laravel AI SDK does not support a selected provider/model needed for this
  story and a custom client would be required.
- Any AI tool would read Academic, Finance, Identity, Notification, student, or
  lecturer data in this story.
- Student or lecturer API/portal behavior becomes impacted.
- Admin governance/quota/audit-viewer behavior expands beyond provider settings
  into `AI-MOD-012` territory.
- MCP-first architecture or write/action behavior is requested before the
  deferred stories are accepted.
- Validation requirements need to be weakened or cannot prove redaction.
