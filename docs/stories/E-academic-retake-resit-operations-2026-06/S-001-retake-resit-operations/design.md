# Design

## Domain Model

Course retake (`học lại`) continues to use `CourseRetakeRegistration` as the
Academic source. This model should be hardened so it can represent both current
staff-created auto-approved records and a future student-requested approval
queue.

Target course-retake lifecycle:

```text
staff creates -> academic_approved/listed -> hq_fee_pending -> charge_created/payment_tracking -> class_placement_pending -> class_placed -> completed by normal course completion
student requests -> requested -> academic_approved/listed -> hq_fee_pending -> charge_created/payment_tracking -> class_placement_pending -> class_placed -> completed by normal course completion
student requests -> requested -> rejected
academic_approved/hq_fee_pending/charge_created/class_placement_pending/class_placed -> cancelled
```

Payment is a parallel HQ/Finance state, not an Academic approval state. A
course-retake source may exist before a target class is chosen, and may later be
class-placed before payment. If payment remains unpaid two weeks after the first
retake class session starts, the row must be an overdue warning for staff and HQ
with a reminder action.

Exam resit (`thi lại`) gets a dedicated Academic source such as
`ExamResitAttempt` or `AcademicExamResitAttempt`. It is not a normal course
offering. It tracks one whole-course exam/outcome resit attempt and links back to
the original Academic record that will receive the final-result update.

Exam-resit scheduling is modeled separately from attempts. The recommended
shape is:

```text
ExamRoomSlot      = one room + date/time block, backed by one room booking
ExamResitSession  = one unit/course exam in that room slot
ExamResitAttempt  = one student's approved resit attempt assigned to a session
```

One `ExamResitSession` is always for exactly one `unit_id`. Different sessions
for different units may share the same `ExamRoomSlot`, so Academic can put
multiple small retake exams in the same physical room/time block without making
them the same exam.

Target exam-resit lifecycle:

```text
staff allows/registers -> academic_approved -> hq_fee_pending -> charge_created/payment_tracking -> scheduled -> completed
student requests -> requested -> academic_approved -> hq_fee_pending -> charge_created/payment_tracking -> scheduled -> completed
student requests -> requested -> rejected
academic_approved/hq_fee_pending/charge_created/scheduled -> cancelled
scheduled -> no_show
```

Exam-resit payment is also an HQ/Finance state. A student may be scheduled or
allowed to sit the resit before payment only when Academic records an explicit
reason/note. The row remains visible to HQ as unpaid or overdue until canonical
Finance evidence confirms payment. By default, an unpaid exam-resit row becomes
overdue two weeks after the scheduled resit session starts, or after the actual
sitting time if Academic updates the session to a later actual time.

The default exam-resit policy is owned by the syllabus/admin policy: one attempt
is allowed by default. Academic/Admin configuration may allow more attempts when
the syllabus or policy permits. Every attempt snapshots the policy used at
creation time, including max attempts, `exam_resit_fee`, eligibility rule,
registration window, late-payment grace days, unpaid-sitting allowance, and
payment deadline.

Exam-resit attempt allowance is consumed only when the student actually sits the
resit and a result is recorded. Rejected requests, cancelled sources,
payment-expired sources, and no-show sessions do not consume the allowed attempt
count. Track request/source sequence separately from consumed `attempt_number`;
do not consume `attempt_number` at request creation time.

Eligibility split:

- Failed by final score/grade: eligible for exam resit when the syllabus/policy
  allows it.
- Failed by attendance: ineligible for exam resit and must use the course-retake
  flow.
- Failed by both grade and attendance: treated as attendance failure for this
  decision and therefore routed to course retake.

Eligibility must come from `academic_records.failure_reason` or an equivalent
derived finalization result, not from `is_passed = false` alone. Expected
failure reasons are `grade_failed`, `attendance_failed`, `both_failed`, and
`manual_failed`. Store a decision snapshot with final score, grade threshold,
attendance percentage, attendance threshold, resolved time, and resolver source.
`not_recorded` attendance blocks final eligibility until it is resolved. Count
`present` and `late` as attended, count `absent` as missed, and exclude
`excused` from the attendance denominator unless a later policy changes that
meaning.

## Application Flow

### Staff-Created Flow

1. Academic staff selects a failed academic record/student/unit.
2. System validates eligibility, campus, semester, syllabus policy, attendance
   failure versus grade failure, existing non-terminal sources, and source
   duplication.
