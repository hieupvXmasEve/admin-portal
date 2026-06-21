# Validation

## Proof Strategy

The story is complete when Academic staff can cancel a scheduled or paid
exam-resit attempt without triggering any external refund or provider reversal,
paid DNG/payment evidence remains visible to the student, unpaid pending fee
collection is cancelled only after explicit confirmation, and the student
receives a cancellation email.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Cancellation classifier derives `kept_paid_no_refund`, `voided_unpaid_charge`, or `no_charge`; completed/no-show attempts are blocked; paid state is derived from `hq_fee_status`, active fully paid charge evidence, or linked paid DNG evidence. |
| Integration | Paid scheduled attempt can be cancelled; attempt becomes cancelled or otherwise removed from active schedule; paid DNG/payment evidence remains; source charge is voided and released to unapplied credit without external refund or provider reversal. |
| Integration | Charge-created unpaid attempt requires confirmation; after confirmation the active `exam_resit_fee` charge is voided, awaiting DNG collection is cancelled, attempt becomes cancelled, and the student email is queued/sent. |
| Integration | No-charge attempt cancels without Finance mutation and still sends the student email. |
| Integration | Existing unpaid scheduled cancellation still works, but now uses the clearer confirmation copy and notification path. |
| E2E | Staff sees the correct cancel dialog on `/exam-resit` for paid, unpaid charge-created, and no-charge attempts; after submit the row updates and the student no longer sees the cancelled sitting in timetable. |
| Platform | Student portal timetable remains accurate; student Finance portal still shows paid payment/credit evidence for paid cancellations. |
| Performance | Cancellation locks one attempt and only linked charge/DNG rows; no broad scans across all charges or requests. |
| Logs/Audit | Audit/log records actor, reason, fee disposition, prior schedule, Finance mutation/no-mutation, and email outcome. |

## Fixtures

- Paid scheduled exam-resit attempt with active `exam_resit_fee` charge, paid
  invoice line, payment, payment application, and paid or reconciled DNG evidence.
- Scheduled but unpaid attempt with active charge and awaiting DNG request.
- Approved no-charge attempt.
- Completed attempt that must be blocked.
- No-show attempt that must be blocked.
- Student with email address.
- Student without email address, to prove clear validation or logged skipped
  notification behavior.

## Commands

Expected validation shape after implementation:

```bash
./scripts/dev.sh test tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php
./scripts/dev.sh test tests/Feature/Academic/ExamResit/ExamResitAttemptControllerTest.php
./scripts/dev.sh test tests/Feature/Academic/ExamResit/ListExamResitAttemptsQueryTest.php
./scripts/dev.sh test tests/Feature/Api/V1/Student/StudentExamResitTimetableTest.php
./scripts/dev.sh test tests/Feature/Finance/Dng tests/Feature/Finance/VoidReleaseAllocationTest.php
./scripts/dev.sh composer exec pint -- --test <changed PHP files>
./scripts/dev.sh npm exec -- eslint resources/js/pages/Academic/ExamResit/Index.vue
./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Academic/ExamResit/Index.vue
git diff --check
```

If student portal code changes:

```bash
cd FE/student-nuxt && pnpm lint && pnpm typecheck && pnpm build
```

If whole-project `vue-tsc` still OOMs in the dev container, record the exact
failure and rely on targeted frontend checks plus CI/host type-check evidence.

## Acceptance Evidence

2026-06-21 implementation evidence:

- Backend/API regression:
  `./scripts/dev.sh test tests/Feature/Academic/ExamResit/CancelExamResitAttemptActionTest.php tests/Feature/Academic/ExamResit/ExamResitAttemptControllerTest.php tests/Feature/Academic/ExamResit/ListExamResitAttemptsQueryTest.php tests/Feature/Api/V1/Student/StudentExamResitTimetableTest.php`
  passed: 22 tests, 137 assertions. The run emitted existing PHP deprecations in
  `App\Services\UserEmailPreferenceService` nullable parameters on lines 307 and
  337.
- PHP style:
  `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed and
  fixed import ordering in `CancelExamResitAttemptActionTest.php`.
- Frontend targeted checks:
  `./scripts/dev.sh npm exec -- eslint resources/js/pages/Academic/ExamResit/Index.vue`
  passed.
- Frontend formatting:
  `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/Academic/ExamResit/Index.vue`
  passed after formatting the Vue file.
- Whole frontend type-check:
  `./scripts/dev.sh npm run type-check` started `vue-tsc --noEmit` but was killed
  with exit code 137 in the dev container before producing type errors.
- Whitespace:
  path-limited `git diff --check` over ACAD-RET-003 files passed. Whole-worktree
  `git diff --check` still fails on pre-existing dirty whitespace in
  `resources/js/constants/menu-sidebar.ts`.
- Portal impact:
  no student portal code was changed because the student timetable/finance API
  response shape did not change; `./scripts/portal-status.sh` showed clean
  nested student and lecturer portal worktrees before implementation.
