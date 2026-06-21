# Academic Retake/Resit Operations 2026-06 Story Set

This epic defines the Academic-owned source of truth for course retake (`học
lại`) and exam resit (`thi lại`) before Finance Reporting depends on these fee
sources.

## Story Order

| Order | Story id | Story packet | Main scope | Depends on |
| --- | --- | --- | --- | --- |
| 1 | `ACAD-RET-001-retake-resit-operations` | `S-001-retake-resit-operations/` | Academic source/lifecycle for staff-created course retake and exam resit, future student-request approval path, HQ fee worklist, late-payment monitoring, schedule, audit, and Finance dependency contract. | Existing Academic Records, Course Retake, Finance Charge, Payment/Ledger flows |
| 2 | `ACAD-RET-002-exam-resit-overdue-reminders` | `S-002-exam-resit-overdue-reminders/` | Move Due Reminders into the new Finance UI and add PTL exam-resit overdue/reminder monitoring with same-page preview/send workflow. | `ACAD-RET-001`, Finance Due Reminders, DNG Worklist, Batch Studio reminder safety |
| 3 | `ACAD-RET-003-paid-exam-resit-cancellation-no-refund` | `S-003-paid-exam-resit-cancellation-no-refund/` | Allow staff to cancel scheduled or paid exam-resit attempts without refund, preserve paid Finance evidence in the student portal, confirm unpaid fee/DNG cancellation before voiding, and email the student after cancellation. | `ACAD-RET-001`, `ACAD-RET-002`, Finance charge/payment evidence, Notification email delivery, Student timetable/finance portal |

## Shared Rules

- Academic owns eligibility, approval/rejection, cancellation, schedule, class
  placement, and result-update decisions for retake/resit operations.
- HQ/Finance owns fee creation, DNG/payment, settlement, invoice, allocation,
  reminders, collection follow-up, and paid state truth.
- Staff-created retake/resit requests are auto-approved. Student-created
  requests are a future capability and must enter an approval queue before any
  source reaches the HQ fee worklist.
- Failed Academic records must expose a `failure_reason` so routing does not
  infer from `is_passed = false` alone. Grade failure can enter exam resit;
  attendance failure, including grade + attendance failure, routes to course
  retake.
- Final score/grade failure can enter `thi lại`; attendance failure must enter
  `học lại` and must not appear in the exam-resit eligibility list.
- `học lại` is a real course-offering/course-registration flow. Payment may
  happen after class placement, but the source must be created/listed before
  class placement. Unpaid rows warn staff after two weeks from the first retake
  class session start.
- `thi lại` is not modeled as a normal course offering by default; it uses a
  dedicated exam-resit attempt source. Scheduling before payment is allowed only
  with a required Academic reason, and unpaid rows warn staff after two weeks
  from the scheduled or actual resit sitting time unless configured otherwise.
- Exam-resit policy lives on the syllabus/admin policy and is snapshotted per
  attempt: max attempts defaults to one, fee amount, registration window, late
  payment grace days, and unpaid-sitting allowance.
- Payment expiry is an operation/worklist projection from deadlines, not a DNG
  provider status; expired payment rows do not consume exam-resit attempts.
- Existing legacy `exam_resit_fee` charges must be backfilled or reconciled into
  Academic exam-resit sources before Reporting treats them as completeness
  evidence.
- Finance Reporting must wait for this Academic source contract before treating
  course-retake or exam-resit fees as expected/missing sources.
- Due/reminder monitoring belongs to HQ/Finance. Academic may see payment and
  overdue state, but Finance owns reminder delivery and paid-state evidence.
- Paid exam-resit cancellation can be an Academic cancellation with no refund:
  paid charge, invoice, payment, payment applications, and paid DNG evidence stay
  intact and student-visible. Unpaid charge-created cancellation must require a
  clear staff confirmation before voiding the pending fee/DNG collection and must
  notify the student.
