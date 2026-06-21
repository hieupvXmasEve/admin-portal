# Design

## Domain Model

This story defines the required runtime model for the future implementation.

Primary entity:

- `AiProviderSetting`
  - Owns one internal staff user's provider configuration for the first AI
    runtime phase.
  - Stores provider/model choice, encrypted credential material, enabled state,
    daily/monthly cost limits, test metadata, and usage timestamps.
  - Does not grant data access and does not store prompts, messages, tool calls,
    or provider responses.

Initial business rules:

- One active provider setting per staff user is enough for Phase 1. Multiple
  provider profiles, tenant-wide defaults, and admin override policies are
  deferred unless explicitly accepted in a later story.
- Provider and model values must be selected from server-side allowlists.
- API key input is write-only. After save, the application returns only
  metadata and a masked key indicator.
- Disabling a setting makes it unusable by later AI runtime slices.
- Clearing or deleting a key must remove usable credential material. If the
  implementation uses soft deletes, the encrypted key must be cleared before
  deletion.
- Daily/monthly cost limits must be non-negative integer minor units such as
  cents. If both limits are present, the monthly limit must not be lower than
  the daily limit.
- Provider tests must never include student, lecturer, finance, academic,
  notification, operational, prompt, or user-entered business content.
- Provider credentials do not determine Swinx data access. Later AI data access
  must still resolve the authenticated user, Gate/policy permissions, campus
  scope, field/section allowlists, and audit policy.

Supporting concepts:

- `AiProvider`: allowlisted provider id and display metadata.
- `AiModel`: allowlisted model id, provider id, label, and optional cost
  metadata used for validation/display. Providers with official model-list
  endpoints may merge cached text-model results into the server-side allowlist,
  while retaining static fallback models.
- `LaravelAiProviderResolver`: Swinx boundary that maps allowlisted Swinx
  provider/model settings to Laravel AI SDK provider primitives such as
  `Laravel\Ai\Enums\Lab`, model names, timeout values, and optional
  provider-specific options.
- `EncryptedAiSecret`: write-only credential material encrypted by Laravel.
- `AiProviderTestResult`: redacted test outcome with status, safe error code,
  duration, tested timestamp, provider, and model.
- `AiPermission`: explicit permission names for viewing, managing, and testing
  provider settings.

## Laravel AI SDK Boundary

The implementation should use `docs/features/ai/laravel-ai-sdk.md` as the
primary SDK reference for supported provider behavior.

Swinx owns:

- staff-owned encrypted provider settings;
- permission checks;
- campus/user context;
- provider/model allowlists;
- provider model-list source, cache, and fallback policy;
- quota and cost limits;
- audit and redaction;
- deterministic provider-test policy;
- selection of which SDK features are exposed in each accepted story.

The Laravel AI SDK should own, where supported:

- provider enum/driver semantics through `Laravel\Ai\Enums\Lab`;
- provider/model/timeout prompt options;
- agent abstractions for later chat/tool stories;
- testing fakes and prompt assertions;
- SDK events that can be listened to for usage/audit records;
- provider failover behavior only if a later story explicitly accepts failover.

Do not expose SDK capabilities just because the SDK supports them. Images,
audio, transcription, embeddings, vector stores, MCP tools, provider tools,
sub-agents, streaming, and failover remain out of scope for this story unless a
later accepted story brings them in with its own permission, audit, cost, and
validation requirements.

Environment-based SDK credentials may still exist for local/system defaults, but
this story's product contract is staff-owned encrypted settings. The
implementation must not rely on `.env` provider keys as the only runtime source
when the authenticated staff user's setting is required.

## Application Flow

Settings page flow:

```text
Staff user
  -> AI provider settings route
  -> permission check
  -> load current user's setting metadata
  -> render Inertia page with provider/model allowlists and masked key state
```

Save or rotate-key flow:

```text
Validated request
  -> permission check
  -> provider/model allowlist validation
  -> cost-limit validation
  -> encrypt API key when a new key is submitted
  -> persist current user's provider setting in a transaction
  -> write redacted audit record
  -> redirect or return safe response without raw key
```

Provider test flow:

```text
Validated request or saved setting
  -> permission check
  -> resolve provider/model allowlist metadata
  -> resolve encrypted key without exposing it to logs or resources
  -> map Swinx provider/model to provider/model/timeout options
  -> for OpenRouter, call /api/v1/chat/completions with a static non-business health prompt
  -> for providers without a live tester, return unsupported_provider_test
  -> map provider/network/auth/model errors to deterministic safe codes
  -> store redacted test metadata
  -> write redacted audit record
  -> return success/failure metadata without raw provider payload
```

Implementation should keep controllers thin:

- FormRequests own validation and authorization entry checks.
- Actions own save, rotate, disable, clear, and test behavior.
- A Swinx adapter/resolver owns the boundary between staff-owned settings and
  provider test calls. The OpenRouter live tester is intentionally a thin
  connectivity probe while the Laravel AI SDK package is not installed; it must
  not grow into a parallel chat/tool framework.
- Resources or Inertia props own safe serialization and masking.

## Interface Contract

Planned internal web surface:

- AI provider settings page for authorized internal staff users.
- Route names should use `route(...)` helpers in Vue and named Laravel routes.
- Inertia page forms should use `useForm` from `@inertiajs/vue3`.
- Non-navigating provider test interactions may use the existing authenticated
  JSON API wrapper pattern if implemented as an inline test button.
