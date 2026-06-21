# Validation

## Proof Strategy

Prove that cancellation first converts paid DNG evidence into local ledger
payment evidence, then preserves paid DNG/payment evidence while voiding the
cancelled source charge and releasing its allocation to unapplied credit without
auto-reallocation.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Shared bridge action finds direct and pivot-linked paid DNG requests and is idempotent. |
| Integration | Exam-resit cancellation with paid unbridged DNG creates payment/payment application, voids the source charge with `exam_resit_cancelled_paid_no_refund`, keeps DNG paid, releases allocation to unapplied credit, and cancels Academic source no-refund. |
| Integration | Course-retake cancellation with paid unbridged DNG creates payment/payment application, voids the source charge with `retake_course_cancelled_paid_no_refund`, keeps DNG paid, and releases allocation to unapplied credit. |
| Integration | Unpaid pending/pushed DNG remains cancelled only when the linked charge is unpaid and staff confirmed cancellation. |
| Platform | Student Finance unpaid filtering needs no code change because the source charge is voided and the paid amount appears as `unapplied_credit`. |
| Logs/Audit | Bridge/cancellation evidence is visible through payment id, DNG payment id, and cancellation disposition/status. |

## Fixtures

- Exam-resit active charge with invoice line and linked `paid_invoiced` DNG with
  `payment_id = null`.
- Course-retake active charge with invoice line and linked `paid_uninvoiced` DNG
  with `payment_id = null`.
- Awaiting DNG linked directly and through pivot for unpaid cancellation.
- Existing paid local charge to prove no duplicate bridge.

## Commands

Red checks captured before implementation:

```bash
./scripts/dev.sh test tests/Feature/Academic/ExamResit/ListExamResitAttemptsQueryTest.php --filter="linked paid dng evidence"
# Failed: payment_state was awaiting_payment instead of paid.

./scripts/dev.sh test tests/Feature/Academic/RetakeCourse/ListRetakeCourseRegistrationsQueryTest.php --filter="linked paid dng evidence"
# Failed: paid_waiting_class filter returned 0 rows instead of 1.
```

Green validation:

```bash
./scripts/dev.sh composer exec pint -- --format agent app/Modules/Finance/Actions/BridgePaidDngRequestsForChargeAction.php app/Modules/Academic/Actions/CancelExamResitAttemptAction.php app/Modules/Academic/Actions/CancelRetakeCourseRegistrationAction.php app/Modules/Academic/Http/Requests/ExamResit/CancelExamResitRequest.php app/Modules/Academic/Queries/ListExamResitAttemptsQuery.php app/Modules/Academic/Queries/ListRetakeCourseRegistrationsQuery.php app/Models/CourseRetakeRegistration.php tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php tests/Feature/Academic/RetakeCourse/CancelRetakeCourseRegistrationActionTest.php tests/Feature/Academic/ExamResit/ListExamResitAttemptsQueryTest.php tests/Feature/Academic/RetakeCourse/ListRetakeCourseRegistrationsQueryTest.php
# Passed.

./scripts/dev.sh test tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php tests/Feature/Academic/RetakeCourse/CancelRetakeCourseRegistrationActionTest.php tests/Feature/Academic/ExamResit/ListExamResitAttemptsQueryTest.php tests/Feature/Academic/RetakeCourse/ListRetakeCourseRegistrationsQueryTest.php tests/Feature/Academic/ExamResit/ExamResitAttemptControllerTest.php tests/Feature/Academic/RetakeCourse/RetakeCourseRegistrationControllerTest.php tests/Feature/Api/V1/Student/StudentExamResitTimetableTest.php tests/Feature/Finance/Dng/DngPaymentServiceTest.php tests/Feature/Finance/DngLedgerReconciliationInvariantTest.php
# Passed: 44 tests, 331 assertions.

git diff --check
# Passed.
```

Revision checks for the paid-cancellation/no-refund credit expectation:

```bash
./scripts/dev.sh artisan migrate:fresh --env=testing --no-interaction
# Passed.

./scripts/dev.sh test tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php --filter="bridges linked paid dng|releases the paid fee|refreshes session candidate"
# Passed: 3 tests, 24 assertions. Existing PHP deprecations in
# App\Services\UserEmailPreferenceService nullable parameters were emitted.

./scripts/dev.sh test tests/Feature/Academic/RetakeCourse/CancelRetakeCourseRegistrationActionTest.php --filter="bridges linked paid dng"
# Passed: 1 test, 9 assertions.

./scripts/dev.sh composer exec pint -- --format agent app/Modules/Academic/Actions/CancelExamResitAttemptAction.php app/Modules/Academic/Actions/CancelRetakeCourseRegistrationAction.php tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php tests/Feature/Academic/RetakeCourse/CancelRetakeCourseRegistrationActionTest.php tests/Feature/Academic/ExamResit/ExamResitAttemptControllerTest.php
# Passed.

./scripts/dev.sh test tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php tests/Feature/Academic/RetakeCourse/CancelRetakeCourseRegistrationActionTest.php tests/Feature/Academic/ExamResit/ListExamResitAttemptsQueryTest.php tests/Feature/Academic/RetakeCourse/ListRetakeCourseRegistrationsQueryTest.php tests/Feature/Academic/ExamResit/ExamResitAttemptControllerTest.php tests/Feature/Academic/RetakeCourse/RetakeCourseRegistrationControllerTest.php tests/Feature/Api/V1/Student/StudentExamResitTimetableTest.php tests/Feature/Finance/Dng/DngPaymentServiceTest.php tests/Feature/Finance/DngLedgerReconciliationInvariantTest.php tests/Feature/Finance/VoidReleaseAllocationTest.php
# Passed: 50 tests, 375 assertions. Existing PHP deprecations in
# App\Services\UserEmailPreferenceService nullable parameters were emitted.
```

## Acceptance Evidence

- Exam-resit cancellation with linked paid DNG now bridges to
  `payments`/`payment_applications`, voids the source charge with
  `exam_resit_cancelled_paid_no_refund`, marks the attempt paid/no-refund,
  keeps DNG paid, and leaves the paid amount as unapplied credit.
- The exam-resit controller regression checks `GetStudentBalanceQuery` directly,
  proving the Student 360 balance source exposes the released amount as
  `unapplied_credit`.
- Course-retake cancellation with linked paid DNG now bridges before cancel,
  voids the source charge with `retake_course_cancelled_paid_no_refund`,
  preserves the paid DNG row, and leaves the paid amount as unapplied credit.
- `/exam-resit` and retake worklist query classification treats linked paid DNG
  as paid evidence before any write-side bridge runs, so staff sees the paid
  no-refund path instead of unpaid fee cancellation.
- Student Finance formulas were not changed; the unpaid symptom is removed by
  voiding the cancelled source charge and letting the released allocation remain
  as student unapplied credit.
