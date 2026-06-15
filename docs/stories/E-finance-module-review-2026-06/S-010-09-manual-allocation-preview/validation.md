# Validation

## Proof Strategy

Prove the endpoint returns allocation preview data without performing any
allocation and without leaking cross-campus payment data.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | None expected unless the query priority logic becomes complex. |
| Integration | `GET /finance/payments/{payment}/allocate-preview` returns `ApiResponse::success`. |
| Integration | Missing `allocate_finance_payment` returns 403. |
| Integration | Cross-campus payment is hidden as 404 under current campus scope. |
| E2E | Allocation drawer smoke belongs to `FIN-REV-010-13`. |
| Platform | No portal impact. |
| Performance | Query should load only the selected payment's student and outstanding lines. |
| Logs/Audit | No write; no audit invariant evidence required. |

## Fixtures

- Current-campus student with a completed payment and unapplied amount.
- Outstanding invoice/charge lines for that student.
- User with and without `allocate_finance_payment`.
- Cross-campus payment owner.

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php
./scripts/dev.sh npm run lint -- resources/js/constants/finance-routes.ts resources/js/utils/routes.ts
```

## Acceptance Evidence

```text
./scripts/dev.sh test tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php
# PASS — 3 tests, 10 assertions

./scripts/dev.sh npm exec eslint -- resources/js/constants/finance-routes.ts resources/js/utils/routes.ts
# PASS — no errors on touched route helper files
```

Verified:

- `GET /finance/payments/{payment}/allocate-preview` returns `ApiResponse::success` with payment id, unapplied amount, and candidates.
- Missing `allocate_finance_payment` returns 403.
- Cross-campus payment returns 404 under current campus scope.
- Route helpers `PAYMENT_ALLOCATE_PREVIEW` and `financeRoutes.student360.allocatePreview` registered.
- No allocation write path added; preview delegates to `SettlementService` with `AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER`.
