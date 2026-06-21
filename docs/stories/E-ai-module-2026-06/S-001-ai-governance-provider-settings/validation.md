# Validation

## Proof Strategy

Validation for the implementation story must prove the credential, permission,
provider-test, and redaction boundaries before any AI tool or chat feature is
exposed.

Automated tests must use Laravel AI SDK fakes/assertions where they apply,
provider adapter fakes, or HTTP fakes. Live provider credentials, real network
calls, real student records, or production data must not be required to prove
this story.

This implementation step is validated by proving the route, credential,
permission, provider-test, and audit boundaries without live provider
credentials or business data.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Provider/model allowlist validation; Swinx provider ids map to Laravel AI SDK provider primitives where supported; API key masking; encryption/decryption wrapper behavior; cost-limit validation; provider-test error mapping; redaction helper removes secrets from logs/audit payloads. |
| Integration | Authorized staff can view/update/test/clear their own setting; unauthorized users are denied; unsupported providers/models are rejected; settings are persisted encrypted; responses never include raw or encrypted keys; Laravel AI SDK-backed provider test success/failure writes safe metadata; settings changes and test attempts write redacted audit records. |
| E2E | Permissioned staff can open the AI provider settings page, save a provider/model/limits, rotate a key, test connection, see masked key state, disable setting, and clear the key; unauthorized staff cannot access the page or actions. |
| Platform | No live provider credentials are needed for tests; Laravel AI SDK fakes or adapter fakes prevent stray provider calls; provider tester has a strict timeout; app logs and audit records are secret-redacted; no student/lecturer portal code changes. |
| Performance | Settings lookup is scoped to the current user and does not add broad cross-domain queries; provider test does not block longer than the accepted timeout. |
| Logs/Audit | Audit includes actor/action/provider/model/status/limit-change metadata and safe Laravel AI SDK event/usage metadata when available; excludes raw key, encrypted key, auth headers, raw provider body, prompts, and business records. |

## Fixtures

Future implementation tests should define:

- internal staff user with AI settings view/manage/test permissions;
- internal staff user without AI settings permissions;
- optional super-admin/admin actor only if existing permission patterns require
  it;
- server allowlist with at least one fake provider and two fake models;
- Laravel AI SDK fake agent/provider response for the supported provider path;
- fake provider success response;
- fake provider authentication failure response;
- fake provider timeout or unavailable response;
- fake provider model-unavailable response;
- redaction samples containing API-key-like strings and authorization headers;
- SDK event or usage payload sample with secret-free metadata;
- existing campus context only if required by shared middleware, not as a data
  access grant.

## Commands

Requirements validation for the original planning step:

```text
./scripts/harness query matrix --numeric | rg -F "AI-MOD-001-ai-governance-provider-settings"
git status --short
```

Implementation validation:

```text
./scripts/dev.sh test --filter=AiProviderSettings
./scripts/dev.sh artisan route:list --name=ai.provider-settings
./scripts/dev.sh composer show laravel/ai
./scripts/dev.sh composer exec pint -- app/Modules/AI database/migrations tests/Feature/AI tests/Unit/AI
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
```

If frontend files are touched and repo-wide type-check remains blocked by
pre-existing diagnostics, the implementation trace must record the targeted
substitute checks and prove no diagnostics come from touched AI files.

## Acceptance Evidence

- Harness intake recorded for this implementation step.
- Story packet exists at
  `docs/stories/E-ai-module-2026-06/S-001-ai-governance-provider-settings/`.
- Harness story row is registered for
  `AI-MOD-001-ai-governance-provider-settings`.
- Route contract is registered:
  `./scripts/dev.sh artisan route:list --name=ai.provider-settings --except-vendor`
  showed four provider-settings routes.
- Initial feature test passed:
  `docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev exec app sh -lc './vendor/bin/pest tests/Feature/AI/AiProviderSettingsTest.php --colors=never'`
  passed 7 tests / 105 assertions.
- Follow-up live OpenRouter provider test slice passed:
  `docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev exec app sh -lc './vendor/bin/pest tests/Feature/AI/AiProviderSettingsTest.php --colors=never'`
  passed 9 tests / 130 assertions, including real OpenRouter HTTP request
  shape, success metadata, authentication-failure mapping, and redacted audit
  assertions.
- Follow-up OpenRouter model catalog/search slice passed:
  `docker compose --env-file .env -f docker/docker-compose.dev.yml -p swinx-dev exec app sh -lc './vendor/bin/pest tests/Feature/AI/AiProviderSettingsTest.php --colors=never'`
  passed 10 tests / 137 assertions, including provider Models API text-model
  merging, non-text model exclusion, and searchable model combobox coverage.
- PHP formatting ran:
  `./scripts/dev.sh composer exec pint -- --format agent app/Modules/AI config/ai_provider_settings.php tests/Feature/AI/AiProviderSettingsTest.php`.
- Whitespace check passed:
  `git diff --check`.
- Targeted frontend lint passed:
  `./scripts/dev.sh npm exec eslint resources/js/pages/AI/ProviderSettings/Index.vue`.
- Targeted frontend format check passed:
  `./scripts/dev.sh npm exec prettier --check resources/js/pages/AI/ProviderSettings/Index.vue`.
- Current follow-up Vite build attempt:
  `./scripts/dev.sh npm exec vite build` transformed 5461 modules, then was
  killed with SIGKILL/exit 1 before chunk output; no compile diagnostic was
  emitted.
- Current follow-up type-check attempt:
  `./scripts/dev.sh npm exec vue-tsc --noEmit --pretty false` was killed with
  SIGKILL before diagnostics.
- Earlier slice evidence recorded a successful Vite build before this model
  selector follow-up; the current follow-up relies on targeted ESLint/Prettier
  plus direct Pest because repo-wide frontend checks exceed the local container
  limits.
- `./scripts/dev.sh test --filter=AiProviderSettings` currently exits 255
  without output through Artisan; direct Pest execution of the AI feature file
  is the recorded passing test command.
- Portal impact remained `none`; no `/api/v1/student/*`, `/api/v1/lecturer/*`,
  or nested Nuxt portal files were changed.
