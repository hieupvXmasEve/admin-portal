# Validation

> Maintenance note (2026-07-15): retired retake action and source-pointer test
> paths were removed below. Current Finance proof uses the canonical intake and
> guarded-reservation seams.

## Proof Strategy

This story is complete only when Academic source records, HQ fee ownership,
Finance charge linkage, payment-derived state, late-payment monitoring,
schedule/result transitions, audit evidence, legacy backfill, and future
student-request compatibility are proven together. Tests must cover both source
models separately and prove HQ/Finance does not create duplicate charges for the
same Academic source.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Eligibility policy splits `failure_reason` values for grade-fail, attendance-fail, both-fail, and manual-fail; attendance evidence handles `present`, `late`, `absent`, `excused`, and `not_recorded`; attempt-number consumption ignores no-show/cancel/reject/payment-expired; request sequence is separate from consumed attempt number; syllabus default policy snapshot; state-transition guards; idempotent source charge lookup; semester triple validation; schedule conflict predicates; shared-room capacity calculation; higher-score result choice. |
| Integration | Academic staff-created course retake and exam resit auto-approve into the HQ fee worklist without Academic-owned fee creation; course-retake sources can be listed before class placement; HQ creates exactly one `retake_fee` or `exam_resit_fee` even under concurrent calls; future student-request records stay `requested` and do not reach HQ until approved; reject path creates no charge; payment confirmation comes from Finance evidence; payment-expired is derived as an operation projection; overdue payment warnings and reminders appear; paid cancellation preserves payment evidence and exposes an HQ-owned refund/reversal/reallocation candidate; one-unit exam-resit sessions can share a room slot with other units without conflicting with each other. |
| E2E | Academic staff can approve/register course retake and exam resit; HQ can create fees and track/remind payment; Academic can class-place/schedule before payment with required reason where allowed; staff can cancel allowed states; paid-but-cancelled sources preserve payment evidence; completed exam resit updates Academic record using higher-score rule; later Canvas overwrite preserves grade/resit history. |
| Platform | Student timetable merges targeted exam-resit schedules with normal class sessions only for assigned students; invigilator timetable shows assigned exam-resit room-slot duty. |
| Performance | Retake/resit worklists are paginated in the database and scoped by campus/semester/status without materializing large datasets in PHP. |
| Logs/Audit | Each transition records previous/next state, actor, reason, charge/payment evidence, and result-update evidence. |

## Fixtures

- A failed finalized `academic_records` row for course retake.
- A failed finalized `academic_records` row with `failure_reason = grade_failed`
  eligible for exam resit.
- A failed finalized `academic_records` row with
  `failure_reason = attendance_failed` that must be excluded from exam-resit
  eligibility.
- A failed finalized `academic_records` row with `failure_reason = both_failed`
  that must route to course retake.
- A failed finalized `academic_records` row with `failure_reason = manual_failed`
  and audited manual reason.
- Attendance evidence covering `present`, `late`, `absent`, `excused`, and
  `not_recorded`, where `not_recorded` blocks eligibility until resolved.
- A student who already passed the unit and must be rejected by eligibility.
- A syllabus template with default one exam-resit attempt.
- A syllabus/admin policy allowing multiple exam-resit attempts, custom
  `exam_resit_fee`, registration window, late-payment grace days, and unpaid
  sitting allowance.
- A Finance charge that is unpaid, partially paid, fully paid, voided, and
  cancelled.
- A staff-created source and a future student-requested source.
- A course-retake source listed before a target class is selected.
- A course-retake class placement after listing, including a full-class override
  with required reason if allowed.
- Separate original, operation, and charge semesters.
- A course-retake source with first retake class started more than two weeks ago
  and unpaid.
- An exam-resit source scheduled before payment with a required Academic reason
  and overdue two weeks after the scheduled or actual resit sitting time.
- No-show, cancelled, rejected, and payment-expired exam-resit sources that must
  not consume attempt allowance.
- Multiple exam-resit requests where only the completed/sat-with-result row
  consumes `attempt_number`.
- A completed exam-resit with a lower score than the original final score.
- A completed exam-resit with a higher score than the original final score.
- A later Canvas grade overwrite after resit application that preserves
  `grade_history`/attempt audit.
- Legacy `exam_resit_fee` charges without Academic sources for backfill proof,
  including unmatched rows that must be reported as exceptions.
- A room with no conflicts.
- A room with an overlapping normal `class_sessions` row.
- A room with an overlapping unrelated active `room_bookings` row.
- Two exam-resit sessions for different units sharing one allowed exam room slot.
- A student with an overlapping class timetable.
- A student timetable response that includes only assigned exam-resit sessions
  and excludes unassigned sessions in the same room slot.
