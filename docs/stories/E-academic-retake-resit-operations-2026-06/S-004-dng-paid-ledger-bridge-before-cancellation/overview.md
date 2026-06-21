# ACAD-RET-004 DNG Paid Ledger Bridge Before Cancellation

## Status

implemented

## Lane

high-risk

## Portal Impact

student

## Current Behavior

When an exam-resit or course-retake charge is paid at DNG but the local ledger
has not yet bridged that provider payment into `payments` and
`payment_applications`, the linked `finance_charges` row can remain `active`
with `balance > 0`. Student Finance surfaces that charge as unpaid because the
portal correctly derives unpaid state from local ledger balance, not directly
from DNG status.

The cancellation flows currently distinguish active charge payment only through
local `FinanceCharge::is_fully_paid`. That misses paid DNG evidence when the DNG
request is `paid_uninvoiced`, `paid_invoiced`, or `reconciled` but
`payment_id`/allocation is still missing.

## Target Behavior

Before cancelling an exam-resit or course-retake source, the system checks linked
DNG requests for paid provider evidence. If a linked DNG request is paid but not
bridged, the system bridges it into the canonical local ledger first by creating
or reusing the payment bridge and allocating it to the linked charge.

After the bridge:

- paid DNG/payment evidence remains intact and is never cancelled/refunded;
- the cancelled Academic source charge is voided with a paid no-refund reason;
- released paid allocations are not auto-reallocated, so the collected cash
  becomes unapplied credit (`phí dư`) on the student balance;
- student Finance no longer shows the cancelled retake/resit fee as a live
  unpaid obligation;
- cancellation does not cancel, refund, or reverse paid DNG/payment evidence;
- only unpaid live DNG requests are cancelled together with a voided unpaid
  charge.

## Affected Users

- Students: paid DNG fees stop appearing as unpaid after Academic cancellation.
- Academic staff: cancellation can safely proceed after paid DNG evidence is
  reconciled locally.
- Finance/HQ staff: provider-paid DNG records remain intact and local ledger
  evidence is created instead of hidden by portal query exceptions.

## Affected Product Docs

- `docs/stories/E-academic-retake-resit-operations-2026-06/README.md`
- `docs/stories/E-academic-retake-resit-operations-2026-06/S-003-paid-exam-resit-cancellation-no-refund/validation.md`

## Non-Goals

- Do not change student Finance balance formulas or unpaid filters.
- Do not cancel paid/reconciled DNG requests.
- Do not refund externally, cancel paid DNG/payment evidence, or auto-reallocate
  released paid cash for no-refund cancellations.
- Do not implement a broad historical reconciliation/backfill job.
