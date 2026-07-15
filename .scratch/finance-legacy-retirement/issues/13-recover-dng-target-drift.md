# 13 — Recover DNG target drift without stranding installments

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Complete the DNG reservation state machine for cases where the provider call succeeds or is ambiguous but finalization detects that the canonical target has changed. An installment may become awaiting payment only after its exact reservation is finalized as pushed; review states must retain enough evidence for safe retry or reconciliation without losing the pending installment.

## Acceptance criteria

- [x] An installment changes from pending to awaiting payment only when its exact DNG reservation is finalized in the pushed state.
- [x] `needs_review`, target-drift, stale-version, and ambiguous-provider outcomes do not strand an installment in a state that the retry workflow cannot process.
- [x] Review evidence preserves the provider response, deterministic identity, original target fingerprint, current target fingerprint, and affected installment.
- [x] Retrying a locally uncommitted push does not issue a second provider request when the prior outcome can be reconciled by deterministic identity.
- [x] Staff reconciliation can resolve the held outcome to pushed, restored-pending, cancelled, or failed through guarded state transitions.
- [x] Batch and next-installment callers report the actual reservation outcome instead of unconditionally reporting awaiting payment.
- [x] Tests cover target drift before provider call, drift during finalization, known provider success, unknown provider outcome, retry, and manual reconciliation.
- [x] No recovery path changes cash, credit, discount, collectible, or installment amount without a separately authorized settlement mutation.

## Blocked by

- [Issue 11 — Push the next installment atomically](11-push-next-installment-atomically.md)
- [Issue 12 — Enforce the exact DNG reservation lifecycle](12-enforce-exact-dng-reservation-lifecycle.md)

## Verification

- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php` — 23 passed, 107 assertions.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/PushNextInstallmentActionTest.php --filter='target drift|staff reconcile|terminal reconciliation|ambiguous'` — 7 passed, 45 assertions.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Dng/CreateBatchDngFromChargesActionTest.php` — 6 passed, 48 assertions.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/DngPaymentRequestPagesTest.php` — 4 passed, 15 assertions.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/DetailRouteContractTest.php` — 5 passed, 70 assertions.
- `./scripts/dev.sh npm run type-check` passed before the final backend-only review fixes. A repeat run after those fixes was killed by the container memory limit (`137`) without reporting a TypeScript diagnostic.
- `./scripts/dev.sh test --compact` could not start the full suite in the current container and exited `255` without test output; all issue-focused suites above passed.