- An invigilator with an overlapping teaching session or invigilation duty.
- An invigilator timetable response that includes assigned exam-resit room-slot
  duty.

## Commands

Before implementation is accepted, targeted commands must exist and pass. The
expected validation shape is:

```bash
./scripts/dev.sh test tests/Feature/Academic/RetakeCourse
./scripts/dev.sh test tests/Feature/Academic/ExamResit
./scripts/dev.sh test tests/Feature/Finance/RetakeResitHqWorklistTest.php
./scripts/dev.sh test tests/Feature/Academic/ExamResitLegacyBackfillTest.php
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh artisan pint --test
git diff --check
```

If the first implementation slice is docs/schema-only, the slice must still run
the narrowest existing Academic/Finance tests affected by the changed files and
record any unavailable future test commands as expected follow-up evidence.

## Acceptance Evidence

Add concrete command output, screenshots, or trace ids after implementation
exists. Do not mark this story implemented until all eligibility,
HQ/Finance-ownership, payment/source/idempotency, schedule, result-update,
backfill, and audit proofs are recorded.

## Slice Evidence - 2026-06-17

Implemented the first staff/HQ source-foundation slice:

- Staff-created course-retake registrations remain Academic-owned approved
  sources and no longer create Finance charges in Academic.
- Course-retake sources can be created and listed before class placement.
- Existing Finance/HQ retake-charge actions reuse an active source charge and
  the new `finance_charges.active_source_key` guard prevents duplicate active
  charges for the same source/type.
- Approved retake sources without charges appear in the HL DNG worklist as
  `needs_charge_creation` rows.
- Added the first `exam_resit_attempts` source model/action for grade-failure
  eligibility, policy snapshotting, and HQ fee handoff without Academic-owned
  charge creation.

Commands run:

```bash
./scripts/dev.sh test tests/Feature/Academic/RetakeCourse/CreateRetakeCourseRegistrationActionTest.php tests/Feature/Academic/ExamResit/CreateExamResitAttemptActionTest.php tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php

./scripts/dev.sh composer exec pint -- --test app/Models/AcademicRecord.php app/Models/CourseRetakeRegistration.php app/Models/FinanceCharge.php app/Models/SyllabusTemplate.php app/Models/ExamResitAttempt.php app/Modules/Academic/Actions/CreateRetakeCourseRegistrationAction.php app/Modules/Academic/Actions/CreateExamResitAttemptAction.php app/Modules/Academic/Http/Requests/RetakeCourse/StoreRetakeCourseRequest.php app/Modules/Academic/Queries/ListRetakeCourseEligibleStudentsQuery.php app/Modules/Academic/Queries/ListRetakeCourseRegistrationsQuery.php app/Modules/Finance/Actions/CreateRetakeCourseChargeSimpleAction.php app/Modules/Finance/Queries/Dng/ListDngWorklistQuery.php database/factories/SyllabusTemplateFactory.php tests/Feature/Academic/RetakeCourse/CreateRetakeCourseRegistrationActionTest.php tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php tests/Feature/Academic/ExamResit/CreateExamResitAttemptActionTest.php

./scripts/dev.sh pnpm exec prettier --check resources/js/pages/Academic/RetakeCourse/Create.vue
# PASS

./scripts/dev.sh pnpm exec eslint resources/js/pages/Academic/RetakeCourse/Create.vue
# PASS

git diff --check
# PASS
```

Validation gap:

- `./scripts/dev.sh pnpm exec vue-tsc --noEmit --pretty false` hit Node heap
  exhaustion.
- `./scripts/dev.sh pnpm exec env NODE_OPTIONS=--max-old-space-size=4096 vue-tsc --noEmit --pretty false`
  was killed by the container before producing type errors.
- Full story remains in progress: exam-resit charge creation/worklist, payment
  monitoring, reminders, schedule/room/invigilator surfaces, result update,
  legacy backfill, and student portal/API work are deferred to later slices.

## Slice Evidence - 2026-06-20 (Slice 3: HQ exam_resit_fee charge + paid syncer)

Implemented the HQ Finance handoff for exam resit (thi lại), mirroring the
existing course-retake (HL) Finance lane. Hard-gate Finance: every new path only
creates/links charges idempotently and derives paid state from canonical Finance
evidence. No schema migration and no new UI (the DNG worklist already exposes the
`PTL` fee type).

