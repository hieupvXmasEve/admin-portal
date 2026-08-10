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
