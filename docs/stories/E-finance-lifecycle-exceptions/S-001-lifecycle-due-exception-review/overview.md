# Overview

## Current Behavior

`finance/operations/due-calendar` is the staff reminder queue for DNG payment
requests. It currently treats every `dng_payment_requests.status =
pushed_to_dng` row with a `due_date` as a due item, regardless of the linked
student lifecycle status.

That means students who have moved to `deferred`, `dropout`, or
`dropout_transfer` can still appear in overdue KPI counts and can still be
selected for generic student or parent reminder emails. These rows are real
finance records, but they are not normal active-collection work.

Existing DNG request audit/cancel pages already exist under
`/finance/dng/payment-requests`, and Finance settlement pages already own
charge, invoice, payment, and void lifecycle. The missing product surface is a
dedicated review queue that explains why a due item was excluded from normal
reminders and guides staff to the correct resolution path.

## Target Behavior

Create a separate Finance operations page for lifecycle-blocked due items:

- `finance/operations/due-calendar` remains the active reminder queue and
  excludes students outside `Student::FINANCIAL_STATUSES` from its default list,
  summary counts, bulk reminder actions, and export.
- A new page, proposed as `/finance/operations/lifecycle-exceptions`, lists open
  DNG due items whose student is no longer in a normal billable lifecycle state.
- The new page groups exceptions by lifecycle reason: deferred, dropout,
  dropout transfer, inactive/non-financial, and unknown status.
- Staff can inspect each row with linked student, DNG request, charge, invoice,
  defer case, and student-action context before deciding what to do.
- Destructive resolutions such as DNG cancellation or charge voiding require
  explicit permission, impact preview, and a staff reason. They must not run
  automatically just because the student is deferred or dropout.
- Non-destructive resolutions such as acknowledge, keep as debt, or route to
  settlement are auditable and keep the queue from reappearing as unreviewed
  work.

## Affected Users

- Finance operations staff who monitor overdue DNG requests.
- Campus finance managers who decide whether to cancel, void, defer, recover, or
  keep debt after a student lifecycle change.
- Student operations/academic staff who need clear links from finance records to
  defer/dropout decisions.

## Affected Product Docs

- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`
- `docs/code-standards.md`
- `docs/features/finance/dng-payment-integration.md`
- `docs/features/finance/tuition-settlement-model-v2.md`

## Portal Impact

None. This story affects admin/staff web Finance pages only. It must not change
`/api/v1/student/*`, `/api/v1/lecturer/*`, student auth/context, lecturer
auth/context, or portal-visible response contracts.

## Non-Goals

- Do not auto-cancel every overdue DNG request for deferred/dropout students.
- Do not auto-void linked finance charges from Due Calendar.
- Do not create a refund workflow in this story.
- Do not change DNG webhook, reconciliation, checksum, or provider protocol
  behavior.
- Do not expose this internal exception queue to student or lecturer portals.
- Do not replace `/finance/dng/payment-requests` as the full DNG audit surface.
