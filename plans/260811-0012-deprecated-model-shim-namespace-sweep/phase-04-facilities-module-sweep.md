---
phase: 4
title: "Facilities module sweep"
status: pending
priority: P1
effort: "2-3h"
dependencies: [1]
---

# Phase 4: Facilities module sweep

## Overview

Sweep the 31 callers of Facilities' four shims — the largest caller set and the
largest morph blast radius (123 rows) — backfill those rows, delete the four
shims, and retarget the boundary assertions that reference them. Touches
`AppServiceProvider::configureMorphMap()`, the single highest-attention file in
the whole plan.

## Requirements

- Functional: no file references `App\Models\{Building, Room, RoomBooking, RoomBookingAction}`.
- Functional: all four shim files deleted.
- Functional: `activity_log.subject_type` holds zero rows for those four FQCNs.
- Functional: `$activity->subject` still resolves for the 123 previously-affected rows.
- Functional: `FacilitiesDeliveryBoundaryArchTest`'s negative assertions still have teeth after the sweep (retargeted, not deleted).
- Non-functional: import rewrites, deletions, and one backfill migration only. No change to morph-map *behavior*.

## Architecture

| Shim | Canonical FQCN | Callers | Morph rows |
|---|---|---|---|
| `Room` | `App\Modules\Facilities\Models\Room` | 27 | 87 |
| `RoomBooking` | `App\Modules\Facilities\Models\RoomBooking` | 8 | 31 |
| `Building` | `App\Modules\Facilities\Models\Building` | 5 | 5 |
| `RoomBookingAction` | `App\Modules\Facilities\Models\RoomBookingAction` | 1 | 0 |

31 unique files, 123 morph rows — the heaviest phase on both axes.

### `AppServiceProvider` is the sensitive file

`app/Providers/AppServiceProvider.php:15` imports `App\Models\RoomBooking`, and
`configureMorphMap()` uses its constants:

```php
Relation::morphMap([
    RoomBooking::BOOKED_BY_USER => User::class,
    RoomBooking::BOOKED_BY_STUDENT => Student::class,
    RoomBooking::BOOKED_BY_LECTURER => User::class,
    RoomBooking::BOOKED_BY_LECTURE => Lecture::class,
    'course' => CourseOffering::class,
    // ...
]);
```

Only the **import line** changes. The constants resolve to the same string
values through either namespace, and the morph map's *keys* are those constant
values, not FQCNs — so the map's runtime behavior is unchanged. Verify by
asserting the resolved map is byte-identical before and after (step 5).

Note the morph map does **not** register any of the four shimmed FQCNs, which is
exactly why the 123 persisted rows resolve today only because `class_alias`
exists. That is the whole reason this phase needs a data migration.

### Boundary assertions must be retargeted, not deleted

`tests/Feature/Architecture/FacilitiesDeliveryBoundaryArchTest.php` asserts at
lines 50, 53, and 63 that three Academic/Delivery files do **not** contain
`App\Models\Room` — the point being that Delivery must reach rooms through
`SpaceReservationContract` / `SpaceReferenceReader`, never the model.

Rewrite each to `App\Modules\Facilities\Models\Room`. Deleting them (the
treatment phase 3 applies to its dead FQCN list) would silently drop the
boundary: after the sweep someone could import the canonical `Room` into
`CreateExamRoomSlotAction` and nothing would catch it.

`FacilitiesModelPlacementArchTest.php:34` carries the usual shim-must-exist
block; drop that one per the phase-2 decision.

### Factories are in scope

`plan.md` Architecture step 3 flags factory resolution. Two factories import the
`Room` shim: `database/factories/ClassSessionFactory.php` and
`database/factories/ExamRoomSlotFactory.php`. Both are plain `use` imports, not
`$model` string resolution, so they are ordinary rewrites — but re-check for the
`$model = 'App\Models\Room'` string form before assuming.

### No cross-phase file overlap

Unlike phases 2/3/5, Facilities' 31 files are genuinely disjoint from every
other phase's set. This phase depends only on phase 1 and can run in parallel
with phase 5 if desired.

## Related Code Files

