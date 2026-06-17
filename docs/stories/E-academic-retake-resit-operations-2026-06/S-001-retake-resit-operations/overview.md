# ACAD-RET-001 Retake/Resit Operations

## Status

planned

## Lane

high-risk

## Portal Impact

student

Student portal impact is contractual/future-facing in this story. The first
implementation may remain staff-only, but the lifecycle and data model must
support a later student self-request flow where staff approve or reject the
request.

## Current Behavior

Course retake (`học lại`) already has a partial Academic source through
`CourseRetakeRegistration`. Staff create the registration, it is immediately
approved, a `retake_fee` Finance charge is created, and payment later moves the
registration toward paid/enrolled state.

Exam resit (`thi lại`) has a Finance charge type (`exam_resit_fee`) but no
dedicated Academic source of truth for eligibility, approval, attempts, schedule,
payment gate, or final-result update. Finance Reporting cannot safely infer
expected exam-resit fees from failed `academic_records` rows.

## Target Behavior

Academic/Đào tạo owns the eligibility and operational source of truth for both
`học lại` and `thi lại` before Finance Reporting continues. HQ/Finance owns fee
generation, payment request, collection, payment monitoring, reminder, and
payment evidence.

For staff-created operations, Academic staff creates the retake/resit source and
it is auto-approved in the Academic lane. The approved source then appears in the
HQ worklist for fee creation and payment tracking. For future student-created
operations, the student can only submit a request; Academic staff must approve or
reject before the source appears in the HQ fee worklist.

Eligibility split:

- A student who fails by final score/grade may be allowed to take an exam resit.
- A student who fails by attendance is not eligible for exam resit and must go
  through course retake.
- A student who fails by both final score/grade and attendance is routed to
  course retake, not exam resit.

This split must be driven by an explicit Academic failure reason, not by raw
`is_passed = false` alone. The Academic result/finalization layer must store or
derive `failure_reason` values such as `grade_failed`, `attendance_failed`,
`both_failed`, and `manual_failed`, plus a decision snapshot with final score,
grade threshold, attendance percentage, attendance threshold, and resolved time.
`not_recorded` attendance evidence must block final retake/resit eligibility
until resolved. `present` and `late` count as attended; `absent` counts as
missed; `excused` is excluded from the attendance denominator unless a later
policy explicitly makes excused absence count as missed.

`học lại` remains a real course-retake flow: Academic staff registers the student
for course retake, the source is auto-approved/listed, and HQ creates/tracks the
`retake_fee`. The source may exist before a target course offering is chosen.
Class placement is a later Academic action after the student is already in the
course-retake list; payment may happen before or after placement. If payment is
still unpaid two weeks after the first retake class session starts, the system
must show a clear warning to staff and provide a payment-reminder action.

`thi lại` becomes a dedicated exam-resit attempt flow: the student retakes the
whole course exam/outcome without a normal course offering. Academic staff uses
an action to allow/register a student for exam resit. After registration succeeds,
the source moves to the HQ worklist for `exam_resit_fee` creation and payment
tracking. When payment succeeds, the student automatically becomes ready for the
exam-resit list and scheduling. Academic may also allow the student to take the
exam before payment, but the action must require a visible reason/note so
Academic and HQ both know why the payment gate was bypassed. The source must keep
both HQ payment tracking state and exam completion state visible. If the exam is
scheduled or taken before payment, the default overdue warning appears two weeks
after the scheduled resit session starts, or after the actual sitting time if
Academic records a later actual time.

Exam-resit scheduling uses a separate session model, not the course schedule
model. One exam-resit session is for exactly one unit/course. Different
exam-resit sessions for different units may share the same room and time block
when Academic deliberately groups them in the same exam room, but the shared room
block must not overlap normal class sessions or unrelated room bookings.

Payment confirmation is derived from canonical Finance charge/payment/settlement
truth. Manual paid confirmation, if later needed, must be an audited override
with reason, actor, timestamp, and immutable evidence.

If a paid course-retake or exam-resit source is cancelled before the student
studies/sits the exam, the active operation and charge/DNG obligation should be
cancelled with a required reason. Payment records must not be deleted; they remain
as Finance evidence for audit, refund, reversal, or later reconciliation. Any
released money becomes an unapplied credit, refund/reversal candidate, or
explicit reallocation candidate owned by HQ/Finance. The system must not silently
reallocate or erase the paid evidence during Academic cancellation.

Exam-resit result policy: when the student completes a resit, the final
`academic_records` result uses the higher score between the existing final score
and the resit score. The previous score/result must be preserved in attempt
history and `academic_records.grade_history`. Later Canvas sync or normal grade
re-finalization may overwrite `academic_records` again, but it must not erase the
resit application history or source audit.

Attempt counting policy: rejected requests, cancelled sources, payment-expired
sources, and no-show exam sessions do not consume an exam-resit attempt. A resit
attempt is consumed only when the student actually sits/completes the resit and a
result is recorded. Use a separate request/source sequence for audit if needed;
do not consume `attempt_number` at request creation time.

## Affected Users

- Academic/Đào tạo staff: decide eligibility, approve/reject, register allowed
  students, schedule, cancel, and complete course retake or exam resit academic
  operations.
- HQ/Finance staff: create fees, send/monitor payment requests, send reminders,
  track overdue payment, and rely on canonical paid state.
- Students: initially view status/schedule/payment obligations; later submit
  their own retake/resit requests for staff approval.
- BOD/Management: later consume clean Finance Reporting statistics only after the
  Academic source contract exists.

## Affected Product Docs

- `docs/stories/E-finance-module-review-2026-06/S-012-fee-tracking-reporting-requirements/overview.md`
- `docs/features/finance/finance-office-ux-redesign-design.md`
- Existing Academic retake-course story and validation packets under
  `docs/stories/retake-course-reconciliation/`

