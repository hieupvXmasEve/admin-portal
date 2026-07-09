# 01 - Defer credit tracer with credit-application ledger

Status: done
Depends on: wave 1 issues 01 and 03
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md
Portal impact: none

## What to build

Build the first credit entitlement tracer for `defer_credit`. Open the Finance Intake Contract's credit branch, persist FinanceCreditEntitlement, add the credit-application ledger, and update settlement derivation so applied credits reduce outstanding alongside payments and discounts. New defer credits must not create negative FinanceCharge rows, and legacy defer-credit rows must convert without changing net settlement.

## Acceptance criteria

- [x] A defer credit intake creates a FinanceCreditEntitlement and credit application records, not a negative charge row.
- [x] Settlement derivation includes credit applications as a fourth source of reduction.
- [x] Legacy defer-credit negative rows convert to credit entitlements and applications while the negative line is voided in the same transaction.
- [x] Reconciliation proves converted rows have unchanged net settlement.
- [x] Tests prove a credit application reduces outstanding and no new negative charge is written.

## Blocked by

- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/01-code-owned-obligation-type-registry.md`
- `.scratch/finance-obligation-v2-wave-1-infrastructure/issues/03-billing-accounts-payer-key.md`

## Delivered

- Tables: `finance_credit_entitlements`, `credit_applications`
- Models: `FinanceCreditEntitlement`, `CreditApplication`
- Intake: `RequestFinanceCreditAction` + credit branch on `FinanceIntakeRouter` / `FinanceIntakeContract::requestCredit`
- Settlement: `SettlementService` + `ObligationLedgerSettlementReader` include credit applications
- Backfill: `finance:backfill-legacy-defer-credit-entitlements` (void negative line + entitlement + applications; remaining hard gate)
- Tests: `tests/Feature/Finance/DeferCreditEntitlementTracerTest.php`
