# AI-MOD-001 - AI Governance Provider Settings

## Status

implemented

## Lane

high-risk

## Current Behavior

Swinx has an accepted AI module roadmap and source blueprint, but no runtime AI
module is present yet.

Current baseline:

- No AI module shell, AI navigation entry, provider settings page, provider
  client, or provider test flow exists in the checked application surface.
- `docs/features/ai/laravel-ai-sdk.md` is available as the official Laravel AI
  SDK reference for provider configuration, agents, provider/model override,
  testing fakes, failover, and SDK events, but the Swinx AI story has not yet
  bound implementation requirements to it.
- No table stores staff-owned AI provider configuration, encrypted API keys,
  model choice, or usage/cost limits.
- No AI-specific permissions are defined.
- No AI provider credentials are stored, rotated, masked, audited, or tested by
  the application.
- No AI tools can read Academic, Finance, Identity, Notification, student, or
  lecturer data.

## Target Behavior

This story creates the first runtime foundation for the AI module: a
permissioned, internal provider-settings surface that can safely store and test
the current staff user's AI provider configuration.

Target behavior:

- Staff users with AI settings permission can open an internal AI provider
  settings page.
- The provider integration should use the official Laravel AI SDK as the primary
  provider/agent abstraction when implementation begins, rather than inventing a
  parallel generic AI client layer.
- The settings page lets the authorized staff user choose an allowlisted
  provider, choose an allowlisted default model, enter or rotate an API key,
  enable/disable the setting, and define daily/monthly cost limits.
- API keys are encrypted at rest, never returned to the frontend after save,
  never logged, never copied into audit details, and only represented by a
  masked display value.
- Provider/model choices come from server-side allowlists. Unknown provider or
  model names are rejected before persistence or provider calls. Provider ids
  should map to Laravel AI SDK provider primitives such as `Laravel\Ai\Enums\Lab`
  wherever the SDK supports the provider.
- A provider test action validates the saved or submitted configuration without
  sending Swinx business data to the provider.
- Provider tests and later agent calls should pass provider/model/timeout
  choices through Laravel AI SDK-supported configuration or prompt options,
  while Swinx remains responsible for resolving the current user's encrypted key,
  permission, quota, audit, and redaction policy.
- Provider tests use strict timeout/error handling and store redacted test
  metadata such as `tested_at`, success/failure, and a safe error code.
- Every create, update, disable, delete/clear-key, and provider-test attempt is
  audit-recorded without exposing the API key or provider request body.
- The API key only controls provider/model/cost behavior. It never determines
  what Swinx data the AI can read.
- No business-data AI access is exposed in this story. Metric catalogs, entity
  catalogs, tool calling, chat, conversation audit, MCP exposure, and write
  actions remain deferred to later stories.

## Implementation Result

Implemented as the first runtime AI module slice:

- `app/Modules/AI` registers internal web routes for the AI provider settings
  page.
- `ai_provider_settings` stores one encrypted staff-owned provider setting per
  user.
- AI permissions are registered for view, manage, and provider-test actions.
- The Inertia page renders only safe metadata and write-only key input.
- Provider/model values are resolved from server-side allowlists.
- OpenRouter model options are populated from the provider Models API for text
  models, with cached results and static fallback models for availability.
- The default model control uses a searchable combobox so long provider model
  lists remain usable.
- OpenRouter provider tests perform a live chat-completions health request with
  a deterministic static non-business prompt, short timeout, and safe error-code
  mapping. Raw provider request/response bodies and credentials are never
  audited.
- Providers without a live tester return a safe `unsupported_provider_test`
  failure instead of a false success.
- Settings updates, test attempts, and key clearing write redacted business
  audit records.

## Affected Users

- Internal staff users who will later use AI analysis features.
- Administrators or platform operators who grant AI provider-settings
  permissions.
- Engineers implementing later AI runtime slices.
- Security/compliance reviewers who need the provider credential boundary to be
  explicit before any AI data access exists.

## Affected Product Docs

- `docs/features/ai/ai.md`
- `docs/features/ai/laravel-ai-sdk.md`
- `docs/stories/E-ai-module-2026-06/README.md`
- `docs/project-overview-pdr.md` when runtime behavior lands.
- `docs/system-architecture.md` when the AI module boundary lands.
- `docs/codebase-summary.md` when code-verified AI behavior exists.

## Portal Impact

Portal impact: none.

This story must not change `/api/v1/student/*`, `/api/v1/lecturer/*`, Identity
student/lecturer auth or context routes, or nested Nuxt portal code.

## Acceptance Criteria

- Create a high-risk story packet for
  `AI-MOD-001-ai-governance-provider-settings`.
- Register the story in Harness before implementation begins.
- Define an AI module shell requirement without implementing chat, tools, MCP,
  or student/lecturer assistant behavior.
- Define AI provider settings for the authenticated internal staff user.
- Define how the implementation must integrate with the official Laravel AI SDK
  for supported providers, models, timeouts, testing fakes, and SDK events.
- Define AI settings permissions and authorization expectations.
- Define encrypted API-key storage, masked display, clear/rotate behavior, and
  redaction requirements.
- Define provider/model allowlists and validation rules.
- Define daily/monthly cost-limit storage and validation rules.
- Define a provider test flow that never sends Swinx business data.
- Define audit requirements for settings changes and test attempts.
- Define deterministic validation expectations using mocked provider responses;
  live provider credentials must not be required for automated tests. Prefer
  Laravel AI SDK fakes/assertions where they cover the behavior under test.
- Keep all runtime implementation, migrations, UI, and provider calls out of
  this requirements-only step unless the user explicitly approves
  implementation later.

## Non-Goals

- Do not implement chat, tools, MCP, student/lecturer assistant behavior, or
  business-data AI access in this story.
- Do not create MetricCatalog, EntityCatalog, QueryPlanValidator, ToolRegistry,
  ToolDispatcher, AgentRunner, conversation storage, or chat UI.
- Do not expose AI access to Academic, Finance, Identity, Notification, student,
  lecturer, or portal data.
- Do not allow AI-generated production SQL.
- Do not add MCP server exposure or MCP-ready mappings.
- Do not add admin-wide quota dashboards, tool toggles, audit viewers, or data
  access preview; those belong to later governance stories.
- Do not implement write/action mode or any side-effectful AI action.
