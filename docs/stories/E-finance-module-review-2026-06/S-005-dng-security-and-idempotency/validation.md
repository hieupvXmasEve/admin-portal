# Validation

## Proof Strategy

Prove the public webhook boundary rejects forged or duplicate mutations while
valid signed events still settle exactly once, **without changing the working
two-call settle-then-invoice behavior**. Verifying Call 1 must not reject the
legitimate no-serial event, and Call 2 must attach invoice metadata without a
second settlement.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Checksum validation incl. Call 1 empty-serial string + exact `amount` format; status ordering; collision-safe item id |
| Integration | Call 1 (valid checksum) settles once; Call 1 (bad checksum) rejected |
| Integration | Call 2 attaches `InvoiceSerialNumber`/`InvoiceDate`, no second payment/settlement |
| Integration | Out-of-order Call 2 before Call 1, and repeated Call 2, stay idempotent |
| Integration | Two charges with colliding `ItemId` correlate to the right payment |
| Integration | Webhook receive/job/bridge, reconciliation, installment settlement locks |
| Integration | DNG cancel rollback does not leave a completed review/audit state |
| E2E | Admin webhook detail retry button visibility if UI changes |
| Platform | Queue/job behavior under retry |
| Performance | Dedup/index additions do not slow webhook inbox lookup |
| Logs/Audit | Failed, skipped, processed, and duplicate events are auditable |

## Fixtures

- Call 1 payment-success event with empty `InvoiceSerialNumber`, signed with the
  empty-serial checksum string (legitimate; previously bypassed checksum).
- Call 2 follow-up event with full `InvoiceSerialNumber`/`InvoiceDate` for the
  same `ItemId`.
- Out-of-order: Call 2 arriving before Call 1.
- Forged event with a wrong/absent checksum.
- Duplicate retry of the same call (same `payload_hash`).
- Two charges pushed in the same second producing colliding `ItemId`.
- Cancelled request with late webhook.
- Concurrent webhook/reconciliation for the same installment.
- Lifecycle exception DNG cancellation that fails after writing an attempted
  event.

## Commands

```text
./scripts/dev.sh test --filter=DngWebhook
./scripts/dev.sh test --filter=DngReconciliation
./scripts/dev.sh test --filter=Installment
./scripts/dev.sh artisan finance:audit-invariants --sample
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run lint
git diff --check
```

## Acceptance Evidence

Add exact test output and before/after duplicate payload counts after
implementation.
