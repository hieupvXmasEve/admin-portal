# 03 - Scholarship classifier and entitlement conversion

Status: done
Depends on: 01, 02
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md
Portal impact: none

## What to build

Open Wave 4's scholarship work with a carrier audit/classifier, then convert scholarship reductions to the correct entitlement type. Fee-specific scholarship reductions that already live in discount allocations become FinanceDiscountEntitlement. Grant-like scholarship reductions without a discount carrier become FinanceCreditEntitlement plus credit applications. A reduction must never be counted through both carriers.

## Acceptance criteria

- [x] The scholarship classifier separates fee-specific discount scholarships from grant-like credit scholarships using auditable evidence.
- [x] Fee-specific scholarship rows convert to discount entitlements with no credit applications.
- [x] Grant-like scholarship rows convert to credit entitlements with credit applications.
- [x] Duplicate negative lines are voided in the same transaction as conversion.
- [x] A test proves a scholarship reduction cannot be counted in both discount allocations and credit applications.

## Blocked by

- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/01-defer-credit-ledger-tracer.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/02-voucher-discount-entitlement-cutover.md`

## Delivered

- Classifier: `ScholarshipCarrierClassifier` + `ScholarshipCarrierClassification` (auditable evidence)
- Backfill: `finance:backfill-legacy-scholarship-entitlements` → `BackfillLegacyScholarshipEntitlementsAction`
  - fee-specific → `FinanceDiscountEntitlement` (link existing discount carrier, no credit apps)
  - grant-like → `FinanceCreditEntitlement` + credit applications
  - void negative line in same transaction; remaining hard gate; dual-carrier runtime guards
- Source ref: `FinanceOwnedObligationSource::legacyScholarshipCreditChargeRef`
- Tests: `tests/Feature/Finance/ScholarshipClassifierEntitlementConversionTest.php`
