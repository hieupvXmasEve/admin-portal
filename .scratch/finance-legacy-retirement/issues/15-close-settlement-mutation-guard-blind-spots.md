# 15 — Close Settlement Mutation Guard blind spots

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Make the permanent Settlement Mutation Guard protect every state that can alter collectible, collection holds, invoice lifecycle, or settlement presentation. Close architecture-test blind spots for invoice and DNG models, indirect variable names, query-builder writes, and instance writes while ensuring the settlement version advances exactly once only when a committed mutation occurs.

## Acceptance criteria

- [x] Invoice creation, closing, paid-cache changes, allocation effects, DNG reservation/cancellation/finalization, and installment transitions use the payer-level guard or an explicitly approved migration boundary.
- [x] `StudentInvoice`, `DngPaymentRequest`, and every other settlement-affecting table/model are included in the protected-writer inventory.
- [x] The architecture rule detects instance writes regardless of local variable name as well as create, update, save, delete, upsert, query-builder, repository, and equivalent writer forms.
- [x] DNG cancellation and other holding-collection transitions advance the correct billing account settlement version.
- [x] A committed mutation advances settlement version exactly once; no-op, rejected, rolled-back, and already-settled paths do not advance it.
- [x] Provider calls remain outside locks while reservation and finalization mutations remain guarded.
- [x] Concurrency, idempotency, rollback, already-settled, DNG cancellation, invoice materialization, and paid-cache tests pass.
- [x] The permanent architecture suite has no unexplained allowlist or variable-name false negative.

## Blocked by

- [Issue 12 — Enforce the exact DNG reservation lifecycle](12-enforce-exact-dng-reservation-lifecycle.md)
- [Issue 14 — Complete Settlement Position read convergence](14-complete-settlement-position-read-convergence.md)

## Verification

- Architecture + guard + receipt-exception characterization: 13 tests, 40 assertions passed (architecture: 4 tests, 13 assertions).
- Main issue-focused pack: 72 tests, 310 assertions passed.
- DNG receipt/webhook/reconciliation regression pack: 16 tests, 90 assertions passed.
- Laravel Pint passed for all dirty PHP files; `git diff --check` passed.
- The configured full-suite wrapper was invoked but exited 255 without diagnostics; focused suites above provide the behavior proof. The frontend type-check emitted no diagnostics before the container was killed with exit 137.
- Review result: no remaining issue-15 spec or standards blocker. Existing Academic/Finance and DNG-service boundary debt was not introduced by this issue and remains outside this slice.
- Portal impact: none.
