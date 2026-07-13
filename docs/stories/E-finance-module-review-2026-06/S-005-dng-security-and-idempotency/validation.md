# Validation

> **Historical INV-6 notice (2026-07-12):** Tài liệu này ghi lại định nghĩa/kết quả audit cũ. Nhiều invoice cùng student/kỳ là hợp lệ theo kiến trúc hiện tại; không cleanup hoặc thêm unique `(student_id, semester_id)` chỉ vì multi-invoice. `INV-6` đã retired; `INV-17` kiểm tra invoice line tham chiếu charge sai student/kỳ.

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

## Acceptance Evidence (2026-06-14)

### Scope delivered

Fixed: FIN-15, FIN-16, FIN-17, FIN-18, FIN-32, FIN-33 (regression-locked), FIN-34,
DB-04. Deferred: FIN-19 (owner decision — needs an outbox/compensation design that
conflicts with the Replacement-Rule atomicity; not implemented to avoid risking the
working batch-push flow).

### Review-driven hardening (2026-06-14, second pass)

Three issues raised in review were fixed after the first pass:

1. **Dedup must not swallow real retries.** The controller no longer ACKs every
   duplicate `payload_hash`. It drops the retry only when the prior event reached a
   STABLE terminal state (`processed` / `mismatch` / `skipped`). For non-terminal
   (`received` / `processing` / `failed_retryable`) or a `failed_terminal` (e.g. the
   request had not been created yet → NOT_FOUND), it re-drives the existing event
   (reset + re-dispatch; `ShouldBeUniqueUntilProcessing` collapses redundant jobs) so
   a genuine DNG retry can still settle. (`DngWebhookController::handleDuplicate`)
2. **Call 1 / Call 2 transition race.** The status decision + field update +
   `transitionTo()` now run inside a `DB::transaction` with `lockForUpdate()` and a
   FRESH re-read of the request status, so a late Call 1 can no longer downgrade a
   request Call 2 already advanced (paid_invoiced → paid_uninvoiced).
   (`DngWebhookService::processEvent`)
3. **Migration dedup keeps the authoritative row.** The unique-restore migration
   collapses duplicates by `processing_status` priority (processed > skipped >
   mismatch > failed_terminal > … ; ties → earliest id), not blind `MIN(id)`, so a
   processed retry is never discarded in favour of an earlier failed/orphan row.

Third pass (reconciliation + recovery completeness):

4. **Reconciliation-vs-webhook race.** `DngReconciliationService::processTransaction`
   now decides + applies its transition inside the same `lockForUpdate()` +
   fresh-re-read pattern as the webhook, so a no-invoice reconciliation row cannot
   downgrade a request a concurrent webhook Call 2 already advanced.
5. **Paid-state recovery settles installments.** The webhook `already_progressed` /
   `equivalent` branches and the reconciliation `up_to_date` branch now run a full
   `recoverPaidState` (bridge Payment **and** settle the linked installment), not just
   the bridge. A prior attempt that advanced status but crashed before settling no
   longer leaves the installment unpaid / the next installment un-pushed.
   `SettleInstallmentFromDngAction` is idempotent + row-locked, so this is safe on
   every late/duplicate callback.

Fourth pass (bind ordering, transition legality, checksum encoding):

6. **No `dng_payment_id` poisoning on mismatch.** Both the webhook
   (`DngWebhookService`) and reconciliation (`DngReconciliationService`) now defer
   binding the callback PaymentId until AFTER checksum + business validation pass,
   inside the locked transition. A mismatched/non-matching callback can no longer
   leave a wrong `dng_payment_id` on a local request.
7. **Illegal transitions never bridge/settle.** Reconciliation now mirrors the webhook:
   if `canTransitionTo($targetStatus)` is false (e.g. a `failed` request), it returns
   `cannot_transition`, logs + counts an error, and does **not** create a Payment or
   settle installments. Both services also check transition legality BEFORE mutating
   any callback fields, so a refused transition leaves the row untouched.
8. **Checksum accepts both encodings.** `DngChecksumService::verify` normalises the
   `=` padding (`%3d` ↔ `=`) on both sides, so a legitimate callback whose checksum
   arrives URL-decoded is accepted — previously only the `%3d` form matched.

Docs: corrected the webhook route in `dng-payment-integration.md` to the real
`POST /api/webhooks/dng/payment` (`routes/api.php`), replacing the stale
`/api/v1/finance/dng/webhook`.

Fifth pass (correlation key, ItemId validation, Call-2 semantics, docs accuracy):

9.  **Reconciliation correlates by the stable key.** `processTransaction` now matches
    `ItemId + StudentId + Campus` FIRST and only falls back to `PaymentId`. A request
    whose stored `dng_payment_id` placeholder differs from DNG's reported PaymentId is
    reconciled by ItemId instead of being wrongly orphaned (the placeholder is kept; a
    differing PaymentId is logged, not failed).
