# Overview

## Current Behavior

Milestone 1 created the Student 360 route shell at
`GET /finance/students/{student}` with identity, derived balances, optional
`focus`, audit link, and the deferred timeline ledger. The page is a valid
landing destination for global finance search, but it does not yet expose the
full operator summary planned for Milestone 2.

Staff still lack:

- Four Student 360 status cards for balance/allocation, active DNG, installments,
  and exception review.
- A grouped "Sổ cái" lens by semester -> invoice -> line.
- Permission flags that let the UI render available actions without guessing.
- A concrete unapplied payment id for the later allocation drawer.

## Target Behavior

Track Task 1 from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as its
own story: add the read-model data required by the full Student 360 surface.

The existing Student 360 route is augmented with additive props only:

- `status_cards`
- `ledger_groups` as an Inertia v3 deferred prop
- `actions`

Every money amount must come from existing Finance read models/services. This
story assembles and groups data; it does not create new money logic.

## Affected Users

- Finance staff using Student 360 as the primary student finance view.
- Finance admins validating what actions should be visible per permission.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`

## Portal Impact

Portal impact: none.

This story targets admin/staff web only and must not change
`/api/v1/student/*` or `/api/v1/lecturer/*` contracts.

## Non-Goals

- Do not add write routes, payment recording, DNG cancellation, or allocation.
- Do not change the Student 360 URL or destination component.
- Do not recalculate balances in a controller or Vue component.
- Do not add frontend cards/drawers; those are separate M2 child stories.
