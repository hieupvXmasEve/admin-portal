# 01 - EGC level fee intake cutover

Status: done
Depends on: wave 1 issues 01, 02, 03 and wave 4 issues 01, 02, 03
Program PRD: ../../finance-obligation-v2-migration/PRD.md
Portal impact: none

## What to build

Cut Batch Studio EGC level-fee generation over to debit intake. EGC block, relevel, retake, and carry-forward facts should be passed as pricing facts, Finance should price through the registry and catalog, and accepted `egc_level_fee` obligations should materialize into the existing read model without direct charge creation by the generator.

## Acceptance criteria

- [x] EGC level-fee generation creates accepted obligations and materialized debit rows through intake.
- [x] EGC block and relevel semantics are preserved in the generated payable and traceable through the source reference and pricing snapshot.
- [x] Carry-forward behavior is explicit and does not silently create or reuse debt outside the intake path.
- [x] Existing EGC operations that read charges, invoices, DNG, or settlement continue to work against the materialized read model.
- [x] Tests cover fresh EGC generation, reissue behavior, carry-forward adjacency, and idempotency.

## Blocked by

- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/01-code-owned-obligation-type-registry.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/02-pricing-operations-coverage-warning.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/03-billing-accounts-payer-key.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/01-defer-credit-ledger-tracer.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/02-voucher-discount-entitlement-cutover.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/03-scholarship-classifier-entitlement-conversion.md`
