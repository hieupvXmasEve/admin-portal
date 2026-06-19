# ACAD-RET-001 — Slice 1 Evidence: `failure_reason` at finalization + safe backfill

Date: 2026-06-18. Hard gate: Academic finalization. Status: implemented (uncommitted).

Unblocks the exam-resit eligibility gate (`CreateExamResitAttemptAction` requires a
non-null `failure_reason`) and gives course-retake routing an explicit reason.

## Locked decisions applied

- **1a forward-only**: new finalizations evaluate grade AND attendance; old data is
  accepted as correct (grade-only, no attendance re-check) and never re-flipped.
- **Backfill scope (owner decision, 2026-06-18)**: a dry-run showed ~190 finalized
  failures. The owner confirmed old results are correct and we only need
  `failure_reason` for students who already did retake/resit, detected from the most
  accurate signals (fee/enrolment data):
  - **Học lại (HL)**: `course_registrations.is_retake = true` → offering → unit → the
    student's failed record for that unit, plus the direct
    `course_retake_registrations.original_academic_record_id` link.
  - **Thi lại (PTL)**: the direct `exam_resit_attempts.academic_record_id` link, plus a
    paid-fee heuristic (below).
  - The other unlinked failures are intentionally left untouched.
- **Thi lại fee → record heuristic (owner decision)**: paid PTL fees prove a student sat
  a resit, but the DNG/charge layer has **no structured unit link** (`item_id` =
  `studentCode|feeType` only; charges have no `unit_id`; legacy PTL has no Academic
  source). So for each student with a paid PTL fee, the command labels their failed
  record **only when there is exactly one outstanding eligible failed record** (grade-final,
  no `failure_reason`, not already covered by a retake/resit link); students with zero or
  several are **reported for manual mapping**, not guessed. The heuristic is
  student-scoped (failure is in the original term, the fee in the operation term). Note:
  the system fee code for thi lại is `PTL` (not `TL`).
- **1b not_recorded → soft-flag**: incomplete attendance evidence is surfaced in the
  snapshot (`attendance_evidence_state='not_recorded'`); it does not, by itself, fail
  the student here — the resit gate already blocks on it.
- **1c attendance %** = `(present+late)/(present+late+absent)`; excused and
  not-recorded are excluded from the denominator (story policy). Attendance is only
  evaluated when there is recorded evidence — courses with none stay grade-only, so
  historical grade-only behavior is preserved.

## Changes

- `app/Modules/Academic/Support/FailureReasonClassifier.php` (new) — pure classifier:
  scalars in → `{is_passed, failure_reason, snapshot}`. Single source of truth for the
  decision matrix (grade / attendance / both / manual + evidence state).
- `app/Services/CourseCompletionService.php` — `finalizeAcademicRecords()` now derives
  `is_passed` + `failure_reason` + `failure_reason_snapshot` via the classifier
  (replaced the grade-only check and removed the dead commented attendance block).
  Attendance threshold read live from `syllabus_templates.min_attendance_threshold ?? 80`.
- `app/Console/Commands/Academic/BackfillFailureReasonCommand.php` (new)
  `academic:backfill-failure-reason {--dry-run}` — **scoped** backfill (owner decision,
  see below): labels `grade_failed` ONLY on failed records (`final` + `is_passed=false`
  + `failure_reason IS NULL`) that an EXISTING remediation source already points at —
  `course_retake_registrations.original_academic_record_id` ∪
  `exam_resit_attempts.academic_record_id`. Idempotent, never flips `is_passed`.

## Snapshot shape (consumed by the resit gate)

`failure_reason_snapshot = { final_score, grade_threshold, attendance_pct,
attendance_threshold, attendance_evidence_state(clean|not_recorded|no_sessions),
grade_failed, attendance_failed, override_pass, evaluated_at }`.
The resit gate reads `attendance_evidence_state === 'not_recorded'` (and `total_not_recorded`).

## Behavior note (for registrar sign-off)

Attendance failure sets `is_passed=false` + `failure_reason` but keeps the computed
grade letter/points (does NOT force "F"/0), consistent with the existing grade-fail
path; this avoids GPA/transcript corruption. The old commented code forced F — flag if
the registrar wants F forced for attendance failures.

## Validation

| Layer | Proof |
| ----- | ----- |
| Unit | `tests/Unit/Academic/FailureReasonClassifierTest.php` — 8 passed (full decision matrix incl. excused/not_recorded/override). |
| Integration | `tests/Feature/Academic/Finalization/FailureReasonFinalizationTest.php` — 7 passed (persists grade/attendance/both/manual + not_recorded soft-flag + grade-only). `BackfillFailureReasonCommandTest.php` — 9 passed (labels via course_registrations retake enrolment, modern retake source, recorded resit attempt, and the single-outstanding paid-PTL heuristic; does NOT guess when a paid-PTL student has several failures; leaves unlinked failures untouched; never flips a pass; idempotent; dry-run). |
| Regression | `tests/Feature/Academic` — 115 passed; existing finalization/GPA/exam-resit/retake suites green. The one failure (`GetStudentAttendanceQueryTest`, `AttendanceService` insert) is **pre-existing**, fails in isolation, and is in code untouched by this slice. |
| Platform | Pint clean on touched files. `git diff --check` clean. |

## Operational follow-up

- Run `php artisan academic:backfill-failure-reason` (dry-run first) once deployed so
  the failed records of students who already did retake/resit gain `grade_failed`.
  Unlinked historical failures are intentionally left null (no functional need now).

## Next (paused for review — next is a hard gate)

- Slice 2: retake create + eligibility query gate on `failure_reason`.
- Slice 3: HQ `exam_resit_fee` charge action + paid syncer (Finance charge hard gate).
