# 03 — Converge DNG creation on guarded reservations

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Make every supported DNG creation path use the same guarded reservation protocol and exact canonical Settlement Position targets. Tuition, EGC, retake/resit, BHYT, other supported fee types, batch operations, and next-installment pushes must all reserve locally, call the provider outside the transaction, then finalize safely.

Remove the legacy collection mode and arbitrary amount override. Partial payment remains an installment-plan behavior, not a caller-selected DNG amount.

## Acceptance criteria

- [x] Every supported fee type and next-installment push uses guarded reservation with exact line/installment targets, target fingerprint, settlement version, and deterministic provider identity.
- [x] No DNG request can exceed canonical remaining collectible or proceed from an invalid Settlement Position.
- [x] Provider calls occur outside database locks and ambiguous outcomes remain held for reconciliation.
- [x] Arbitrary amount override is absent from validation, API payloads, UI, and domain actions.
- [x] Legacy collection mode and unguarded single/batch creation methods have no callers and are removed.
- [x] Selected-fee and pay-all behavior remain distinct and preserve exact fee-type breakdowns.
- [x] Focused tests cover each fee family, installments, retry/idempotency, stale targets, invalid positions, and unknown provider outcomes.

## Blocked by

- None — can start immediately.

## Comments

- 2026-07-14: Batch commits now reject arbitrary `amount_overrides` at the request, controller, and Batch Studio UI boundaries. Batch creation and next-installment pushes enter `ReserveAndPushSingleFeeDngAction`; supported fee types resolve their canonical charge types through `ListDngWorklistQuery` and reserve exact invoice-line targets before calling DNG. Next-installment requests cap the reservation to the pending installment and retain that installment link on reservation/pivot rows.
- Verification: `ReserveAndPushSingleFeeDngActionTest` passes (HL and HP); Pint and Vue type-check pass. Existing legacy batch tests still assume direct `createAndPush` without invoice-line fixtures and amount-override behavior; they must be replaced with canonical-reservation batch/installment coverage before this issue can be completed.
- 2026-07-14: Batch and retake creation now build only canonical invoice-line targets and call `ReserveAndPushSingleFeeDngAction`; next-installment pushes also use that path and fail closed if its exact installment target is no longer collectible. Batch and retake tests now use canonical billing-account/obligation/invoice-line fixtures, and the legacy amount-override test cases were replaced with exact-target coverage.
- Verification: `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php tests/Feature/Finance/Dng/CreateBatchDngFromChargesActionTest.php tests/Feature/Finance/PushNextInstallmentActionTest.php tests/Feature/Finance/RetakeCourseChargeActionTest.php` — 18 passed (79 assertions); `./scripts/dev.sh npm run type-check`; `./scripts/dev.sh composer exec pint -- --dirty --format agent`; `git diff --check`.
- 2026-07-14: Removed the retired `DngPaymentService::createAndPush()` and `createAndPushBatch()` implementations. Runtime source audit has no remaining caller or unguarded DNG creation entrypoint. The four historical direct-service tests are explicitly skipped because their behavior is retired; guarded reservation tests now own creation-path proof.
- Verification: focused DNG service plus guarded-path suite — 22 passed, 4 retired tests skipped (95 assertions); `rg` confirms no runtime `createAndPush` or `createAndPushBatch` reference.
- 2026-07-14: Replaced the retired direct-service tests with active `pushReserved`/provider-access tests; no DNG test remains skipped. Added canonical guarded-reservation coverage for PTL, BHYT, and KHAC in addition to HL and HP.
- Completion verification: `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng/DngPaymentServiceTest.php tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php tests/Feature/Finance/Dng/CreateBatchDngFromChargesActionTest.php tests/Feature/Finance/PushNextInstallmentActionTest.php tests/Feature/Finance/RetakeCourseChargeActionTest.php tests/Feature/Finance/Dng/FinanceDngCutoverCommandTest.php` — 30 passed (126 assertions), no skips.
