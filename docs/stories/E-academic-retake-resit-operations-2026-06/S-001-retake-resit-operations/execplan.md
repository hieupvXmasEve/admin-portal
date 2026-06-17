# Exec Plan

## Goal

Build the Academic-owned eligibility/source foundation and HQ-owned fee/payment
handoff before continuing Finance Reporting. The result should let Finance
consume clean expected-fee sources for `retake_fee` and `exam_resit_fee` without
guessing from failed grades.

## Scope

In scope:

- Harden existing course-retake registration lifecycle for staff-created
  auto-approved records and future student-request approvals.
- Add dedicated exam-resit attempt source, policy snapshot, fee source linkage,
  schedule, and result-update lifecycle.
- Add explicit Academic failure routing, then route grade-fail records to exam
  resit and attendance-fail or both-fail records to course retake.
- Decouple course-retake source/listing from class placement.
- Separate Academic source ownership from HQ fee/payment ownership.
- Add HQ worklist and late-payment warning/reminder contract for course retake
  and exam resit.
- Use Finance charge/payment/settlement truth for paid state.
- Allow scheduling/attendance before payment only with required Academic reason,
  while keeping HQ payment monitoring visible.
- Backfill or reconcile legacy `exam_resit_fee` charges into Academic exam-resit
  sources.
- Preserve audit evidence for all transitions.
- Keep student self-request UI/API out of the first release while making the
  model compatible with it.
- Update Finance Reporting requirements to depend on this source contract.

Out of scope:

- Finance Reporting implementation.
- Student self-registration UI/API in the first staff-only slice.
- Replacing Finance ledger, settlement, invoice, payment, or DNG internals.
- Treating exam resit as a normal course offering.

## Risk Classification

Risk flags:

- data-model
- authz
- audit
- finance
- payment
- student-portal
- academic-records

Hard gates:

- Any schema migration that changes existing course-retake behavior.
- Any code path that creates, voids, or links Finance charges.
- Any update to final `academic_records` result.
- Any change to Academic finalization or `failure_reason` calculation.
- Any backfill or reconciliation of existing `exam_resit_fee` charges.
- Any future student API/portal change.
- Any weakening of payment-derived state or audit requirements.

## Work Phases

1. Discovery
   - Re-read existing course-retake model/actions/queries/tests.
   - Re-read Academic finalization, Canvas grade sync, and attendance sync to
     locate the safest `failure_reason`/snapshot source.
   - Re-read Finance charge/payment/idempotency paths for retake charges.
   - Re-read student timetable event/session paths before choosing exam-resit
     schedule storage.
   - Re-read Academic record completion/GPA/progression paths before updating
     final grade from exam-resit completion.
2. Data contract
   - Add `failure_reason` and failure decision snapshot semantics for
     `academic_records` or an equivalent queryable finalization evidence table.
   - Decide exact migration shape for course-retake request/review fields.
   - Make course-retake class placement nullable/separate until a target class is
     chosen, with placement audit and override reasons.
   - Decide exact `exam_resit_attempts` schema, indexes, and status enum.
   - Separate request/source sequence from consumed exam-resit `attempt_number`.
   - Add explicit `original_semester_id`, `operation_semester_id`, and
     `charge_semester_id` semantics.
   - Add syllabus/admin policy fields for max attempts, `exam_resit_fee`,
     registration window, late-payment grace days, and unpaid-sitting allowance.
   - Design `exam_room_slots`, unit-scoped `exam_resit_sessions`, and
     invigilator assignments so multiple units can share one room/time block
     while still rejecting normal class-session and unrelated room-booking
     conflicts.
3. Course-retake hardening
   - Preserve current staff-created auto-approved behavior.
   - Add future-ready request origin/review semantics.
   - Create/list course-retake sources before class placement, and validate
     prerequisites/capacity/campus when Academic places the student into a class.
   - Move fee creation responsibility into the HQ worklist/Finance lane while
     preserving current behavior through a controlled migration path.
   - Add first-class-start plus two-week overdue warning/reminder rules.
   - Prove idempotent charge creation by `source_type/source_id`.
