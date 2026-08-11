---
phase: 2
title: "Upload module sweep"
status: pending
priority: P1
effort: "1-2h"
dependencies: [1]
---

# Phase 2: Upload module sweep

## Overview

Repoint all 25 callers of the three Upload shims to
`App\Modules\Upload\Models\*`, delete those three shim files, and invert the
module's placement arch test. Chosen as the first real sweep because Upload's
models carry **zero** persisted morph rows — this proves the mechanical
procedure end to end with no stateful risk.

## Requirements

- Functional: no file outside `app/Models/` references `App\Models\{ApplicationDocument, ApplicationDocumentType, UploadRecord}`.
- Functional: the three shim files are deleted.
- Functional: `UploadModelPlacementArchTest` asserts the shims are **gone** instead of present.
- Non-functional: import-line and deletion diff only. No logic, schema, or behavior change.
- Non-functional: no data migration in this phase — measured zero morph rows.

## Architecture

Shims in scope, with caller counts measured 2026-08-11:

| Shim | Canonical FQCN | Callers |
|---|---|---|
| `ApplicationDocument` | `App\Modules\Upload\Models\ApplicationDocument` | 13 |
| `ApplicationDocumentType` | `App\Modules\Upload\Models\ApplicationDocumentType` | 14 |
| `UploadRecord` | `App\Modules\Upload\Models\UploadRecord` | 8 |

25 unique files (counts overlap — several files import two of the three).

**Zero persisted state.** None of these three FQCNs appears in
`activity_log.subject_type` or any other morph column, and `jobs` is empty with
`failed_jobs` clean. No backfill migration is needed here — this is the purely
mechanical half of the sweep.

### Cross-module coupling (correction to plan.md)

`plan.md` states phases 2-5 "own disjoint file sets". That is **wrong** — the
shims import each other across module boundaries:

- `app/Modules/Engagement/Models/FormResponse.php` and
  `app/Modules/Engagement/Models/QueryReply.php` both import
  `App\Models\UploadRecord`, so **this phase edits two files inside the
  Engagement module.** That is correct and safe: rewriting an import to a
  canonical FQCN does not require the target model's own phase to be done,
  because the canonical class already exists today.
- Symmetrically, `app/Modules/Upload/Models/UploadRecord.php` imports three
  Engagement shims and is therefore swept by **phase 3**, not this one.

Three test files are shared with phase 3 and will conflict on merge if the two
phases are developed in parallel:

- `tests/Feature/Api/V1/Student/QueryTicketApiTest.php`
- `tests/Feature/Form/AdminQueryInboxTest.php`
- `tests/Feature/Form/QueryTicketWorkflowTest.php`

Run phases 2 and 3 sequentially, not in parallel. Either order works.

### Arch-test inversion (correction to plan.md)

`plan.md` Architecture step 5 says to "run the module's existing placement arch
test". Doing only that **fails**: `UploadModelPlacementArchTest.php:34` asserts
`file_exists(app/Models/<Model>.php)` is `true` with the message *"Expected shim
to remain"*, and asserts the file contains `class_alias(`. Deleting the shim
makes that assertion red by design.

**Decision:** delete that second `it()` block rather than inverting it. The
phase-1 guard `DeprecatedModelShimArchTest` already asserts the exact set of
remaining shims repo-wide via exact-match, so a per-module "shim is gone"
assertion would be redundant. Keep the first `it()` block (the real class lives
under `app/Modules/Upload/Models`) — that is the placement guarantee and stays
valuable after the shim is gone.

## Related Code Files