## Product Contract

- Staff-created `học lại` and `thi lại` sources are auto-approved in the Academic
  lane, then handed to HQ for fee creation/payment tracking.
- Student-created records are not available in the first staff-only release, but
  the model must support `requested -> approved/rejected`.
- Approval enables the HQ expected-fee worklist; rejection must not create a
  charge.
- Academic must not be the owner of fee creation or collection. HQ/Finance owns
  `retake_fee` and `exam_resit_fee` generation, DNG/payment requests, collection,
  reminders, and payment tracking.
- Course retake charge source is the course-retake registration. The registration
  may be listed before class placement; `course_offering_id`/class link must be
  nullable or represented by a separate placement state until Academic chooses a
  class.
- Exam-resit charge source is the exam-resit attempt.
- Charge creation must be idempotent by `source_type/source_id`, row-locked, and
  semester-bound to the Academic source. Active charges must have a DB-level
  uniqueness guard, such as a generated active-source key, so two concurrent HQ
  actions cannot create duplicate active charges for the same source/type.
- Payment state must come from Finance charge/payment/settlement truth.
- Payment expiry is a retake/resit operation projection derived from deadlines
  and HQ actions. It is not required to be a DNG provider status. Payment-expired
  exam-resit sources do not consume attempt allowance.
- Course retake class placement happens after Academic registration/listing. The
  student may pay after placement, but unpaid sources older than two weeks after
  the first retake class starts must be warning rows with reminder actions.
- Attendance-failed students, including students who also failed by score, must
  not appear in the exam-resit eligibility list.
- Exam resit may be scheduled before payment if Academic records a required
  reason/note. Payment state and exam completion state must remain visible to HQ
  and Academic. Unpaid exam-resit rows must warn staff after the configured
  deadline, defaulting to two weeks after the scheduled or actual resit sitting
  time.
- One exam-resit session is unit-scoped: it contains attempts for exactly one
  unit/course.
- Multiple unit-scoped exam-resit sessions may share one exam room/time block if
  capacity, invigilation, and conflict checks pass.
- Exam-resit room/time blocks must not overlap normal class sessions or
  unrelated active room bookings.
- Exam resit completion updates the final `academic_records` result and preserves
  each attempt as audit/history.
- Exam resit uses the higher score between the original final result and the
  resit result.
- Canvas grade sync is allowed to overwrite `academic_records` after a resit
  result has been applied, but grade history and attempt audit must keep the
  previous and resit-applied values.
- No-show, cancellation, payment expiry, and rejection do not consume exam-resit
  attempt allowance.
- Separate request/source sequence from consumed exam-resit `attempt_number`.
  `attempt_number` is consumed only when the student actually sits/completes and
  a result is recorded.
- Exam-resit syllabus/admin policy must include max attempts, fee amount,
  registration window, late-payment grace days, and unpaid-sitting allowance.
  Max attempts defaults to one and each attempt snapshots the policy.
- Every source stores `original_semester_id`, `operation_semester_id`, and
  `charge_semester_id` distinctly when applicable.
- Student timetable must merge assigned exam-resit sessions with normal class
  sessions; invigilator/staff duty timetable must merge assigned room-slot
  invigilation.
- Existing legacy `exam_resit_fee` charges must be backfilled or reconciled into
  Academic exam-resit sources before Reporting treats them as expected-fee
  sources.
- Finance Reporting must not mark missing course-retake or exam-resit fees from
  raw failed grades alone.

## Non-Goals

- Do not implement Finance Reporting in this story.
- Do not expose student self-registration UI/API in the first implementation
  unless a later slice explicitly expands portal scope.
- Do not replace canonical Finance ledger, settlement, invoice, DNG, or payment
  flows.
- Do not model exam resit as a normal `course_offerings` row by default.
- Do not use a manual checkbox as the primary paid confirmation source.

## Acceptance Criteria

- The story defines the lifecycle for staff-created and future student-created
  retake/resit requests.
- The story keeps `học lại` and `thi lại` as separate source models and fee
  sources.
- The story requires syllabus-owned default exam-resit policy with one allowed
  attempt by default and policy snapshot per attempt.
- The story requires explicit Academic `failure_reason` routing and decision
  snapshots for grade failure, attendance failure, both, and manual failure.
- The story requires staff approval/rejection before charge creation for future
  student requests.
- The story requires staff-created records to be auto-approved.
- The story records that Academic owns eligibility/approval, while HQ/Finance
  owns fee creation, collection, reminder, and payment monitoring.
- The story requires course-retake listing/source creation before class
  placement and forbids Academic-owned auto-charge creation in the new flow.
- The story requires Finance paid state to be derived from canonical Finance
  payment/settlement evidence.
- The story records paid cancellation treatment: keep payment evidence and move
  released money to explicit HQ-owned refund/reversal/reallocation handling.
- The story records payment expiry as an operation projection, not a required DNG
  status.
- The story requires exam-resit scheduling to support one-unit sessions, shared
  exam room/time blocks across multiple units, invigilator assignment, and
  conflict checks against class schedules and unrelated room bookings.
- The story records late-payment monitoring for both course retake and exam
  resit, including the two-week post-first-class deadline for course retake and
  the two-week post-scheduled/actual-sitting deadline for exam resit.
- The story records higher-score exam-resit result handling and attempt-count
  rules, including request sequence versus consumed attempt number.
- The story records that Canvas may overwrite final scores later while preserving
  resit attempt/history evidence.
- The story requires student and invigilator timetable merge contracts for
  exam-resit schedules.
- The story records the legacy `exam_resit_fee` backfill requirement.
- The story defines the integration contract Finance Reporting must wait for.
