# 01 - BHYT intake cutover, backfill, and DNG closure

Status: done
Depends on: wave 1 issues 01, 02, 03
Program PRD: ../../finance-obligation-v2-migration/PRD.md
Portal impact: none

## What to build

Cut BHYT generation over to the Finance Intake Contract as a complete simple-debit tracer. Finance-owned generation should mint the source reference, price through the catalog, materialize the payable through the Finance materializer, backfill legacy BHYT rows to obligations, and close any DNG path that can create BHYT debt without an existing obligation.

## Acceptance criteria

- [x] New BHYT generation creates a FinanceObligation, FinanceCharge, and InvoiceLine through intake, not direct charge creation.
- [x] Active and voided legacy BHYT rows are backfilled to obligations with `legacy_backfill` provenance and no duplicate rows on rerun.
- [x] DNG push for BHYT works only from existing payables and blocks missing obligations instead of creating charges.
- [x] Fee Monitor output for BHYT remains unchanged or is deliberately updated in the same slice.
- [x] Tests cover intake, backfill idempotency, DNG guard behavior, and unchanged reporting semantics.

## Blocked by

- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/01-code-owned-obligation-type-registry.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/02-pricing-operations-coverage-warning.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/03-billing-accounts-payer-key.md`
