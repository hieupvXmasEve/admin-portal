# Validation

## Proof Strategy

Prove that Student 360 renders the M2 read-only display with correct prop usage
and no write behavior.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | None; no frontend unit runner exists. |
| Integration | Backend prop presence covered by `FIN-REV-010-08-student-360-status-ledger`. |
| E2E | Manual/browser check: four cards render, DNG stepper highlights status, both ledger lenses load. |
| Platform | Responsive desktop/mobile visual check for the Student 360 page. |
| Performance | `ledger_groups` and timeline remain deferred. |
| Logs/Audit | No writes; no audit evidence required. |

## Fixtures

- Student with balances.
- Student with active DNG.
- Student with invoices and invoice lines.
- Student with installments, including next pending installment.
- Student with and without exception/blocking reasons.

## Commands

```text
./scripts/dev.sh npm run lint -- resources/js/types/finance.ts resources/js/components/finance/student360/StatusCards.vue resources/js/components/finance/student360/DngStateStepper.vue resources/js/components/finance/student360/LedgerLens.vue resources/js/pages/Finance/Student360/Show.vue
```

## Acceptance Evidence

```text
./scripts/dev.sh npm exec eslint -- resources/js/types/finance.ts resources/js/components/finance/student360/StatusCards.vue resources/js/components/finance/student360/DngStateStepper.vue resources/js/components/finance/student360/LedgerLens.vue resources/js/pages/Finance/Student360/Show.vue
# PASS — no errors

./scripts/dev.sh test tests/Feature/Finance/Student360/Student360OverviewTest.php tests/Feature/Finance/Student360/StudentOverviewShellTest.php
# PASS — 10 tests, 102 assertions (backend prop contract unchanged)
```

Verified:

- M2 TypeScript types added with snake_case parity (`status_cards`, `ledger_groups`, `actions`).
- `StatusCards` renders four operator cards with permission-gated affordances (no-op emits).
- `DngStateStepper` renders the 2-call lifecycle from DNG status.
- `LedgerLens` uses Inertia v3 `<Deferred data="ledger_groups">` and `<Deferred data="ledger">`.
- M1 four-balance header cards and audit link preserved.
- No write behavior wired; action emits are inert placeholders for S-010-13.
