# 02 - EGC retake and exempt entitlement handling

Status: done
Depends on: 01 and wave 4 entitlement issues
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md
Portal impact: none

## What to build

Move EGC reduction flows onto entitlement carriers. EGC retake discount should become FinanceDiscountEntitlement, while EGC exempt or major-entry credit reductions should become FinanceCreditEntitlement plus credit applications. New EGC reduction paths must not write negative charge rows.

## Acceptance criteria

- [x] EGC retake reductions create discount entitlements and allocate through discount allocations.
- [x] EGC exempt or major-entry credit reductions create credit entitlements and credit applications.
- [x] Existing EGC retake targeting rules are preserved for later mapped retake blocks.
- [x] No EGC reduction is represented by both discount allocations and credit applications.
- [x] Tests cover the live EGC credit path, retake discount allocation, and settlement net unchanged.

## Delivered

- `RequestFinanceDiscountAction`: supports `egc_retake` + targeted `invoice_line_id` allocation (`egc_retake_target`)
- `ApplyEgcRetakeDiscountAction`: routes through discount intake; keeps retake targeting + `egc_retake_discount_links`
- `ApplyEgcMajorEntryCreditAction`: routes through credit intake; no negative charge rows
- Tests: `tests/Feature/Finance/Egc/EgcRetakeExemptEntitlementsTest.php` + updated `RetakeAdjustmentsTest.php`

## Blocked by

- `.scratch/finance-obligation-v2-wave-5-egc/issues/01-egc-level-fee-intake-cutover.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/01-defer-credit-ledger-tracer.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/02-voucher-discount-entitlement-cutover.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/03-scholarship-classifier-entitlement-conversion.md`
