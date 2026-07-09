# 01 - Tuition term intake with installment materialization

Status: ready-for-human
Depends on: wave 1 issues 01, 02, 03
Program PRD: ../../finance-obligation-v2-migration/PRD.md
Portal impact: none

## What to build

Cut major tuition generation over to debit intake while preserving the existing installment and discount-adjacent behavior staff rely on. Batch Studio and legacy tuition generation should submit pricing facts, Finance should price from its catalog, and accepted tuition obligations should materialize into the same charge, invoice-line, and installment read model currently consumed by DNG, reports, and settlement.

## Acceptance criteria

- [x] Tuition generation creates one accepted obligation per chargeable student and materializes the expected charge and invoice line through the Finance materializer.
- [x] Installment behavior remains available for tuition rows according to the registry declaration.
- [x] Pricing uses intake facts plus Finance-owned rules, not caller-supplied final amounts.
- [x] Existing zero-amount skip behavior and current scholarship/voucher discount application behavior do not regress.
- [x] Tests cover the Batch Studio tuition path, idempotency, installment compatibility, and pricing-rule selection.

## Blocked by

- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/01-code-owned-obligation-type-registry.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/02-pricing-operations-coverage-warning.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/03-billing-accounts-payer-key.md`
