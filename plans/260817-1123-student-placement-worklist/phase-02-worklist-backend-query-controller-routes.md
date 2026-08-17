---
phase: 2
title: "Worklist backend query controller routes"
status: completed
priority: P1
effort: "4h"
dependencies: [1]
---

# Phase 2: Worklist backend query controller routes

## Overview

Backend for the dedicated Academic classification page: query listing
approved-but-unclassified students for the current campus, Inertia controller,
route. No new write endpoint — classification posts to the existing
`students.placement.initialize`.

## Requirements

- Functional: paginated list of students whose **primary**
  `program_enrollments` row has `study_stage IS NULL` and `enrollment_status`
  not in `('withdrawn','graduated')`, scoped to `session('current_campus_id')`;
  search by student code/name; `program_id` filter (validated: v1 filters =
  search + program); include fields the UI needs (id, student_id, full_name,
  email, program name, intake semester label, admission_date) plus program
  options for the filter dropdown.
- Semester dropdown + IELTS threshold for the classify dialog: reuse
  `LifecycleFormOptions::placementOptions()`
  (`app/Modules/Academic/Progression/Support/LifecycleFormOptions.php`,
  verified — same payload `EgcControls` consumes).
- Non-functional: campus not selected → redirect to `select-campus.index`
  (same pattern as `app/Modules/StudentRegistry/Http/Web/StudentController.php:55`).
- Permission (validated): whole page behind `can:change_student_status` —
  dept-specific operational page. Classify posts already behind the same
  permission.

## Architecture

Owner module: **Academic/Progression** (placement already lives there).
Query object per repo pattern (single `handle(array $filters, int $campusId)`
returning paginator). Controller renders Inertia page
`Academic/PlacementWorklist/Index` with students + semesters + IELTS threshold
(same options `EgcControls` uses, e.g. `ieltsScoreThreshold`).

## Related Code Files

- Create: `app/Modules/Academic/Progression/Queries/ListUnclassifiedStudentsQuery.php`
- Create: `app/Modules/Academic/Progression/Http/Web/StudentPlacementWorklistController.php`
- Modify: `app/Modules/Academic/routes/web.php` (GET
  `students-placement-worklist` → controller `index`, `can:change_student_status`,
  name `students.placement-worklist.index`)
- Create: `tests/Feature/Academic/Progression/PlacementWorklistTest.php`

## Implementation Steps

1. Query: join students → primary program_enrollment; filters
   `study_stage IS NULL`, `enrollment_status NOT IN (withdrawn, graduated)`,
   `students.campus_id = $campusId`; optional `q` on student_id/full_name;
   optional `program_id`; order `admission_date DESC`; paginate.
2. Controller `index`: campus guard, run query, load
   `LifecycleFormOptions::placementOptions()` + program options, render
   Inertia page.
3. Route registration in module route file (`can:change_student_status`).
4. Tests: unclassified visible; classified (study_stage set) absent;
   withdrawn/graduated absent; other-campus absent; program filter narrows;
   permission denied without `change_student_status`; after
   `POST students.placement.initialize` student drops off the list. CSRF:
   include `_token` per repo test gotcha.
5. Run: `./scripts/dev.sh artisan test tests/Feature/Academic/Progression/PlacementWorklistTest.php --compact`.

## Success Criteria

- [x] List correct + campus-scoped + searchable + program filter
- [x] Redirect when no campus selected
- [x] Permission enforced (real-gate test, no Gate::before bypass)
- [x] Classified student disappears from list (integration with phase 1 action)
- [x] New test file green (PlacementWorklistTest 5 passed, 58 assertions)

## Risk Assessment

Medium-low. Read-only surface; main risk is unclassified-definition edge cases
(students with no primary enrollment at all — approve flow always materializes
one, so treat missing-enrollment as out of scope; do not LEFT JOIN them in).
