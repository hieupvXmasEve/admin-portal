# DNG Payment Gateway Integration

Last updated: 2026-06-14
Status: Implementation complete. Webhook security/idempotency hardening delivered in
S-005 — FIN-16 (checksum on every event incl. Call 1), FIN-17/DB-04 (payload_hash
dedup + unique restored), FIN-18 (cancel_pushed_to_dng terminal ordering), FIN-15
(installment settlement lock), FIN-32 (campus mismatch defense-in-depth), FIN-34
(lifecycle cancel audit ordering). FIN-19 (move the batch external push out of the
uncommitted transaction) is deferred pending an outbox/compensation design decision.
Owner: Finance Module

See also: `finance-module-review-2026-06-13.md` (findings FIN-15..19, FIN-32/33)
and `docs/stories/E-finance-module-review-2026-06/S-005-dng-security-and-idempotency`.

## Overview

DNG is a Vietnamese payment gateway provider. Swinx integrates with DNG to:

1. Allow staff to create and push payment requests from the UI
2. Generate QR codes for student payments
3. Receive webhook notifications when payments are confirmed
4. Audit all DNG API interactions and webhook events
5. Allow staff to cancel unpaid DNG requests from the admin audit page

## Webhook Callback Protocol (provider source of truth)

> Authoritative description of how DNG calls back after a payment. This is the
> reference for webhook checksum verification and settle-once idempotency.
> Hardening of this flow is tracked in S-005 and findings FIN-15..19/32/33.

### Two-call settlement

For one successful payment, DNG calls the webhook **twice**:

1. **Call 1 — payment success.** `InvoiceSerialNumber` and `InvoiceDate` are
   empty. This call confirms money received and **settles** the payment.
2. **Call 2 — invoice issued.** Sent after the e-invoice exists, with
   `InvoiceSerialNumber` and `InvoiceDate` populated. This call only **attaches**
   invoice metadata to the already-settled payment; it must not create a second
   payment or settle again.

`ItemId` is identical on both calls and is the stable correlation / settle-once
key. `InvoiceSerialNumber` and the webhook payload hash both differ between the
two calls, so neither may be used as the settle-once key. A `payload_hash`
dedup therefore guards retries of the *same* call only — it does not (and must
not) collapse Call 1 and Call 2.

### Callback payload

```json
{
  "StudentId": "FSC0664",
  "StudentName": "Trương Ngọc Hân",
  "PaymentId": "12345678",
  "PSPCode": "BIDV",
  "FeeType": "HP",
  "Amount": 10000.0,
  "CampusCode": "FAN1HCM",
  "ItemId": "HP0ISH4BdH0S",
  "InvoiceSerialNumber": "1/001;K23TAA-00004142",
  "InvoiceDate": "2023-03-20T03:08:02",
  "CheckSum": "9kzQqcnhSboOl1qACVQR4dTYTqs%3d"
}
```

On Call 1, `InvoiceSerialNumber` and `InvoiceDate` are empty.

### Callback checksum (incoming)

Checksum string, exact order:

```
AccessCode + ClientCode + amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode
```

- `AccessCode` and `ClientCode` are server-side secrets (config); the checksum
  cannot be forged without them.
- On **Call 1** the `InvoiceSerialNumber` segment is the **empty string** — this
  is how DNG itself signs Call 1, so Call 1 **is verifiable** and must be
  checksum-checked, not skipped and not rejected.
- `amount` must be serialized with the exact format DNG uses (e.g. `10000.0`); a
  different decimal rendering yields a different hash and would reject a
  legitimate callback. Confirm against real payloads.
- `CheckSum` may arrive URL-encoded (e.g. `%3d` → `=`); decode before comparing.
- Compare with a constant-time function (`hash_equals`).

> This **incoming/callback** checksum differs from the **outgoing**
> `insertNewRecord` checksum
> (`AccessCode + ApiCode + CampusCode + Amount + ItemId + lowercase(StudentId)`,
> see `DngClient` below). Do not conflate the two.
> Because `CampusCode` is inside this string, correct checksum verification also
> guards campus mismatch (FIN-32); keep an explicit business-field match as
> defense-in-depth.

### Checksum enforcement (S-005, FIN-16 — fixed)

Call 1 is now checksum-verified with the empty `InvoiceSerialNumber` segment using
the same formula as Call 2 (`DngWebhookService::processEvent` no longer skips
verification for serial-less callbacks). A forged Call 1 is rejected as a
checksum mismatch; the legitimate empty-serial Call 1 still settles. The working
two-call settle-then-invoice behavior is unchanged — only verification, dedup,
locks, and audit ordering were added around it.

