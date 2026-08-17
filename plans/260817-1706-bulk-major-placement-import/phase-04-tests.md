---
phase: 4
title: "Tests"
status: pending
priority: P1
effort: "3h"
dependencies: [1, 2]
---

# Phase 4: Tests

## Overview

Unit-test the pure mapper (including the IELTS-test-type filter and
campus scoping), feature-test the preview/execute endpoints against real DB
fixtures. Pest, flat-function style, matching this repo's actual convention
(confirmed via `tests/Feature/Academic/Progression/PlacementWorklistTest.php`,
and this session's own `BulkEgcPlacementImportTest.php`).

## Requirements

- Functional: cover every skip-reason code (including
  `below_threshold_excluded`), cross-campus exclusion, the IELTS-vs-Other
  test-type filter, the threshold boundary, the semester-not-configured hard
  error (via a *valid* preview token, not a token-mismatch 422), and the
  already-placed row producing a per-row error without aborting the batch.
  <!-- Updated: Validation Session 1 - "score-threshold fallback branch" is
  now "threshold boundary that excludes, not falls back" -->
  No test needs to assert an EGC-fallback placement outcome — this importer
  never produces one; the below-threshold case is fully covered by asserting
  the row is skipped and nothing is written for it.
- Non-functional: **must not redeclare any top-level function name already
  used elsewhere in `tests/`.** This repo's Pest test files share one PHP
  global namespace — a duplicate `function` name across two files crashes
  the *entire* combined test run with exit code 255 and zero output (hit
  and fixed once already this session in `BulkEgcPlacementImportTest.php`).
  Before writing this phase's feature test, grep `tests/` for the exact
  helper names you're about to declare. If reusing the
  `grantChangeStudentStatus`/`makeWorklistStudent` shape, suffix them
  uniquely for this file (e.g. `grantChangeStudentStatusForBulkMajorImport`,
  `makeBulkMajorWorklistStudent`) — do not copy the plain names verbatim.

## Related Code Files

- Create: `tests/Unit/Academic/Progression/Support/BulkMajorPlacementRowMapperTest.php`
- Create: `tests/Feature/Academic/Progression/BulkMajorPlacementImportTest.php`
- Reference (fixture source, copy-and-rename the helper functions rather
  than importing them — Pest has no cross-file helper import):
  `tests/Feature/Academic/Progression/PlacementWorklistTest.php:29-70`,
  `tests/Feature/Academic/Progression/BulkEgcPlacementImportTest.php` (for
  the campus-scoped-student + intake-semester-config beforeEach shape)

## Implementation Steps

1. Mapper unit tests (`BulkMajorPlacementRowMapperTest`, Pest style,
   `uses(RefreshDatabase::class)` since the mapper does real DB reads):
   - `it('finds the Student ID column regardless of other columns', function (): void {...})`.
   - `it('reports missing headers when Student ID is absent', function (): void {...})`.
   - `it('excludes a student found only in another campus', function (): void {...})` —
     create the student in campus A, map with campus B's id,
     assert `student_not_found` (same test shape as the EGC mapper's
     equivalent cross-campus test).
   - `it('skips a student whose only application is not IELTS', function (): void {...})` —
     `StudentApplication` with `english_test_type: 'Other'`, assert
     `application_score_missing`.
   - `it('skips a student whose IELTS application has a null overall score', function (): void {...})`.
   - `it('picks the most recent qualifying IELTS application when multiple exist', function (): void {...})` —
     two `StudentApplication` rows, older with a lower score, newer with a
     different score; assert the mapper picks the newer one by
     `created_at`.
   - `it('marks the row valid at or above the 5.5 threshold and excludes it below', function (): void {...})` —
     parametrize `5.5` → `status: 'valid'`, `5.4` → `status: 'skip'` +
     `skip_reason: 'below_threshold_excluded'` (boundary case, not just an
     obviously-high/obviously-low pair). <!-- Updated: Validation Session 1 -
     was "predicts major/egc_fallback"; below-threshold is now an exclusion,
     not a predicted outcome -->
   - `it('sets overall_score on a below_threshold_excluded row so staff can see the actual number', function (): void {...})`.

2. Feature test (`BulkMajorPlacementImportTest`, Pest style,
   `uses(RefreshDatabase::class)`, `use function Pest\Laravel\{actingAs, post};`):
   - `beforeEach`: seed a `Campus`, a `Semester`, configure the intake
     mapping (`app(CrmMappingSettings::class)->setIntakeCode(...)`), create
     several unplaced students each with a `StudentApplication`
     (`english_test_type: 'IELTS'`, varying `overall` scores spanning above
     and below 5.5), one student with an `Other`-type application only, one
     student with no application at all, one already-placed student with a
     qualifying IELTS application (for the re-run/error path).
   - Build the CSV with `UploadedFile::fake()->createWithContent()` — header
     row + `Student ID` column only.
   - `it('previews without writing any placement data', function (): void {...})`
     — assert summary counts split correctly across valid / each skip
     reason, and assert zero rows in `ielts_certificates`,
     `student_action_logs`, `academic_progression_events` after the call.
   - `it('executes only the at-or-above-threshold, not-yet-placed rows, always into Major', function (): void {...})`
     <!-- Updated: Validation Session 1 - was "placing Major and EGC-fallback
     correctly"; below-threshold rows are never sent to execute at all now -->
     — preview first to get `preview_token`, execute, assert one
     `IeltsCertificate` + one `StudentActionLog` + one `PLACEMENT_INITIALIZED`
     event per successfully placed row, assert every placed student's
     `ProgramEnrollment.study_stage` is `intake_course` (never
     `intake_pre_uni_gc` — this importer only ever produces Major
     placements), assert the below-threshold student produced zero writes
     (excluded at preview, never reached execute), and assert the
     already-placed student's row comes back `status: 'error'` in the
     response body.
   - `it('rejects both routes without change_student_status', function (): void {...})`.
   - `it('fails fast when the intake semester is not configured', function (): void {...})`
     — same corrected shape as the EGC importer's equivalent test: preview
     with the mapping unset to get a real `preview_token`, then execute
     with that valid token and assert `200` + `global_errors` + zero writes
     (not a 422 from the token-mismatch guard).

## Success Criteria

- [ ] All new tests pass in isolation
      (`docker exec -w /app swinx-app-dev ./vendor/bin/pest --filter=BulkMajorPlacement`).
- [ ] All new tests pass together with
      `tests/Feature/Academic/Progression/BulkEgcPlacementImportTest.php`,
      `PlacementWorklistTest.php`, and `PlacementOwnershipTest.php` in one
      combined run — this is the actual regression check that catches the
      duplicate-helper-function crash class; running each file in isolation
      is not sufficient proof.
- [ ] No test depends on wall-clock "current semester" or "latest
      application" ordering ambiguity — every ordering-sensitive fixture
      (multiple applications per student) sets `created_at` explicitly
      rather than relying on factory-default timestamps happening to sort
      correctly.

## Risk Assessment

The `tests/Feature/Academic` suite has pre-existing, unrelated failures
(see prior session notes on Academic attendance/placement fixtures) and a
pre-existing duplicate-Pest-helper landmine elsewhere in `tests/`
(`rosterStudent`, `makeWorklistStudent` collide across unrelated file pairs
— found during the EGC importer's code review, not yet fixed). Run this
phase's new files against their direct siblings only (per the Success
Criteria above), not the whole suite, to avoid mistaking either pre-existing
issue for a regression introduced here.
