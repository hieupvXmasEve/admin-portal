---
name: exam-resit-lane-gate-invariants
description: Exam-resit write-path guards — in-flight guard now IS in the action (old gap closed); live risks are list/gate parity on null failure_reason and no cross-lane guard vs course retake
metadata:
  type: project
---

`CreateExamResitAttemptAction::assertRecordCanEnterExamResit` now DOES hold the
in-flight/consumed-attempt guard, inside the `lockForUpdate` transaction
(re-verified 2026-08-14). The earlier "duplicate submit double-charges" note is
obsolete — do not re-report it.

Two lane invariants that are NOT enforced anywhere and keep resurfacing:

1. **List/gate parity.** The action still rejects `failure_reason === null`, but
   `ListExamResitEligibleStudentsQuery` does not filter on it. Most historical
   failed records have `failure_reason = NULL` permanently —
   `BackfillFailureReasonCommand` only labels students who already remediated —
   so the create page lists records the action will refuse.
2. **Cross-lane double charge.** Course retake (`ListRetakeCourseEligibleStudentsQuery`
   / `CreateRetakeCourseRegistrationAction`) excludes only `grade_failed`; exam
   resit (since 2026-08-14) excludes nothing. Neither action checks the other
   lane, so one failed record can carry both a retake fee and a resit fee.

**Why:** eligibility was originally partitioned by `failure_reason` alone, so the
two lanes were mutually exclusive by accident, not by an explicit guard. The
2026-08-14 user-requested relaxation removed that accident.

**How to apply:** any exam-resit or retake eligibility change must state which of
these two invariants it upholds. Related: [[finance-obligation-v2-migration-program]].