- Redirect flash messages must use Inertia v3-compatible flash behavior.

Suggested route contract for implementation planning:

```text
GET    /ai/provider-settings        ai.provider-settings.index
PUT    /ai/provider-settings        ai.provider-settings.update
POST   /ai/provider-settings/test   ai.provider-settings.test
DELETE /ai/provider-settings/key    ai.provider-settings.key.destroy
```

The exact route set may be refined during implementation, but the contract must
preserve these user-visible capabilities:

- view current safe setting metadata;
- create/update provider/model/limits/enabled state;
- rotate API key;
- test provider connection;
- clear the stored API key.

Safe response/prop fields:

```text
provider
default_model
enabled
daily_limit_cents
monthly_limit_cents
has_api_key
api_key_mask
tested_at
last_test_status
last_test_error_code
last_used_at
available_providers
available_models
permissions
```

Forbidden response/prop/log fields:

```text
api_key
encrypted_api_key
raw_provider_request
raw_provider_response
authorization_header
business_prompt
student_data
lecturer_data
finance_data
academic_data
```

Expected error categories:

- unauthorized;
- unsupported provider;
- unsupported model;
- missing API key;
- invalid cost limit;
- provider authentication failed;
- provider model unavailable;
- provider timeout;
- provider rate limited;
- provider temporarily unavailable;
- provider response invalid.
- unsupported by Laravel AI SDK for this feature.

## Data Model

Planned table:

```text
ai_provider_settings
- id
- user_id
- provider
- default_model
- encrypted_api_key
- enabled
- daily_limit_cents
- monthly_limit_cents
- tested_at
- last_test_status
- last_test_error_code
- last_used_at
- created_by_user_id
- updated_by_user_id
- created_at
- updated_at
```

Expected constraints/indexes:

- `user_id` references the internal users table.
- Initial implementation should enforce one setting row per staff user unless a
  later accepted design chooses multi-provider profiles.
- Provider/model strings should be indexed only if query patterns require it.
- The API key column must be encrypted application data, not plain text.
- If the Laravel AI SDK publishes conversation migrations, those SDK tables are
  not part of this story unless the implementation explicitly needs SDK-backed
  conversation storage. Provider settings must stay in Swinx-owned storage.
- Audit metadata should live in the project's accepted audit/logging mechanism
  or a dedicated AI audit table if the implementation story accepts it.

Retention and redaction:

- Raw API keys must not be retained outside encrypted settings storage.
- Audit records must keep who/when/what metadata without storing the secret,
  provider authorization headers, provider request body, or provider raw
  response.
- Provider test failure detail must be normalized into safe error codes.

## UI / Platform Impact

The future UI should be a quiet internal settings surface, not a marketing or
chat page.

Expected controls:

- provider select;
- searchable default model combobox filtered by provider;
- API key password input for create/rotate only;
- enabled toggle;
- daily/monthly cost-limit numeric inputs;
- test connection button with loading/success/failure states;
- clear key action with confirmation;
- masked key state and last-tested metadata.

The provider settings page must:

- reuse existing layout, form, button, toast, dialog, and icon primitives;
- show only if the current user has the relevant AI permission;
- avoid exposing student/lecturer/business data;
- keep disabled and no-key states obvious before later AI runtime slices depend
  on the setting.

No student or lecturer portal files are in scope.

## Observability

Provider settings events are product audit events, not just application logs.

Audit should capture:

- actor user id;
- route/action name;
- setting id;
- provider and model after validation;
- enabled state changes;
- cost-limit changes;
- whether a key was added, rotated, or cleared;
- provider test status and safe error code;
- Laravel AI SDK event name or SDK usage metadata when it is available and safe
  to store;
- request id or correlation id when available;
- timestamp and duration for provider tests.

Audit must not capture:

- raw API key;
- encrypted API key;
- authorization headers;
- raw provider request/response bodies;
- prompts, messages, tool inputs, or business records.

Operational logs should use the same redaction boundary and should be safe to
ship to centralized logging.

## Alternatives Considered

1. Environment-only provider configuration.
   - Rejected for this phase because the blueprint explicitly needs user-owned
     provider/model/cost behavior before staff copilot work can be tested
     safely.
2. Tenant-wide or admin-managed provider configuration first.
   - Deferred because it expands governance, quota, and override policy scope.
     The first implementation slice should prove staff-owned secret handling
     and provider testing before wider admin controls.
3. Store plain text API keys.
   - Rejected because provider credentials are secrets and must never be
     returned, logged, or audited in usable form.
4. Start with chat or tool calling before provider governance.
   - Rejected because later AI data access depends on an explicit provider,
     permission, cost, and redaction boundary.
5. MCP-first provider setup.
   - Rejected for this phase because MCP exposure is intentionally deferred
     until internal tool and governance behavior is stable.
6. Custom provider abstraction instead of Laravel AI SDK.
   - Rejected because the official Laravel AI SDK already provides provider,
     model, timeout, agent, testing, failover, and event surfaces that fit the
     Laravel monolith. Swinx should wrap the SDK for permissions, encrypted
     staff settings, quota, audit, and redaction instead of duplicating it.
