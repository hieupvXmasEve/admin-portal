---
phase: 1
title: "DEAD remainder, dead ingest stack, guards + audit refresh"
status: pending
priority: P1
effort: "0.5d"
dependencies: []
---

# Phase 1: DEAD remainder, dead ingest stack, guards + audit refresh

## Overview

Delete the last DEAD route file AND the dead legacy ingest stack it points at, fix the two guard configs that pin those paths, run the morph-FQCN pre-flight that later phases depend on, and bring the audit report in line with verified reality.

Scope grew from red-team: the dead route's controller, its FormRequest, and 2 orphan services are unhardened duplicates of the live Admissions ingest path (live = `app/Modules/Admissions/Http/Api/IngestionController.php`, mounted via `AdmissionsServiceProvider:22`). Namespace-swapping them later would polish dangerous code; they die here instead.

## Requirements

- Functional: zero route-table change; live CRM ingest untouched.
- Non-functional: `MigrationDebtInventoryTest` and `DeprecatedModelShimArchTest` stay green in the same commit (both use exact two-way snapshots — stale entries fail as hard as new ones).

## Related Code Files

- Delete: `routes/api/v1/admissions.php` (orphan — no include anywhere)
- Delete: `app/Http/Controllers/Api/V1/Admissions/IngestionController.php` (only consumer of the dead route; lacks finding-7/12 hardening present in `UpsertCrmApplicationAction`)
- Delete: `app/Http/Requests/Admissions/IngestApplicationRequest.php` (verify sole consumer = dead controller first)
- Delete: `app/Services/Admissions/ApplicationIngestionService.php` (sole caller = dead controller; unhardened `updateOrCreate` on bare `crm_file_id`)
- Delete: `app/Services/ApplicationDocumentService.php` (zero callers)
- KEEP: `app/Services/Admissions/ApplicationBackfillService.php` — live via `BackfillApplicationsCommand`
- Modify: `config/migration_debt_paths.php` — remove `frozen_routes` entry `routes/api/v1/admissions.php` (line ~102) + remove `frozen_services` entries for the 2 deleted services (lines ~10-16; `frozen_routes`/`frozen_services` are mode `max`, so counts need no bump)
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — remove the 3 deleted files from `SHIMMED_MODEL_IMPORT_BASELINE` (dead controller + 2 services import `App\Models\ApplicationDocument`; leaving them = staleEntries red)
- Modify: `plans/reports/audit-260810-0004-legacy-code-inventory.md` (append dated update)

## Implementation Steps

1. Capture `./scripts/dev.sh artisan route:list --json | md5` baseline.
2. Verify sole-consumer claims: `grep -rn "IngestApplicationRequest\|ApplicationIngestionService\|ApplicationDocumentService" app tests routes config --include='*.php'` — expected hits only in the files being deleted + arch baseline. Any extra hit → stop, reassess that file.
3. Delete the 5 files; apply both config edits + baseline shrink in the same commit.
4. Re-run route:list hash — must be identical. Confirm module ingest auth coverage still green (`tests/Feature/Admissions/` — allowlist/ability/throttle live on the module routes).
5. Morph-FQCN pre-flight (blocks Phases 2-5): on prod DB, `SELECT subject_type, COUNT(*) FROM activity_log WHERE subject_type LIKE 'App\\\\Models\\\\%' GROUP BY 1` plus equivalent for any other `*_type` morph columns. If any of the 6 shimmed FQCNs appears → a third backfill migration must land BEFORE the owning phase. Record result (even "zero rows") in the audit report update.
6. Append audit update: DEAD 6/6 deleted (incl. ingest stack), split-brain `EgcRetakeDiscountLink` resolved, `app/Models` = 84, shims = 6, morph pre-flight result.

## Success Criteria

- [ ] 5 files deleted; route:list hash unchanged
- [ ] `./scripts/dev.sh artisan test tests/Feature/Architecture` green (incl. MigrationDebtInventoryTest + DeprecatedModelShimArchTest)
- [ ] Morph pre-flight result recorded; blockers (if any) filed before Phase 2
- [ ] Audit report updated

## Rollback

Single revert commit restores the 5 files + the 2 config edits + baseline entries together — never partially.

## Risk Assessment

Low but not "near-zero": the two-way snapshot guards mean forgetting either config/baseline edit turns arch suite red. Deletion targets verified dead by grep at plan time; step 2 re-verifies at execution time.
