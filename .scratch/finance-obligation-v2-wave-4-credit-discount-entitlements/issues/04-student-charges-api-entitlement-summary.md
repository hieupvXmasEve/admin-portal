# 04 - Student charges API reads entitlement summaries

Status: done
Depends on: 01, 02, 03
Program PRD: ../../finance-obligation-v2-migration/PRD.md
Portal impact: student

## What to build

Rework the student charges API summary so `total_credits` and `net_amount` derive from FinanceCreditEntitlement, FinanceDiscountEntitlement, discount allocations, and credit applications instead of active negative charge rows. This keeps the student-facing finance contract truthful after credit and discount migrations.

## Acceptance criteria

- [x] Student charges summary totals include migrated credits and discounts from entitlement carriers.
- [x] Active negative charge rows are not required for correct `total_credits` or `net_amount`.
- [x] Fixtures that previously depended on negative charge rows are rewritten to the new carriers.
- [x] Student portal impact is inspected, and affected types, composables, stores, pages, or API docs are updated in the same work window if the response contract changes.
- [x] Backend and affected student portal checks are run, or any gap is explicitly recorded.

## Validation notes

- Backend: `StudentChargesApiEntitlementSummaryTest` + related entitlement/settlement tests green.
- Student portal: response contract unchanged (`total_charges` / `total_credits` / `net_amount` numbers only). Inspected `FE/student-nuxt/shared/types/finance.ts` + charges UI on `finance/index.vue` — no portal code change. Portal `pnpm lint|typecheck|build` not run (no portal edits).

## Blocked by

- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/01-defer-credit-ledger-tracer.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/02-voucher-discount-entitlement-cutover.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/03-scholarship-classifier-entitlement-conversion.md`
