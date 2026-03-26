# Code Review: DNG Payment Gateway Integration

**Reviewer**: code-reviewer | **Date**: 2026-03-26 | **Branch**: dev

## Scope
- **New files**: 15 (models, services, controllers, jobs, migrations, tests)
- **Modified files**: 6 (config, routes, bootstrap, service provider)
- **Approx LOC**: ~1,100 (new code) + ~300 (tests)
- **Focus**: Security, idempotency, error handling, architecture, code quality

## Overall Assessment

**Solid implementation.** The DNG payment gateway integration follows established module patterns, has proper state machine guards, dedup via `payload_hash`, forward-only transitions, and double-check bridging. Test coverage targets the right scenarios. A few issues need attention before production.

---

## Critical Issues

### C1. Webhook dedup race condition (payload_hash check-then-insert)
**File**: `DngWebhookController.php:43-49`

The dedup check (`exists()` then `create()`) is not atomic. Two concurrent identical webhooks can both pass the `exists()` check before either inserts. The `unique` DB constraint on `payload_hash` will throw an unhandled exception on the second insert.

**Fix**: Wrap in try-catch for `QueryException` with unique violation, or use `firstOrCreate`:
```php
$existing = DngWebhookEvent::where('payload_hash', $payloadHash)->first();
if ($existing) {
    return response()->json([...]);
}

// ... create event ...
// Add try-catch around create for race condition
try {
    $event = DngWebhookEvent::create([...]);
} catch (\Illuminate\Database\QueryException $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        return response()->json(['Code' => 200, 'Type' => 'Success', 'Message' => 'Already received', 'data' => null]);
    }
    throw $e;
}
```

### C2. Webhook endpoint has no IP allowlisting or rate limiting
**File**: `routes/api.php:30-31`

The webhook route is fully public with only checksum verification. A brute-force or replay attack could flood the webhook queue. DNG's callback IP ranges should be allowlisted at middleware or infrastructure level.

**Recommendation**:
- Add rate limiting middleware (e.g., `throttle:60,1` per IP)
- Document expected DNG source IPs for firewall/middleware allowlisting
- Consider adding a shared secret header check as defense-in-depth

---

## High Priority

### H1. `DngPaymentService::createAndPush` only catches `RuntimeException`
**File**: `DngPaymentService.php:63`

`ConnectionException` thrown by HTTP client is not caught here (it extends `Exception`, not `RuntimeException`). If DNG is unreachable, the local record stays in `pending` status with no error message, and the exception propagates unhandled to the controller.

**Fix**: Catch `\Throwable` or explicitly catch both:
```php
} catch (RuntimeException|ConnectionException $e) {
```

### H2. `bridgeToPayment` concurrency guard relies on refresh + check, not SELECT FOR UPDATE
**File**: `DngPaymentService.php:119-124`

The double-check pattern (`refresh()` + `hasBridgedPayment()`) inside `DB::transaction` does not use pessimistic locking. Two concurrent webhook processors could both pass the check. The `payment_id` column is nullable without a unique constraint on `(dng_payment_request_id, payment_id)`, so duplicate payments could be created.

**Fix**: Use `lockForUpdate()`:
```php
$request = DngPaymentRequest::where('id', $request->id)->lockForUpdate()->first();
if ($request->hasBridgedPayment()) {
    return $request->payment;
}
```

### H3. `ProcessDngWebhookJob` should implement `ShouldBeUnique`
**File**: `ProcessDngWebhookJob.php`

Multiple dispatches for the same event_id can run concurrently (e.g., if the queue worker restarts). Adding `ShouldBeUnique` with `uniqueId()` returning the event_id prevents parallel processing of the same webhook.

```php
class ProcessDngWebhookJob implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }
}
```

### H4. `DngChecksumService` constructor reads config once -- stale in long-running workers
**File**: `DngChecksumService.php:13`

Since services are registered as singletons, the hash_key is read once at boot. If config changes (e.g., key rotation via env), the singleton retains the old key until restart.

**Fix**: Read from config on each call, or register as non-singleton. For a payment system, reading config per-call is safer:
```php
public function generate(string $value): string
{
    $hashKey = (string) config('services.dng.hash_key');
    // ...
}
```
Same applies to `DngClient` (base_url, access_code, etc.).

### H5. Missing `campus_code` index on `dng_payment_requests` table
**File**: `migration 2026_03_26_134500`

The reconciliation service queries by `campus_code` + `item_id` + `student_code`. There is an index on `item_id` but none on `campus_code`. As volume grows, reconciliation queries will degrade.

**Fix**: Add composite index:
```php
$table->index(['campus_code', 'item_id', 'student_code']);
```

---

## Medium Priority

### M1. `DngPaymentRequest::transitionTo` uses `update()` without refreshing model state
**File**: `DngPaymentRequest.php:116`

After `$this->update(['status' => $newStatus])`, the in-memory model's `status` attribute is updated. However, if `transitionTo` is called right after `update()` on the same model (as in `DngWebhookService::processEvent` line 88+91), the `canTransitionTo` check uses the old in-memory status from before the `update()` call on line 88. This works by accident since `update()` does sync the attribute, but relying on this is fragile.

**Recommendation**: Add `$this->status = $newStatus;` explicitly or call `$this->refresh()` after.

