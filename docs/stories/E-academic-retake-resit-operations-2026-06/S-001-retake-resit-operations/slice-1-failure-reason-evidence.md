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
    paid-fee + resit-unit rule (below).
  - The other unlinked failures are intentionally left untouched.
- **Thi lại fee → record rule (owner decision)**: paid PTL fees prove a student sat a
  resit, but the DNG/charge layer has **no structured unit link** (`item_id` =
  `studentCode|feeType` only; charges have no `unit_id`; legacy PTL has no Academic
  source). Per the Academic owner, **exam resit applies only to TEC002 / TEC001**, and
  one paid fee maps to one resit unit, so the command labels a paid-PTL student's failed
  record for the **first resit unit in priority order they failed (TEC002 by default)** —
  not every resit failure — and ignores non-resit failures. A paid-PTL student with **no**
  failed resit-unit record is reported (anomaly), never guessed — almost always because
  they already passed the resit. The owner accepts the TEC002 default and fixes any wrong
  case by hand. Unit set + priority overridable via `--resit-units`; `--reset` clears only
  this backfill's labels (snapshot `backfilled=true`), never finalization-written reasons,
  so the rule can be re-applied deterministically. Note: the thi-lại fee code is `PTL`.
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
  `academic:backfill-failure-reason {--dry-run} {--resit-units=} {--report-unmapped}` —
  **scoped** backfill: labels `grade_failed` ONLY on failed records (`final` +
  `is_passed=false` + `failure_reason IS NULL`) reached from an existing remediation —
  retake (`course_registrations.is_retake` + `CourseRetakeRegistration`), recorded resit
  (`exam_resit_attempts`), or a paid PTL fee mapped to the student's failed
  TEC001/TEC002 record(s). Idempotent, never flips `is_passed`. `--report-unmapped` lists
  paid-PTL anomalies (no resit-unit failure).

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
| Integration | `tests/Feature/Academic/Finalization/FailureReasonFinalizationTest.php` — 7 passed (persists grade/attendance/both/manual + not_recorded soft-flag + grade-only). `BackfillFailureReasonCommandTest.php` — 14 passed (labels via course_registrations retake enrolment, modern retake source, recorded resit attempt, and paid-PTL → one resit unit with TEC002 default when both failed / TEC001 otherwise; **stays on TEC002 across repeated runs, never falling through to TEC001**; ignores non-resit failures; reports paid-PTL students with no resit-unit failure; respects --resit-units; `--reset` clears only backfilled labels not finalization reasons; leaves non-remediation failures untouched; never flips a pass; idempotent; dry-run). |
| Regression | `tests/Feature/Academic` — 115 passed; existing finalization/GPA/exam-resit/retake suites green. The one failure (`GetStudentAttendanceQueryTest`, `AttendanceService` insert) is **pre-existing**, fails in isolation, and is in code untouched by this slice. |
| Platform | Pint clean on touched files. `git diff --check` clean. |

## Operational follow-up

- Run `php artisan academic:backfill-failure-reason` (dry-run first) once deployed so
  the failed records of students who already did retake/resit gain `grade_failed`.
  Unlinked historical failures are intentionally left null (no functional need now).
- `--resit-units=TEC001,TEC002` (default): the resit-eligible unit set used to map paid
  PTL fees to a failed record. `--report-unmapped`: read-only table of paid-PTL students
  with no failed resit-unit record (anomalies).

### Real-data outcome (run 2026-06-19, after `--reset` + re-run)

- 29 records labelled deterministically: retake links + one resit unit per paid-PTL
  student (TEC002 default). The 6 students who failed both TEC001 and TEC002 are labelled
  on TEC002 only (TEC001 left null), per the one-fee-one-unit default.
- 8 paid-PTL students remain unlabelled and are correct: verified each has TEC001 AND
  TEC002 with `is_passed=true` (passing scores) — they paid, sat, and passed the resit, so
  there is no failure to label. Surfaced via `--report-unmapped`; no action needed.

## Next (paused for review — next is a hard gate)

- Slice 2: retake create + eligibility query gate on `failure_reason`.
- Slice 3: HQ `exam_resit_fee` charge action + paid syncer (Finance charge hard gate).