- HQ creates the `exam_resit_fee` charge from the Academic `ExamResitAttempt`
  source via `CreateExamResitChargeSimpleAction`: source-linked
  (`source_type=ExamResitAttempt`), billed on `charge_semester_id`, idempotent
  (reuses the active source charge), and guarded by the existing
  `finance_charges.active_source_key` unique key against concurrent duplicates.
- `ExamResitAttempt` gains `transitionToChargeCreated()` and `transitionToPaid()`
  fee-status transitions (hq_fee_status only; the Academic lifecycle status is
  untouched because payment is a parallel HQ state).
- `SyncPaidExamResitAttemptsAction` (bound to the new
  `ExamResitAttemptPaymentSyncer` contract) flips an attempt to paid only when its
  charge is active and `is_fully_paid` (settlement-derived), never from a manual
  flag. Wired into the same canonical paid-detection points as retake:
  `AllocatePaymentAction`, `AutoAllocatePaymentsAction`, and the DNG webhook
  (`PTL` fee type).
- `CreateBatchDngFromChargesAction` auto-creates exam-resit charges for `PTL`
  before pushing the DNG, mirroring the HL `ensureRetakeChargesExist` path.
- `ListDngWorklistQuery` surfaces approved exam-resit attempts without a charge as
  `needs_charge_creation` rows under `PTL`, reusing the existing worklist row shape.

Commands run:

```bash
./scripts/dev.sh test tests/Feature/Finance/ExamResitChargeActionTest.php tests/Feature/Academic/ExamResit/SyncPaidExamResitAttemptsActionTest.php tests/Feature/Academic/ExamResit/CreateExamResitAttemptActionTest.php tests/Feature/Finance/RetakeResitHqWorklistTest.php tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php tests/Feature/Academic/RetakeCourse/SyncPaidRetakeRegistrationsActionTest.php

./scripts/dev.sh test tests/Feature/Finance/Batch tests/Feature/Finance/Dng
# PASS: 145 tests, 558 assertions

./scripts/dev.sh test tests/Feature/Academic/ExamResit tests/Feature/Academic/RetakeCourse
# PASS: 60 tests, 237 assertions

./scripts/dev.sh composer exec pint -- --test <16 changed PHP files>
# PASS: 16 files

git diff --check
# PASS
```

Regression check:

- `tests/Feature/Finance/{AutoAllocatePaymentsTest,SettlementWorklistTest,PaymentPagesTest}.php`
  report 10 fail / 4 pass both with and without this slice (verified via
  `git stash -u`), confirming those are the documented pre-existing finance
  baseline failures, not regressions from the allocation/webhook wiring.

Validation gap (unchanged from prior slices):

- `vue-tsc --noEmit` still OOMs in the dev container; no frontend source changed
  in this slice (PTL was already a selectable worklist fee type), so no per-file
  type-check was required.
- Deferred to later slices: exam-resit overdue/reminder monitoring, paid
  cancellation refund/reversal handling, schedule/room/invigilator surfaces,
  result update, legacy `exam_resit_fee` backfill, and student portal/API.

## Slice Evidence - 2026-06-20 (Slice 4: Exam-resit completion + higher-score + history)

Implemented the exam-resit (thi lại) sitting-result write-back to the final
Academic record. Hard gate: `academic_records`. No schema migration (the
`exam_resit_attempts` result columns and `academic_records.grade_history` already
exist) and no new UI (the staff completion controller/route ships with the
Academic workspace slice).

- `CompleteExamResitAttemptAction` records a resit result on an approved/scheduled
  attempt and applies the locked **higher-score rule**: the academic record's
  `final_percentage` becomes `max(original, resit)`. A lower resit never reduces
  the record; it is still recorded and consumes an attempt.
- On an applied (strictly higher) resit, the record recomputes `final_letter_grade`,
  `grade_points`, `quality_points`, pass state, `completion_status`,
  `satisfies_prerequisite`, and earned credit. The grade threshold reuses the
  syllabus `min_grade_threshold` (default 60, EGC 70), matching
  `CourseCompletionService` finalization.
- The applied resit is score-authoritative: it clears any prior manual override
  (`override_pass`), clears `failure_reason` on pass, and normalizes a
  still-failing result to `grade_failed` (attendance was already fine to be
  eligible for resit). Confirmed product decision 2026-06-20.
- **Attempt counting**: `attempt_number` is consumed ONLY at completion (max
  consumed + 1), separate from the creation-time `request_sequence`.
