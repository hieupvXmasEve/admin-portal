# Validation

## Proof Strategy

Prove that the Student 360 route records a manual payment only for an authorized
current-campus student and does so through existing service behavior.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | None expected; service behavior remains owned by existing Finance tests. |
| Integration | Authorized user can record a valid manual payment. |
| Integration | Non-positive amount is rejected by FormRequest validation. |
| Integration | Missing `create_finance_payments` returns 403. |
| Integration | Cross-campus student is hidden as 404 before write. |
| E2E | Drawer smoke and invariant before/after evidence belong to `FIN-REV-010-14`. |
| Platform | No portal impact. |
| Performance | Single student/payment write; no bulk operation. |
| Logs/Audit | M2 final evidence must run finance invariant checks after exercising this write. |

## Fixtures

- Current-campus student.
- Cross-campus student.
- User with and without `create_finance_payments`.
- Valid cash/manual payment payload.

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Student360/RecordManualPaymentTest.php
```

## Acceptance Evidence

```text
./scripts/dev.sh test tests/Feature/Finance/Student360/RecordManualPaymentTest.php
# PASS — 4 tests, 6 assertions

./scripts/dev.sh npm exec eslint -- resources/js/constants/finance-routes.ts resources/js/utils/routes.ts
# PASS — no errors on touched route helper files
```

Verified:

- Authorized user records a manual payment through `PaymentService::recordPayment`.
- Non-positive amount rejected by `RecordManualPaymentRequest`.
- Missing `create_finance_payments` returns 403.
- Cross-campus student returns 404 before write.
- Success uses `Inertia::flash('success', ...)` and redirects back.
- Route helper `financeRoutes.student360.recordPayment` registered.
