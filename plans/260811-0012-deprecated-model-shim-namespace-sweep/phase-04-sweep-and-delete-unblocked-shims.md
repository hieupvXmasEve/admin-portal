---
phase: 4
title: "Sweep and delete the 20 unblocked shims"
status: pending
priority: P1
effort: "4-6h across 3 PRs"
dependencies: [1, 2, 3]
---

# Phase 4: Sweep and delete the 20 unblocked shims

<!-- Rewritten after red-team session 1: was "Facilities module sweep". Restructured around deletable-vs-blocked because only 20 of 30 shims can go -->

## Overview

Repoint callers and delete the **20** shims that no cross-module caller depends
on. The other 10 stay — see phase 5. Split into three PRs along module lines
purely for reviewability; all three are the same mechanical operation.

Runs only after phase 2's backfill is deployed and verified in production, and
after phase 3 has hardened the guard that proves a shim is safe to delete.

## Requirements

- Functional: the 20 unblocked shims are deleted; `class_exists('App\Models\<Name>')` is false for each.
- Functional: no file references those 20 FQCNs.
- Functional: the 10 blocked shims and their 12 cross-module callers are untouched.
- Functional: each affected placement arch test inverted per V3-a, retaining the duplicate-model assertion.
- Non-functional: import rewrites and 20 file deletions. No migration, no config, no logic change.
- Non-functional: **no `config/migration_debt.php` edit** — see Architecture.

## Architecture

### The 20 deletable shims

| Module | Deletable | Blocked (stay — phase 5) |
|---|---|---|
| Facilities | `RoomBooking`, `RoomBookingAction` | `Room`, `Building` |
| Engagement | `Club`, `ClubMember`, `ClubMemberRoleHistory`, `Event`, `EventParticipant`, `Form`, `FormResultVisibility`, `FormSection`, `FormSurvey`, `FormVersion`, `QueryAssignment`, `QueryTopic` | `FormResponse`, `FormTarget`, `QueryReply`, `QueryTicket` |
| Merchandise | `Merchandise`, `MerchandiseImage`, `MerchandiseVariant`, `RedemptionOrder`, `RedemptionOrderItem`, `StockMovement` | `GoldTransaction` |
| Upload | — none — | `ApplicationDocument`, `ApplicationDocumentType`, `UploadRecord` |

**Upload contributes nothing to this phase.** All three of its shims are blocked,
which is why the original "Upload first, it is the cleanest" phase no longer
exists.

### Why 10 shims are blocked

`cross_context_concrete_imports` in `config/migration_debt.php:75-81` is
`baseline: 0, mode: exact` — zero tolerance, currently sitting exactly at 0. Its
detector (`app/Support/MigrationDebt/MigrationDebtInventory.php:349-388`) fires
when a file namespaced `App\Modules\A\` names `App\Modules\B\` on a non-comment
line. `MigrationDebtGuard.php:109` turns any non-zero into an error.

`App\Models\X` is namespace-neutral, so a cross-module read through a shim is
invisible to that rule. Rewriting it to the canonical FQCN makes it visible and
trips the guard. **The shims are load-bearing for that boundary rule** — that is
the real reason `260809-1557` left them behind.

12 files would trip it, keeping 10 shims alive. They are phase 5's problem, not
this phase's:

```
Academic   -> Facilities    GetClassSessionFormOptionsQuery, LecturerTimetableService (Room)
Academic   -> Facilities    CampusBuildingCountReader (Building)
Academic   -> Engagement    GetCourseOfferingSurveyQuery (FormTarget)
Admissions -> Upload        UpsertCrmApplicationAction, IngestionController,
                            GetApplicantDocumentChecklistQuery, ListApplicationsQuery
