# Overview

## Current Behavior

The old `/finance/payments/create` page is not a Student 360 drawer flow and
does not provide the Milestone 2 "Ghi nhận thanh toán" experience. Staff need a
way to record a manual payment from Student 360, then allocate unapplied credit
through the preview/apply flow.

## Target Behavior

Track Task 3 from
`docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md` as its
own story: add a thin Student 360 adapter over
`PaymentService::recordPayment`.

The adapter validates request input, enforces permission and campus scope, calls
the existing service, flashes success through Inertia v3, and redirects back.

## Affected Users

- Finance staff recording counter/manual payments.
- Finance leads reviewing whether manual payments remain auditable and
  allocatable.

## Affected Product Docs

- `docs/features/finance/finance-office-ux-redesign-design.md`
- `docs/superpowers/plans/2026-06-15-finance-office-student-360-full.md`
- `docs/stories/E-finance-module-review-2026-06/S-010-finance-staff-workspace/`

## Portal Impact

Portal impact: none.

This is an admin/staff web route only.

## Non-Goals

- Do not auto-allocate the payment after recording.
- Do not rewrite `PaymentService`.
- Do not change DNG payment behavior.
- Do not build the drawer UI; that is a later child story.
