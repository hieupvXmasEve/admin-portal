# DNG Payment Gateway Integration

Last updated: 2026-03-26
Status: Implementation complete
Owner: Finance Module

## Overview

DNG is a Vietnamese payment gateway provider. Swinx integrates with DNG to:

1. Allow staff to create and push payment requests from the UI
2. Generate QR codes for student payments
3. Receive webhook notifications when payments are confirmed
4. Audit all DNG API interactions and webhook events

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
DNG webhook → POST /api/v1/finance/dng/webhook
  ↓
DngWebhookController validates checksum
  ↓
ProcessDngWebhookJob queues async processing
  ↓
DngWebhookService::processPaymentNotification()
  ↓
DngReconciliationService matches to Payment record
  ↓
DngWebhookEvent audit stored
```

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

Route: `POST /api/v1/finance/dng/webhook`
Middleware: none (public, but checksum-validated)

Expected payload from DNG:

```json
{
    "Code": 200,
    "Type": "payment_confirmed",
    "Message": "Success",
    "dng_transaction_id": "TXN123",
    "dng_payment_id": "PAY123",
    "amount": 1000000,
    "student_code": "STU001",
    "CheckSum": "hmac_sha256_hash"
}
```

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

Checksum flow:

1. Build checksum string: `AccessCode + ApiCode + CampusCode + Amount + ItemId + lowercase(StudentId)`
2. Generate HMAC-SHA256 using `DNG_HASH_KEY`
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

- `processPaymentNotification(array $data): void`

Process:

1. Validate checksum (SHA256 HMAC)
2. Find matching `DngPaymentRequest` by `dng_transaction_id` or `dng_payment_id`
3. Call `DngReconciliationService::reconcile()` to create/match `Payment` record
4. Update `DngPaymentRequest.status = confirmed`
5. Audit in `DngWebhookEvent`

#### `DngReconciliationService`

Matches DNG webhook data to Payment records.

Main method:

- `reconcile(DngPaymentRequest $dngRequest): Payment`

Process:

1. Search for existing `Payment` by student + amount + date
2. If not found, create new `Payment` record with `source = dng`
3. Link to `DngPaymentRequest` via `dng_payment_id`
4. Return `Payment` model

#### `DngChecksumService`

HMAC-SHA256 checksum generation/validation.

Methods:

- `generate(string $data): string` — Generate checksum for outgoing requests
- `validate(string $data, string $checksum): bool` — Validate incoming webhook checksum

Key: `DNG_HASH_KEY` env var.

### Jobs

#### `ProcessDngWebhookJob`

Async webhook processing.

Triggered by: `DngWebhookController` after checksum validation

Process:

1. Deserialize webhook payload
2. Call `DngWebhookService::processPaymentNotification()`
3. Update `DngWebhookEvent.processed = true`
4. On error: log, update `processing_error`, do not retry

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
DNG_CAMPUS_CODE=FAUHN              # Configurable per campus
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
    'campus_code' => env('DNG_CAMPUS_CODE', 'FAUHN'),
    'timeout' => env('DNG_API_TIMEOUT', 30),
]
```

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

`DngWebhookController` rejects if:

- Checksum invalid → 403 Forbidden
- Payload missing required fields → 400 Bad Request

### Reconciliation Failures

`DngReconciliationService` logs errors but does not throw:

- Student not found → log warning, skip reconciliation
- Amount mismatch → log info, still create Payment record (manual review required)

Jobs retry with `ShouldQueue` + `Retryable` traits (configurable attempts).

## Security Considerations

1. **Checksum Validation**: All DNG requests and webhooks validated with HMAC-SHA256.
2. **Student Identity**: Uses `students.student_id` (string MSSV), not DB primary key.
3. **Sensitive Data**: Avoid logging full payment amounts or CCCD in production. Audit tables store full payloads for reconciliation only.
4. **Webhook Authentication**: Checksum is the only authentication (DNG does not send API key in webhook). No IP allowlist needed.
5. **Campus Isolation**: `dng_payment_requests` and `dng_webhook_events` include `campus_id` for multi-campus support.

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
php artisan test --filter=Dng
```

## Troubleshooting

### "Checksum invalid"

- Verify `DNG_HASH_KEY` matches DNG's test/prod key
- Ensure `DNG_CAMPUS_CODE` is correct
- Check `student_code` is string (MSSV), not int

### "Payment not reconciled"

- Check `dng_webhook_events` table for webhook receipt
- Verify `processed = true` and `checksum_valid = true`
- Check `DngReconciliationService` logs for matching errors
- Manual matching: search `dng_payment_requests` by student + amount + date

### "QR not displaying"

- Verify DNG response includes `qr` field
- Check browser console for `POST /api/v1/finance/dng/payment-requests` errors
- Verify staff user has `create_finance_payments` permission

## Future Enhancements

- [ ] Batch payment request API for bulk uploads
- [ ] Payment status polling UI (real-time QR refresh)
- [ ] Webhook signature verification via DNG public key (if offered)
- [ ] Payment reconciliation dashboard
- [ ] DNG fee/commission tracking per payment