4. HQ fee worklist and payment monitoring
   - Add HQ worklist for Academic-approved course-retake and exam-resit sources.
   - Add idempotent charge creation, DNG/payment request handoff, payment status,
     payment overdue warnings, and reminder actions.
   - Add an active-source DB uniqueness guard for Finance charges so concurrent
     HQ actions cannot create duplicate active `retake_fee` or `exam_resit_fee`
     charges for the same Academic source.
   - Use the first retake class session start for course-retake overdue
     detection, and the scheduled or actual resit sitting time for exam-resit
     overdue detection. Both default to a two-week grace window.
   - Treat `payment_expired` as an operation projection derived from deadline and
     HQ/system action, not as a required DNG provider status.
   - For paid cancellation before study/exam, preserve payment evidence and
     expose explicit HQ-owned refund, reversal, unapplied-credit, or reallocation
     handling.
5. Exam-resit source
   - Add attempt creation, approval/rejection/cancellation, policy snapshot, and
     HQ fee handoff.
   - Enforce grade-fail versus attendance-fail eligibility split.
   - Add payment confirmation handling from Finance evidence.
   - Enforce attempt counting: no-show, cancellation, payment expiry, and
     rejection do not consume the allowed attempt count.
   - Consume `attempt_number` only when the student sat/completed the resit and a
     result is recorded.
6. Schedule and result
   - Add targeted student-visible schedule source.
   - Merge assigned exam-resit sessions into student timetable responses and
     assigned room-slot invigilation into invigilator/staff timetable responses.
   - Add shared room slot conflict checks against class sessions, unrelated room
     bookings, student timetable overlap, invigilator timetable overlap, and room
     capacity.
   - Add invigilator assignment on shared room slots and duty visibility.
   - Add completion action that updates final `academic_records` result and
     preserves previous score/result evidence.
   - Apply higher-score result rule and trigger or flag GPA/progression/warning
     recalculation.
   - Allow later Canvas sync or grade finalization to overwrite final score while
     preserving resit application evidence in grade history and attempt audit.
7. Legacy backfill
   - Reconcile existing `exam_resit_fee` charges without Academic sources into
     exam-resit attempts or documented legacy-linked rows.
   - Emit an exception report for legacy charges that cannot be safely matched
     to student, unit, failed Academic record, semester, and payment evidence.
8. UI and query surfaces
   - Add Academic staff workspace/worklists for `Học lại`, `Thi lại`, and
     approval queue readiness.
   - Add HQ worklist for fee creation, payment tracking, overdue warnings, and
     reminders.
   - Keep Academic actions as eligibility/schedule/result actions rather than
     fee mutation.
9. Validation and trace
   - Run targeted Academic, Finance, frontend, and formatting checks.
   - Record Harness trace, test output, and any deferred student portal gap.

## Stop Conditions

Pause for human confirmation if:

- Exam-resit scheduling cannot be targeted without broad student timetable/API
  changes.
- Shared room slots cannot be validated cleanly against existing room booking or
  class-session conflict rules.
- Invigilator assignment collides with an existing lecturer/staff timetable rule
  that needs a product decision.
- Existing course-retake behavior would be broken for current staff workflows.
- Payment state cannot be derived from canonical Finance evidence.
- Finance charge idempotency requires new global constraints beyond this story.
- Academic result update rules conflict with the grading-rule engine roadmap.
- `failure_reason` cannot be made queryable without broad grading/finalization
  changes.
- Canvas overwrite behavior conflicts with grade history/audit expectations.
- Student self-request scope becomes required for the first release.
- HQ ownership of fee creation conflicts with existing Academic-created charge
  behavior and needs a migration/compatibility decision.
- Higher-score result update cannot safely trigger GPA/progression recalculation
  or must defer it to a separate grading story.
