---
phase: 1
title: "Enable safe re-finalize of a diverged semester"
status: todo
priority: P1
effort: "5h"
dependencies: []
---

# Phase 1: Enable safe re-finalize of a diverged semester

## Overview

Today a finalized semester GPA can never be corrected: `FinalizeSemesterGpaAction` skips any student with an `is_finalized=true` row, and even without that guard `GpaCalculation::create()` would violate `UNIQUE(student_id, semester_id)` and abort the whole campus batch. This phase makes re-finalize possible and safe, in place, with an audit entry — the single blocker for fixing AUS19927 and every future grade correction.

## Requirements

- Functional: re-running Finalize for a semester recomputes and **updates the existing row in place** when the recomputed values differ from stored; skips (no write, no churn) when they match.
- Functional: `is_current` is only claimed when the finalized semester is the student's latest finalized semester by `Semester.start_date`; re-finalizing an earlier semester must NOT pull `is_current` backwards.
- Functional: every value-changing re-finalize emits an activity-log entry carrying prior and new `semester_gpa` / `cumulative_gpa`, the causer, and the `gpa_calculations.id`. With in-place update there is no superseded row, so this log is the only historical record — it is mandatory.
- Non-functional: no schema change; the unique index stays as the correctness guarantee.
- Compatibility: an unchanged re-run must still report students as skipped so existing behavior and `FinalizeSemesterGpaActionTest` expectations hold.

## Architecture

Guard semantics change from positional to semantic. Today: *"skip if a finalized row exists."* After: *"skip if the stored row already equals the recomputed values."* This makes Finalize idempotent and self-correcting, while keeping a human (registrar clicking Finalize) as the approving actor — no automatic number swap.

```
foreach student:
  semesterData = CalculateStudentSemesterGpaAction
  cumulativeData = CalculateCumulativeGpaAction
  existing = GpaCalculation::where(student_id, semester_id)->first()
  if existing && values identical  -> skippedAlreadyFinal++, continue   // unchanged re-run
  updateOrCreate(['student_id','semester_id'], [...payload...])
  if existing && values differ     -> activity('gpa_refinalized') with old vs new
  is_current: claim only if this semester is the student's latest finalized
```

Cascade is deliberately omitted. Re-finalizing semester N leaves later semesters' stored cumulative divergent — but those semesters then surface as divergent on their own Finalize page and in `academic:audit-progression-reconciliation` (phase 2 makes this visible). Operators re-finalize each affected semester; no hidden cascade writes.

**Pre-existing defects this phase must not inherit:** `GpaManagementController::finalize` does not catch `QueryException`, and the whole student loop runs in one transaction — so any per-student failure still aborts the batch. Keep the transaction, but the `updateOrCreate` change removes the duplicate-key failure mode that would have made this fatal.

## Related Code Files

- Create: `app/Shared/Support/Academic/GpaValueComparator.php` — the single definition of "these GPA values are the same". Home chosen to match the existing precedent `app/Shared/Support/Academic/CourseGradeScale.php`: a pure Academic policy helper with no record access, consumed by both global `app/Actions/Academic/*` and module queries (`CourseGradeScale` is used from `app/Models/AcademicRecord.php`, `app/Modules/Academic/Support/FailureReasonClassifier.php`, and `.../Delivery/Actions/SaveLecturerGradebookScoresAction.php`). A `Shared\Contracts` interface + provider binding would be over-engineering for a stateless value comparison — no binding, no interface.
- Modify: `app/Actions/Academic/FinalizeSemesterGpaAction.php` (guard semantics, `updateOrCreate`, `is_current` rule, activity log)
- Modify: `app/Modules/Academic/Progression/Queries/GetAcademicProgressionReconciliationQuery.php` — its `private function normalize()` (line 899) is currently the only definition of the rule; move the body verbatim into `GpaValueComparator` and delegate. Behaviour-preserving refactor, not a rule change.
- Read-only deps: `app/Actions/Academic/CalculateStudentSemesterGpaAction.php`, `CalculateCumulativeGpaAction.php`, `app/Models/GpaCalculation.php`, `app/Models/Semester.php`
- Modify test: `tests/Feature/Academic/Gpa/FinalizeSemesterGpaActionTest.php` (existing `is_current` forward-move test at ~:184 must stay green; add cases below)
- Create test: `tests/Feature/Academic/Gpa/RefinalizeDivergedSemesterTest.php`
- Migration: none. Route: none. FormRequest: none. Frontend: none.

