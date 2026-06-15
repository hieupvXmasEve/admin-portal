# Overview

## Current Behavior

Student 360 can show balances after Milestone 1, and the existing settlement
write path can allocate a payment to a charge. What is missing for the full
Student 360 workflow is a payment-scoped preview that tells staff what an
unapplied payment would cover before they apply it.

The current write route posts directly. Auto-allocation preview exists in a
different legacy context, but it is not the manual Student 360 preview required
by the UX plan.

## Target Behavior

Track Task 2 from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as its
own story: add a read-only manual allocation preview endpoint.

The endpoint returns:

- payment id
- unapplied amount
- candidate invoice/charge lines
- amount that would be applied to each candidate line

The actual apply still reuses the existing `finance.payments.allocate` write
route. This story creates no new allocation write path.

## Affected Users

- Finance staff previewing how unapplied credit will be allocated.
- Finance admins reviewing permission/campus boundaries around allocation.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`

## Portal Impact

Portal impact: none.

This is an authenticated staff-web JSON endpoint under the Finance web surface,
not a student/lecturer portal API contract.

## Non-Goals

- Do not allocate or auto-allocate a payment.
- Do not create a new settlement write action.
- Do not change `AllocatePaymentAction`.
- Do not build the drawer UI; that is a later M2 child story.
