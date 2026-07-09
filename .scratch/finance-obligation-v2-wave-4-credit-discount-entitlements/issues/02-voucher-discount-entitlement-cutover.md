# 02 - Voucher discount entitlement cutover

Status: done
Depends on: 01
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md
Portal impact: none

## What to build

Cut voucher reductions over to FinanceDiscountEntitlement while keeping the existing discount allocation carrier as settlement truth. New voucher reductions should enter through the discount branch, allocate through discount headers and allocations, and never create credit applications or negative charge rows.

## Acceptance criteria

- [x] Voucher intake creates a FinanceDiscountEntitlement and discount allocations through the existing discount carrier.
- [x] Legacy voucher-credit rows with existing discount-allocation carrier convert to discount entitlements and void duplicate negative lines.
- [x] No converted voucher reduction is represented as both a discount allocation and a credit application.
- [x] Net settlement remains unchanged under the reconciliation hard gate.
- [x] Tests cover new voucher discount intake, legacy conversion, and the no-double-reduction invariant.

## Blocked by

- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/01-defer-credit-ledger-tracer.md`

## Delivered

- Tables: `finance_discount_entitlements`, `invoice_discounts.finance_discount_entitlement_id`
- Model: `FinanceDiscountEntitlement`
- Intake: `RequestFinanceDiscountAction` + discount branch on `FinanceIntakeRouter` / `FinanceIntakeContract::requestDiscount`
- Backfill: `finance:backfill-legacy-voucher-discount-entitlements` (link existing discount carrier, void negative line; no credit applications; remaining hard gate)
- Tests: `tests/Feature/Finance/VoucherDiscountEntitlementCutoverTest.php`
