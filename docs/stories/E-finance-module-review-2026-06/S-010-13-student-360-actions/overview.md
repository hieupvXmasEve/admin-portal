# Overview

## Current Behavior

Student 360 can display finance state after the display story, but staff still
cannot perform the planned M2 actions from the page. Existing write/read
endpoints live in separate backend stories and need a permission-aware UI layer
that keeps unsafe actions blocked before submit.

## Target Behavior

Track Task 6 from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as its
own story: add the Student 360 action menu, action drawers, and
`focus=<type>:<id>` highlight behavior.

The UI wires:

- "Thao tác" menu,
- record-payment drawer,
- allocation preview/apply drawer,
- reviewed DNG cancel drawer,
- retry next installment push,
- focus highlight/deep link behavior.

## Affected Users

- Finance staff completing day-to-day student finance actions.
- Finance admins verifying permission-aware destructive UI.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`

## Portal Impact

Portal impact: none.

This story changes only admin/staff Inertia UI.

## Non-Goals

- Do not add new money-write backend logic.
- Do not change `PaymentService`, `AllocatePaymentAction`, or
  `CancelDngPaymentRequestAction`.
- Do not alter DNG webhook/reconciliation behavior.
- Do not add student/lecturer portal APIs.