- **History preservation**: the pre-resit result is snapshotted on the attempt
  (`previous_result_snapshot`) and appended to
  `academic_records.grade_history['exam_resit_applications']`. A later Canvas sync /
  re-finalization can overwrite the score without erasing the resit audit (proven
  by test: grade_history + attempt result survive a later `final_percentage`
  overwrite).
- **Payment gate** (finance-derived): completion requires `hq_fee_status = paid`,
  unless the snapshotted policy allows an unpaid sitting AND a visible
  `unpaid_sitting_reason` is recorded (persisted with actor + timestamp).
- **GPA/progression recalculation is FLAGGED, not run** (`result_snapshot.requires_gpa_recalc`),
  honoring the execplan stop-condition that defers grade-engine recalculation to a
  separate grading story.

Commands run:

```bash
./scripts/dev.sh test tests/Feature/Academic/ExamResit/CompleteExamResitAttemptActionTest.php
# PASS: 12 tests, 51 assertions

./scripts/dev.sh test tests/Feature/Academic/ExamResit tests/Feature/Academic/RetakeCourse
# PASS: 72 tests, 288 assertions

./scripts/dev.sh composer exec pint -- --test app/Modules/Academic/Actions/CompleteExamResitAttemptAction.php tests/Feature/Academic/ExamResit/CompleteExamResitAttemptActionTest.php
# PASS: 2 files

git diff --check
# PASS
```

Validation gap (unchanged from prior slices):

- `vue-tsc --noEmit` still OOMs in the dev container; no frontend source changed
  in this slice.
- Deferred to later slices: exam-resit scheduling (sessions/room slots/invigilators),
  GPA/progression/warning recalculation execution, overdue/reminder monitoring,
  paid cancellation refund/reversal, legacy backfill, and the Academic staff
  completion UI/route.

Confirmed product decisions 2026-06-20 (resolved unresolved questions):

- Completion currently allows the `approved` state because scheduling infra does
  not exist yet. Once the scheduling slice lands, completion must require
  `scheduled` (no bypass). Recorded as an interim note in `assertCanComplete`.
- GPA/progression recalculation stays deferred: this slice only flags
  `result_snapshot.requires_gpa_recalc`; a separate grading story executes it.
- A still-failing applied resit normalizes `failure_reason` to `grade_failed` and
  clears any prior manual override, since a recorded resit sitting is
  score-authoritative.

## Slice Evidence - 2026-06-20 (Slice 5: Exam-resit scheduling)

Implemented the dedicated exam-resit (thi lại) scheduling model and its lifecycle
into the `scheduled` state. Exam resit is scheduled with its own session model,
never the course `class_sessions` model, so it never looks like normal course
delivery. Backend-only slice (no UI), matching prior slices.

Schema (one migration, `2026_06_20_120000_create_exam_resit_scheduling_tables`):

- `exam_room_slots` = one room + date/time block (the unit of room-conflict and
  invigilation; capacity defaults to room capacity).
- `exam_resit_sessions` = one unit/course exam inside a slot; multiple unit-scoped
  sessions may share one slot, bounded by the slot's seat capacity.
- `exam_room_slot_invigilators` = invigilators (lecturers) attached to the shared
  room block, unique per `(slot, lecture)`.
- `exam_resit_attempts` gains `exam_resit_session_id`, `scheduled_at`,
  `scheduled_by_user_id` (all nullable/additive).

Behavior:

- `CreateExamRoomSlotAction` reserves a room block and rejects overlap with live
  `class_sessions`, active (`pending`/`approved`) `room_bookings`, and other
  scheduled `exam_room_slots` in the same room. Half-open overlap rule, so
  adjacent blocks (e.g. 09:00–11:00 then 11:00–13:00) do not conflict. Capacity
  defaults to room capacity and cannot exceed it.
- `CreateExamResitSessionAction` creates a unit-scoped session inside a slot;
  several different-unit sessions share one slot without conflict, and the sum of
  their `expected_candidates` cannot exceed the slot capacity. A session must plan
  at least one seat (`expected_candidates >= 1`), since attempt assignment is
  capped by it.
- `ScheduleExamResitAttemptAction` assigns an `approved` attempt to a matching
  unit/campus session → `scheduled`. Guards: only `approved` is schedulable; the
  session must be live; the session's planned seats cap how many attempts it can
  hold; the student must be free of overlapping enrolled `class_sessions` and other
  scheduled exam-resit sessions; scheduling before payment is allowed only when the
  snapshotted policy permits an unpaid sitting AND a visible reason is recorded
  (payment stays a parallel HQ state, so the unpaid row remains visible).