10. **ItemId is validated.** `callbackMismatchReasons` now rejects a callback whose
    `ItemId` differs from the local request (when present) — the checksum does not
    cover ItemId, so a PaymentId-matched callback carrying a wrong ItemId is caught.
11. **Call 2 is invoice-attach only.** The webhook advance branch now gates Call 1's
    side effects (installment settle, retake auto-enroll, "payment received"
    notification) on the FIRST paid transition. Call 2 (paid_uninvoiced →
    paid_invoiced) only attaches invoice metadata + idempotent bridge — no second
    settlement, no duplicate notification. Out-of-order Call 2 (pushed_to_dng →
    paid_invoiced) is still a first settlement and runs the full flow.
12. **Docs match the real flow.** `dng-payment-integration.md` no longer claims the
    controller validates the checksum / returns 403 or that an amount mismatch still
    creates a Payment; the data-flow, error-handling, job, and troubleshooting
    sections now describe the inbox-then-job-validate pipeline and the
    `processing_status` outcomes.

Sixth pass (webhook correlation key + remaining doc accuracy):

13. **Webhook resolver scoped by campus.** Both `DngWebhookService::resolvePaymentRequest`
    and `DngWebhookController::resolveDngPaymentRequest` now add `campus_code` to the
    `ItemId + StudentId` lookup when the callback carries `CampusCode` (matching the
    reconciliation matcher and the `(campus_code, item_id, student_code)` index). Two
    requests sharing ItemId+StudentId across campuses now resolve to the right row
    instead of the first-inserted one (which then failed with a Campus mismatch). New
    test covers the cross-campus collision.
14. **Services-section docs corrected.** Fixed stale method names
    (`processPaymentNotification` → `processEvent`, `reconcile()` → `reconcileDay()`),
    the `status = confirmed` claim (now `paid_uninvoiced`/`paid_invoiced`), and the
    algorithm (code is **HMAC-SHA1**, not SHA256).
15. **Migration comment fixed.** The collapse docblock now says "most authoritative by
    processing_status priority, ties by earliest id" to match the code (was "earliest
    by id").

### Targeted tests

- `test --filter=Dng`: 184 passed (was 161). The 3 prior
  `DngReconciliationServiceTest` failures (pre-existing `ArgumentCountError` — stale
  2-arg constructor) were fixed. Remaining 7 failures are pre-existing and unrelated
  to S-005 (`GenerateDngWebhookChecksumCommandTest` CommandNotFound,
  `PaymentPagesTest` RouteNotFound, `StudentFinanceDngAccessTest` 401).
- `test --filter=ProcessDngWebhookJob`: 31 passed (incl. FIN-16 reject + valid
  empty-serial settle, FIN-32 campus mismatch, FIN-18 cancel_pushed_to_dng skip, the
  stale-Call-1 no-downgrade race guard, the paid-state recovery installment settle, the
  no-bind-on-mismatch guard, the ItemId mismatch rejection, and the cross-campus
  ItemId+StudentId collision resolver).
- `test --filter=DngWebhookController`: 13 passed (dedup → 1 row on identical retry;
  stable-terminal skip vs non-terminal/failed_terminal re-dispatch+reset).
- `test --filter=DngReconciliation`: 8 passed (incl. FIN-18 cancel_pushed_to_dng, the
  no-downgrade race guard, no-bind-on-mismatch, illegal-transition no-bridge/settle, and
  ItemId-correlation when the PaymentId differs).
- `test --filter=DngWebhookServiceNotification`: 3 passed (incl. exactly-one
  payment-received notification across Call 1 + Call 2).
- `test --filter=DngChecksumService`: 8 passed (incl. Call-1 empty-serial + URL-decoded
  `=` checksum acceptance).
- `test --filter=LifecycleDueException`: 16 passed (incl. FIN-34 rollback test).
- `test --filter="CreateBatchDngFromCharges|DngChecksumService"`: 18 passed (incl.
  FIN-33 same-second item_id regression and FIN-16 empty-serial checksum unit test).

### Before/after duplicate payload counts (DB-04, dev `asia` DB)

Migration `2026_06_14_120000_restore_unique_payload_hash_on_dng_webhook_events`:

- Before: `dng_webhook_events` = 230 rows, 3 duplicate `payload_hash` groups
  (3 extra retry rows), INV-11 = ❌ 3.
- After: 227 rows (3 exact-duplicate retries collapsed, earliest kept), 0 duplicate
  groups, index now `dng_webhook_events_payload_hash_unique (unique=yes)`, INV-11 = ✅ 0.

### Other gates

- `vendor/bin/pint --dirty`: 13 files, 1 style issue fixed (clean after).
- `git diff --check`: clean.
- `finance:audit-invariants --sample`: INV-11 (webhook payload_hash dup) cleared by
  the migration. Remaining offending invariants (INV-6 invoice dups, INV-13 voided-
  charge installment) are out of S-005 scope (other stories).
