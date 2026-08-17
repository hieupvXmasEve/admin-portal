---
phase: 1
title: "Row mapper + application score lookup"
status: pending
priority: P1
effort: "3h"
dependencies: []
---

# Phase 1: Row mapper + application score lookup

## Overview

Row-level parsing/validation: turn one CSV row (just a `Student ID`) into
either a normalized Major-placement payload or a skip reason. Read-only
lookups only (`Student`, `StudentApplication`) — no writes, mirrors
`BulkEgcPlacementRowMapper`'s shape from the sibling plan.

<!-- Updated: Validation Session 1 - below-threshold rows are excluded
(status=skip, skip_reason=below_threshold_excluded), not marked valid with a
predicted-fallback outcome. Every 'valid' row is unconditionally Major-bound. -->

## Requirements

- Functional:
  - Header detection is by column name (`Student ID`), case-insensitive,
    trimmed — same convention as `BulkEgcPlacementRowMapper::validateHeaders()`.
    Other columns in the file, if any, are ignored.
  - Student lookup: `Student::query()->where('student_id', trim($raw))
    ->where('campus_id', $campusId)->first()` — campus-scoped from the
    start (do not repeat the EGC importer's HIGH-severity cross-campus bug;
    it was fixed there post-review, bake the fix in here from commit one).
  - IELTS application lookup, only after a `Student` is resolved:
    `StudentApplication::query()->where('student_id', $student->id)
    ->where('english_test_type', 'IELTS')->whereNotNull('overall')
    ->orderByDesc('created_at')->first()`. Verified via `php artisan tinker`
    against the running dev DB: `english_test_type` has exactly two live
    values, `IELTS` and `Other` — exact string match is correct, no fuzzy
    matching needed.
  - Threshold check (confirmed with user during validation — exclude, do
    not auto-fallback): compare the found `overall` score against
    `IeltsCertificate::SCORE_THRESHOLD_INTAKE_COURSE` (5.5). `>= 5.5` →
    `status: 'valid'`. `< 5.5` → `status: 'skip'`,
    `skip_reason: 'below_threshold_excluded'` — this row never executes
    through this importer; staff handle it separately. Reads the same
    constant `InitializeStudentPlacementAction` uses internally — single
    source of truth for the threshold value, even though this importer's
    own execute path never lets that action's fallback branch actually
    fire (every row it sends is already >= 5.5).
- Non-functional: pure/stateless mapper class (DB reads only, no HTTP/file
  I/O), easy to unit test — same shape as `BulkEgcPlacementRowMapper`.

## Related Code Files

- Create: `app/Modules/Academic/Progression/Support/BulkMajorPlacementRowMapper.php`
- Read (reference, no changes): `app/Modules/Academic/Progression/Support/BulkEgcPlacementRowMapper.php`
- Read (reference, no changes): `app/Models/StudentApplication.php`
- Read (reference, no changes): `app/Models/IeltsCertificate.php` (for the
  `SCORE_THRESHOLD_INTAKE_COURSE` constant)

## Implementation Steps

1. `validateHeaders(array $headerRow): array` — trim each header cell,
   case-insensitive match for a `student id` column only. Return
   `['ok' => true, 'student_id_col' => int]` or
   `['ok' => false, 'missing' => ['Student ID']]`.

2. `mapRow(array $row, int $rowNumber, int $studentIdCol, int $campusId): array`
   returns:
   ```php
   [
       'row_number' => $rowNumber,
       'status' => 'valid'|'skip',
       'skip_reason' => ?string,          // one of the codes below when status=skip
       'student' => ['id' => ?int, 'student_id' => ?string, 'full_name' => ?string],
       'overall_score' => ?float,         // the looked-up IELTS overall, when found (set even on below_threshold_excluded, so preview can show the number)
       'application_exam_date' => ?string, // for issue_date, when present
       'normalized_payload' => ?array,    // ['student_id' => int, 'ielts_score' => float, 'issue_date' => ?string] when valid — always score >= 5.5
   ]
   ```

3. Skip reason codes (exact strings):
   - `student_id_missing` — `Student ID` cell blank.
   - `student_not_found` — no `Student` row for that `student_id` scoped to
     the current campus.
   - `application_score_missing` — no `StudentApplication` with
     `english_test_type = 'IELTS'` and a non-null `overall` found for that
     student.
   - `below_threshold_excluded` — a qualifying IELTS application was found,
     but `overall < 5.5`. Set `overall_score` on the row even though it's a
     skip, so the preview table can show staff the actual number.

4. Do **not** check "already placed" here — write-path concern, same
   deferral rationale as the EGC importer (`InitializeStudentPlacementAction`
   already throws `InvalidProgressionState`; Phase 2's action catches that
   per-row instead of duplicating the check).

## Success Criteria

- [ ] `validateHeaders` finds the `Student ID` column regardless of other
      column names/order/count in the file.
- [ ] A student not found in the current campus (even if they exist in
      another campus) maps to `student_not_found`, not a match.
- [ ] A student with only a non-IELTS application (`english_test_type = 'Other'`)
      or an IELTS application with a null `overall` maps to
      `application_score_missing`.
- [ ] A student with multiple IELTS applications gets the most recent
      (`created_at desc`) qualifying one.
- [ ] `status` is `'valid'` at exactly `overall >= 5.5` and `'skip'` with
      `skip_reason: 'below_threshold_excluded'` below it (boundary-tested at
      `5.5` and `5.4`), matching `IeltsCertificate::meetsIntakeCourseRequirement()`.

## Risk Assessment

Low risk, additive-only, read-only in this phase. The one real correctness
risk this phase exists to close: reading `overall` from a non-IELTS
application (e.g. TOEFL, which uses a wider numeric range) would silently
misrepresent the score against the IELTS-scale 5.5 threshold and record a
factually wrong certificate — the `english_test_type = 'IELTS'` filter is
not optional and must not be dropped for "simplicity."
