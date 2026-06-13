# Overview

## Current Behavior

`S-001-lifecycle-due-exception-review` adds a dedicated Finance operations page
for lifecycle-blocked DNG due items at
`/finance/operations/lifecycle-exceptions`.

The review state is represented by the latest lifecycle exception review for a
DNG payment request. Staff can acknowledge, keep as debt, route to settlement,
or run guarded destructive resolution actions. That latest state is useful for
queue filtering, but it is not enough to answer audit questions such as:

- who acknowledged the item first;
- what reasons were entered across multiple actions;
- what changed before and after a DNG cancellation attempt;
- whether an action failed, was retried, or was superseded;
- why a row disappeared from the active exception queue.

The existing DNG payment request audit pages remain provider-centric. They do
not present the Finance lifecycle decision trail as a staff workflow.

## Target Behavior

Create a shared Finance operations history page for lifecycle due exception
actions:

- Add a standalone history page, proposed as
  `/finance/operations/lifecycle-exception-history`, that staff can open from
  the Finance Operations menu without first finding an active exception row.
- Add a `View history` action from
  `/finance/operations/lifecycle-exceptions` that deep-links to the shared
  history page with the relevant DNG request or student filter applied.
- Show a searchable/filterable append-only history of Finance lifecycle
  exception actions across all DNG payment requests.
- Allow staff to search by student code/name, DNG request id, DNG item id,
  action type, actor, review status, lifecycle reason, and date range.
- Preserve the current/latest review record for active queue status and
  filtering, but store each staff action as a separate immutable history event.
- Show actor, timestamp, action type, from/to review status, DNG status before
  and after when available, reason, impact preview, and any failure metadata.
- Keep history reachable after the DNG request is cancelled, resolved, or no
  longer appears in the active lifecycle exception list.

## Affected Users

- Finance operations staff reviewing deferred, dropout, transfer, and
  non-financial lifecycle due items.
- Campus finance managers auditing why a DNG request was kept, routed,
  cancelled, or voided.
- Admin/audit users who need a chronological explanation of staff decisions.

## Affected Product Docs

- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`
- `docs/code-standards.md`
- `docs/features/finance/dng-payment-integration.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/stories/E-finance-lifecycle-exceptions/S-001-lifecycle-due-exception-review/overview.md`

## Portal Impact

None. This story affects admin/staff web Finance pages only. It must not change
`/api/v1/student/*`, `/api/v1/lecturer/*`, student auth/context, lecturer
auth/context, or portal-visible response contracts.

## Non-Goals

- Do not implement lifecycle exception history in this story packet.
- Do not replace the existing DNG payment request audit page.
- Do not add bulk history export.
- Do not make the history page depend on the active lifecycle exception list.
- Do not expose the history page to student or lecturer portals.
- Do not change DNG provider protocol, webhook, reconciliation, or checksum
  behavior.
- Do not decide new debt write-off, refund, or settlement policy.
