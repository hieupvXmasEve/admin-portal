# Validation

## Proof Strategy

Prove that the existing Student 360 route now exposes the M2 read-model props
without changing money state or weakening the M1 authorization/campus boundary.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | None expected; query behavior is covered through feature tests unless extraction becomes complex. |
| Integration | Student 360 Inertia response includes `status_cards`, `actions`, and deferred `ledger_groups`. |
| Integration | `actions` reflects `create_finance_payments`, `allocate_finance_payment`, and `void_finance_charges`. |
| Integration | `status_cards.balance.unapplied_payment_id` is either a real visible unapplied payment id or `null`. |
| E2E | Covered later by the Milestone 2 UI/evidence story. |
| Platform | No portal impact. |
| Performance | `ledger_groups` remains deferred; no eager full-ledger load on initial render. |
| Logs/Audit | No money state change; no invariant evidence required for this story. |

## Fixtures

- Current-campus student with finance data.
- User with `view_finance_student_overview`.
- User variants with/without `create_finance_payments`,
  `allocate_finance_payment`, and `void_finance_charges`.
- Completed payment with unapplied amount.
- Optional invoices/installments/DNG request to exercise populated cards.

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Student360/Student360OverviewTest.php
```

## Acceptance Evidence

```text
./scripts/dev.sh test tests/Feature/Finance/Student360/Student360OverviewTest.php
# PASS — 5 tests, 65 assertions

./scripts/dev.sh test tests/Feature/Finance/Student360/StudentOverviewShellTest.php
# PASS — 5 tests (M1 regression, no breakage)
```

Verified:

- `status_cards` exposes balance (with `unapplied_payment_id`), dng, installments, and exception shapes.
- `actions` reflects `create_finance_payments`, `allocate_finance_payment`, and `void_finance_charges`.
- `ledger_groups` is deferred on initial render and loads via the `default` deferred group.
- No money-write paths added; campus/permission boundary unchanged from M1.
