---
phase: 1
title: "Database Authority and Compatibility"
status: in-progress
priority: P1
effort: "2 days"
dependencies: []
---

# Phase 1: Database Authority and Compatibility

## Overview

Replace whole-file JSON mutation with typed, per-key MySQL persistence while
keeping current Platform readers, callers, route contracts, and public response
shape compatible.

## Requirements

- Functional: DB-only runtime reads/writes; atomic partial upserts; migration-created defaults;
  unchanged `SystemConfigurationReader` behavior for existing internal callers.
- Non-functional: shared-cache consistency; no lost updates; no arbitrary
  public exposure; no runtime dependency on legacy storage.

## Architecture

Create a Platform-owned `system_settings` table with unique `key`, JSON `value`,
and timestamps. `SystemConfigurationStore` remains the contract adapter: it
loads one canonical configuration/branding snapshot through an explicitly
shared cache store and invalidates it only after the surrounding transaction
commits. Multi-instance deployment rejects process-local/file cache. A code
registry defines supported admin keys, validation/type expectations, required
keys, and public keys; it does not store runtime values.

The migration materializes every required setting directly in
`system_settings`: default text for editable labels/copyright/country, typed
defaults for required Facilities/Engagement keys, and `null` branding upload
IDs. It deliberately ignores legacy JSON values and asset paths. Application
requests never read or write the JSON file. Missing required rows fail
readiness instead of silently falling back to source values.

### Initial DB Values

| Key | Type | Initial value |
|---|---|---|
| `app_name` | string | `Swinx` |
| `copyright_text` | string | `© 2026 Asia Vietnam University. All rights reserved.` |
| `country` | string | `Việt Nam` |
| `survey_enabled` | boolean | `false` |
| `default_course_survey` | nullable integer | `null` |
| `active_query_forms` | integer array | `[]` |
| `system_booking_start_time` | time string | `07:00` |
| `system_booking_end_time` | time string | `20:00` |
| `allow_student_booking` | boolean | `true` |
| `student_booking_limit_per_day` | integer | `2` |
| Four branding upload-ID keys | nullable integer | `null` |

Users may change these values through their authorized management surfaces
after deployment.

## Related Code Files

- Create: `/Users/hunt2412/hieupvdev/project/swinx/database/migrations/<timestamp>_create_system_settings_table.php`
- Create: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Models/SystemSetting.php`
- Create: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Support/SystemConfigurationDefinition.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Support/SystemConfigurationStore.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Actions/UpdateSystemConfigurationAction.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Providers/PlatformServiceProvider.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Http/Requests/SystemConfiguration/UpdateSystemConfigurationRequest.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Platform/SystemConfigurationMigrationTest.php`

## Implementation Steps

1. Inventory supported keys/types and all internal keys used by Facilities and
   Engagement; do not copy current JSON values.
2. Add `system_settings`, its module-owned model, and explicit initial rows;
   keep scope global and branding upload IDs nullable.
3. Build the definition registry for admin-editable keys, types, requiredness,
   and the public allowlist. Do not keep mutable value defaults in the registry
   or persist an editable `is_public`.
4. Refactor the store to DB-only typed reads and granular transactional
   upserts. Use one snapshot cache key and an after-commit invalidation path.
5. Remove JSON loading/writing from `SystemConfigurationStore`. Fail the
   cutover gate if required DB rows are missing; do not add dual-read fallback.
6. Require `hasSystemRole('super_admin')` for global mutations; do not treat
   one campus `manage_system_config` grant as global authority.
7. Preserve the current Action/Query/shared-contract signatures so Facilities,
   Engagement, API, and tests migrate without cross-context churn.

## Todo

- [x] Add table/model and setting definition registry.
- [x] Add DB-only store with transaction-safe cache invalidation.
- [x] Materialize every required initial value into `system_settings`.
- [x] Restrict global mutation to system-role super administrators.
- [x] Preserve typed values and existing public redaction.
- [x] Add focused persistence/import/cache tests.

## Success Criteria

- [x] String, boolean, integer, array, and null values round-trip without type drift.
- [ ] Updating one key cannot overwrite another concurrent key update.
- [x] Migration creates deterministic defaults without reading legacy JSON.
- [x] Runtime requests never access `system_config.json`.
- [x] Missing required rows fail readiness instead of using source defaults.
- [x] Non-public imported keys remain inaccessible from public endpoints.
- [x] Current `SystemConfigurationReader` callers pass unchanged.

## Risk Assessment

- Omitting required Facilities/Engagement keys would break runtime behavior;
  seed their explicit typed defaults and cover them with tests.
- Forever-cache invalidation before commit can expose stale or rolled-back
  state; invalidate after commit and test with the shared cache binding.
- The reset intentionally discards legacy values. Record this deployment
  decision and expose all editable defaults on the admin page.

## Security Considerations

- Do not log setting values.
- Do not allow request payloads to set unknown keys or public visibility.
- Keep authentication, CSRF, and audit boundaries. Tighten mutations to the
  existing global `super_admin` system role and test a campus-only manager denial.
