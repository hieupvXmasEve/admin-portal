# 05 — Route settlement mutations through the guard

Status: ready-for-human

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Route every operation that can change collectible, invoice materialization, payment application, discount allocation, credit application, installment state, or collection state through the payer-level Settlement Mutation Guard.

Strengthen the permanent architecture check so it detects supported direct-writer forms rather than only simple create/update calls. The completed slice must prevent new bypasses while preserving the existing business workflows.

## Acceptance criteria

- [x] Every money-changing production flow locks the canonical billing account scope and advances settlement version exactly once per committed mutation.
- [x] Payment, credit, discount, invoice-line, installment, void, cancellation, and allocation mutations use guarded Finance-owned actions.
- [x] No controller, query, model observer, job, or cross-module service directly writes protected settlement tables outside an explicitly temporary migration boundary.
- [x] Architecture detection covers create, update, save, delete, first-or-create, update-or-create, first-or-new followed by save, query-builder writes, and equivalent repository conventions.
- [x] Existing mutation concurrency, idempotency, rollback, and cash-preservation behavior remains covered by focused tests.
- [x] Before/after canonical totals are identical for non-repair fixtures.

## Blocked by

- [Issue 04 — Converge settlement reads on Settlement Position](04-converge-settlement-reads.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/SettlementBypassArchitectureTest.php tests/Feature/Finance/FinanceDebitIntakeContractTest.php tests/Feature/Finance/AllocationConcurrencyGuardTest.php tests/Feature/Finance/PushNextInstallmentActionTest.php` — 20 passed (118 assertions).
- `./scripts/dev.sh test` — passed.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