3. System creates the course-retake registration or exam-resit attempt as
   Academic-approved.
4. Course retake can be listed before a target course offering is selected.
   Class placement is a separate Academic action and validates class capacity,
   campus, prerequisites, and schedule context at placement time.
5. The approved source appears in the HQ worklist for idempotent fee creation,
   DNG/payment request, collection, payment tracking, and reminder actions.
6. Payment confirmation updates the HQ/Finance payment state from canonical
   Finance evidence.
7. Course retake may be class-placed after it is in the Academic-approved list.
   Payment may happen after placement, but unpaid rows become overdue two weeks
   after the first retake class session starts.
8. Exam resit waits for targeted schedule assignment into a unit-scoped
   `ExamResitSession`, then completion updates the related `academic_records`
   final result. Scheduling before payment requires an explicit Academic reason.

### Future Student-Requested Flow

1. Student submits a request for an eligible course retake or exam resit.
2. System creates the source as `requested` with `request_origin = student`.
3. Academic staff reviews the queue and approves or rejects.
4. Approval moves the source to the HQ worklist and follows the same
   payment/schedule flow as staff-created records.
5. Rejection records reason, actor, timestamp, and leaves no active expected fee.

## Interface Contract

Initial staff-only routes should live under the Academic module and use
permissions parallel to existing retake-course routes:

- `view_retake_resit_operations`
- `create_retake_resit_operation`
- `approve_retake_resit_operation`
- `reject_retake_resit_operation`
- `cancel_retake_resit_operation`
- `schedule_exam_resit`
- `complete_exam_resit`
- `allow_unpaid_exam_resit`
- `send_retake_resit_payment_reminder`

Future student API impact must be explicit before implementation. The contract
should support a later `POST /api/v1/student/academic/retake-resit-requests`
without requiring a data-model rewrite, but that endpoint is out of the first
staff-only slice.

Errors must distinguish:

- ineligible because the course was already passed
- ineligible because the Academic record is not finalized
- ineligible because attendance or grade evidence is still `not_recorded`
- ineligible for exam resit because attendance failed
- max attempts exceeded
- missing fee/policy configuration
- duplicate active source for the same student/unit/semester/attempt
- source semester/campus mismatch
- charge already exists
- payment not complete and no approved unpaid-before-pay reason exists
- payment overdue beyond configured deadline
- payment expired by operation policy
- class placement missing for course retake
- class placement capacity exceeded without override reason
- schedule missing
- schedule room/time overlaps a normal class session
- schedule room/time overlaps an unrelated active room booking
- student has overlapping class timetable or another exam-resit session
- invigilator has overlapping teaching timetable or another invigilation
- result already completed/finalized for this attempt

## Data Model

Course retake should extend the existing source rather than invent a parallel
model. Expected additions may include:

- `original_semester_id`, `operation_semester_id`, `charge_semester_id`
- nullable `course_offering_id` until class placement, or a separate placement
  table/state that links the source to the selected class
- `request_origin`: `staff` or `student`
- `requested_by_student_id`, `requested_by_user_id`, `requested_at`
- `reviewed_by_user_id`, `reviewed_at`, `rejected_at`, `rejection_reason`
- `hq_fee_status`, `hq_fee_assigned_at`, `hq_fee_assigned_to_user_id`
- `policy_snapshot` JSON
- `unpaid_allowed_reason`, `unpaid_allowed_by_user_id`, `unpaid_allowed_at`
- `first_class_session_at`, `payment_overdue_at`, `last_reminded_at`
- `class_placed_by_user_id`, `class_placed_at`, capacity/prerequisite override
  reason fields if placement is allowed despite warnings
- optional `completed_at` if Academic needs a retake operation closure state
  separate from normal course completion

Academic records need a first-class failure routing contract:

- `failure_reason`: `grade_failed`, `attendance_failed`, `both_failed`,
  `manual_failed`, or null for passed/not-finalized records
- `failure_reason_snapshot` JSON with final score, grade threshold, attendance
  percentage, attendance threshold, attendance evidence state, resolver, and
  resolved time
- grade sync/finalization may overwrite score fields later, but must preserve
  grade history and the failure/resit decision history needed to audit why a
  retake/resit source was created

Exam resit needs a new table such as `exam_resit_attempts` with:

