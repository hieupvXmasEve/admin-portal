# ACAD-RET-001 — Phase 1 Discovery Findings

Date: 2026-06-18. Read-only investigation (execplan Phase 1). No code changed.
All claims cite `path:line`. Synthesis of 5 parallel traces: course-retake
lifecycle, finalization/`failure_reason`, Finance charge/payment idempotency,
timetable/scheduling, and completion/GPA/progression.

## Headline: foundation scaffolded, behavior missing

A prior slice already landed the **data foundation** but not the logic:

- `database/migrations/2026_06_17_100000_add_retake_resit_source_foundation.php`
  — adds to `course_retake_registrations`: `request_origin`, requested/reviewed
  audit, `hq_fee_status`, `policy_snapshot`, `original/operation/charge_semester_id`,
  payment-deadline fields; adds `failure_reason` + `failure_reason_snapshot` to
  `academic_records`; adds `active_source_key` unique index + resit policy fields
  on `syllabus_templates`.
- `database/migrations/2026_06_17_100100_create_exam_resit_attempts_table.php` —
  `exam_resit_attempts` with `finance_charge_id`, `hq_fee_status`, `resit_score`,
  `final_chosen_score`, `result_snapshot`, status enum.
- Model constants exist: `AcademicRecord::FAILURE_*` (`app/Models/AcademicRecord.php:15-28`),
  `CourseRetakeRegistration` request/hq-fee constants, `ExamResitAttempt` statuses.
- `CreateExamResitAttemptAction` already **hard-gates** on
  `failure_reason != null` and `failure_reason_snapshot.attendance_evidence_state
  != 'not_recorded'` (`app/Modules/Academic/Actions/CreateExamResitAttemptAction.php:104-124`).

So Phase 2 (data contract) is largely migrated. The remaining work is the
**logic that writes and consumes** these fields — most of it behind hard gates.

## Area 1 — Course-retake (`học lại`) lifecycle  [largely DONE]

- Source: `course_retake_registrations` / `app/Models/CourseRetakeRegistration.php`.
  Status enum `approved → payment_pending → paid → enrolled` (+`cancelled`)
  (`:17-25`); `hq_fee_status` `hq_fee_pending → charge_created → paid → cancelled`.
- Create (staff, auto-approved): `CreateRetakeCourseRegistrationAction`
  (`app/Modules/Academic/Actions/CreateRetakeCourseRegistrationAction.php:36`) —
  sets `approved`, `request_origin=staff`, `hq_fee_status=hq_fee_pending`. Does
  **not** create a charge (ownership already moved to HQ).
- Class placement decoupled: `course_offering_id` is now **nullable**
  (`2026_06_17_100000:39`); create runs prereq check only when supplied.
- Charge+DNG (HQ): Batch Studio `commitDng` → `CreateBatchDngFromChargesAction`
  → `CreateRetakeCourseChargeSimpleAction` (`.../CreateRetakeCourseChargeSimpleAction.php:36`).
  Dedicated single-charge route removed (`app/Modules/Finance/routes/web.php:291`).
- Paid sync: `DngWebhookService` → `AutoEnrollRetakeCourseAction` /
  `SyncPaidRetakeRegistrationsAction` (canonical `charge.is_fully_paid`).
- **Gaps:** (a) no `requested` status in the DB enum yet (future student-request
  path); (b) `CreateRetakeCourseRegistrationAction` checks `is_passed=false` but
  does **not** gate on `failure_reason` (`:60-80`); (c) overdue warning
  (`first_class_session_at + 14d`) computation not wired; (d) eligibility query
  `ListRetakeCourseEligibleStudentsQuery` has no `failure_reason` filter.

## Area 2 — Finalization & `failure_reason`  [HARD GATE — blocking]

- Finalization: `MarkCourseOfferingCompletedAction` →
  `CourseCompletionService::finalizeCourse()` →
  `finalizeAcademicRecords()` (`app/Services/CourseCompletionService.php:99-229`).
  Pass/fail = `final_percentage >= threshold` where threshold =
  `syllabusTemplate.min_grade_threshold ?? (egc?70:60)` (`:107,172-193`).
