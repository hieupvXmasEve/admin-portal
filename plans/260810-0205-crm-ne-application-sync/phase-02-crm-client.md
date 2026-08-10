---
phase: 2
title: "CRM client and config"
status: completed
priority: P1
effort: "0.5d"
dependencies: [1]
---

# Phase 2: CRM client and config

## Overview

One HTTP client that logs in, holds the bearer token for the duration of a run, fetches `/api/ne`, and
turns every failure mode into a typed exception. No credentials in source, none in logs.

## Requirements

- Functional: `login()` → token; `fetchNewEnrollments(): array` returning the raw `data` array.
- Non-functional: configurable base URL and timeout; 401 on `/ne` triggers exactly one re-login retry; nothing sensitive logged.

## Architecture

Follows the existing `DngClient` precedent ([app/Modules/Finance/Dng/Services/DngClient.php:26](../../app/Modules/Finance/Dng/Services/DngClient.php)):
constructor reads `config('services.crm.*')`, methods use `Http::`.

```php
config/services.php
'crm' => [
    'base_url' => env('CRM_BASE_URL', 'https://dangky.asia-vn.edu.vn'),
    'username' => env('CRM_USERNAME'),
    'password' => env('CRM_PASSWORD'),
    'timeout'  => env('CRM_TIMEOUT', 120),
],
```

Only the host is configurable. The two endpoint paths are part of the API contract and live as constants
in `CrmClient`, not as env keys — `POST {base_url}/api/login` and `GET {base_url}/api/ne`. An env key per
path would just be a way to misconfigure the client.

Timeout defaults to 120s: `/api/ne` returns the whole list in one response with no pagination, and the
expected volume is a few hundred records (validation V5). No host allowlist — URL validation is
scheme-only (V4), so there is no config key for it.

<!-- Updated: Validation Session 1 - timeout 30→120s, host allowlist config removed -->

If a run ever approaches the timeout, that is the signal to add batching — record the observed run
duration in the Phase 3 summary output so the threshold is measurable rather than guessed.

Token lifetime = one process. No cache, no DB persistence — a sync run is short and a fresh login is cheap.
`ponytail: in-memory token, revisit only if login rate-limiting shows up.`

Failure taxonomy, all extending one `CrmSyncException`:
- `CrmAuthenticationException` — login non-2xx, `status != success`, or missing `data.token`.
- `CrmRequestException` — 4xx/5xx/timeout/connection error on `/ne`.
- `CrmResponseException` — missing `data`, or `data` not a list.

The client validates envelope shape but does **not** validate record contents; that is the mapper's job
(Phase 3), because a single malformed record must not fail the whole fetch.

## Related Code Files

- Create: `app/Modules/Admissions/Integrations/Crm/CrmClient.php`
- Create: `app/Modules/Admissions/Exceptions/CrmSyncException.php` (+ the three subclasses, same file only if they stay trivial)
- Modify: `config/services.php` (append `crm` block)
- Modify: `.env.example` (`CRM_BASE_URL`, `CRM_USERNAME`, `CRM_PASSWORD`, `CRM_TIMEOUT` — names only, empty values)
- Create: `tests/Feature/Admissions/CrmClientTest.php`

## Implementation Steps

1. Add the `crm` config block and `.env.example` keys. Never commit real values.
2. Write `CrmClient`: `login()` posts `{username, password}`, reads `data.token` + `data.token_type`; `fetchNewEnrollments()` sends `Authorization: {token_type} {token}`, logging in first if no token is held.
3. On 401 from `/ne`: clear the token, log in once more, retry once. Second failure → `CrmAuthenticationException`.
4. Exception messages carry status code and endpoint — never the request body, credentials, or token. Add a test asserting the password does not appear in the exception message.
5. Tests with `Http::fake()` (repo precedent: `tests/Feature/AI/AiProviderSettingsTest.php:64`): login success, login failure, missing token, `/ne` success, empty `data`, `data` not an array, 500, timeout, 401-then-success retry.

## Success Criteria

- [ ] Every listed failure mode raises its specific exception type.
- [ ] 401 retry logs in exactly once more (assert via `Http::assertSentCount`).
- [ ] No credential or token appears in any exception message or log line (asserted).
- [ ] Client works with zero DB access — pure HTTP unit-level test.

## Risk Assessment

- **Silent credential leak in logs** (high): mitigated by an explicit assertion, not by convention.
- **CRM login rate limit under repeated retries** (low): single retry only, no loop.

## Addendum: dynamic config (2026-08-10, post-implementation)

Follow-up request: make the connection config staff-editable without a deploy. `system_settings`
(Platform's closed, all-keys-required registry, plaintext JSON values) was considered and rejected — no
place for an optional integration credential, and no encryption. Instead:

- New Admissions-owned table `crm_integration_settings` (single row, `id=1`): `base_url`, `username`,
  `password` (Eloquent `encrypted` cast — precedent: `AiProviderSetting::encrypted_api_key`), `timeout`.
- `CrmIntegrationSettings::resolve()` — DB row wins; any field left blank falls back to
  `config('services.crm.*')` (`.env`), so an unconfigured environment keeps working.
- `CrmIntegrationSettings::forDisplay()` — UI-facing read, exposes `has_password: bool` only, never the
  value.
- `CrmClient` now takes `CrmIntegrationSettings` via constructor injection instead of reading `config()`
  directly.
- UI: a "CRM connection" card on the existing mapping screen (`CrmMappings.vue`), gated behind the same
  `manage_crm_value_mapping` permission. Password field is write-only — starts blank, a blank submit means
  "keep the current password" (`CrmIntegrationSettings::save()`).
- Gotcha hit during implementation: `id` is not in `$fillable`, so
  `updateOrCreate(['id' => 1], $attributes)` silently dropped the id on the create path and inserted an
  autoincrement row instead of row 1. Fixed by setting `$row->id` directly (bypasses the fillable guard)
  before `fill()->save()`.

Files: `database/migrations/2026_08_10_143700_create_crm_integration_settings_table.php`,
`app/Modules/Admissions/Models/CrmIntegrationSetting.php`,
`app/Modules/Admissions/Support/Crm/CrmIntegrationSettings.php`,
`app/Modules/Admissions/Http/Requests/Admissions/SaveCrmIntegrationSettingsRequest.php`,
`tests/Feature/Admissions/CrmIntegrationSettingsTest.php`. Modified: `CrmClient.php`,
`CrmMappingController.php` (+`storeIntegration`), `routes/web.php`, `CrmMappings.vue`.
