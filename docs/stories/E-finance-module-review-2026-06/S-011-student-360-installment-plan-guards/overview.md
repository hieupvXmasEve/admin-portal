# Overview

## Current Behavior

Student 360 currently treats any `finance_charge_installments` row as evidence
that the student has an installment/trả góp plan. The `Trả góp` status card
loads all installments for a student, picks the first `pending` row, and exposes
`Đẩy kỳ tới` when the user has the generic record-payment action flag.

This is unsafe because the May 2026 backfill created one installment row for
every active positive charge as a technical compatibility row. Those rows do
not prove that staff approved a trả góp plan. For example, student `588`
(`AUH110281`) has pending Fall 2025 and Spring 2026 installment rows on charges
whose ledger balance is already zero, so Student 360 can point the button at a
settled historical charge instead of a staff-approved current plan.

## Target Behavior

Student 360 must distinguish a real staff-approved installment plan from
backfilled technical rows. The `Trả góp` card and any DNG installment push action
must only operate on collectable, approved, ledger-valid installments.

Backend write paths must reject stale or unsafe installment pushes even if the
UI still sends a request. Data repair must make historical backfilled rows stop
appearing as actionable installments when their charge is already settled,
voided, or not part of a staff-approved plan.

## Affected Users

- Finance staff using Student 360 actions.
- Finance admins reviewing DNG/payment correctness.
- Students whose DNG requests could be created from stale installment rows.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/stories/E-finance-module-review-2026-06/S-004-charge-discount-installment-correctness/`
- `docs/stories/E-finance-module-review-2026-06/S-010-student-360-full/`

## Portal Impact

Portal impact: none.

This story changes admin/staff Finance UI and backend finance write guards. It
does not change `/api/v1/student/*` or `/api/v1/lecturer/*` unless a future
implementation explicitly expands scope.

## Non-Goals

- Do not implement the fix in this pending story.
- Do not run destructive data repair without a reviewed target-row export and
  backup.
- Do not hide unsafe actions only in Vue while backend routes can still push
  stale installments.
- Do not redefine the whole Finance ledger model beyond what is needed to make
  installment eligibility explicit.