- `AssignExamResitInvigilatorAction` assigns a lecturer to a slot (lead/assistant/
  backup), rejecting an invigilator who is teaching a `class_session` or already
  invigilating another slot at an overlapping time, and duplicate assignment.
- All conflict predicates live in one shared
  `App\Modules\Academic\Services\ExamScheduleConflictChecker`.
- `CompleteExamResitAttemptAction` now requires `scheduled` (the prior interim
  `approved` bypass is removed, honoring the confirmed slice-4 decision).

Commands run:

```bash
./scripts/dev.sh test tests/Feature/Academic/ExamResit
# PASS: 56 tests, 145 assertions (10 room-slot + 6 session + 10 schedule +
#       8 invigilator + 13 completion + 9 prior create/sync)

./scripts/dev.sh test tests/Feature/Academic/ExamResit tests/Feature/Academic/RetakeCourse
# PASS: 106 tests, 346 assertions

./scripts/dev.sh test tests/Feature/Finance/ExamResitChargeActionTest.php \
  tests/Feature/Finance/RetakeResitHqWorklistTest.php \
  tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php
# PASS: 24 tests, 65 assertions (finance exam-resit handoff unaffected)

./scripts/dev.sh composer exec pint -- --test <18 new/changed PHP files>
# PASS: 18 files

git diff --check
# PASS (clean)
```

Confirmed product decisions 2026-06-20 (slice 5):

- Completion now requires `scheduled` (no `approved` bypass); the scheduling action
  moves an approved attempt to `scheduled` by assigning it to a unit-scoped session.
- Slot capacity defaults to room capacity and cannot exceed it; per-session
  assignment is capped by the session's `expected_candidates`.
- Invigilation attaches to the room slot (not a single session) because one slot may
  host multiple unit-scoped sessions; invigilators are existing `lectures` rows.

Validation gap (unchanged from prior slices):

- `vue-tsc --noEmit` still OOMs in the dev container; no frontend source changed in
  this slice.
- Deferred to later slices: student/invigilator timetable merge of assigned
  exam-resit sessions (Platform contract), exam-resit overdue/reminder monitoring,
  paid cancellation refund/reversal, legacy `exam_resit_fee` backfill, the Academic
  staff scheduling UI/routes, and student portal/API.

## Slice Evidence - 2026-06-20 (Slice 6: Legacy exam_resit_fee backfill / reconciliation)

