---
phase: 5
title: "Merchandise sweep and final shim removal"
status: pending
priority: P1
effort: "1-2h"
dependencies: [1, 3]
---

# Phase 5: Merchandise sweep and final shim removal

## Overview

Sweep the 17 callers of Merchandise's seven shims, delete them, and close the
plan: assert `app/Models/` holds zero `class_alias` shims, retire the guard's
allow-list to empty, and confirm no morph column anywhere still names a deleted
shim.

## Requirements

- Functional: no file references `App\Models\<any of the 7 Merchandise shims>`.
- Functional: all seven shim files deleted — and with them, the last of the 30.
- Functional: `DeprecatedModelShimArchTest`'s `SHIMMED_MODELS` is empty.
- Functional: every plan-level success criterion in `plan.md` verified, not just this phase's.
- Non-functional: import rewrites and deletions only. No morph migration needed — measured zero rows.

## Architecture

| Shim | Canonical FQCN | Callers |
|---|---|---|
| `GoldTransaction` | `App\Modules\Merchandise\Models\GoldTransaction` | 10 |
| `Merchandise` | `App\Modules\Merchandise\Models\Merchandise` | 9 |
| `MerchandiseVariant` | `App\Modules\Merchandise\Models\MerchandiseVariant` | 8 |
| `RedemptionOrder` | `App\Modules\Merchandise\Models\RedemptionOrder` | 6 |
| `StockMovement` | `App\Modules\Merchandise\Models\StockMovement` | 5 |
| `RedemptionOrderItem` | `App\Modules\Merchandise\Models\RedemptionOrderItem` | 2 |
| `MerchandiseImage` | `App\Modules\Merchandise\Models\MerchandiseImage` | **0** |

17 unique files. `MerchandiseImage` is the **only** genuinely zero-reference shim
in the whole plan — `plan.md` claimed six, but re-measurement found the other
five are referenced as string literals in Engagement's arch test (see phase 3).

**Zero persisted state.** None of the seven appears in
`activity_log.subject_type` or any other morph column. No backfill migration.

### Two callers are docblock-only

