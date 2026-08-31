---
id: ADR-0052
title: "Retake/resit business-rule alignment: Finance-catalog pricing, paid-cancellation outcomes, no-show, live attempt policy"
status: accepted
owner: Academic Platform Team
last_verified: 2026-08-31
scope: architecture-decision
---

# Retake/resit business-rule alignment

**Context.** Owner-confirmed business rules (validation session 1,
`plans/260831-1213-retake-resit-business-rule-alignment/`) diverged from the
implementation in four areas: retake pricing source, cancellation of paid
sources, `no_show` recording, and the exam-resit attempt limit. This ADR
records the decisions that changed money or state-machine behavior.

**Decisions.**

1. **Retake fee comes from the Finance pricing catalog only.** The
   `unit.retake_fee` gate was removed from retake registration
   (`CreateRetakeCourseRegistrationAction`). The single resolve happens inside
   the Academic transaction through `FinanceIntakeContract::request()`; a
   missing catalog rule surfaces as a 422 message (parity with the resit
   lane). The `retake_fee` column on `CourseRetakeRegistration` is a display
   projection written from `FinanceIntakeResult::amount` at intake — never an
   independent source of truth.
2. **Cancelling a paid retake before class link releases money to unapplied
   balance.** `CourseRetakeRegistration::CANCELLABLE_STATUSES` now includes
   `paid`, guarded by `course_registration_id === null` in `isCancellable()`.
   The handoff uses `retake_course_cancelled_paid_keep_for_later`; the legacy
   direct `cancel()` transition rejects `paid` — paid sources must go through
   the Finance Cancellation Operation.
3. **Resit paid cancel has two explicit staff actions** (owner decision):
   `fee_outcome` on the handoff — `forfeit` (`exam_resit_cancelled_paid_no_refund`,
   the paid charge stands as revenue: no void, obligation stays accepted) or
   `keep_for_later` (`exam_resit_cancelled_paid_keep_for_later`: charge voided,
   captured cash released to unapplied balance). A new shared disposition
   `FinanceCancellationFeeDisposition::PaidReleaseToBalance` carries the
   keep-for-later outcome; both paid dispositions are terminal in
   `ResumeFinanceCancellationOnPaidEvidenceAction`,
   `reconcileLatePayment`, and the in-processor guard so paid evidence fired
   twice can never release twice.
4. **No-show consumes an attempt and forfeits the fee.**
   `MarkExamResitAttemptNoShowAction` transitions `scheduled → no_show`,
   sets `attempt_number` via the shared next-attempt-number helper (one
   definition of "consumed": `attempt_number IS NOT NULL`), and leaves the
   academic record unchanged. Cancelling before the sitting keeps the money;
   missing the sitting loses it.
5. **The resit attempt limit is live policy.** The create guard blocks only
   in-flight sources (`IN_FLIGHT_STATUSES`) plus
   `assertAttemptsRemaining` against the current syllabus
   `exam_resit_max_attempts` (`CreateExamResitAttemptAction::liveMaxAttemptsFor`).
   `IN_FLIGHT_OR_CONSUMED_STATUSES` was removed; display lists derive blocking
   from the same consumed definition. Raising the syllabus max re-opens the
   lane without code changes.

**Consequences.** Money mutations stay inside Finance
(`ProcessFinanceCancellationOperationAction` + `SettlementMutationGuard`);
Academic only projects dispositions into `cancellation_fee_disposition` /
`hq_fee_status`. Boundary rules of ADR-0026 are unchanged.

**Related.** `docs/features/academic/` runbooks; user guide
`docs-site/src/content/docs/academic-operations/course-delivery.md` (4 locales).
