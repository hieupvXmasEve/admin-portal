# Validation

## Proof Strategy

Prove that reviewed cancel is blocked, previewed, and gated before destructive
DNG/charge state changes occur.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | None expected unless impact relation resolution becomes complex. |
| Integration | Impact endpoint returns linked charges, blocking reasons, and void-permission requirement. |
| Integration | Missing reason/acknowledgement fails validation. |
| Integration | Pending DNG with no linked charges can be cancelled by `create_finance_payments`. |
| Integration | DNG with linked charges requires `void_finance_charges`; without it the status remains unchanged. |
| Integration | Non-cancellable statuses are blocked with a user-visible result. |
| E2E | Drawer smoke and permission matrix belong to `FIN-REV-010-14`. |
| Platform | No portal impact. |
| Performance | Impact query only loads linked charges for one DNG request. |
| Logs/Audit | Final M2 evidence must include invariant output after DNG cancel/void smoke. |

## Fixtures

- Current-campus student.
- Pending or pushed DNG request.
- DNG request linked to a `FinanceCharge`.
- User with `create_finance_payments`.
- User with and without `void_finance_charges`.
- DNG request with bridged payment or blocked status.

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Dng/ReviewedCancelDngTest.php
```

## Acceptance Evidence

```text
./scripts/dev.sh test tests/Feature/Finance/Dng/ReviewedCancelDngTest.php
# PASS — 5 tests, 18 assertions

./scripts/dev.sh npm exec eslint -- resources/js/constants/finance-routes.ts resources/js/utils/routes.ts
# PASS — no errors on touched route helper files
```

Verified:

- `cancel-impact` returns linked charges, blocking reasons, and `requires_void_permission`.
- Missing reason/acknowledgement fails FormRequest validation.
- Pending DNG with no linked charges cancels via `CancelDngPaymentRequestAction`.
- Linked-charge DNG requires `void_finance_charges`; without it status stays pending (403).
- Non-cancellable statuses redirect with error flash and unchanged status.
- Legacy `finance.dng.payment-requests.cancel` route left intact.
- Route helpers `financeRoutes.student360.dngCancelImpact` and `.dngCancelReviewed` registered.