Engagement -> Upload        FormResponse, QueryReply (UploadRecord)
Engagement -> Merchandise   EventParticipationOperations (GoldTransaction)
Upload     -> Engagement    UploadRecord (FormResponse, QueryReply, QueryTicket)
```

### Mixed-namespace files are expected

40 files reference a deletable shim, and **19 of them also reference a blocked
one**. Those get a *partial* sweep: rewrite the deletable names, leave the
blocked names as `App\Models\*`. The result is files holding both namespaces at
once — for example `tests/Feature/Form/QueryTicketWorkflowTest.php` ends up with
canonical `Form`/`FormVersion` imports alongside `App\Models\FormResponse`,
`FormTarget`, `QueryReply`, `QueryTicket`, `UploadRecord`.

This looks wrong and is correct. Do not "tidy" a blocked name into canonical
form — that is exactly the change that trips the boundary guard. The phase-3
guard's importer baseline is what keeps the distinction honest.

### No migration-debt baseline edits (V2-c withdrawn)

Earlier revisions told every phase to lower `shared_model_imports` in
`config/migration_debt.php`. That instruction was wrong three ways:

1. `MigrationDebtGuard.php:100-107` requires the config baseline to **equal** the
   hardcoded `MigrationDebtContract::BASELINE_CEILINGS['shared_model_imports']`
   (= 289, `MigrationDebtContract.php:79`). Editing config alone *creates* an error.
2. The metric is **already failing**: live count 401 vs 289. It is a pre-existing
   red gate, not a drift risk this plan must protect.
3. `mode: max` already tolerates a decrease, and the rule's scope is
   `app/Modules/**` only — not "`app/` + `routes/`" as an earlier revision
   claimed — so most of this phase's rewrites do not move the number at all.

**Corrected decision (V3-b, supersedes V2-c): touch neither
`config/migration_debt.php` nor `MigrationDebtContract`.** Record the delta the
sweep produces; the sweep can only move 401 downward, toward the ceiling.

### Placement arch tests: invert, do not delete (V3-a)

Per phase 3, for each swept model in a placement arch test change
`expect(file_exists($legacyPath))->toBeTrue(...)` to `->toBeFalse(...)`, drop the
`toContain('class_alias(')` line, and **keep**
`not->toContain("class {$model} extends")` — the duplicate-model guard the
phase-1 test does not replace.

Tests holding a mix of swept and blocked models keep both branches:
`FacilitiesModelPlacementArchTest` asserts `RoomBooking`/`RoomBookingAction` are
gone while `Room`/`Building` must remain. Same for the three Engagement tests and
`MerchandiseModelPlacementArchTest`.

`EngagementQueryTicketModelPlacementArchTest.php:46-52` holds a 16-FQCN negative
list; remove only the 12 swept entries, keep the 4 blocked ones.

`FacilitiesDeliveryBoundaryArchTest.php:49,52,63` asserts Delivery files do not
contain `App\Models\Room`. `Room` is **blocked**, so those assertions stay exactly
as they are this phase — the earlier "retarget them" instruction (V2-b) does not
apply until `Room` is actually swept in a future boundary-refactor plan.

## Related Code Files

**PR 1 — Merchandise (6 shims, lowest risk: 0 morph rows, no cross-module callers among them)**
- Delete: `app/Models/{Merchandise,MerchandiseImage,MerchandiseVariant,RedemptionOrder,RedemptionOrderItem,StockMovement}.php`
- Modify: `tests/Feature/Architecture/MerchandiseModelPlacementArchTest.php` (invert 6, keep `GoldTransaction`)
- Modify callers: `app/Modules/Engagement/Actions/EventParticipationOperations.php` (partial — keeps `GoldTransaction`), `tests/Feature/Merchandise/*` (10 files, 4 partial), `tests/Feature/Gold/*` (partial)

**PR 2 — Engagement (12 shims)**
- Delete: `app/Models/{Club,ClubMember,ClubMemberRoleHistory,Event,EventParticipant,Form,FormResultVisibility,FormSection,FormSurvey,FormVersion,QueryAssignment,QueryTopic}.php`
- Modify: `tests/Feature/Architecture/EngagementClubEventModelPlacementArchTest.php`, `EngagementFormModelPlacementArchTest.php`, `EngagementQueryTicketModelPlacementArchTest.php` (invert swept, keep the 4 blocked)
- Modify callers: `app/Services/{NotificationService,QRCodeService}.php`, `app/Services/V1/Student/{TimetableEventQuery,TimetableService}.php`, `database/seeders/InitialSetup/EventSeeder.php`, `tests/Feature/Form/*` (5, all partial), `tests/Feature/Api/V1/Student/*` (3, partial), `tests/Feature/Engagement/*`, `tests/Unit/Notification/EventNotificationServiceTest.php`, `tests/Feature/Lecture/LecturerGpaReportTest.php` (partial)
- **Do not modify:** `database/migrations/2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php` — the old FQCN is its `WHERE` value, not an import. Rewriting it makes the migration a no-op for anyone who has not run it.
- **Do modify:** `tests/Feature/Engagement/ClubMemberShimMorphBackfillMigrationTest.php` — its `reverses the backfill on down()` case breaks once the `ClubMember` shim is gone. See step 5.

**PR 3 — Facilities (2 shims)**
- Delete: `app/Models/{RoomBooking,RoomBookingAction}.php`
- Modify: `tests/Feature/Architecture/FacilitiesModelPlacementArchTest.php` (invert 2, keep `Room`/`Building`)
- Modify: `app/Providers/AppServiceProvider.php:15` — the `RoomBooking` import. Behavior-neutral (the morph map's keys are `RoomBooking::BOOKED_BY_*` constant *values*, identical through either name, and both names are the same class object) but review the hunk on its own.
- Modify callers: `app/Http/Requests/{StoreRoomBookingRequest,UpdateRoomBookingRequest}.php`, `tests/Feature/Facilities/*` (4, all partial — keep `Room`/`Building`)

**All three PRs**
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` — remove the PR's names from `SHIMMED_MODELS` only. `ALL_MIGRATED_MODELS` and the importer baseline both stay at 30 / 93 minus the paths actually cleaned.

## Implementation Steps

1. **Gate check.** Confirm phase 2's migration is applied in production and phase
   3's guard fixes are merged. Do not start otherwise — the guard is what proves a
   deletion is safe, and the backfill is what makes it safe.

2. **Record the pre-sweep baseline** per PR:

   ```bash
   ./scripts/dev.sh artisan test tests/Feature/Merchandise tests/Feature/Gold tests/Feature/Architecture   # PR 1
   ./scripts/dev.sh artisan test tests/Feature/Form tests/Feature/Engagement tests/Feature/Api/V1/Student tests/Feature/Architecture   # PR 2
   ./scripts/dev.sh artisan test tests/Feature/Facilities tests/Feature/Architecture   # PR 3
   ```

3. **Re-measure the PR's caller set** — including `app/Models/`, which phase 3's
   fix makes visible:

   ```bash
   for m in <this PR's models>; do
     echo "=== $m"
     grep -rlP "\bApp\\\\{1,2}Models\\\\{1,2}${m}\b" app tests database routes config --include='*.php'
   done
   ```

   Note there is no `grep -v app/Models/` — that exclusion is what hid
   `app/Models/Answer.php` for `UploadRecord`. `Answer.php` is not in this
   phase's scope (`UploadRecord` is blocked), but the same class of miss could
   apply to any model, so scan honestly.

4. **Also grep for runtime-assembled FQCNs once**, before PR 1. The repo already
   contains this idiom for a non-shimmed model, so a literal-text detector is not
   sufficient on its own:

   ```bash
   grep -rn "'App', *'Models'\|\"App\\\\\\\\Models\\\\\\\\\" *\." app tests database routes config --include='*.php'
   ```

   Known precedent: `app/Modules/Academic/routes/web.php:66` —
   `Route::model('courseOffering', implode('\\', ['App', 'Models', 'CourseOffering']));`.
   `CourseOffering` is not a shimmed model, so it is out of scope; the point is
   that the idiom exists here and could have been copied.

5. **In PR 2, fix the pilot migration's test in the same commit as the
   `ClubMember` deletion.** `tests/Feature/Engagement/ClubMemberShimMorphBackfillMigrationTest.php:72-88`
   asserts `down()` reverts rows. Phase 1's `down()` self-disables via
   `class_exists(OLD_FQCN)`, so deleting the shim makes it a no-op and that case
   fails. Replace the assertion with one that documents the post-deletion
   contract: `down()` is a no-op once the shim is gone. Keep the `up()` and
   leave-others-alone cases as they are.

6. **Sweep, delete, invert** per PR. On partial-sweep files, change only the
   deletable names.

7. **Prove the deletion behaviorally**, not just by grep — phase 3 added the
   assertion, so this should already be covered, but verify in-process:

   ```bash
   ./scripts/dev.sh artisan tinker --execute="foreach ([<this PR's models>] as \$m) { echo \$m.' => '.(class_exists('App\\\\Models\\\\'.\$m) ? 'STILL RESOLVES (BAD)' : 'gone') .PHP_EOL; }"
   ```

   Run `./scripts/dev.sh composer dump-autoload` before this check. Composer's
   classmap still points at a deleted shim file until the map is regenerated,
   so `class_exists` can read false for the wrong reason (stale reference
   dropped by autoload failure, not by an absent name) — flagged by phase 3's
   review (M2).

8. **Shrink `SHIMMED_MODELS`** only. Leave `ALL_MIGRATED_MODELS`,
   `config/migration_debt.php`, and `MigrationDebtContract` alone.

9. **Compare each PR's suites against its step-2 baseline.** For PR 1, run the
   three campus-scoped tests individually rather than relying on the aggregate —
   `MerchandiseVariantCrossCampusPolicyTest`, `RedemptionAccessControlTest`,
   `MerchandiseReportCampusScopeTest` — so an accidental edit inside a
   campus-scoping test is not masked by the known pre-existing failures.

## Success Criteria

- [ ] Phase 2 migration verified applied in production before any deletion
- [ ] 20 shim files deleted; `class_exists('App\Models\<Name>')` false for all 20, checked in-process
- [ ] The 10 blocked shims still present; the 12 cross-module callers byte-identical
- [ ] `cross_context_concrete_imports` still `0` — `./scripts/dev.sh artisan migration-debt:inventory` shows `pass`
- [ ] `config/migration_debt.php` and `MigrationDebtContract` unchanged; the `shared_model_imports` delta recorded, not edited
- [ ] Every affected placement arch test inverted per V3-a, retaining `not->toContain("class X extends")`
- [ ] `EngagementQueryTicketModelPlacementArchTest` negative list retains its 4 blocked entries
- [ ] `FacilitiesDeliveryBoundaryArchTest` unchanged (`Room` is blocked)
- [ ] Pilot migration file unmodified; its test's `down()` case updated to the post-deletion contract
- [ ] `SHIMMED_MODELS` down to 10; `ALL_MIGRATED_MODELS` still 30
- [ ] Each PR's suites match its own pre-sweep baseline; the 3 campus-scope tests pass individually
- [ ] Diff contains only import rewrites, 20 deletions, and arch-test inversions

## Risk Assessment

| Risk | Mitigation |
|---|---|
| A "tidy-up" rewrites a blocked name to canonical and trips the zero-tolerance boundary guard | Explicit success criterion re-runs `migration-debt:inventory` and requires `cross_context_concrete_imports = 0`; the 12 files are named and must stay byte-identical |
| Mixed-namespace files read as half-finished work and get "fixed" later | Documented in Architecture as intended; the phase-3 importer baseline records exactly which references are deliberate |
| A caller hidden inside `app/Models/` is missed, producing a production fatal | Phase 3 removes the directory-wide exclusion; step 3 scans without it; step 7 checks `class_exists` in-process rather than trusting grep |
| A runtime-assembled FQCN survives every grep | Step 4 sweeps for the known idiom once, and step 7's behavioral check catches a resolvable name regardless of how it was written |
| Deleting `ClubMember` ships a red test | Step 5 fixes it in the same commit; the file is in this phase's modify list |
| `AppServiceProvider` edit changes polymorphic resolution | Import line only; both names are the same class object and the map keys are constant values, not FQCNs. Reviewed as its own hunk |
| Campus-scope regressions masked by the 5 known Architecture failures | Step 9 runs the three campus-scoped tests individually |
| 20 deletions in one diff hides a real edit | Three PRs, grouped by module; reviewer greps rather than reads |
