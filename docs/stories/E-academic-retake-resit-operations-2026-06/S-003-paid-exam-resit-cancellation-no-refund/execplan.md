# Exec Plan

## Goal

Allow Academic staff to cancel scheduled or paid exam-resit attempts while
preserving paid Finance evidence, avoiding refunds, safely cancelling unpaid
fee collection, and notifying the student by email.

## Scope

In scope:

- Update exam-resit cancellation behavior for `requested`, `approved`, and
  `scheduled` attempts before completion/no-show.
- Allow paid attempts to be cancelled with explicit no-refund acknowledgement.
- Keep paid DNG/payment evidence intact and visible in student Finance portal;
  ACAD-RET-004 defines the current local ledger treatment as voiding the
  cancelled source charge and exposing released cash as unapplied credit.
- For unpaid charge-created attempts, require explicit confirmation before
  voiding the pending `exam_resit_fee` charge and cancelling awaiting DNG
  collection.
- Send a student email after successful cancellation.
- Keep cancelled exam-resit sittings out of the student timetable.
- Add audit/log evidence for cancellation fee disposition and notification
  outcome.

Out of scope:

- External refund or provider reversal implementation.
- Auto-reallocation implementation.
- Student self-service cancellation.
- Cancelling completed or no-show exam-resit attempts.
- Changing DNG webhook/provider paid semantics.
- Reworking the wider retake/resit source lifecycle.

## Risk Classification

Risk flags:

- data-model
- audit/security
- external-systems
- public-contracts
- existing-behavior
- multi-domain
- student-portal
- weak-proof

Hard gates:

- Any cancellation/refund of paid DNG/payment evidence.
- Any email delivery or notification-template change.
- Any student API or portal contract change.
- Any weakening of completion/no-show cancellation guards.

## Work Phases

1. Discovery
   - Re-read `CancelExamResitAttemptAction`, `ListExamResitAttemptsQuery`,
     `ExamResitAttemptController`, `DueCalendar` reminder actions, notification
     email patterns, student timetable query/resource, and student finance API.
   - Confirm the exact linked DNG lookup path for direct `finance_charge_id` and
     `dng_payment_request_charges` pivot rows.
2. Data and audit shape
   - Decide whether additive `exam_resit_attempts` columns are needed for
     cancelled actor, fee disposition, and notification outcome, or whether the
     existing audit/event system is enough.
   - Keep the migration additive only.
3. Backend command
   - Refactor cancellation into a fee-aware action.
   - Paid branch: cancel Academic operation/schedule, keep paid DNG/payment
     evidence, void the cancelled source charge without auto-reallocation, mark
     fee disposition `kept_paid_no_refund`.
   - Unpaid charge-created branch: require confirmation, void active charge,
     cancel awaiting linked DNG, mark fee disposition `voided_unpaid_charge`.
   - No-charge branch: cancel Academic operation and mark `no_charge`.
   - Block completed/no-show attempts.
4. Notification
   - Add or reuse a notification/email template for exam-resit cancellation.
   - Send or queue email after the transaction commits.
   - Record skipped/failed notification when the student email is missing or
     delivery fails.
5. UI
   - Show cancel action for paid and scheduled eligible attempts.
   - Add fee-aware dialog copy and required acknowledgements.
   - Keep Inertia v3 `useForm` page-form conventions.
6. Student-facing proof
   - Verify cancelled attempts disappear from student timetable.
   - Verify paid payment/credit evidence remains visible through student finance
     endpoints and, if needed, student portal UI/types.
7. Validation and Harness
   - Run targeted Academic, Finance, notification, student API, frontend, and
     portal checks.
   - Update this story evidence and Harness matrix/trace with exact proof.

## Stop Conditions

Pause for human confirmation if:

- Product wants a paid cancellation to keep the exam-resit source active but only
  remove the schedule, instead of cancelling the attempt.
- Finance requires any external refund, provider reversal, or auto-reallocation
  after paid cancellation.
- DNG provider state cannot safely distinguish awaiting collection from paid
  evidence.
- Notification delivery has no reliable outbox/retry path.
- Student portal needs a new visible cancelled-exam-resit history surface beyond
  existing timetable and finance endpoints.
