# Overview

## Current Behavior

Student 360 has a Milestone 1 shell and, after the M2 backend read-model story,
will have the props needed for full display. The UI still needs dedicated
components for the four status cards, DNG state stepper, and dual ledger lenses.

## Target Behavior

Track Task 5 from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as its
own story: render the M2 read data without wiring write drawers yet.

The page should display:

- four status cards,
- DNG two-call lifecycle stepper,
- grouped "Sổ cái" lens,
- existing "Dòng thời gian" timeline lens,
- permission-aware visible affordances without submitting actions.

## Affected Users

- Finance staff inspecting a student's finance state.
- Finance admins reviewing DNG/installment/exception state at a glance.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`

## Portal Impact

Portal impact: none.

This story changes only the admin/staff Inertia page.

## Non-Goals

- Do not submit payments, allocation, DNG cancel, or installment push actions.
- Do not add new backend routes.
- Do not change money calculations.
- Do not implement `focus=<type>:<id>` highlight; that lands with action wiring.
