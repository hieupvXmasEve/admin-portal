---
phase: 4
title: "Migration, Deployment, and Verification"
status: in-progress
priority: P1
effort: "0.5-1.5 days plus soak"
dependencies: [1, 2, 3]
---

# Phase 4: Migration, Deployment, and Verification

## Overview

Create default DB settings without legacy import, prove persistence through a
production-style redeploy, document recovery, and delete the JSON authority.

## Requirements

- Functional: deterministic default rows, DB migration, smoke test, recovery
  rehearsal, legacy file deletion, and manual post-cutover branding upload.
- Non-functional: no destructive schema rollback after cutover; DB and object
  storage backups are treated as one recoverable configuration set.

## Architecture

The migration creates `system_settings` and deterministic defaults without
reading legacy JSON or asset files. Text/typed keys are immediately usable;
the four branding upload IDs start `null`. Activate the DB-only build, verify
all required rows and empty branding states, then delete
`storage/app/private/system_config.json`. Runtime authority is MySQL plus the
configured branding disk. Users update text and upload replacement branding
after cutover.

## Related Code Files

- Delete after DB smoke check: `/Users/hunt2412/hieupvdev/project/swinx/storage/app/private/system_config.json`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Platform/Support/SystemConfigurationStore.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Platform/SystemConfigurationMigrationTest.php`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture/SystemConfigurationBoundaryArchTest.php`
- Create: `/Users/hunt2412/hieupvdev/project/swinx/docs/features/platform/system-configuration.md`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/docs/README.md`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/docs/system-architecture.md`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/docs/features/upload/storage.md`
- Modify: `/Users/hunt2412/hieupvdev/project/swinx/docs/deployment-guide.md`

## Implementation Steps

1. Record `Portal impact: none`; do not change either nested Nuxt repository.
2. Back up MySQL. Existing JSON and branding values are intentionally not
   migrated; record this reset in the deployment checklist.
3. In staging/local-production, apply the migration and verify the exact
   default key/type/value inventory plus four `null` branding upload IDs.
4. Apply the schema through Docker wrappers, verify required rows, then
   activate the DB-only build.
5. Rebuild/redeploy the app image and prove settings/assets persist. For
   multi-host operation, verify a shared cache store and S3-compatible storage.
6. Smoke-test anonymous login, authenticated settings, API redaction, and
   explicit empty branding states. Then remove
   `storage/app/private/system_config.json`.
7. Rehearse recovery using DB + object-storage backups and a DB-capable
   last-known-good image. Explicitly prohibit rollback to file-based code.
8. Have an authorized user update text settings and upload new logo/favicon;
   verify immutable URLs and first-render branding.
9. Add an architecture test against JSON storage/importer resurrection.
10. Update canonical system-configuration, upload, architecture, and deployment
   docs; run documentation validation.

## Validation Matrix

| Area | Targeted evidence |
|---|---|
| Persistence/API/authorization | `./scripts/dev.sh artisan test --compact tests/Feature/Platform/SystemConfigurationMigrationTest.php` |
| Branding upload | `./scripts/dev.sh artisan test --compact tests/Feature/Platform/SystemBrandingUploadTest.php` |
| Blade/Inertia delivery | `./scripts/dev.sh artisan test --compact tests/Feature/Platform/SystemBrandingDeliveryTest.php` |
| Architecture | `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/SystemConfigurationBoundaryArchTest.php` |
| PHP format | `./scripts/dev.sh composer exec pint -- --dirty --format agent` |
| Frontend | file-scoped ESLint and Prettier on changed Vue/TS files |
| Docs | `./scripts/check-docs.sh` |
| Deploy | local-production image rebuild plus persistence/health smoke test |
| Cache | two-instance read-after-write check using the configured shared store; reject file/array cache for multi-instance deploy |

## Todo

- [x] Apply migration and verify deterministic default rows.
- [x] Confirm the four branding slots start empty.
- [x] Delete `storage/app/private/system_config.json` after DB smoke checks.
- [ ] Update text and upload replacement branding through the admin page.
- [ ] Rehearse DB/object-storage recovery with a DB-capable image.
- [x] Update canonical runbooks and pass scoped checks.

## Success Criteria

- [x] Fresh-install initialization creates every required `system_settings` row.
- [x] No legacy value or asset is imported/backfilled.
- [x] All four branding upload IDs start `null` and accept new user uploads.
- [ ] Normal redeploy/rebuild retains DB values and branding objects.
- [ ] Restore rehearsal recovers both settings and referenced objects.
- [x] No runtime code reads/writes `system_config.json`, and the file no longer exists.
- [x] No permanent migration command or fixed-name branding overwrite remains.
- [x] All targeted checks pass; broader repository-wide suites are recorded as not run.

## Risk Assessment

- Named Docker volumes survive normal deploy but not volume deletion, project
  rename, or host replacement. Backups/object storage are mandatory operations.
- DB-only restore can leave dangling upload references; restore procedures must
  pair database and object storage recovery points.
- Database cache is shared, but Redis is not guaranteed by default. Verify the
  actual production cache store rather than assuming Redis.
- File-based builds cannot safely roll back after cutover. Keep a DB-capable
  last-known-good image and prefer roll-forward or backup restore.
- The cutover intentionally resets current settings/assets. Confirm stakeholder
  acceptance and schedule the replacement logo/favicon upload immediately after deploy.

## Security Considerations

- Do not copy legacy values into migration logs, seed rows, or deployment output.
- Confirm production uses `APP_DEBUG=false`, correct public origin, a shared
  cache store, and private database ports.
- Never use destructive migration rollback or file-based application rollback
  once DB settings are authoritative.
