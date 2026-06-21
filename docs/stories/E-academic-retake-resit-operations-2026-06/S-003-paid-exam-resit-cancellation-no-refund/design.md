# Design

## Domain Model

The story separates **Academic cancellation** from **Finance refund/reversal**.
Academic may cancel the exam-resit operation or schedule, but Finance payment
truth remains canonical.

Relevant source records:

- `exam_resit_attempts`: owns Academic operation state, schedule assignment,
  cancellation reason, fee status projection, and student notification audit.
- `exam_resit_sessions` and `exam_room_slots`: own scheduled room/time capacity.
  Cancelling one student attempt must update the session candidate count without
  cancelling the room slot or other unit sessions.
- `finance_charges`, `student_invoices`, `invoice_lines`, `payments`, and
  `payment_applications`: own fee and payment evidence. Paid attempts must keep
  paid DNG/payment evidence, while the later ACAD-RET-004 ledger contract voids
  the cancelled source charge and releases its allocation to unapplied credit.
- `dng_payment_requests` and `dng_payment_request_charges`: own provider payment
  request state. Awaiting unpaid requests may be cancelled; paid/reconciled
  requests must remain evidence.

Recommended cancellation fee disposition values:

- `kept_paid_no_refund`: paid attempt cancelled, Finance evidence retained.
- `voided_unpaid_charge`: unpaid active charge/DNG obligation cancelled.
- `no_charge`: attempt had no Finance charge yet.

The implementation can add explicit audit columns such as
`cancelled_by_user_id`, `cancellation_fee_disposition`,
`cancellation_notice_sent_at`, and `cancellation_notice_error`, or store the same
facts in the existing audit/event system if that is the local pattern chosen
during implementation discovery.

## Application Flow

1. Staff opens `/exam-resit` and clicks cancel for an attempt in
   `requested`, `approved`, or `scheduled`.
2. The UI shows a fee-aware confirmation:
   - Paid: "Cancel exam resit, keep paid fee recorded, no refund will be
     created."
   - Charge created but unpaid: "Cancel exam resit, void the pending fee, cancel
     awaiting DNG collection, and email the student."
   - No charge: "Cancel exam resit and email the student."
3. The backend command locks the attempt and derives current Finance state from
   `hq_fee_status` plus the linked active `FinanceCharge::is_fully_paid`.
4. For paid attempts:
   - Set the Academic attempt to cancelled.
   - Clear or detach the schedule assignment so the sitting leaves student and
     invigilator timetable views.
   - Keep `hq_fee_status = paid`, `finance_charge_id`, `paid_at`, payments, and
     paid DNG evidence.
   - Void the cancelled source charge and release its allocations to unapplied
     credit without auto-reallocation.
   - Record `kept_paid_no_refund`.
5. For charge-created unpaid attempts:
   - Require the explicit confirmation token/body from the FormRequest.
   - Void the active `exam_resit_fee` charge through the existing Finance void
     action.
   - Cancel awaiting DNG requests linked to the charge, including direct
     `finance_charge_id` and pivot links.
   - Set Academic attempt to cancelled and `hq_fee_status = cancelled`.
   - Record `voided_unpaid_charge`.
6. Send a student email after the database transaction succeeds. If email
   delivery is asynchronous, queue the notification and record the queued state.
   A failed email must be visible to staff or logs without rolling back the
   already-committed cancellation unless implementation discovery chooses an
   outbox pattern with retry.

## Interface Contract

Existing route family:

- `POST academic.exam-resit.cancel`

Expected request shape:

```text
reason: string, required, min length enforced by existing request
confirmation: string, required when the attempt has an unpaid active charge
acknowledge_no_refund: boolean, required true when the attempt is paid
```

Expected validation messages:

- Paid attempt without acknowledgement: staff must confirm that cancellation
  keeps paid evidence and creates no refund.
- Unpaid charge-created attempt without confirmation: staff must confirm that
  the pending fee/DNG collection will be cancelled.
- Completed or no-show attempt: cancellation is blocked.

The exam-resit worklist row must expose enough state for the UI to choose the
right dialog copy. At minimum it needs operation state, schedule state,
payment state, `hq_fee_status`, `finance_charge_id`, and whether the linked
charge is fully paid.

Student-facing contract:

- Timetable should not show cancelled exam-resit sittings as upcoming sessions.
- Finance endpoints must continue to show paid payments/credit evidence for
  paid attempts.
- If a student-facing exam-resit history/status endpoint is later added, it
  should show cancelled with the no-refund fee disposition for paid attempts.

## Data Model

Implementation discovery must decide the smallest migration needed. Likely
additive fields on `exam_resit_attempts`:

- `cancelled_by_user_id`
- `cancellation_fee_disposition`
- `cancellation_notice_sent_at`
- `cancellation_notice_error`

If the current audit log already captures actor, before/after state, and
notification outcome in a queryable way, avoid extra columns and document the
chosen evidence path in validation.

No migration may delete or mutate paid DNG/payment evidence. Paid cancellation
must not call provider reversal, external refund, or payment auto-reallocation
code.

## UI / Platform Impact

`resources/js/pages/Academic/ExamResit/Index.vue` must show the cancel action for
eligible paid and scheduled attempts before completion/no-show. The dialog copy
must change by fee state:

- Paid: destructive academic cancellation with no-refund acknowledgement.
- Unpaid charge-created: destructive cancellation plus fee/DNG cancellation
  confirmation.
- No charge: normal cancellation.

Student portal impact is expected through existing APIs:

- `/api/v1/student/timetable`: cancelled exam-resit schedule disappears from the
  timetable.
- `/api/v1/student/finance/*`: paid payment/credit evidence remains visible.

If TypeScript types or UI copy in `FE/student-nuxt` rely on exam-resit timetable
or finance status, update that nested repo separately.

## Observability

Record an audit event or model activity for:

- who cancelled,
- previous operation status and schedule id,
- fee disposition,
- whether an active charge/DNG request was voided/cancelled,
- whether a student notification was queued/sent/failed.

Application logs should include attempt id, student id, finance charge id, fee
disposition, and email outcome without logging private email body content.

## Alternatives Considered

1. Keep blocking all paid cancellations until a refund workflow exists. Rejected
   because the requested business rule is no refund, and staff still need to
   cancel the exam-resit operation.
2. Void/refund paid fees automatically. Rejected because the requirement says the
   paid fee remains in the portal and no refund is allowed.
3. Only unschedule the attempt and leave it approved. Rejected as the default
   because the user asked to cancel the resit; however, implementation may add a
   separate "unschedule only" action later if Academic needs rescheduling without
   cancellation.