- Create: `database/migrations/<timestamp>_backfill_facilities_shimmed_morph_subject_types.php`
- Delete: `app/Models/{Building,Room,RoomBooking,RoomBookingAction}.php`
- Modify: `app/Providers/AppServiceProvider.php` (import line only — see above)
- Modify: `tests/Feature/Architecture/FacilitiesDeliveryBoundaryArchTest.php` (retarget 3 negative assertions)
- Modify: `tests/Feature/Architecture/FacilitiesModelPlacementArchTest.php` (drop shim-must-exist block)
- Modify: `tests/Feature/Architecture/DeprecatedModelShimArchTest.php` (shrink both lists)
- Modify: `config/migration_debt.php` (lower `shared_model_imports` baseline)
- Modify (remaining callers):
  - `app/Http/Requests/GenerateClassSessionsRequest.php`
  - `app/Http/Requests/StoreRoomBookingRequest.php`
  - `app/Http/Requests/UpdateRoomBookingRequest.php`
  - `app/Modules/Academic/Delivery/Queries/GetClassSessionFormOptionsQuery.php`
  - `app/Modules/Academic/Delivery/Support/LecturerTimetableService.php`
  - `app/Modules/Academic/Support/CampusBuildingCountReader.php`
  - `app/Services/AdminScheduleService.php`
  - `app/Services/DashboardStatsService.php`
  - `database/factories/ClassSessionFactory.php`
  - `database/factories/ExamRoomSlotFactory.php`
  - `database/seeders/InitialSetup/InstitutionSetupSeeder.php`
  - `tests/Feature/Academic/ExamResit/AssignExamResitInvigilatorActionTest.php`
  - `tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php`
  - `tests/Feature/Academic/ExamResit/CreateExamResitSessionActionTest.php`
  - `tests/Feature/Academic/ExamResit/CreateExamRoomSlotActionTest.php`
  - `tests/Feature/Academic/ExamResit/ExamScheduleControllerTest.php`
  - `tests/Feature/Academic/ExamResit/ListExamResitAttemptsQueryTest.php`
  - `tests/Feature/Academic/ExamResit/ListExamRoomSlotsQueryTest.php`
  - `tests/Feature/Academic/ExamResit/ScheduleExamResitAttemptActionTest.php`
  - `tests/Feature/Academic/RemediateEgcAttendanceFailuresCommandTest.php`
  - `tests/Feature/Api/V1/Lecturer/LecturerInvigilationTimetableTest.php`
  - `tests/Feature/Api/V1/Student/StudentExamResitTimetableTest.php`
  - `tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php`
  - `tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php`
  - `tests/Feature/Facilities/RoomAvailabilityExamBlockTest.php`
  - `tests/Feature/Facilities/RoomBookingExamConflictTest.php`
  - `tests/Feature/Facilities/RoomBookingSeriesTest.php`
  - `tests/Feature/Facilities/SpaceReservationContractTest.php`
  - `tests/Feature/Finance/Operations/exam_resit_due_helpers.php`

## Implementation Steps