> Deploy note: the verifier reuses the exact formula already proven by Call 2 in
> production, and `verifyWebhookChecksum` tries multiple `amount` renderings.
> Before enabling in a new environment, confirm reproduced Call-1 checksums match
> real captured production payloads (execplan stop condition).

## Architecture

### Data Flow

```
Staff UI (Create Payment)
  ↓
Backend: PaymentController::create() [web form render]
  ↓
Staff selects student → GET /finance/payments/{student}/dng-data
  ↓
Frontend loads student data (name, email, code, CCCD, etc.)
  ↓
Staff enters amount, fee type, item id
  ↓
POST /api/v1/finance/dng/payment-requests
  ↓
DngPaymentService::createPaymentRequest()
  ↓
DngClient::insertNewRecord() [with HMAC checksum]
  ↓
DNG API returns QR + transaction ID
  ↓
DngPaymentRequest model audits request
  ↓
Frontend displays QR to student
  ↓
Student pays via DNG (external)
  ↓
DNG webhook → POST /api/webhooks/dng/payment
  ↓
DngWebhookController: dedup by payload_hash, store raw inbox event, ALWAYS 200
  ↓
ProcessDngWebhookJob (queue 'webhooks')
  ↓
DngWebhookService::processEvent()
  ↓ verifies checksum (incl. Call 1), cross-validates, transitions under row lock
DngPaymentService::bridgeToPayment() → canonical Payment (Call 1 only settles once)
  ↓
DngWebhookEvent marked processed / mismatch / skipped / failed_terminal
```

> The controller does NOT validate the checksum or return 403. It captures the raw
> payload (deduplicating exact retries) and always returns 200 so DNG stops retrying;
> all checksum/business validation happens in `DngWebhookService::processEvent` (run
> by the job), which records the outcome on the `DngWebhookEvent`.

### Database Tables

#### `dng_payment_requests`

Audit table for all payment requests sent to DNG.

Fields:

- `id` (UUID)
- `student_id` (FK to students)
- `campus_id` (FK to campuses)
- `dng_transaction_id` (nullable, from DNG response)
- `dng_payment_id` (nullable, from DNG response)
- `amount` (numeric)
- `fee_type` (string, e.g., 'HP', 'KH')
- `item_id` (string, unique per request)
- `request_payload` (JSON, full DNG request)
- `response_data` (JSON, full DNG response)
- `qr_data` (JSON, QR code details if returned)
- `status` (enum: pending, confirmed, failed)
- `created_by` (FK to users)
- `created_at`, `updated_at`

#### `dng_webhook_events`

Audit table for all webhook events received from DNG.

Fields:

- `id` (UUID)
- `dng_transaction_id` (from webhook payload)
- `dng_payment_id` (from webhook payload)
- `event_type` (string, e.g., 'payment_confirmed', 'payment_failed')
- `payload` (JSON, full webhook body)
- `checksum_valid` (boolean)
- `processed` (boolean)
- `processed_at` (nullable timestamp)
- `processing_error` (nullable text)
- `created_at`

### Controllers

#### `DngPaymentController`

API endpoint for creating payment requests.

Route: `POST /api/v1/finance/dng/payment-requests`
Middleware: `auth`, can:create_finance_payments
Validation: `CreateDngPaymentFormRequest`

Input:

```json
{
    "student_id": 123,
    "amount": 1000000,
    "fee_type": "HP",
    "item_id": "STU001_1711539900000"
}
```

Output:

```json
{
    "id": "uuid",
    "status": "pending",
    "dng_transaction_id": "TXN123",
    "dng_payment_id": "PAY123",
    "qr": {
        "qr_code": "data:image/png;base64,...",
        "qr_url": "https://..."
    }
}
```

#### `DngWebhookController`

Webhook receiver for DNG payment confirmations.

Route: `POST /api/webhooks/dng/payment` (`routes/api.php`, name `api.webhooks.dng.payment`)
Middleware: `throttle:60,1` only (public, no auth — checksum-validated in the pipeline)