Reconciled legacy `exam_resit_fee` Finance charges (created before the exam-resit
source contract, so unlinked to any `ExamResitAttempt`) into Academic exam-resit
sources, and reported the charges that cannot be matched safely. Hard gate
(Finance charge linkage + Academic source creation). Backend-only, no schema
migration (resolves open question 6: **reconciliation action + exception report**,
not a blind migration — mirroring slice-1's `academic:backfill-failure-reason`).

- A "legacy" charge = ACTIVE `exam_resit_fee` `FinanceCharge` whose `source_type`
  is not `ExamResitAttempt` AND that no attempt references via `finance_charge_id`.
  Voided charges and already-linked charges are excluded (not completeness evidence).
- `ReconcileLegacyExamResitFeesAction::run(dryRun)` matches each legacy charge to
  exactly ONE exam-resit-eligible failed academic record of the same student
  (`grade_status=final`, `is_passed=false`, no `override_pass`, `failure_reason =
  grade_failed`, attendance recorded — the same eligibility split as
  `CreateExamResitAttemptAction`; attendance/both-fail are ineligible). The unit is
  resolved from a unit code named in the charge `description`, otherwise from a
  single eligible candidate.
- A safe match creates a legacy-linked `ExamResitAttempt` (`status=approved`,
  `policy_snapshot.legacy_backfill=true` with the original charge source preserved)
  and **repoints the charge** (`source_type/source_id` → the new attempt) so future
  idempotency and Fee Monitor missing-fee inference see a queryable Academic source.
- **Payment evidence is derived, never asserted**: `hq_fee_status` is set to `paid`
  (with `paid_at`) when `FinanceCharge::is_fully_paid` (settlement-derived),
  otherwise `charge_created`. `attempt_number` stays null (a legacy fee does not
  prove a sat result).
- Charges that cannot be matched (no eligible failed record — e.g. the student
  passed or only failed by attendance; or ambiguous multiple units) are emitted as
  exceptions (`no_eligible_failed_record` / `ambiguous_multiple_units`) instead of
  guessing an Academic source.
- Idempotent: a repointed charge is no longer "legacy", so a second run is a no-op.
- Thin CLI wrapper `academic:reconcile-legacy-exam-resit-fees` (`--dry-run`,
  `--report-exceptions`). Must run AFTER `academic:backfill-failure-reason` so the
  failed records are already classified into the grade-fail lane.

Commands run:

```bash
./scripts/dev.sh test tests/Feature/Academic/ExamResitLegacyBackfillTest.php
# PASS: 12 tests, 49 assertions

./scripts/dev.sh test tests/Feature/Academic/ExamResit tests/Feature/Academic/RetakeCourse
# PASS: 107 tests, 347 assertions (no regressions)

./scripts/dev.sh test tests/Feature/Finance/ExamResitChargeActionTest.php \
  tests/Feature/Finance/RetakeResitHqWorklistTest.php \
  tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php
# PASS: 24 tests, 65 assertions (finance exam-resit handoff unaffected)

./scripts/dev.sh composer exec pint -- --test \
  app/Modules/Academic/Actions/ReconcileLegacyExamResitFeesAction.php \
  app/Console/Commands/Academic/ReconcileLegacyExamResitFeesCommand.php \
  tests/Feature/Academic/ExamResitLegacyBackfillTest.php
# PASS: 3 files

git diff --check
# CLEAN
```

Validation gap (unchanged from prior slices):

- `vue-tsc --noEmit` still OOMs in the dev container; no frontend source changed in
  this slice.
- Deferred to later slices: Academic + HQ worklist UI surfaces, the
  student/invigilator timetable merge (Platform contract), exam-resit
  overdue/reminder monitoring, paid cancellation refund/reversal, flipping
  `FeeMonitorAcadRetGate::missingInferenceEnabled()` to enable Finance Reporting
  missing-fee inference, and student portal/API.

## Slice Evidence - 2026-06-20 (Slice 7: Academic Thi lại UI + worklist)

Built the full Academic exam-resit (thi lại) staff UI + the backend wiring it
needs. Course retake (Học lại) UI already shipped (slice 1); HQ fee worklist
already lives in Finance `DngWorklist.vue` (`ListDngWorklistQuery` surfaces HL +
PTL). This slice fills the Thi lại gap: worklist, create-from-eligible, cancel,
scheduling authoring (room slots / unit sessions / invigilators), assign-to-session,
and result entry. New page + sidebar entry (NOT a tabbed refactor of Học lại); no
Approval Queue tab; `FeeMonitorAcadRetGate` flip deferred (separate Finance slice).

Backend (TDD, no schema migration — slice-5/6 tables already exist):

- `CancelExamResitAttemptAction`: cancellable = requested/approved/scheduled;
  voids an unpaid `charge_created` charge + cancels the awaiting `exam_resit_fee`
  DNG (mirrors retake cancel); **blocks paid cancellation** (HQ refund/reversal
  deferred) and preserves payment evidence; never consumes `attempt_number`.
  Added `ExamResitAttempt::CANCELLABLE_STATUSES` + `isCancellable()`.
- `ListExamResitAttemptsQuery`: DB-paginated, campus/semester/status-scoped
  worklist with derived `payment_state` (Finance-evidence-derived, never a manual
  flag), `schedule_state`, `result_state`, `operation_state`, summary counts, and
  `available_actions` (schedule/complete/cancel; cancel hidden when paid).
- `ListExamResitEligibleStudentsQuery`: inverse of the retake lane — `grade_failed`
  finals only, attendance recorded (`not_recorded` blocked), excludes passed units
  and records with an in-flight/consumed attempt. No curriculum-units join (the
  failed record already proves enrolment).
- `ListExamRoomSlotsQuery`: campus slots with sessions(+unit), invigilators(+lecturer),
  seat usage.
- `ExamResitAttemptController` (index/create/store/scheduleForm/schedule/completeForm/
  complete/cancel) + `ExamScheduleController` (index/storeRoomSlot/storeSession/
  assignInvigilator). Stores wrap the existing slice 1–5 actions; conflict/capacity/
  payment guards stay in the actions and surface as 422 validation errors.
- 8 FormRequests, routes under `academic.exam-resit.*` + `academic.exam-schedule.*`,
  6 new permissions (`view/create/cancel/schedule/complete_exam_resit`,
  `manage_exam_schedule`) in `config/permission.php`.

Frontend (Inertia v3 + Vue 3, swinx-frontend standards):

- `Academic/ExamResit/Index.vue` (useDataTable worklist, summary cards, derived
  badges, cancel dialog), `Create.vue` (eligible picker), `Schedule.vue`
  (assign-to-session), `Complete.vue` (result entry + higher-score hint),
  `Schedule/Index.vue` (slot/session/invigilator management, inline forms to dodge
  modal date/select portal gotchas). Sidebar: "Thi lại" + "Lịch thi lại".

Commands run:

```bash
./scripts/dev.sh test tests/Feature/Academic/ExamResit
# PASS: 86 tests, 302 assertions (30 new across 6 new test files)

./scripts/dev.sh test tests/Feature/Academic/ExamResit tests/Feature/Academic/RetakeCourse
# PASS: 137 tests, 504 assertions (no retake regressions)

./scripts/dev.sh test tests/Feature/Finance/ExamResitChargeActionTest.php \
  tests/Feature/Finance/RetakeResitHqWorklistTest.php \
  tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php
# PASS: 24 tests, 65 assertions (Finance handoff unaffected)

./scripts/dev.sh composer exec pint -- --test <23 changed PHP files>
# PASS (after auto-fix)

./scripts/dev.sh pnpm exec prettier --check <5 new Vue> + eslint <5 new Vue + menu-sidebar.ts>
# PASS

git diff --check
# CLEAN
```

Confirmed product/scope decisions 2026-06-20 (slice 7):

- Thi lại ships as its own page + sidebar entry, mirroring Học lại; Học lại is NOT
  refactored into a shared tabbed shell (avoid churn on working code).
- Approval Queue tab omitted (student self-request out of first release; model
  already supports `requested`).
- Paid exam-resit cancellation is blocked in the Academic action and routed to the
  (deferred) HQ refund/reversal flow; payment evidence preserved.
- `cancel` action is only offered in the worklist when it will succeed (cancellable
  status AND not paid).

Validation gap:

- `vue-tsc --noEmit` still OOMs in the dev container; per-file eslint + prettier used
  instead (whole-project type-check belongs on host/CI).
- Deferred: `FeeMonitorAcadRetGate::missingInferenceEnabled()` flip (separate Finance
  reporting-behavior slice), student/invigilator timetable merge (Platform contract),
  exam-resit overdue/reminder monitoring, paid-cancellation refund/reversal handling,
  and student portal/API.

## Slice Evidence - 2026-06-20 (Slice 8: Fee Monitor missing-fee inference for retake/resit)

Enabled the Finance Reporting Fee Monitor to infer **missing** `retake_fee` /
`exam_resit_fee` from the Academic source contract (now that slices 1–7 + the
slice-6 legacy reconciliation exist). Hard gate (Finance reporting behavior).
Backend-only, no schema migration. This is the deferred follow-up the prior slices
named; it was correctly NOT one boolean — the Fee Monitor had no expected-population
enumeration for retake/resit, only existing-charge rows.

- `ListFeeMonitorQuery::rowsFromRetakeResitExpectation()` enumerates approved
  Academic sources for the billed semester/campus and emits a `missing` row when no
  Finance charge exists yet:
  - course retake: `CourseRetakeRegistration` in `NON_TERMINAL_STATUSES`
    (approved/payment_pending/paid); `STATUS_CANCELLED`/`ENROLLED` are terminal and
    excluded. Matches `charge_semester_id` (fallback `semester_id`).
  - exam resit: `ExamResitAttempt` status in approved/scheduled/completed/no_show and
    `hq_fee_status != cancelled`; matches `charge_semester_id`.
- **Dup-free**: a row is emitted only when `resolveChargeForStudent()` is null;
  charged rows stay owned by `rowsFromExistingSourceCharges` (generated/voided).
- **Eligibility split is automatic**: the Fee Monitor only sees CREATED sources, and
  `CreateExamResitAttemptAction` already blocks attendance/both failures — so an
  attendance-failed student never has an attempt and never shows a missing resit fee.
- Missing rows carry a `batch_handoff` pointing at the HL/PTL **DNG worklist** (the
  HQ surface that actually creates these fees, not Batch Studio).
- Flipped `FeeMonitorAcadRetGate::missingInferenceEnabled()` → `true`
  (`excludedMissingSources()` now `[]`). While the gate was off, the new builder was
  inert (missing rows dropped by the `applyRowFilters` missing-inference filter), so
  the change is isolated to the gate flip.

Commands run:

```bash
./scripts/dev.sh test tests/Feature/Finance/Reporting/FeeMonitorRetakeResitInferenceTest.php
# PASS: 4 tests (missing retake + missing resit + no-dup-when-charged + cancelled-excluded)

./scripts/dev.sh test tests/Feature/Finance/Reporting tests/Unit/Finance/Reporting
# PASS: 45 tests, 323 assertions (gate unit test + Fee Monitor view meta flipped)

./scripts/dev.sh test tests/Feature/Finance/ExamResitChargeActionTest.php \
  tests/Feature/Finance/RetakeResitHqWorklistTest.php \
  tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php
# PASS: 24 tests (HQ handoff unaffected)

./scripts/dev.sh test tests/Feature/Academic/ExamResit tests/Feature/Academic/RetakeCourse
# PASS: 137 tests (Academic sources unaffected)

./scripts/dev.sh composer exec pint -- --test <changed Finance files + tests>
# PASS

git diff --check
# CLEAN
```

Confirmed decisions 2026-06-20 (slice 8):

- Fee Monitor expected-population for retake/resit comes ONLY from approved Academic
  sources, never from raw failed `academic_records` (honors the story contract).
- Per-(student, source, semester) granularity is kept (matches existing Fee Monitor
  rows); a student with multiple uncharged attempts surfaces one missing resit row.
- Gate is a hardcoded flip (not env/config) — consistent with how it shipped; revert
  is a one-line change if reporting needs to be paused.

Remaining deferred (unchanged): student/invigilator timetable merge (Platform
contract), exam-resit overdue/reminder monitoring, paid-cancellation refund/reversal
handling, and student portal/API.

## Slice Evidence - 2026-06-20 (Slice 9: Student + invigilator timetable merge)

Closed the Platform acceptance contract: assigned exam-resit sittings merge into the
student timetable, and assigned invigilation duty merges into the lecturer timetable.
Read-only, targeted (never leaks a shared room slot to unassigned students), additive
to the existing V1 API shape. No schema migration.

- `ExamResitTimetableQuery` (Student): returns ONLY the student's own
  scheduled/completed/no_show attempts that have an assigned session, with
  unit/room/time/invigilators/instructions/payment status, in the schedule range.
  Wired into `TimetableService` (constructor + `generateWeeklySchedule`) as a per-day
  `exam_resits` bucket, and surfaced through `TimetableResource.formatWeeklySchedule`
  (the Resource rebuilds each day with a fixed key set, so the new key had to be added
  there too — a service-only key would be dropped).
- `InvigilationDutyQuery` (Lecturer): returns ONLY the lecturer's assigned
  `ExamRoomSlotInvigilator` rows in the date range (slot not cancelled), with
  role/room/time and the sessions inside the slot. Added as a top-level
  `invigilation_duties` key on `LecturerTimetableService.getTimetable` +
  `LecturerTimetableResource.toArray` (same fixed-key-rebuild caveat).
- Drive-by fix: `Student\TimetableController` passed the `semester_id` query string to
  `getStudentTimetable(?int)` → a 500 TypeError on any call with a semester. Cast to
  int in `index()`/`weekly()`. (This bug was latent because the only existing student
  timetable test died earlier on a missing `intake` factory field — also fixed.)

Targeting proof (tests): an assigned student/lecturer sees the item; a different
student/lecturer with no assignment sees an empty list, even though the session/slot
exists.

Commands run:

```bash
./scripts/dev.sh test tests/Feature/Api/V1/Student/StudentExamResitTimetableTest.php \
  tests/Feature/Api/V1/Lecturer/LecturerInvigilationTimetableTest.php \
  tests/Feature/Api/V1/Student/TimetableControllerTest.php
# PASS: 6 tests, 32 assertions (2 new student, 2 existing student now fixed, 2 new lecturer)

./scripts/dev.sh composer exec pint -- --test <changed timetable files>
# PASS

git diff --check -- <my changed files>
# CLEAN (my files; see WORKING-TREE NOTE below)
```

Lecturer side is proven at the service + Resource level (no lecturer API feature-test
auth pattern exists in the repo, and the actor middleware makes HTTP-level Sanctum auth
of a Lecture non-trivial); the test asserts both the service output and the Resource
passthrough so the API contract is covered without fighting the auth middleware.

WORKING-TREE NOTE (not caused by this slice): the repo working tree is in an
unresolved-merge state (conflict markers in `.codex/*`, `AGENTS.md`, `docs/*`,
`scripts/README.md`; staged `.khuym/*`) plus unrelated modified Canvas files, likely
from a khuym/codex session hook. Slice 9 was NOT committed pending that cleanup.

ACAD-RET-001 acceptance status after slice 9: Platform timetable-merge contract met.
Still deferred (not required to ship staff-only release): exam-resit overdue/reminder
monitoring, paid-cancellation refund/reversal handling, student self-request portal/API.
