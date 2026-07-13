# 01 - Materializer-only hardening and legacy backstop retirement

Status: ready-for-human
Depends on: waves 2, 3, 4, 5, and 6 closure
Program PRD: ../../finance-obligation-v2-migration/PRD.md
ADRs: docs/adr/0026-academic-finance-boundary-and-retake-resit-candidate-cutover.md, docs/adr/0028-finance-charge-is-materialized-ledger-read-model.md, docs/adr/0030-credit-reduction-flows-through-credit-application-ledger.md
Portal impact: none

## What to build

Close the migration program by enforcing that FinanceCharge is only a debit read model written by the Finance materializer. Remove remaining source-pointer coupling, remove DNG auto-create globally, enforce registry coverage, and retire the legacy negative-line settlement fallback once converted credit and discount rows no longer depend on active negative charge lines.

## Acceptance criteria

- [x] Architecture tests fail on production app code that writes FinanceCharge outside the materializer.
- [x] Architecture tests fail on Academic and Finance model imports that violate the boundary.
- [x] Every DB charge type has a registry entry and DNG has zero auto-create paths.
- [x] `finance_charges.source_type`, `source_id`, and remaining active-source legacy paths are **retired on new writes** after data closure (columns kept for legacy readers; physical DROP is follow-up).
- [x] Active negative charge rows are zero or explicitly excluded as historical artifacts, the negative-line settlement fallback is removed, and ledger fixture helpers are available (`tests/Feature/Finance/Support/ledger_fixtures.php`).

## Implementation notes (2026-07-10)

- Materializer = `CreateFinanceChargeAction` via `RequestFinanceDebitAction` only (+ allowlisted one-shot `CreateLegacyExamResitChargeFromPaidPtlAction`).
- Staff form cut to `manual_fee` / `admission_fee` / positive `adjustment` through `CreateStaffDebitAction`.
- Defer FORFEIT adjustment via intake `source_kind=defer_forfeit`.
- Retake/resit Simple + HQ charge actions use `FinanceIntakeContract`.
- SettlementService + charge summary: negative-line netting backstop **deleted**.
- `source_type`/`source_id`: **retired on new writes** (columns retained for legacy readers until a follow-up drop migration when all remaining morph lookups move to obligation source triples). `active_source_key` already dropped.
- Arch suite: `tests/Feature/Architecture/MaterializerOnlyFinanceChargeArchTest.php`.
- Ledger fixtures: `tests/Feature/Finance/Support/ledger_fixtures.php`.
- Open follow-up (optional): physical DROP of `source_type`/`source_id` after remaining readers (DngWebhook legacy branch, ObligationLedgerSettlementReader legacyChargeIds, DeferCaseService) migrate fully.

## Blocked by

- `.scratch/finance-obligation-v2-wave-2-simple-debits/issues/01-bhyt-intake-backfill-dng-closure.md`
- `.scratch/finance-obligation-v2-wave-2-simple-debits/issues/02-manual-fee-intake-cutover.md`
- `.scratch/finance-obligation-v2-wave-3-tuition-term/issues/01-tuition-term-intake-installments.md`
- `.scratch/finance-obligation-v2-wave-3-tuition-term/issues/02-tuition-backfill-dng-closure.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/01-defer-credit-ledger-tracer.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/02-voucher-discount-entitlement-cutover.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/03-scholarship-classifier-entitlement-conversion.md`
- `.scratch/finance-obligation-v2-wave-4-credit-discount-entitlements/issues/04-student-charges-api-entitlement-summary.md`
- `.scratch/finance-obligation-v2-wave-5-egc/issues/01-egc-level-fee-intake-cutover.md`
- `.scratch/finance-obligation-v2-wave-5-egc/issues/02-egc-retake-exempt-entitlements.md`
- `.scratch/finance-obligation-v2-wave-5-egc/issues/03-egc-backfill-reconciliation-closure.md`
- `.scratch/finance-obligation-v2-wave-6-audit-tail/issues/01-admission-course-adjustment-dng-audit.md`
