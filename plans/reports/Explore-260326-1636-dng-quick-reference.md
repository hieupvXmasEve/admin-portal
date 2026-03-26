# DNG Payment Webhook - Quick Reference

## Route & Entry Point

```
POST /api/webhooks/dng/payment
→ DngWebhookController (invokable)
→ Throttled 60/min, no auth required
→ Checksum verified in controller
```

## Incoming Webhook Payload

```json
{
  "StudentId": "STU001",                    // Required
  "PaymentId": "12345678",                  // Required - unique identifier
  "Amount": "5000000",                      // Required
  "CampusCode": "CAMPUS001",                // Required
  "CheckSum": "...",                        // Required - HMAC-SHA1
  "ItemId": "ITEM001",                      // Required
  "PSPCode": "BIDV",                        // Optional
  "FeeType": "tuition",                     // Optional
  "InvoiceSerialNumber": "1/001;K23...",   // Optional - triggers paid_invoiced
  "InvoiceDate": "2023-03-20T03:08:02"     // Optional - triggers paid_invoiced
}
```

## Quick Checksum Rules

**Webhook Verification:**
```
Value = CampusCode + StudentId + PaymentId + Amount
CheckSum = HMAC-SHA1(Value, DNG_HASH_KEY) → base64 → replace = with %3d, space with +
Verify = hash_equals(generated, provided)
```

**Other Operations:**
- Push Debt: `AccessCode + ApiCode + CampusCode + Amount + ItemId + strtolower(StudentId)`
- Get QR: `strtolower(AccessCode + ApiCode + StudentCode)`
- Reconcile: `CampusCode + AccessCode + Date`

## Database Tables (Key Columns)

**dng_payment_requests**
- `dng_payment_id` (unique, indexed) - Primary match key
- `student_code`, `item_id`, `campus_code` - Fallback match
- `status` - pending → pushed_to_dng → qr_ready → paid_uninvoiced → paid_invoiced → reconciled
- `payment_id` (FK) - Bridge to canonical payment

**dng_webhook_events**
- `payload_hash` (unique) - SHA-256 dedup
- `dng_payment_id` (indexed)
- `is_valid_checksum` - Boolean result
- `processing_status` - pending → processed (or failed/mismatch)
- `dng_payment_request_id` (FK) - Links to payment request

## Event Processing Flow

1. **Controller** - Validates fields, verifies checksum, deduplicates via payload_hash
2. **Returns 200 immediately** - Whether checksum valid or not
3. **If checksum valid** - Dispatches `ProcessDngWebhookJob` async
4. **Job processes** - Finds DngPaymentRequest, cross-validates, transitions state
5. **Bridges to Payment** - Creates canonical payment record, auto-allocates

## Status Transitions

```
pending → pushed_to_dng
pushed_to_dng → qr_ready (if QR pulled)
pushed_to_dng → paid_uninvoiced (callback without invoice)
paid_uninvoiced → paid_invoiced (second callback with invoice)
```

**Two-Callback Pattern:**
- DNG calls webhook twice for same PaymentId
- First: no invoice fields → paid_uninvoiced
- Second: invoice fields present → paid_invoiced
- Both have same PaymentId, processed as state machine advances

## Service Layer Map

| Service | Purpose |
|---------|---------|
| `DngClient` | HTTP calls to DNG (InsertNewRecord, createVirtualAccountByFeeType, checkPaidOfDay) |
| `DngChecksumService` | Generate/verify HMAC-SHA1 checksums |
| `DngPaymentService` | Create request, push to DNG, pull QR, bridge to Payment |
| `DngWebhookService` | Process webhook events, state transitions |
| `DngReconciliationService` | Reconcile via checkPaidOfDay, backfill missing payments |

## Jobs

| Job | Schedule | Queue | Purpose |
|-----|----------|-------|---------|
| `ProcessDngWebhookJob` | On-demand | `webhooks` | Async webhook processing (5 retries, backoff: 10/30/60/300/600s) |
| `ReconcileDngPaymentsJob` | Every 15min | `reconciliation` | Reconcile all campuses, detect orphans/stale |

## Controllers

**DngPaymentController**
- `POST /dng-payments` - Create payment (requires request validation)
- `GET /dng-payments/{id}/qr` - Pull QR code

**DngWebhookController**
- `POST /api/webhooks/dng/payment` - Receive webhook (invokable)

## Environment Variables (Required)

```bash
DNG_BASE_URL=https://googleauthensite02.fpt.edu.vn:90
DNG_ACCESS_CODE=          # Must be set
DNG_HASH_KEY=             # Must be set - used for all HMAC
DNG_API_CODE=HC_SWB       # Default
DNG_CLIENT_CODE=HC_ASIA   # Default
DNG_LOGIN=HC_SWB          # Default
DNG_CAMPUS_CODE=FPTUHN    # Default
DNG_API_TIMEOUT=30        # Default
```

## DNG API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/apiv2/InsertNewRecord` | POST | Create debt/payment record |
| `/api/dng/createvirtualaccountbyfeetype` | POST | Get QR/Virtual Account |
| `/api/checkpaid/checkpaidofday` | POST | Reconcile payments by date |

## Error Handling

**Invalid Checksum:**
- Controller returns 401 immediately
- Event marked as `failed`
- Job not dispatched

**Duplicate Payload:**
- Unique constraint on `payload_hash`
- Returns 200 "Already received"
- No re-processing

**Processing Failures:**
- Job retries 5 times with backoff
- After max retries, marked `failed`
- Can manually retry by re-dispatching job

**Data Mismatches:**
- Amount differs by >0.01 → mismatch
- Student code differs → mismatch
- Event marked as `mismatch`, not `processed`

## Key Design Patterns

1. **Async processing** - Returns 200 immediately, processes in queue
2. **Idempotent** - Payload hash dedup + unique constraint
3. **State machine** - Only forward transitions allowed
4. **Two-phase callbacks** - Handles DNG sending same PaymentId twice
5. **Reconciliation fallback** - Primary by PaymentId, secondary by item_id+student_code
6. **Locked row bridges** - Row-lock prevents race conditions when creating canonical Payment

## Critical Notes

- ⚠️ Checksum string for webhook callback NOT officially confirmed with DNG
- ⚠️ PaymentId field mapping in DNG response needs confirmation
- ⚠️ Same `DNG_HASH_KEY` used for all HMAC operations - if rotated, old payloads invalid
- ⚠️ Webhook endpoint must be publicly accessible to DNG servers
- ⚠️ Two callbacks for same payment - system must handle state advancement correctly

