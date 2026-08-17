---
phase: 5
title: "Tests"
status: done
priority: P1
effort: "3h"
dependencies: [1, 2, 3]
---

# Phase 5: Tests

## Overview

Unit-test the pure mapper, feature-test the preview/execute endpoints against
real DB fixtures, and unit-test the intake semester contract. No frontend
test required — matches this repo's existing coverage level for import
dialogs (`StudentActionsImport.vue` has no dedicated Vue test either).

<!-- Updated: Validation Session 1 - Phase 5 rewritten to Pest style after
verification found the plan's original PHPUnit-class assumption did not
match this repo's actual sibling test suite. -->

## Requirements

- Functional: cover every skip-reason code, the semester-not-configured hard
  error, and the already-placed row producing a per-row error without
  aborting the batch.
- Non-functional: **Pest, flat-function style** (`it('...', function (): void {...})`),
  matching this repo's actual convention — confirmed against
  `tests/Feature/Academic/Progression/PlacementWorklistTest.php` and
  `PlacementOwnershipTest.php`, not the PHPUnit-class style this plan
  originally assumed. Reuse those files' fixture helpers
  (`grantChangeStudentStatus(User $user, Campus $campus)` and
  `makeWorklistStudent(Campus $campus, array $studentAttributes = [], ?string $studyStage = null, string $enrollmentStatus = 'pending')`,
  both defined at file scope in `PlacementWorklistTest.php`) rather than
  re-deriving the fixture shape from memory.

## Related Code Files

- Create: `tests/Unit/Academic/Progression/Support/BulkEgcPlacementRowMapperTest.php`
- Create: `tests/Unit/Admissions/EloquentIntakeSemesterReaderTest.php`
- Create: `tests/Feature/Academic/Progression/BulkEgcPlacementImportTest.php`
- Reference (fixture source, copy the two helper functions into the new
  feature test file — Pest has no cross-file helper import, each file
  declares its own): `tests/Feature/Academic/Progression/PlacementWorklistTest.php:29-70`

## Implementation Steps

1. Mapper unit tests (`BulkEgcPlacementRowMapperTest`, Pest style):
   - `it('finds Student ID and Level columns regardless of surrounding columns', function (): void {...})`
   - `it('excludes GCS levels', function (): void {...})` — `GCS5`, `GCS`, `gcs1` → `level_excluded_gcs`.
   - `it('treats EGC6 and unrecognized values as unmapped', function (): void {...})` — `EGC6`, `x` → `level_unmapped`, distinct from `level_missing`.
   - `it('maps Foundation and EGC1-5 case-insensitively', function (): void {...})` — `Foundation`/`foundation`/`FOUNDATION` → `0`, `EGC1`..`EGC5` → `1`..`5`.
   - `it('reports missing student id and unknown student id separately', function (): void {...})`.

2. `EloquentIntakeSemesterReaderTest` (Pest style, `uses(RefreshDatabase::class)`):
   - `it('resolves the semester id from the configured CRM intake code', function (): void {...})` — seed a `Semester`, set the intake mapping the same way `CrmMappingController::store()` does (via `CrmMappingSettings`), assert `IntakeSemesterReader::currentIntakeSemesterId()` matches.
   - `it('returns null when no intake mapping is configured', function (): void {...})`.

3. Feature test (`BulkEgcPlacementImportTest`, Pest style, `uses(RefreshDatabase::class)`,
   `use function Pest\Laravel\{actingAs, post};`):
   - Copy `grantChangeStudentStatus()` and `makeWorklistStudent()` from
     `PlacementWorklistTest.php` into this file (same pattern that file
     itself uses — file-local helper functions, not a shared trait).
   - `beforeEach`: seed a `Campus`, a `Semester`, configure the intake
     mapping to point at that semester's code, create several
     `makeWorklistStudent($campus)` students (unplaced: `studyStage: null`)
     plus one already-placed student (`studyStage: 'intake_pre_uni_gc'`) to
     exercise the re-run/error path.
   - Build an in-memory CSV (temp file via `UploadedFile::fake()->createWithContent()`
     or a real temp CSV written with `Storage::fake()`) matching the real
     header shape (extra unrelated columns + `Student ID`/`Level` present),
     rows: one `Foundation`, one `EGC3`, one `GCS5`, one `EGC6`, one
     blank-Level, one unknown `Student ID`, one referencing the
     already-placed student with `EGC2`.
   - `it('previews without writing any placement data', function (): void {...})`
     — `post(route('students.placement-worklist.bulk-import.preview'), [...])`,
     assert summary counts (2 mappable-new + 1 already-placed-but-mappable =
     3 `valid` at preview time, 4 skipped across the 4 reason codes), and
     assert zero rows in `student_action_logs`/`academic_progression_events`
     after the call.
   - `it('executes only the valid, not-yet-placed rows', function (): void {...})`
     — preview first to get `preview_token`, then
     `post(route('students.placement-worklist.bulk-import.execute'), [...])`,
     assert exactly 2 new `StudentActionLog` (`STUDENT_ENROLLMENT_NE`), 2
     new `AcademicProgressionEvent` (`PLACEMENT_INITIALIZED`), the two
     `ProgramEnrollment.egc_current_level` values are `0` and `3`, and the
     already-placed student's row comes back `status: 'error'` in the
     response body (not a 500, not silently dropped).
   - `it('rejects both routes without change_student_status', function (): void {...})`
     — `actingAs` a user without the granted permission, assert 403 on both
     preview and execute.
   - `it('fails fast when the intake semester is not configured', function (): void {...})`
     — don't call the CRM mapping setter in this test's setup; assert
     preview and execute both return the configured-error response and
     create zero rows.

## Success Criteria

- [x] All new tests pass in isolation
      (`./scripts/dev.sh artisan test --filter=BulkEgcPlacement`) and
      alongside `PlacementWorklistTest.php`/`PlacementOwnershipTest.php`
      without new failures.
- [x] No test depends on wall-clock "current semester" logic — the intake
      semester is set explicitly per test via the CRM mapping settings, same
      as `makeWorklistStudent()` does for `intake_semester_id`.
- [x] New feature test file duplicates zero fixture logic that already
      exists correctly in `PlacementWorklistTest.php` — it copies the two
      helper functions verbatim rather than inventing new ones.

## Risk Assessment

The existing `tests/Feature/Academic` suite has known pre-existing failures
unrelated to this change (see prior session notes on Academic
attendance/placement fixtures). Run the new test file in isolation first to
avoid mistaking an unrelated pre-existing red for a regression introduced
here.