- **CRITICAL:** `failure_reason` / `failure_reason_snapshot` are **never written**
  at finalization (`:202-216`). The attendance check is **commented out**
  (`:129-165`) — today every student is graded grade-only.
- Attendance %: canonical rollup `AcademicRecordGenerationServiceOptimized:304-308`
  uses denominator `present+late+excused+absent` (excludes `not_recorded`);
  `CourseStatisticsService:202` uses all sessions — two formulas, must pick one
  for the decision. `not_recorded` is derived, not a stored `attendances` status
  (`2025_06_15_103000_create_attendances_table.php:19-27`).
- Canvas sync overwrites `final_percentage`/`final_letter_grade` without
  touching `failure_reason`/`is_passed` (`app/Services/Canvas/CanvasGradeSyncService.php:394-413`).
- **Consequence:** every record finalized today has `failure_reason=null`, so
  `CreateExamResitAttemptAction` rejects it (422). A backfill is required.
- **Minimal-change path:** add `failure_reason` + snapshot to the single
  `$record->update([...])` in `finalizeAcademicRecords()` (`:202`); snapshot
  shape `{final_score, grade_threshold, attendance_pct, attendance_threshold,
  resolved_at, attendance_evidence_state}` (consumed at
  `CreateExamResitAttemptAction.php:120`). Re-enabling attendance gating is a
  behavioral change → **hard gate + stop-condition** (grading-rule-engine roadmap).

## Area 3 — Finance charge/payment idempotency  [partial; HARD GATE on charge paths]

- `FinanceCharge` polymorphic source (`source_type`/`source_id`) + computed
  `active_source_key = "{source_type}|{source_id}|{charge_type}"` with a DB
  **unique** index (`app/Models/FinanceCharge.php:111-116,258-274`;
  `2026_06_17_100000:42-43`). Voiding nulls the key (releases the slot). This is
  the reusable active-source uniqueness guard — **no new global constraint needed**.
- Current `retake_fee` creation pattern: the Academic source calls the Finance
  Intake Contract, which materializes the canonical obligation and charge; DNG
  creation is a separate guarded-reservation flow. PAID derives from canonical
  settlement evidence; never a checkbox.
- **Gaps for `exam_resit_fee`:** no Finance action creates it; `ExamResitAttempt.finance_charge_id`/
  `hq_fee_status` never populated; no paid syncer (the retake syncer only handles
  `CourseRetakeRegistration`); batch DNG path handles only `HL`/`retake_fee`
  (`CreateBatchDngFromChargesAction.php:129-131`); `exam_resit_fee` missing from
  `FinanceCharge::CHARGE_TYPES` array (`:78-92`) though in DB enum + DNG mapping (→`PTL`).

## Area 4 — Scheduling (exam-resit sessions)  [NET-NEW; scheduling stop-conditions]

- `class_sessions.course_offering_id` is **NOT NULL** — every session needs a CO;
  resit sessions have none. Recommended: **separate `exam_resit_sessions` table**
  (cleaner than nullable FK / dummy CO).
- Student timetable merge point: `TimetableService::generateWeeklySchedule()`
  (`app/Services/V1/Student/TimetableService.php:150`); `events` are the existing
  template for a non-class item type (`item_type`). Student sessions are
  enrollment-scoped (`ClassSessionRepository:22`) so resit sessions are invisible
  unless a parallel source is added.
- Lecturer/invigilator timetable: `LecturerTimetableService::getSessionsInPeriod()`
  (`:344`) via `lecturer->classSessions()`; invigilation in a separate table is
  invisible to it AND to conflict detection (`:451`,`:497`).
- Room conflicts only unified in `RoomBookingService::checkAllConflicts()`
  (`app/Services/RoomBookingService.php:442`) which checks both `room_bookings`
  + `class_sessions`; `AdminScheduleService`/`LecturerTimetableService` each
  check only one source. Overlap predicate everywhere: `start < end AND end > start`.
- **Shared room/time block** (many unit-sessions, one block) needs a parent block
  concept; the existing `room_session_conflict_idx` treats any overlap as conflict.
