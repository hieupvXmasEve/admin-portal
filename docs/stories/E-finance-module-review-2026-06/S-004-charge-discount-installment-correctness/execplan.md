# Exec Plan

## Goal

Make charge generation, scholarship/discount application, installments, and DNG
pivots produce consistent money results across preview and execution.

## Scope

In scope:

- `FIN-04`, `FIN-05`, `FIN-06`, `FIN-07`, `FIN-08`, `FIN-09`, `FIN-10`, `FIN-12`.
- `FIN-10b` (model correctness): DNG pivot rows allocate the **installment being
  collected**, not a `charge.balance` slice. Adds nullable
  `finance_charge_installment_id` to `dng_payment_request_charges` and invariants
  INV-14/INV-15. (Webhook lock/idempotency race stays in `FIN-REV-005`; this is a
  model/amount-truth fix that belongs here.)
- `DB-12`.
- `UI-SAFE-3` where EGC preview/execute scope and page-local block overrides
  can mislead staff.
- Related generation drift from `FIN-30`, `FIN-31`, `FIN-32`, `FIN-33` if it
  touches the same paths.

Out of scope:

- Public webhook checksum hardening.
- Final DB constraints.
- BOD dashboard.

## Risk Classification

Risk flags:

- Data model.
- Public contracts.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Money correctness.
- Existing charge generation behavior.

Portal impact:

- None.

## Work Phases

1. Discovery: map every scholarship, voucher, EGC, installment, and DNG pivot
   calculation path.
2. Confirm EGC fee source decision with product owner before code changes.
3. Write preview/execute parity tests.
4. Prove EGC preview scope matches execution scope, including page-local
   `block_count` overrides and non-visible eligible students.
5. Apply scholarship cap and active-charge filtering consistently.
6. Fix model casts and amount refresh behavior.
7. Revalidate installment totals after discount changes.
8. Allocate rounding remainder to keep DNG pivot sum equal to request amount.
9. Fix or explicitly block void flows that leave linked installments active or
   trigger hidden auto-reallocation.
10. Run targeted tests and invariant audit.

## Stop Conditions

Pause for human confirmation if:

- Product has not decided `15_000_000` vs `Unit.base_fee` for EGC.
- Existing historical charges would need mass recomputation.
- Installment drift requires changing already-pushed DNG amounts.
- Voiding linked installments would affect paid installments, registrations, or
  already-pushed DNG requests.
- Preview and execute cannot share logic without a larger refactor.
