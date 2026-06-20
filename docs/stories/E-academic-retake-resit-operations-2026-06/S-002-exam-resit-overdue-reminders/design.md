# Design

## Domain Model

`ExamResitAttempt` is the Academic source for PTL. Finance remains the owner of
fee creation, DNG push, reminder delivery, and payment evidence.

Reminder eligibility has three source states:

- `needs_charge_or_dng`: Academic source is approved/scheduled but has no active
  charge or pushed DNG request yet. It is visible for triage and handoff, not
  directly remindable.
- `remindable`: an active pushed DNG request exists, the obligation is unpaid,
  and lifecycle exception rules do not block reminders.
- `blocked`: the row is paid, cancelled, refunded/reversed, under lifecycle
  exception, missing student email, or otherwise not safe to remind.

Exam-resit overdue age is derived from the scheduled session time plus
`late_payment_grace_days_snapshot` (default 14). If a later actual sitting time
is introduced, it becomes the preferred deadline source.

## Application Flow

The existing `finance.operations.due-calendar` route remains the route, but it
becomes the new UI entrypoint under `Finance Office (New UI) -> Thu & Đối soát
-> DNG Due Reminders`.

`ListDueItemsQuery` should keep DNG request rows and add PTL source context by
joining or resolving linked `ExamResitAttempt` rows. It should also surface
Academic source rows that are overdue but still need charge/DNG creation.

Reminder send flow should stay Finance-owned:

1. Staff filters/selects rows on Due Reminders.
2. The page previews selected rows in-page, using the same safety contract as
   Batch Studio reminders where practical.
3. Staff confirms.
4. Existing Finance reminder actions send student/parent reminders for
   remindable rows.
5. Successful sends update `last_reminder_at` on the DNG request and, when
   needed, mirror `last_reminded_at` on the linked `ExamResitAttempt`.

## Interface Contract

Reuse the existing page route:

- `GET /finance/operations/due-calendar`
- route name `finance.operations.due-calendar`

Expected additional filters:

- `fee_type`: includes `PTL`
- `source`: `dng_request`, `exam_resit`
- `due_state`: `upcoming`, `due_today`, `overdue`, `needs_charge_or_dng`,
  `blocked`
- existing search/semester filters remain.

Rows should include source context when available:

- `source_type`, `source_id`
- `fee_type`
- `unit_code`, `unit_name`
- `exam_date`, `exam_start_time`, `exam_end_time`, `room`
- `unpaid_allowed_reason`
- `due_at`, `days_overdue`
- `last_reminder_at`
- `reminder_state`
- `handoff` target for `needs_charge_or_dng`.

## Data Model

No new tables are expected for the first slice.

Use existing columns:

- `exam_resit_attempts.payment_deadline`
- `exam_resit_attempts.payment_overdue_at`
- `exam_resit_attempts.last_reminded_at`
- `exam_resit_attempts.late_payment_grace_days_snapshot`
- `dng_payment_requests.due_date`
- `dng_payment_requests.last_reminder_at`

If the implementation needs to persist a computed deadline that is not currently
available, prefer an additive nullable column or source snapshot update with
tests; do not rewrite historical paid evidence.

## UI / Platform Impact

Move the sidebar entry from Legacy Finance to the new Finance Office IA. The
page remains a worklist with actions, not a passive report.

Batch Studio Reminders should no longer be the primary menu entry for daily
reminder triage. It may stay as the preview/commit engine or a deep route opened
from selected rows.

Academic `Thi lại` can show payment state and overdue badges, but sending
reminders remains in Finance.

## Observability

Reminder attempts must log source ids, DNG request ids, recipient type, skipped
reason, actor, and outcome. Logs must not expose sensitive student data beyond
existing Finance reminder conventions.

## Alternatives Considered

1. Keep only Batch Studio Reminders as the entrypoint. Rejected because staff
   must go through wizard steps before seeing the queue.
2. Create a separate read-only Due Reminders page and keep sending in Batch
   Studio. Rejected because it splits triage and action across pages.
3. Upgrade Due Reminders into a worklist/action page. Accepted because it keeps
   daily operations fast while preserving preview/confirm safety.