- Delete: `app/Models/ApplicationDocument.php`
- Delete: `app/Models/ApplicationDocumentType.php`
- Delete: `app/Models/UploadRecord.php`
- Modify: `tests/Feature/Architecture/UploadModelPlacementArchTest.php` (drop the shim-must-exist block)
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` (drop 3 entries from `SHIMMED_MODELS`, drop this phase's swept paths from `SHIMMED_MODEL_IMPORT_BASELINE`)
- Modify: `config/migration_debt.php` (lower the `shared_model_imports` baseline — see Implementation Steps)
- Modify (callers, 25 files):
  - `app/Exports/StudentApplicationExport.php`
  - `app/Http/Controllers/Api/V1/Admissions/IngestionController.php`
  - `app/Http/Controllers/Web/StudentApplicationController.php`
  - `app/Modules/Admissions/Actions/UpsertCrmApplicationAction.php`
  - `app/Modules/Admissions/Http/Api/IngestionController.php`
  - `app/Modules/Admissions/Queries/GetApplicantDocumentChecklistQuery.php`
  - `app/Modules/Admissions/Queries/ListApplicationsQuery.php`
  - `app/Modules/Engagement/Models/FormResponse.php`
  - `app/Modules/Engagement/Models/QueryReply.php`
  - `app/Services/Admissions/ApplicationBackfillService.php`
  - `app/Services/Admissions/ApplicationIngestionService.php`
  - `app/Services/ApplicationDocumentService.php`
  - `app/Services/ApplicationDocumentTypeSyncService.php`
  - `tests/Feature/Academic/StudentLifecycleTimelineQueryTest.php`
  - `tests/Feature/Admissions/ApplicationBackfillTest.php`
  - `tests/Feature/Admissions/CrmApplicationSyncTest.php`
  - `tests/Feature/Admissions/IngestionApplicationsTest.php`
  - `tests/Feature/Api/V1/Student/QueryTicketApiTest.php`
  - `tests/Feature/Form/AdminQueryInboxTest.php`
  - `tests/Feature/Form/QueryTicketWorkflowTest.php`
  - `tests/Feature/Platform/SystemConfigurationMigrationTest.php`
  - `tests/Feature/StudentApplication/DocumentsTest.php`
  - `tests/Feature/StudentApplication/ExportTest.php`
  - `tests/Feature/StudentApplication/IndexCampusScopeTest.php`
  - `tests/Feature/Upload/UploadPlatformTest.php`

## Implementation Steps

1. **Record the pre-sweep baseline.** Run the touched suites first so later
   failures are attributable:

   ```bash
   ./scripts/dev.sh artisan test tests/Feature/Upload tests/Feature/StudentApplication tests/Feature/Admissions tests/Feature/Architecture
   ```

2. **Re-measure.** Confirm the caller set has not drifted and morph rows are
   still zero:

   ```bash
   for m in ApplicationDocument ApplicationDocumentType UploadRecord; do
     echo "=== $m"
     grep -rlP "\bApp\\\\{1,2}Models\\\\{1,2}${m}\b" app tests database routes config --include="*.php" | grep -v '^app/Models/'
   done
   ```

   ```bash
   ./scripts/dev.sh artisan tinker --execute="foreach (['ApplicationDocument','ApplicationDocumentType','UploadRecord'] as \$m) { echo \$m.' = '.DB::table('activity_log')->where('subject_type','App\\\\Models\\\\'.\$m)->count().PHP_EOL; }"
   ```

   All three must be `0`. If any is non-zero, stop and add a backfill migration
   modelled on `2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php`.

3. **Rewrite the imports** in all 25 files: `App\Models\X` →
   `App\Modules\Upload\Models\X`. Watch for the escaped string-literal form
   (`'App\\Models\\X'`) as well as `use` lines — the phase-1 guard's regex
   matches both, so anything it flags must be handled.

4. **Delete the three shim files.**

5. **Update `UploadModelPlacementArchTest.php`** — remove the
   `it('has no real (non-shim) ... under app/Models')` block per the decision
   above. Keep the placement block.

6. **Shrink the phase-1 guard.** In `DeprecatedModelShimArchTest.php`: drop the
   three model names from `SHIMMED_MODELS`, and drop from
   `SHIMMED_MODEL_IMPORT_BASELINE` every path that no longer matches. The guard
   asserts exact equality in both directions, so a stale entry now fails —
   that is the intended forcing function.

7. **Shrink the migration-debt baseline.** `config/migration_debt.php`
   `shared_model_imports` is `baseline: 289, mode: max` and counts
   `App\Models\*` references under `app/` + `routes/`. Lower it by the number of
   `app/` references this phase removed, or `MigrationDebtInventoryTest` keeps
   passing against a stale ceiling and the two guards drift.

## Success Criteria

- [ ] `grep -rlP '\bApp\\{1,2}Models\\{1,2}(ApplicationDocument|ApplicationDocumentType|UploadRecord)\b' app tests database routes config --include='*.php'` returns nothing
- [ ] `app/Models/{ApplicationDocument,ApplicationDocumentType,UploadRecord}.php` deleted
- [ ] `SHIMMED_MODELS` is down to 27 entries; `DeprecatedModelShimArchTest` green in both directions
- [ ] `UploadModelPlacementArchTest` green with the shim-must-exist block removed
- [ ] `shared_model_imports` baseline lowered to match the new real count
- [ ] Touched suites match the step-1 baseline pass/fail counts — no new failures
- [ ] Diff contains only import rewrites, 3 file deletions, and the two test/config baseline edits

## Risk Assessment

| Risk | Mitigation |
|---|---|
| A caller referenced the shim as an escaped string literal and grep-by-`use` misses it | Use the `\\{1,2}` regex form in step 2 (matches both single- and double-backslash); the phase-1 guard uses the same pattern and will fail if one is missed |
| Deleting the shim reddens `UploadModelPlacementArchTest` | Step 5 removes that block in the same PR |
| Merge conflict with phase 3 on the 3 shared QueryTicket/AdminQueryInbox test files | Run phases 2 and 3 sequentially; do not parallelize |
| Editing two Engagement-module files feels out of scope for an "Upload" phase | Intentional and documented above — those files import `App\Models\UploadRecord`; leaving them would fail the guard |
| Morph rows appear between measurement and deletion | Step 2 is re-run immediately before deletion, not once at plan time |
| Baseline failures get blamed on this phase | Step 1 records the pre-sweep baseline; repo has known pre-existing failures in Finance/Academic and 5 in `tests/Feature/Architecture` |