- source references: `student_id`, `academic_record_id`,
  `original_course_offering_id`, `unit_id`, `semester_id`, `campus_id`,
  `syllabus_template_id`, `original_semester_id`, `operation_semester_id`,
  `charge_semester_id`
- request/review fields: `request_origin`, requester, reviewer, reject reason
- lifecycle fields: `status`, `request_sequence`, nullable consumed
  `attempt_number`, `approved_at`,
  `payment_deadline`, `paid_at`, `scheduled_at`, `completed_at`, `cancelled_at`
- finance/HQ fields: `hq_fee_status`, `fee_amount`, `finance_charge_id`,
  charge-created actor/time, reminder timestamps, payment-overdue state
- policy fields: `policy_snapshot`, `max_attempts_snapshot`,
  `exam_resit_fee_snapshot`, `late_payment_grace_days_snapshot`,
  `allow_unpaid_sitting_snapshot`
- schedule fields: targeted session/event reference, date/time/room/location,
  schedule actor/time, unpaid-before-pay reason/actor/time,
  payment_overdue_at, last_reminded_at
- result fields: resit score/grade/pass state, previous final result snapshot,
  final chosen score, result actor/time
- audit fields: notes, cancellation/no-show/rejection reasons

Exam-resit scheduling needs a source separate from `class_sessions`, because
`class_sessions` is tied to `course_offerings` and would make exam resit look
like normal course delivery. Expected scheduling tables:

- `exam_room_slots`: `campus_id`, `room_id`, `room_booking_id`, `exam_date`,
  `start_time`, `end_time`, `capacity`, `status`, creator/approver/cancel audit,
  and notes. One slot may host multiple unit-scoped exam-resit sessions.
- `exam_resit_sessions`: `exam_room_slot_id`, `unit_id`, `semester_id`,
  `campus_id`, `syllabus_template_id`, `status`, expected/actual candidate
  counts, instructions, materials allowed, schedule/complete/cancel audit, and
  notes. One session belongs to exactly one unit/course.
- `exam_room_slot_invigilators`: `exam_room_slot_id`, `user_id` or
  `lecture_id`, role (`lead`, `assistant`, `backup`), assigned-by audit, and
  notes. Invigilators attach to the shared room/time block because one room slot
  may host multiple unit-scoped sessions.
- Optional later `exam_resit_session_examiners`: `exam_resit_session_id`,
  `user_id` or `lecture_id`, role, assigned-by audit, and notes when a specific
  unit needs a grader or subject examiner distinct from room invigilation.

Attempts are assigned to sessions through `exam_resit_attempts.exam_resit_session_id`
or a dedicated join table if later business rules require moving attempts
between sessions with history.

Indexes must support student/unit/semester lookup, active-state uniqueness,
finance source lookup, schedule lookup, and reporting joins. HQ charge creation
must lock the Academic source row and pre-check active Finance charges by
`source_type/source_id`; this is required because previous retake-charge flows
have had idempotency and semester-binding risks. Active Finance charges for
retake/resit sources also need a DB-level uniqueness guard, such as a generated
active-source key over `source_type/source_id/charge_type`, so concurrent HQ
actions cannot create duplicate active charges.

Semester fields must not be collapsed:

- `original_semester_id`: the semester of the failed Academic record.
- `operation_semester_id`: the semester in which retake/resit is being
  administered.
- `charge_semester_id`: the semester used by HQ/Finance for charge, invoice, DNG,
  and reporting.

HQ fee status must be explicit enough for both Academic and HQ screens. Expected
values include `not_required`, `hq_fee_pending`, `charge_created`, `payment_sent`,
`paid`, `overdue`, `payment_expired`, `cancelled`, and `refunded_or_reversed`
where the existing Finance state can support them. These values are display and
workflow projections from Finance evidence; they must not replace the canonical
ledger/payment state.

`payment_expired` is a retake/resit operation projection, not a required DNG
provider status. It is derived from the configured deadline and an HQ/system
expiry action. Expiry closes or flags the operation for follow-up, but does not
consume an exam-resit attempt.

Paid cancellation before study/exam must preserve `payments` and payment
applications as evidence. Cancelling the academic source may void/cancel the
remaining obligation, but any released money must become an explicit HQ-owned
refund, reversal, unapplied-credit, or reallocation candidate. No flow should
silently erase or reallocate paid evidence during Academic cancellation.

