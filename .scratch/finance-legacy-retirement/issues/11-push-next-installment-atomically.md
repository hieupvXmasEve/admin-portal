# 11 — Push the next installment atomically

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Make the next-installment workflow select the earliest eligible pending installment and reserve it atomically. Parallel workers must not select and push the same installment, and provider calls must still occur outside long-running database locks through the guarded reservation protocol.

## Acceptance criteria

- [x] The workflow selects the lowest sequence pending installment for each charge and never lets map or collection ordering replace it with a later installment.
- [x] Selection and local reservation occur inside a real transaction with an effective payer/target lock.
- [x] A concurrent webhook, job, staff request, or retry cannot create two provider pushes for the same installment.
- [x] The provider call remains outside database locks and uses the deterministic identity of the successfully reserved installment.
- [x] If no pending installment exists, the workflow exits without changing settlement state or version.
- [x] Feature tests cover multiple pending installments, reversed query order, parallel attempts, retry/idempotency, and a charge with no eligible installment.
- [x] Correct existing installment amounts and canonical collectible totals remain unchanged.

## Blocked by

- [Issue 09 — Restore a parseable Finance baseline](09-restore-parseable-finance-baseline.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/PushNextInstallmentActionTest.php tests/Feature/Finance/Dng/ReserveAndPushSingleFeeDngActionTest.php` — 18 passed, 102 assertions.
- The focused feature coverage proves reversed row order still selects sequence 1; a fresh second action started after reservation but before provider completion sees the durable `awaiting_payment` claim and does not issue another provider push; retry of an unknown provider outcome makes no provider call.
- The same test preserves canonical gross, discount, cash, credit, remaining collectible, derived invoice totals, and payment totals; it also proves no eligible installment does not provision a payer or advance settlement version.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- `./scripts/dev.sh artisan finance:audit-invariants --sample` — completed read-only; existing local data still reports INV-13 sample `1067` and INV-18 sample `1443`, outside this code-only slice.
- Full suite attempts exited `255` without output and `npm run type-check` was killed with exit `137` in the local container; focused Finance tests passed.
