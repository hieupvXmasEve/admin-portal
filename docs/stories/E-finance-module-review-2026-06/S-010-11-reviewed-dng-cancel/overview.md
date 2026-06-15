# Overview

## Current Behavior

The existing DNG cancel flow is a one-step confirm gated by
`create_finance_payments`. However, the underlying
`CancelDngPaymentRequestAction` can void linked charges when they exist. That
means the current gate is too broad for the destructive effect.

Student 360 Milestone 2 needs a safer cancel flow that shows impact, explains
blocking reasons, requires a human reason/acknowledgement, and adds the missing
void permission when linked charges would be voided.

## Target Behavior

Track Task 4 from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as its
own story: add a reviewed DNG cancel adapter and a read-only cancel-impact
endpoint.

The reviewed flow:

- keeps the existing cancel route intact for backward compatibility,
- adds an impact endpoint for linked charges and blocking reasons,
- requires `reason` and `acknowledged`,
- requires `void_finance_charges` when linked charges exist,
- delegates the actual cancel to `CancelDngPaymentRequestAction`.

## Affected Users

- Finance staff cancelling pending/pushed DNG requests.
- Finance leads responsible for destructive finance permissions.
- Auditors reviewing DNG cancel and charge-void history.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`

## Portal Impact

Portal impact: none.

This story targets authenticated admin/staff web routes only.

## Non-Goals

- Do not change DNG webhook/reconciliation state machines.
- Do not revive or modify cancelled DNG behavior.
- Do not create lifecycle exception events for this flow unless explicitly
  approved; DNG cancel uses its own audit sink.
- Do not remove the existing one-step route in this story.
- Do not build the cancel drawer UI; that is a later child story.