### M2. `computePayloadHash` uses `ksort` -- only sorts top-level keys
**File**: `DngWebhookEvent.php:96`

If DNG sends nested arrays with varying key order, the hash will differ. This is low risk if DNG payloads are flat, but worth documenting the assumption.

### M3. `DngPaymentController::store` authorize returns `true` unconditionally
**File**: `CreateDngPaymentFormRequest.php:12`

No permission check. Any authenticated user can push debts to DNG. Should check for a finance admin permission.

**Fix**:
```php
public function authorize(): bool
{
    return $this->user()->can('finance.dng.create-payment');
}
```

### M4. Reconciliation job runs for ALL campuses every 15 minutes
**File**: `ReconcileDngPaymentsJob.php:34`, `routes/console.php:74`

For many campuses, this could be slow and hit DNG rate limits. Consider:
- Dispatching per-campus jobs in parallel
- Reducing frequency to hourly or configurable
- Adding a circuit breaker if DNG is down

### M5. `DngClient::post` logs full response body on error
**File**: `DngClient.php:163`

Response body could contain sensitive data (student PII). Truncate or sanitize before logging:
```php
'body' => Str::limit($response->body(), 500),
```

### M6. No factory classes for `DngPaymentRequest` or `DngWebhookEvent`
Tests manually create records via `::create()`. Per project conventions, models should have factories for cleaner test setup and reuse.

---

## Low Priority

### L1. `DngWebhookController` validates after `$request->all()`
**File**: `DngWebhookController.php:28-37`

`$payload = $request->all()` captures all input before validation. The payload hash is computed on raw input including extra fields. If DNG sends different extra fields with the same core data, dedup could fail. Consider using `$request->validated()` after validation for the hash computation.

### L2. Checksum string for webhook verification is a guess
**File**: `DngWebhookController.php:54-58`

Comment says "exact checksum string for callbacks must be confirmed with DNG." This needs to be validated against DNG's actual spec before go-live.

### L3. `ReconcileDngPaymentsJob` unused import
**File**: `ReconcileDngPaymentsJob.php:8`

`DngPaymentRequest` is imported but used via static method -- this is fine, just noting the import is used only for `stale()` and `awaitingInvoice()` scopes.

---

## Edge Cases Found by Scouting

1. **Callback 2 before Callback 1**: Tested and handled. Transition map allows `pushed_to_dng -> paid_invoiced` directly. Good.
2. **Fallback match (item_id + student_code)**: Could match wrong record if a student has multiple pending payments for the same item. Consider adding amount check to fallback query.
3. **`dng_payment_id` unique constraint on `dng_payment_requests`**: Good for dedup, but the fallback update (`$request->update(['dng_payment_id' => $dngPaymentId])`) could throw if another request already has that ID.
4. **`failed -> pending` transition**: Allows retry, but `createAndPush` always creates a new record. The transition is defined but no code path uses it. Dead transition?
5. **Reconciliation + webhook processing same payment simultaneously**: Both call `bridgeToPayment`. Without `lockForUpdate` (see H2), double payment creation is possible.

---

## Positive Observations

- Clean modular structure under `app/Modules/Finance/Dng/`
- Forward-only state machine with explicit transition map
- Dedup via `payload_hash` unique index
- Double-check pattern in `bridgeToPayment` (needs locking improvement but intent is correct)
- Comprehensive test suite covering happy path, mismatch, dedup, out-of-order callbacks
- CSRF exclusion properly scoped to `api/webhooks/dng/*`
- `hash_equals` for constant-time checksum comparison (timing-attack safe)
- Async webhook processing with retry/backoff
- Good logging throughout with structured context
- Services properly registered as singletons in `FinanceServiceProvider`

---

## Recommended Actions (Priority Order)

1. **[C1]** Fix dedup race condition with try-catch on unique violation
2. **[C2]** Add rate limiting to webhook endpoint; document IP allowlist
3. **[H2]** Add `lockForUpdate()` in `bridgeToPayment` transaction
4. **[H1]** Catch `ConnectionException` in `createAndPush`
5. **[H3]** Add `ShouldBeUnique` to `ProcessDngWebhookJob`
6. **[H4]** Read config per-call in `DngChecksumService` and `DngClient` (or accept restart requirement)
7. **[H5]** Add composite index for reconciliation queries
8. **[M3]** Add permission check to `CreateDngPaymentFormRequest::authorize()`
9. **[M5]** Truncate logged response bodies
10. **[M6]** Create model factories

---

## Metrics

| Metric | Value |
|--------|-------|
| Type coverage | Good -- strict_types everywhere, PHPDoc arrays |
| Test coverage | ~70% of services (webhook controller + job covered; reconciliation + DngClient not tested) |
| Linting issues | 0 (assumed -- pint not run in this review) |
| Migration quality | Good -- proper FK constraints, indexes, nullable columns |
| CSRF exclusion | Correct |

---

## Unresolved Questions

1. What is the exact checksum string format for DNG callbacks? Current implementation is a guess (see L2).
2. Does DNG have documented IP ranges for allowlisting webhook sources?
3. Is the `failed -> pending` transition intentional? No code path triggers it.
4. Should reconciliation create `DngPaymentRequest` records for orphan payments found in DNG, or just log them?
5. Is `PaymentService::recordPayment` safe to call from queue context (does it use any session/auth state)?