`database/migrations/2026_08_01_144001_create_merchandise_table.php:11` and
`database/migrations/2026_08_01_150001_create_redemption_orders_table.php:11`
mention the shim FQCN **only inside a docblock comment** ("statuses live in
`App\Models\Merchandise::STATUSES` rather than a DB enum"). Comment-only
rewrites, zero behavioral risk — but they must be rewritten anyway or the
phase-1 guard stays red.

### Cross-module coupling

- `app/Modules/Engagement/Actions/EventParticipationOperations.php` imports `App\Models\GoldTransaction` — so **this phase edits a file inside the Engagement module**, the mirror of phases 2/3.
- Shared with **phase 3**: `tests/Feature/Gold/ReclaimGoldRewardTest.php` (imports `Event` + `EventParticipant` there, `GoldTransaction` here). Hence the phase-3 dependency.

### Plan closure work (only in this phase)

This phase owns the plan-level acceptance, not just its own module:

1. `SHIMMED_MODELS` becomes `[]`. With the array empty, `DeprecatedModelShimArchTest`'s first assertion degenerates to "no `class_alias` exists under `app/Models/` at all" — the plan's headline goal, now permanently guarded.
2. `SHIMMED_MODEL_IMPORT_BASELINE` should also be `[]`, **except** the two intentional data-holder files documented in phase 3 (the ClubMember pilot migration and its test). Either keep those two entries with a comment, or retire the pilot migration and empty the list fully. Decide and record it here.
3. Re-run the full `information_schema` morph-column enumeration once at the end — the plan's Evidence Base checked all 64 `%_type`/`%_class` columns on 2026-08-11; re-confirm none picked up a deleted shim's FQCN in the meantime.
4. Simplify the guard's regex alternation, which becomes an empty `()` group once `SHIMMED_MODELS` is empty — guard against building a pattern that matches everything.

## Related Code Files

- Delete: `app/Models/{GoldTransaction,Merchandise,MerchandiseImage,MerchandiseVariant,RedemptionOrder,RedemptionOrderItem,StockMovement}.php`
- Modify: `tests/Feature/Architecture/MerchandiseModelPlacementArchTest.php` (drop shim-must-exist block)
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` (empty `SHIMMED_MODELS`; handle the empty-alternation regex case; settle the two data-holder baseline entries)
- Modify: `config/migration_debt.php` (final `shared_model_imports` baseline reduction)
- Modify (callers, 17 files):
  - `app/Http/Controllers/Api/GoldTransactionController.php`
  - `app/Modules/Engagement/Actions/EventParticipationOperations.php`
  - `app/Services/GoldService.php`
  - `database/migrations/2026_08_01_144001_create_merchandise_table.php` (docblock)
  - `database/migrations/2026_08_01_150001_create_redemption_orders_table.php` (docblock)
  - `tests/Feature/Gold/GoldServiceTest.php`
  - `tests/Feature/Gold/GoldTransactionOwnershipTest.php`
  - `tests/Feature/Gold/ReclaimGoldRewardTest.php`
  - `tests/Feature/Merchandise/MerchandiseAdminPageTest.php`
  - `tests/Feature/Merchandise/MerchandiseCrudTest.php`
  - `tests/Feature/Merchandise/MerchandiseVariantCrossCampusPolicyTest.php`
  - `tests/Feature/Merchandise/Redemption/RedemptionAccessControlTest.php`
  - `tests/Feature/Merchandise/Redemption/RedemptionCheckoutTest.php`
  - `tests/Feature/Merchandise/Redemption/RedemptionRefundAndStateMachineTest.php`
  - `tests/Feature/Merchandise/Reports/MerchandiseReportCampusScopeTest.php`
  - `tests/Feature/Merchandise/Reports/MerchandiseReportDataTest.php`
  - `tests/Feature/Merchandise/StockServiceTest.php`

## Implementation Steps

1. **Record the pre-sweep baseline:**

   ```bash
   ./scripts/dev.sh artisan test tests/Feature/Merchandise tests/Feature/Gold tests/Feature/Architecture
   ```

2. **Re-measure** callers and confirm morph rows are still zero:

   ```bash
   ./scripts/dev.sh artisan tinker --execute="foreach (['GoldTransaction','Merchandise','MerchandiseImage','MerchandiseVariant','RedemptionOrder','RedemptionOrderItem','StockMovement'] as \$m) { \$n = DB::table('activity_log')->where('subject_type','App\\\\Models\\\\'.\$m)->count(); if (\$n) { echo \$m.' = '.\$n.PHP_EOL; } }"
   ```

   Silence means zero. Anything non-zero needs a backfill migration first.

3. **Sweep the 17 callers**, docblocks included.

4. **Delete the seven shim files.** `app/Models/` now contains no `class_alias`.

5. **Drop the shim-must-exist block** from `MerchandiseModelPlacementArchTest`.

6. **Close out the guard.** Set `SHIMMED_MODELS = []`. Guard the regex build
   against an empty alternation — an empty `()` group would match every
   `App\Models\` reference in the repo and turn the test into noise. Simplest fix
   is an early return when the list is empty:

   ```php
   if (SHIMMED_MODELS === []) {
       expect(true)->toBeTrue('All shims swept — nothing left to guard.');
       return;
   }
   ```

   Then settle `SHIMMED_MODEL_IMPORT_BASELINE` per the decision in Architecture
   item 2, and update the docblock so it describes the finished state.

7. **Final `shared_model_imports` reduction** in `config/migration_debt.php`.

8. **Re-run the full morph-column enumeration** to close plan success criterion 3:

   ```bash
   ./scripts/dev.sh artisan mysql -e "SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND (column_name LIKE '%_type' OR column_name LIKE '%_class');"
   ```

   Then check each candidate column for any of the 30 now-deleted FQCNs. Expect
   zero hits. The five columns that legitimately hold `App\Models\%` values for
   *non-shimmed* models (`payment_applications.source_ref_type`,
   `discount_allocations.source_ref_type`,
   `personal_access_tokens.tokenable_type`, `activity_log.causer_type`,
   `finance_charges.source_type`) stay as they are — out of scope per plan
   Non-Goals.

9. **Verify every `plan.md` success criterion**, not just this phase's, and mark
   the plan complete.

## Success Criteria

- [ ] `grep -rl 'class_alias(' app/Models/` returns nothing
- [ ] `grep -rlP '\bApp\\{1,2}Models\\{1,2}(GoldTransaction|Merchandise|MerchandiseImage|MerchandiseVariant|RedemptionOrder|RedemptionOrderItem|StockMovement)\b' app tests database routes config --include='*.php'` returns nothing
- [ ] All 30 shim files across the plan are gone
- [ ] `SHIMMED_MODELS` is `[]` and the guard handles the empty case without matching everything (prove it: add a scratch `App\Models\User` reference and confirm the test does **not** flag it)
- [ ] `SHIMMED_MODEL_IMPORT_BASELINE` resolved per the recorded decision, docblock updated to match
- [ ] `MerchandiseModelPlacementArchTest` green with the shim-must-exist block removed
- [ ] Full morph-column enumeration returns zero hits for all 30 deleted FQCNs
- [ ] `shared_model_imports` baseline reflects the final real count
- [ ] Every `plan.md` success criterion ticked
- [ ] Touched suites match the step-1 baseline

## Risk Assessment

| Risk | Mitigation |
|---|---|
| Emptying `SHIMMED_MODELS` builds a regex with an empty alternation that matches every `App\Models\` reference, flooding the test with false positives | Step 6 adds an explicit empty-list early return, and a success criterion proves a non-shim reference is not flagged |
| The two intentional data-holder files block the "baseline empty" completion signal | Named decision point in Architecture item 2 — either grandfather them with a comment or retire the pilot migration; do not leave it ambiguous |
| Docblock-only rewrites in old migrations look like editing shipped migrations | They are comments; no `up()`/`down()` behavior changes. Call this out in the PR description |
| Merge conflict with phase 3 on `ReclaimGoldRewardTest.php` | This phase declares a phase-3 dependency for exactly that reason |
| Editing an Engagement-module file in a "Merchandise" phase looks out of scope | Documented above; `EventParticipationOperations.php` imports `App\Models\GoldTransaction` |
| Plan declared done while a morph column still names a deleted shim | Step 8 re-runs the full `information_schema` enumeration rather than trusting the 2026-08-11 measurement |