Scheduling conflict rules:

- Creating an `exam_room_slots` row must reject overlap with non-cancelled normal
  `class_sessions` in the same room.
- Creating an `exam_room_slots` row must reject overlap with unrelated active
  `room_bookings` in the same room.
- Multiple `exam_resit_sessions` may share the same `exam_room_slots` row; that
  is not a room conflict.
- Assigned students must not have overlapping enrolled class sessions or other
  exam-resit sessions.
- Assigned invigilators must not have overlapping teaching sessions or other
  invigilation assignments.
- Shared room slots must validate total expected candidates against room/slot
  capacity.

## UI / Platform Impact

Academic should get a staff workspace for retake/resit operations before Finance
Reporting resumes. The preferred shape is one Academic page with separate tabs:

- `Học lại`: existing course-retake records, eligibility, payment state, and
  class placement status.
- `Thi lại`: exam-resit attempts, policy/attempt count, payment state, schedule,
  and result status.
- `Approval Queue`: future-ready queue for student-submitted requests; may be
  hidden or empty in the first staff-only release.

The UI must show payment state from Finance evidence, not from a local manual
checkbox. Any manual override must be a separate audited action.

HQ must have a worklist for approved course-retake and exam-resit sources that
need fee creation, payment monitoring, overdue warning, and reminders. Academic
must still be able to see the HQ payment state and any unpaid-before-pay notes
while scheduling or completing the academic operation.

Student timetable impact is future-facing but required by the data contract:
exam-resit schedules must be targeted to assigned students and rendered in the
same calendar/timetable experience as classes. The timetable source should merge
normal `class_sessions` with assigned `exam_resit_sessions`, labeling the item as
`exam_resit` and showing unit, room, time, invigilator/contact, instructions, and
payment/schedule status. If existing campus `events` are reused for presentation,
student timetable queries must still filter by assigned attempt/session so a
resit event does not appear to every student on the campus.

Lecturer/staff timetable impact: assigned invigilators should see exam-resit
sessions in their own duty schedule. Invigilation is not the same as teaching
hours unless a later policy explicitly includes it in workload calculations.
This timetable merge is an implementation acceptance contract, not an optional
future UI note.

## Observability

Every lifecycle transition must produce auditable evidence: source id, previous
status, next status, actor, timestamp, reason, charge id, payment evidence, and
result-update evidence where applicable.

Finance handoff must trace:

- Academic source created/approved
- HQ fee worklist entry created
- Finance charge created or found idempotently by HQ
- DNG/payment request sent when applicable
- payment confirmed from canonical Finance state
- payment overdue warning/reminder sent
- exam room slot created and conflict-checked
- invigilator assigned or changed
- course-retake enrollment linked or exam-resit schedule/result completed

Exam-resit result update must trace:

- previous academic record final percentage, grade, pass state, credit earned,
  and prerequisite state
- resit score/result
- final chosen score/result after applying the higher-score rule
- actor, timestamp, and source attempt id
- whether GPA, prerequisite, progression, warning, and notification recalculation
  is required
- later Canvas sync or grade finalization overwrites, if any, with enough
  history to reconstruct the resit-applied value and the later overwritten value

## Finance Reporting Dependency

`FIN-REV-012-fee-tracking-reporting-requirements` depends on this story for
retake/resit expected-fee sources. Finance Reporting may display existing
charges, but it must not mark missing `retake_fee` or `exam_resit_fee` until the
Academic source contract is implemented and queryable.

Legacy `exam_resit_fee` charges that already exist without an Academic source
must be backfilled or reconciled to `ExamResitAttempt` records before Finance
Reporting treats them as expected-fee completeness evidence.
Charges that cannot be matched to a student, unit, failed Academic record,
semester, and payment evidence must go to a documented backfill exception report
instead of creating a guessed Academic source.

## Alternatives Considered

1. Build Finance Reporting first and infer retake/resit expected fees from
   failed `academic_records`.
   Rejected because it makes Finance guess Academic intent and will produce false
   missing-fee rows.
2. Model exam resit as a normal course offering.
   Rejected by default because course offerings trigger enrollment, completion,
   GPA/progression, survey, Canvas, and class-session side effects.
3. Use a manual paid checkbox in Academic.
   Rejected as primary truth because payment must come from Finance
   charge/payment/settlement evidence. Manual override can exist only as an
   audited exception.