Expected payload and the two-call protocol are documented authoritatively in
[Webhook Callback Protocol](#webhook-callback-protocol-provider-source-of-truth)
above (real fields: `StudentId`, `PaymentId`, `FeeType`, `Amount`, `CampusCode`,
`ItemId`, `InvoiceSerialNumber`, `InvoiceDate`, `CheckSum`). The earlier
`Code/Type/student_code` example was a placeholder and did not match the live
provider payload.

#### `DngPaymentRequestController`

Admin audit pages for DNG payment requests.

Routes:

- `GET /finance/dng/payment-requests`
- `GET /finance/dng/payment-requests/{dngPaymentRequest}`
- `POST /finance/dng/payment-requests/{dngPaymentRequest}/cancel`

Cancel behavior:

- Requires `create_finance_payments`.
- Only requests in `pending` or `pushed_to_dng` can be cancelled.
- Cancelled requests are terminal and remain protected from late webhook/reconciliation revival.
- The admin list uses a confirmation dialog before submitting cancel.
- Search filters are debounced through shared filter/table components.

Response:

```json
{
    "success": true,
    "message": "Webhook processed"
}
```

### Services

#### `DngClient`

Low-level HTTP client for DNG API calls.

Main methods:

- `insertNewRecord(array $data): array` — Push debt/payment to DNG (returns QR, transaction ID)
- `createVirtualAccountByFeeType(array $data): array` — Get virtual account for payment
- `checkPaymentStatus(array $data): array` — Poll payment status

Checksum flow (outgoing):

1. Build checksum string: `AccessCode + ApiCode + CampusCode + Amount + ItemId + lowercase(StudentId)`
2. Generate via `DngChecksumService::generate` — HMAC-SHA1 + base64, with `=`→`%3d`
   and space→`+` (keyed by `DNG_HASH_KEY`)
3. Append as `CheckSum` field in request payload

#### `DngPaymentService`

High-level service for payment workflow.

Main method:

- `createPaymentRequest(int $studentId, float $amount, string $feeType, string $itemId): array`

Process:

1. Fetch student + campus
2. Prepare payload (student code as string MSSV, not int ID)
3. Call `DngClient::insertNewRecord()`
4. Audit request/response in `DngPaymentRequest` model
5. Return response with QR

**Critical**: Student identifier is `students.student_id` (string MSSV), not `students.id` (int DB PK).

#### `DngWebhookService`

Processes incoming webhook events.

Main method:

- `processEvent(DngWebhookEvent $event): void`

Process:

1. Resolve the `DngPaymentRequest` by `ItemId + StudentId + Campus` (stable
   correlation key), falling back to `dng_payment_id`/`dng_transaction_id`.
2. Validate checksum (HMAC-SHA1) using the incoming callback string, with the empty
   `InvoiceSerialNumber` segment on Call 1 — see
   [Webhook Callback Protocol](#webhook-callback-protocol-provider-source-of-truth).
3. Cross-validate amount/student/fee/campus/item; bind `dng_payment_id` only after
   validation passes, inside the locked transition.
4. Transition status under a row lock (forward only): Call 1 → `paid_uninvoiced`,
   Call 2 → `paid_invoiced` (invoice-attach only — settle/notify run once, on the
   first paid transition).
5. Bridge to the canonical `Payment` via `DngPaymentService::bridgeToPayment()`.
6. Record the outcome on `DngWebhookEvent.processing_status`.

#### `DngReconciliationService`

Daily fallback that reconciles DNG's paid-of-day feed against local requests.

Main method:

- `reconcileDay(string $campusCode, string $date): array` (returns
  `{backfilled, up_to_date, orphans, errors}`)

Process (per transaction):

1. Resolve the local request by `ItemId + StudentId + Campus` first, then `PaymentId`.
2. Cross-validate; skip terminal/cancelled; refuse illegal transitions.
3. Under a row lock, backfill status + bind `dng_payment_id` if null, then bridge to
   `Payment` and settle the linked installment (idempotent).

#### `DngChecksumService`

HMAC-SHA1 checksum generation/validation (base64, `=`↔`%3d` tolerant on verify).

Methods:

- `generate(string $data): string` — Generate checksum for outgoing requests
  (string: `AccessCode + ApiCode + CampusCode + Amount + ItemId + lowercase(StudentId)`)
- `validate(string $data, string $checksum): bool` — Validate incoming webhook
  checksum (string:
  `AccessCode + ClientCode + amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode`,
  with empty `InvoiceSerialNumber` on Call 1; decode URL-encoded checksum and use
  `hash_equals`)

The outgoing and incoming checksum strings are different — see
[Webhook Callback Protocol](#webhook-callback-protocol-provider-source-of-truth).

Key: `DNG_HASH_KEY` env var.

### Jobs

#### `ProcessDngWebhookJob`

Async webhook processing.

Triggered by: `DngWebhookController` after the inbox event is stored

Process:

1. Load the `DngWebhookEvent` by id; skip if already in a terminal state
   (`processed` / `mismatch` / `failed_terminal` / `skipped`)
2. Call `DngWebhookService::processEvent()` (checksum + validation + transition)
3. The service sets `DngWebhookEvent.processing_status` to the outcome
4. On exception: retry with backoff (`tries = 5`); exhausted → `failed_terminal`

#### `ReconcileDngPaymentsJob`

Scheduled reconciliation (fallback).

Frequency: Daily (configurable)

Process:

1. Find pending `DngPaymentRequest` records older than 1 hour
2. Poll DNG for payment status via `DngClient::checkPaymentStatus()`
3. If confirmed, trigger reconciliation
4. Update status in `dng_payment_requests`

## Frontend Integration

### Create Payment Page

File: `resources/js/pages/Finance/Payments/Create.vue`

Workflow:

1. Staff navigates to `/finance/payments/create`
2. Searches for and selects a student
3. Frontend calls `GET /finance/payments/{student}/dng-data`
4. Pre-populates form: name, email, code, CCCD, campus code
5. Staff enters amount, fee type, item ID
6. Form submits to `POST /api/v1/finance/dng/payment-requests`
7. Receives QR code in response
8. QR displayed to student for payment

Form fields:

- `student` (autocomplete select)
- `amount` (number, required)
- `fee_type` (select, default 'HP')
- `item_id` (text, auto-generated from student code + timestamp)
- `estimate_time` (hidden, auto-filled MM/YY format)

### Payment Index Page

File: `resources/js/pages/Finance/Payments/Index.vue`

Added button:

- "Create Payment" → redirects to Create page
- Only visible if user has `create_finance_payments` permission

### DNG Payment Requests Admin Pages

Files:

- `resources/js/pages/Finance/Payments/DngPaymentRequests/Index.vue`
- `resources/js/pages/Finance/Payments/DngPaymentRequests/Show.vue`

Routes:

- `GET /finance/dng/payment-requests`
- `GET /finance/dng/payment-requests/{dngPaymentRequest}`

Behavior:

- Lists campus-scoped DNG request audit records.
- Request list and detail access scope by the linked student's internal campus, not by `dng_payment_requests.campus_code`.
- Supports `search`, `status`, `has_payment`, `has_webhook`, `created_from`, `created_to`, `sort`, `direction`, and `per_page` filters.
- Detail page shows linked `payment` data, stored request/response payloads, and related webhook events.

### DNG Webhook Events Admin Pages

Files:

- `resources/js/pages/Finance/Payments/DngWebhookEvents/Index.vue`
- `resources/js/pages/Finance/Payments/DngWebhookEvents/Show.vue`

Routes:

- `GET /finance/dng/webhook-events`
- `GET /finance/dng/webhook-events/{dngWebhookEvent}`

Behavior:

- Lists webhook audit events across campuses, including orphan payloads without a linked request.
- Supports `search`, `processing_status`, `event_type`, `checksum_validity`, `linked_request`, `created_from`, `created_to`, `sort`, `direction`, and `per_page` filters.
- Detail page still enforces campus access using the linked request `campus_code` or, for orphan events, the webhook payload campus code field.
- Detail page shows payload facts, checksum validity, processing status, and linked request/payment context when available.

## Configuration

Environment variables:

```
DNG_BASE_URL=https://api.dng.vn
DNG_ACCESS_CODE=YOUR_ACCESS_CODE
DNG_HASH_KEY=YOUR_HASH_KEY
DNG_API_CODE=HC_ASIA
DNG_CLIENT_CODE=HC_ASIA
DNG_LOGIN=HC_ASIA
DNG_API_TIMEOUT=30
```

Mapped to `config/services.php`:

```php
'dng' => [
    'base_url' => env('DNG_BASE_URL'),
    'access_code' => env('DNG_ACCESS_CODE'),
    'hash_key' => env('DNG_HASH_KEY'),
    'api_code' => env('DNG_API_CODE', 'HC_ASIA'),
    'client_code' => env('DNG_CLIENT_CODE', 'HC_ASIA'),
    'login' => env('DNG_LOGIN', 'HC_ASIA'),
    'timeout' => env('DNG_API_TIMEOUT', 30),
]
```

Campus mapping:

- DNG campus code is now resolved from `campuses.dng_code` instead of a single global env var.
- Leave `dng_code` nullable for campuses that do not integrate with DNG.
- Request creation should fail validation/business checks if the current campus has no `dng_code`.

## Permission

Relevant permissions:

- `create_finance_payments`
- `view_finance_dng_payment_requests`
- `view_finance_dng_webhook_events`

Gating:

- `GET /finance/payments/create` — View payment creation form
- `GET /finance/payments/{student}/dng-data` — Fetch student DNG data
- `POST /api/v1/finance/dng/payment-requests` — Submit payment request
- `GET /finance/dng/payment-requests` — View DNG request audit list
- `GET /finance/dng/payment-requests/{dngPaymentRequest}` — View DNG request detail
- `GET /finance/dng/webhook-events` — View DNG webhook audit list
- `GET /finance/dng/webhook-events/{dngWebhookEvent}` — View DNG webhook event detail

## Error Handling

### DNG API Errors

`DngClient` throws `RuntimeException` on:

- Network failures (caught as `ConnectionException`)
- HTTP errors (5xx, 4xx with meaningful body)
- Timeout (configurable, default 30s)

Controller catches and returns JSON error response.

### Webhook Validation

`DngWebhookController` always returns 200 (it never rejects with 403/400) — it only
captures the raw payload and deduplicates exact retries. Validation happens in
`DngWebhookService::processEvent` (via `ProcessDngWebhookJob`), which records the
outcome on the `DngWebhookEvent.processing_status`:

- Missing `PaymentId` → `failed_terminal` (`malformed_payload`)
- No matching request → `failed_terminal` (`not_found`)
- Checksum invalid (incl. Call 1) → `mismatch` (`checksum`) — no settlement
- Amount/student/fee/campus/item mismatch → `mismatch` — **no Payment created**
- Cancelled/superseded or already-progressed → `skipped`
- Valid first settlement → `processed` (bridges Payment, settles installment once)

### Reconciliation Failures

`DngReconciliationService` logs errors but does not throw:

- Student/request not found → `orphans++`, skip (no Payment)
- Amount/student/campus/item mismatch → `errors++`, skip — **no Payment created**
- Illegal transition (e.g. a `failed` request) → `errors++`, skip — no bridge/settle

Jobs retry with `ShouldQueue` (`tries = 5`, exponential backoff); exhausted attempts
mark the event `failed_terminal`.

## Security Considerations

1. **Checksum Validation**: All DNG requests and webhooks validated with HMAC-SHA1 (base64).
2. **Student Identity**: Uses `students.student_id` (string MSSV), not DB primary key.
3. **Sensitive Data**: Avoid logging full payment amounts or CCCD in production. Audit tables store full payloads for reconciliation only.
4. **Webhook Authentication**: Checksum is the only authentication (DNG does not send API key in webhook). No IP allowlist needed.
5. **Campus Isolation**: request access is scoped by the linked student's internal campus; DNG external campus mapping is stored in `campuses.dng_code`.

## Testing

### Unit Tests

- `DngChecksumServiceTest` — Checksum generation/validation
- `DngClientTest` — API call mocking

### Feature Tests

- `DngWebhookControllerTest` — Webhook receive + validation
- `ProcessDngWebhookJobTest` — Job processing
- `DngAdminPagesTest` — Admin request/event index + detail pages; webhook index includes cross-campus and orphan events
- Payment creation form validation

Run tests:

```bash
./scripts/dev.sh test --filter=Dng
```

## Troubleshooting

### "Checksum invalid" (event `processing_status = mismatch`, category `checksum`)

- Verify `DNG_HASH_KEY` matches DNG's test/prod key
- Ensure the current campus has the correct `campuses.dng_code`
- Check `student_code` is string (MSSV), not int
- Confirm `DNG_ACCESS_CODE`/`DNG_CLIENT_CODE` config (both are in the callback string)
- The checksum is accepted in both `%3d` and decoded `=` padding forms

### "Payment not reconciled"

- Check `dng_webhook_events` table for webhook receipt
- Verify `processing_status = 'processed'` and `is_valid_checksum = true`
- For a stuck event, inspect `error_category` / `error_message` (mismatch, not_found, …)
- Check `DngReconciliationService` logs for matching errors
- Manual matching: search `dng_payment_requests` by item_id / student + amount + date

### "QR not displaying"

- Verify DNG response includes `qr` field
- Check browser console for `POST /api/v1/finance/dng/payment-requests` errors
- Verify staff user has `create_finance_payments` permission

## Future Enhancements

- [ ] Batch payment request API for bulk uploads

## Replacement Rule

- DNG only keeps one unpaid debt per `student + fee_type`.
- When Swinx creates a newer request for the same `student + fee_type`, old unpaid requests in `pending` or `pushed_to_dng` are moved to `cancelled` only after the new push succeeds.
- If the new push fails, older unpaid requests remain unchanged.
- Late webhook/reconciliation events for cancelled requests are skipped and must not create/bridge payments.
- [ ] Payment status polling UI (real-time QR refresh)
- [ ] Webhook signature verification via DNG public key (if offered)
- [ ] Payment reconciliation dashboard
- [ ] DNG fee/commission tracking per payment