- Hits execplan stop-conditions (target scheduling without broad timetable/API
  change; shared-slot validation; invigilator vs lecturer-timetable rule).

## Area 5 — Completion, higher-score, GPA/progression  [HARD GATE on academic_records + GPA]

- `academic_records` unique `(student_id, course_offering_id)`
  (`2025_06_15_106000:123`) → resit MUST update in place, no second row.
- `grade_history` (JSON) only written by `EgcLevelProgressionService:329-342`;
  `finalizeAcademicRecords` does **not** write it. No `CompleteExamResitAttemptAction`
  exists; `resit_score`/`final_chosen_score`/`result_snapshot` columns unwritten.
- Higher-score rule slot-in: new completion action loads AR `lockForUpdate`,
  snapshots prior result, applies `max(existing, resit)`, appends a
  `resit_attempt_n` key to `grade_history`, recomputes grade points via
  `AcademicRecord::calculateGradePoints/LetterGrade`.
- GPA recompute is **manual-only** (`FinalizeSemesterGpaAction`, admin route); no
  event hook on `academic_records` change; re-finalize is blocked once
  `gpa_calculations.is_finalized=true`. Warning center reads `gpa_calculations`
  only (read-only). → resit completion **cannot safely auto-recompute** GPA if
  already finalized (execplan stop-condition) — defer via flag/queue, or build a
  single-student re-finalize path first.
- Canvas re-sync after resit would silently overwrite the applied resit score
  (`CanvasGradeSyncService.php:410-413`) — needs a guard.

## Recommended slice order (proposed for Phase 2+)

1. **Finalization `failure_reason` writer + backfill** (Area 2) — unblocks the
   existing resit-attempt gate. HARD GATE (academic finalization). Includes the
   attendance-denominator decision + `not_recorded` block policy.
2. **Retake eligibility routing** (Area 1c/d) — gate create + eligibility query on
   `failure_reason` (attendance/both → retake).
3. **HQ `exam_resit_fee` charge action + paid syncer** (Area 3) — mirror retake
   charge action; reuse `active_source_key`. HARD GATE (Finance charge path).
4. **Exam-resit completion + higher-score + history** (Area 5) — `CompleteExamResitAttemptAction`,
   deferred GPA/progression recompute. HARD GATE (academic_records result).
5. **Exam-resit scheduling** (Area 4) — `exam_resit_sessions`, shared room block,
   conflict-check extension, timetable merge. Largest; scheduling stop-conditions.
6. **Legacy `exam_resit_fee` backfill + reconciliation report** (Area 3/5).
7. **Academic + HQ worklist UI** and **flip `FeeMonitorAcadRetGate`** to enable
   Finance Reporting missing-fee inference (`FeeMonitorAcadRetGate.php:16`).

## Hard gates / stop-conditions hit (require human sign-off before coding)

- Any change to `CourseCompletionService` finalization / `failure_reason`
  computation, esp. re-enabling the commented-out attendance gate (behavioral).
- Any path creating/voiding/linking Finance charges (`exam_resit_fee`).
- Any update to final `academic_records` result (higher-score completion).
- Backfill/reconcile of legacy `exam_resit_fee` charges and `failure_reason`.
- Auto GPA/progression/warning recompute after resit (blocked if already finalized).
- Exam-resit scheduling vs student/lecturer timetable + room-booking conflict rules.

## Open questions for product/owner

1. Attendance gate re-enable: new courses only or retroactive? Hard-block vs
   soft-flag `not_recorded`? Which attendance-% denominator governs the decision?
2. Student-request path (`status=requested`) in this story or staff-only first?
3. `exam_resit_fee` HQ flow: inline DNG (like retake) or two-step Batch Studio
   (no PTL batch path exists yet)?
4. Exam-resit schedule: separate table (recommended) vs nullable FK on
   `class_sessions`? Invigilator = `Lecture` or `User`? Shared-block parent entity?
5. Resit GPA recompute: defer/queue vs build single-student re-finalize path?
6. Legacy `exam_resit_fee` backfill: migration vs reconciliation action +
   exception report?
