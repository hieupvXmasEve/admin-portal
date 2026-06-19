# Validation

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
./scripts/dev.sh test tests/Feature/Finance/RetakeResitChargeSourceTest.php
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
./scripts/dev.sh test tests/Feature/Academic/RetakeCourse/CreateRetakeCourseRegistrationActionTest.php tests/Feature/Finance/RetakeCourseChargeActionTest.php tests/Feature/Finance/RetakeResitChargeSourceTest.php tests/Feature/Academic/ExamResit/CreateExamResitAttemptActionTest.php tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php
# PASS: 39 tests, 113 assertions

./scripts/dev.sh composer exec pint -- --test app/Models/AcademicRecord.php app/Models/CourseRetakeRegistration.php app/Models/FinanceCharge.php app/Models/SyllabusTemplate.php app/Models/ExamResitAttempt.php app/Modules/Academic/Actions/CreateRetakeCourseRegistrationAction.php app/Modules/Academic/Actions/CreateExamResitAttemptAction.php app/Modules/Academic/Http/Requests/RetakeCourse/StoreRetakeCourseRequest.php app/Modules/Academic/Queries/ListRetakeCourseEligibleStudentsQuery.php app/Modules/Academic/Queries/ListRetakeCourseRegistrationsQuery.php app/Modules/Finance/Actions/CreateRetakeCourseChargeAction.php app/Modules/Finance/Actions/CreateRetakeCourseChargeSimpleAction.php app/Modules/Finance/Queries/Dng/ListDngWorklistQuery.php database/factories/SyllabusTemplateFactory.php tests/Feature/Academic/RetakeCourse/CreateRetakeCourseRegistrationActionTest.php tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php tests/Feature/Finance/RetakeCourseChargeActionTest.php tests/Feature/Finance/RetakeResitChargeSourceTest.php tests/Feature/Academic/ExamResit/CreateExamResitAttemptActionTest.php
# PASS: 19 files

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
./scripts/dev.sh test tests/Feature/Finance/ExamResitChargeActionTest.php tests/Feature/Academic/ExamResit/SyncPaidExamResitAttemptsActionTest.php tests/Feature/Academic/ExamResit/CreateExamResitAttemptActionTest.php tests/Feature/Finance/RetakeResitHqWorklistTest.php tests/Feature/Finance/RetakeResitChargeSourceTest.php tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php tests/Feature/Academic/RetakeCourse/SyncPaidRetakeRegistrationsActionTest.php
# PASS: 39 tests, 147 assertions

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
