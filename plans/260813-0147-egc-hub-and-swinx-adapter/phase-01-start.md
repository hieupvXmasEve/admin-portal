---
phase: 1
title: "Hub Bootstrap and Tenancy"
status: pending
priority: P1
effort: "4d"
dependencies: []
---

# Phase 1: Hub Bootstrap and Tenancy

## Overview
Create `egc-hub` repo: Laravel 12 skeleton, Docker dev environment (copy Swinx `scripts/dev.sh` pattern), auth for staff users, per-school API clients with static keys.

## Requirements
- Functional: staff login (session), roles `egc-admin` / `lecturer`, permission `finalize-block`; school registry with API keys (2 active max, rotation, revoke)
- Non-functional: key hashed at rest; `last_used_at` tracked; no student login surface exists at all

## Architecture
Flat Laravel structure (no Swinx-style module system — hub is small; YAGNI): `app/Models`, `app/Http/Controllers`, `app/Http/Controllers/Api/V1`, `app/Jobs`, `app/Services`. Vue 3 + Inertia UI. spatie/laravel-permission for roles. Sanctum for API tokens bound to a `School` "client user".

## Related Code Files (all in new repo `egc-hub/`)
- Create: `docker-compose.yml`, `scripts/dev.sh` (ported pattern), `Dockerfile`
- Create: `app/Models/School.php` (name, code, campus list JSON or child table, is_active)
- Create: `app/Models/SchoolApiKey.php` (school_id, name, token hash via Sanctum `personal_access_tokens` + pivot, last_used_at, revoked_at)
- Create: `app/Http/Middleware/AuthenticateSchoolClient.php` (resolve school from token, bind to request context, update last_used_at)
- Create: `app/Http/Controllers/Admin/SchoolController.php`, `Admin/SchoolApiKeyController.php`, `Admin/UserController.php`
- Create: `resources/js/pages/Admin/Schools/*.vue`, `Admin/Users/*.vue`
- Create: migrations for users/roles/schools/api keys; seeder for first egc-admin

## Implementation Steps
1. `laravel new egc-hub`, add Inertia + Vue 3, Pest, Horizon, spatie/permission, Sanctum.
2. Port Docker + dev.sh pattern from Swinx repo (MySQL 8, Redis, app container).
3. Roles/permissions seed: `egc-admin` (all), `lecturer` (own-class scoped); standalone permission `finalize-block`.
4. School model + API key issuance UI: show plaintext once, store hash, enforce max 2 active keys/school, revoke button.
5. `AuthenticateSchoolClient` middleware + `school()` request accessor; deny disabled schools.
6. CI: Pest + pint + larastan on GitHub Actions.

## Success Criteria
- [ ] Admin creates school, issues/rotates/revokes keys; 3rd active key rejected
- [ ] API request with valid key resolves school; revoked key → 401; `last_used_at` updates
- [ ] Lecturer role cannot access admin pages

## Risk Assessment
- Key leak in logs → mitigation: show once, never log Authorization header (scrub in logging config).