1. **Record the pre-sweep baseline** (this phase reaches into Academic, whose
   suite has known pre-existing failures — see the plan's Risks table):

   ```bash
   ./scripts/dev.sh artisan test tests/Feature/Facilities tests/Feature/Academic/ExamResit tests/Feature/CourseOffering tests/Feature/Architecture
   ```

2. **Capture the resolved morph map before the change**, to prove step 4 is
   behavior-neutral:

   ```bash
   ./scripts/dev.sh artisan tinker --execute="\$m = Illuminate\Database\Eloquent\Relations\Relation::\$morphMap; ksort(\$m); echo json_encode(\$m, JSON_PRETTY_PRINT).PHP_EOL;" > /tmp/morphmap-before.json
   ```

3. **Re-measure morph rows** (123 at plan time; they accrue):

   ```bash
   ./scripts/dev.sh artisan tinker --execute="foreach (['Building','Room','RoomBooking','RoomBookingAction'] as \$m) { \$n = DB::table('activity_log')->where('subject_type','App\\\\Models\\\\'.\$m)->count(); echo \$m.' = '.\$n.PHP_EOL; }"
   ```

4. **Sweep the callers** — `App\Models\X` → `App\Modules\Facilities\Models\X`
   across all 31 files. Do `AppServiceProvider.php` deliberately and review that
   hunk on its own.

5. **Re-capture the morph map and diff it.** Must be byte-identical:

   ```bash
   ./scripts/dev.sh artisan tinker --execute="\$m = Illuminate\Database\Eloquent\Relations\Relation::\$morphMap; ksort(\$m); echo json_encode(\$m, JSON_PRETTY_PRINT).PHP_EOL;" > /tmp/morphmap-after.json
   diff /tmp/morphmap-before.json /tmp/morphmap-after.json && echo "morph map unchanged"
   ```

6. **Write the backfill migration** for all four FQCNs (one migration, four
   `UPDATE`s), copying the `class_exists` guard pattern from
   `2026_08_11_021157_backfill_clubmember_shimmed_morph_subject_type.php`. Run it
   after step 4, in the same PR.

7. **Delete the four shim files.**

8. **Retarget** the three `FacilitiesDeliveryBoundaryArchTest` negative
   assertions to the canonical FQCN, and drop the shim-must-exist block from
   `FacilitiesModelPlacementArchTest`.

9. **Shrink the phase-1 guard and the debt baseline** (phase 2 steps 6-7).
   `SHIMMED_MODELS` drops by 4.

10. **Spot-check subject resolution on real data** for all four models:

    ```bash
    ./scripts/dev.sh artisan tinker --execute="\$r=0;\$n=0; foreach (Spatie\Activitylog\Models\Activity::where('subject_type','like','App\\\\Modules\\\\Facilities\\\\Models\\\\%')->get() as \$a) { \$a->subject ? \$r++ : \$n++; } echo 'resolved='.\$r.' null='.\$n.PHP_EOL;"
    ```

    Record the `null` count. Any null is a pre-existing orphan (subject row
    hard-deleted) — confirm by checking the id against the table, the same way
    phase 1 confirmed `ClubMember subject_id=3`. Nulls are not caused by the
    migration, which only rewrites the type string.

## Success Criteria

- [ ] `grep -rlP '\bApp\\{1,2}Models\\{1,2}(Building|Room|RoomBooking|RoomBookingAction)\b' app tests database routes config --include='*.php'` returns nothing
- [ ] All four shim files deleted; `SHIMMED_MODELS` shrunk by 4
- [ ] `activity_log.subject_type` holds zero rows for the four old FQCNs
- [ ] Resolved morph map byte-identical before vs after (step 5 diff clean)
- [ ] Facilities activity resolves its subject; every remaining null traced to a hard-deleted subject row
- [ ] Backfill `down()` reverses on a test DB and carries the `class_exists` guard
- [ ] `FacilitiesDeliveryBoundaryArchTest` negative assertions retargeted to `App\Modules\Facilities\Models\Room` and still failing when a violation is introduced (prove it once with a scratch edit)
- [ ] `shared_model_imports` baseline lowered
- [ ] Touched suites match the step-1 baseline

## Risk Assessment

| Risk | Mitigation |
|---|---|
| Touching `configureMorphMap()` changes polymorphic resolution in production | Import line only; step 2/5 diff the resolved map and require byte-identical output |
| Retargeting the boundary assertions is skipped, silently dropping the Delivery-must-not-touch-Room boundary | Called out as its own step 8 and its own success criterion, including a prove-it-fails check |
| 123 morph rows is the largest blast radius; a missed reference breaks `$activity->subject` in production | Deletion is the last step, after callers are clean; step 3 re-measures immediately before; step 10 verifies resolution on real data |
| A null subject after migration is mistaken for a regression | Phase 1 established the pattern: nulls are hard-deleted subject rows and resolve to null identically before the migration. Trace each id before accepting |
| `Room` (27 callers) makes the largest diff in the plan | Reviewer greps rather than reads; split `Room` into its own PR if preferred |
| Factory `$model` string resolution missed by a `use`-only grep | Step 3's regex uses `\\{1,2}` and covers string literals; factories explicitly listed |
| Academic suite pre-existing failures blamed on this phase | Step 1 records the baseline first |