## The comparison rule

`GetAcademicProgressionReconciliationQuery::normalize()` (line 899) is the existing rule and it is **not** an epsilon tolerance — it is `number_format((float) $value, 3, '.', '')` then string comparison, with `bool` mapped to `'true'`/`'false'` and `null` to `'null'`. Three decimal places is consistent end to end: the calculate actions `round(..., 3)` and `GpaCalculation` casts the GPA columns to `decimal:3`.

Extracting it verbatim is what makes "the finalize guard, the Phase 2 badge, and the audit agree" true structurally rather than by coincidence of two similar implementations. Do not restate the rule as "0.001 tolerance" anywhere — it is 3-dp normalization.

## Implementation Steps

1. Failing tests first (AAA):
   - stored row matches recomputed → skipped, row `updated_at` unchanged, no activity entry;
   - grade changed after finalize → row updated in place to new values, one `gpa_refinalized` activity entry with old + new GPA, still exactly one row for `(student, semester)`;
   - re-finalizing an EARLIER semester while a later finalized semester exists → later semester keeps `is_current`; earlier row updated;
   - finalizing a LATER semester still moves `is_current` forward (guard the existing behavior);
   - no `(student_id, semester_id)` duplicate is ever attempted (regression for `unique_student_semester_gpa`).
2. Extract `GpaValueComparator` from the reconciliation query's private `normalize()` (verbatim), have that query delegate to it, and confirm `academic:audit-progression-reconciliation --student-id=506` output is unchanged by the refactor. Then implement the finalize guard on top of it.
3. Swap `create()` → `updateOrCreate()` keyed on `['student_id','semester_id']`.
4. Implement the `is_current` rule (claim only when latest finalized semester by `start_date`; treat NULL `start_date` as not-later to avoid stealing the flag).
5. Add `activity('gpa_refinalized')->causedBy(...)->withProperties([...])`. Note the reopen/refinalize can run from console context where `auth()` is null — resolve the actor explicitly from the passed `$adminId` as the existing code already does.
6. Run: `./scripts/dev.sh artisan test tests/Feature/Academic/Gpa`

## Todo

- [ ] Tests red
- [ ] `GpaValueComparator` extracted; audit query delegates; audit output byte-identical for student 506
- [ ] Guard + updateOrCreate + is_current rule + activity log implemented, tests green
- [ ] Existing 5 Gpa test files still green
- [ ] Manual: re-finalize campus 2 / SPRING2026 on dev → AUS19927 row becomes 80.737 / 81.293, single row, activity entry present

## Success Criteria

- [ ] `academic:audit-progression-reconciliation --student-id=506` reports zero `GPA-001` exceptions after re-finalize
- [ ] No duplicate-key error under any re-finalize path
- [ ] `is_current` still points at the student's latest finalized semester after re-finalizing an earlier one

## Risk Assessment

- **Concurrency: no locking, accepted by decision.** A bulk finalize can interleave with a lecturer's Recalculate and write a row that is already divergent by the time it commits. `lockForUpdate()` was considered and rejected: the finalize transaction already spans every student in the campus, and the Recalculate transaction holds locks across an external Canvas HTTP call, so lengthening either lock is the worse trade. Compensating control: divergence is derived, so the next finalize-page load or audit run surfaces the row again — the failure mode is a delayed correction, not a silent permanent error. Do not add `lockForUpdate` without revisiting this decision.
- Comparison-rule drift would cause perpetual "divergent" or perpetual "clean". Mitigation: one extracted `GpaValueComparator` used by the guard, the Phase 2 badge, and the audit query — plus a test asserting the audit's output is unchanged by the extraction.
- `Semester.start_date` is nullable (0 rows in dev today); the `is_current` rule must not crash or mis-rank on NULL.
