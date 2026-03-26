# DNG Payment Gateway Webhook Implementation - Exploration Report

**Date:** 2026-03-26  
**Status:** Complete  
**Scope:** Routes, Controllers, Models, Services, Configuration, Migrations, Jobs

---

## 1. WEBHOOK ROUTES/ENDPOINTS

### Incoming Webhook (Public)
**Route:** `POST /api/webhooks/dng/payment`  
**File:** `/routes/api.php` (line 29-32)  
**Middleware:** `throttle:60,1` (60 requests per minute)  
**Name:** `api.webhooks.dng.payment`  
**Controller:** `App\Modules\Finance\Dng\Http\Controllers\DngWebhookController` (invokable)

**Characteristics:**
- Public endpoint (no authentication required)
- Checksum verification performed in controller
- Throttled to prevent abuse
- Runs async job for processing

### Outgoing API Calls (to DNG)
**Base URL:** Configurable via `DNG_BASE_URL` (default: `https://googleauthensite02.fpt.edu.vn:90`)

Three main outbound operations:
1. **InsertNewRecord** - Push debt/payment to DNG
   - Endpoint: `/api/apiv2/InsertNewRecord`
   - Method: POST
   
2. **CreateVirtualAccountByFeeType** - Get QR/Virtual Account
   - Endpoint: `/api/dng/createvirtualaccountbyfeetype`
   - Method: POST
   
3. **CheckPaidOfDay** - Reconcile payments
   - Endpoint: `/api/checkpaid/checkpaidofday`
   - Method: POST

---

## 2. WEBHOOK PAYLOAD STRUCTURE

### Incoming Webhook Payload (DNG → Laravel)

**Required Fields:**
```json
{
  "StudentId": "STU001",              // Student code (e.g., "FSC0664")
  "StudentName": "Student Name",       // Optional in validation but present in samples
  "PaymentId": "12345678",            // Unique payment identifier - CRITICAL for matching
  "Amount": "10000000",               // Amount in smallest unit
  "CampusCode": "CAMPUS001",          // Campus identifier
  "CheckSum": "9kzQqcnhSboOl1q...",  // HMAC-SHA1 base64 checksum
  "ItemId": "HP0ISH4BdH0S",          // Fee item identifier
  "PSPCode": "BIDV",                  // Payment service provider (nullable)
  "FeeType": "HP",                    // Fee type
  "InvoiceSerialNumber": "1/001;K23TAA-00004142",  // Present only after invoicing (nullable)
  "InvoiceDate": "2023-03-20T03:08:02"  // Timestamp when invoice generated (nullable)
}
```

**Validation (in Controller):**
- Required: `StudentId`, `PaymentId`, `Amount`, `CampusCode`, `CheckSum`
- Optional: `InvoiceSerialNumber`, `InvoiceDate`, `PSPCode`

**Two Event Types Differentiated By Invoice Fields:**
1. `payment_succeeded_without_invoice` - `InvoiceSerialNumber` and `InvoiceDate` are null/missing
2. `payment_invoiced` - Both invoice fields present

### Response Format (from Controller)

**Success (200):**
```json
{
  "Code": 200,
  "Type": "Success",
  "Message": "Accepted",
  "data": null
}
```

**Duplicate Detection (Already Received):**
```json
{
  "Code": 200,
  "Type": "Success",
  "Message": "Already received",
  "data": null
}
```

**Validation Failure (400):**
Returns Laravel validation error response

**Invalid Checksum (401):**
```json
{
  "Code": 401,
  "Type": "Error",
  "Message": "Invalid checksum",
  "data": null
}
```

### Outgoing Payloads (Laravel → DNG)

**InsertNewRecord (Create Debt):**
```json
{
  "ApiCode": "HC_SWB",
  "StudentId": "STU001",
  "CampusCode": "CAMPUS001",
  "Type": "tuition",
  "Amount": 5000000,
  "ItemId": "ITEM001",
  "Login": "HC_SWB",
  "CheckSum": "...",
  "StudentName": "Name",
  "Email": "student@example.com",
  "EstimateTime": "30",
  "StudentAddress": "Address",
  "CCCD": "ID_NUMBER" // optional
}
```

**CreateVirtualAccountByFeeType (Get QR):**
```json
{
  "StudentCode": "STU001",
  "ApiCode": "HC_SWB",
  "CampusCode": "CAMPUS001",
  "FeeTypes": ["tuition", "activity"],
  "CheckSum": "..."
}
```

**CheckPaidOfDay (Reconciliation):**
```json
{
  "CampusCode": "CAMPUS001",
  "Date": "2026-03-26",
  "ClientCode": "HC_ASIA",
  "CheckSum": "..."
}
```

---

## 3. AUTHENTICATION & VERIFICATION MECHANISM

### Checksum Algorithm

**Implementation:** `DngChecksumService` (`/app/Modules/Finance/Dng/Services/DngChecksumService.php`)

**Algorithm:**
1. HMAC-SHA1 hash using `DNG_HASH_KEY` environment variable
2. Base64 encode result
3. Replace `=` with `%3d`
4. Replace space with `+`

```php
$hash = hash_hmac('sha1', $value, $this->hashKey, true);
$checksum = str_replace(['=', ' '], ['%3d', '+'], base64_encode($hash));
```

### Checksum String Construction (by operation)

**For Webhook Callback Verification:**
```
CampusCode + StudentId + PaymentId + Amount
```
Example: `CAMPUS001STU001PAY0015000000`

**For InsertNewRecord (Push Debt):**
```
AccessCode + ApiCode + CampusCode + Amount + ItemId + strtolower(StudentId)
```

**For CreateVirtualAccountByFeeType (Get QR):**
```
strtolower(AccessCode + ApiCode + StudentCode)
```

**For CheckPaidOfDay (Reconciliation):**
```
CampusCode + AccessCode + Date
```

### Configuration

**File:** `/config/services.php` (lines 56-65)

```php
'dng' => [
    'base_url' => env('DNG_BASE_URL', 'https://googleauthensite02.fpt.edu.vn:90'),
    'access_code' => env('DNG_ACCESS_CODE'),           // MUST be configured
    'hash_key' => env('DNG_HASH_KEY'),                 // MUST be configured
    'api_code' => env('DNG_API_CODE', 'HC_SWB'),
    'client_code' => env('DNG_CLIENT_CODE', 'HC_ASIA'),
    'login' => env('DNG_LOGIN', 'HC_SWB'),
    'campus_code' => env('DNG_CAMPUS_CODE', 'FPTUHN'),
    'timeout' => (int) env('DNG_API_TIMEOUT', 30),
]
```

### Security Features

1. **Deduplication:** Payloads hashed (SHA-256) and unique constraint on `payload_hash`
2. **Checksum Validation:** HMAC-SHA1 constant-time comparison via `hash_equals()`
3. **Invalid Checksum Handling:** Events marked failed, 401 returned to DNG
4. **Throttling:** 60 requests/minute rate limit

---

## 4. EVENTS & STATE TRANSITIONS

### Event Types

**Webhook Event Model:** `DngWebhookEvent` 

Events differentiated by presence of invoice fields:

| Event Type | Constant | Condition | Target Status |
|------------|----------|-----------|----------------|
| Payment without invoice | `EVENT_PAYMENT_WITHOUT_INVOICE` | `InvoiceSerialNumber` && `InvoiceDate` null | `paid_uninvoiced` |
| Payment invoiced | `EVENT_PAYMENT_INVOICED` | Both fields present | `paid_invoiced` |

### Payment Request State Machine

**Model:** `DngPaymentRequest`

**Status Constants:**
- `pending` - Initial local record created
- `pushed_to_dng` - Successfully pushed to DNG
- `qr_ready` - QR/Virtual Account retrieved
- `paid_uninvoiced` - Payment confirmed but no invoice
- `paid_invoiced` - Payment confirmed with invoice
- `reconciled` - Reconciliation complete
- `failed` - Operation failed

**Allowed Transitions (defined in TRANSITIONS constant):**

```
pending → [pushed_to_dng, failed]
pushed_to_dng → [qr_ready, paid_uninvoiced, paid_invoiced, failed]
qr_ready → [paid_uninvoiced, paid_invoiced, failed]
paid_uninvoiced → [paid_invoiced, reconciled]
paid_invoiced → [reconciled]
reconciled → [] (terminal)
failed → [pending] (can retry)
```

**Webhook Event Status:**
- `pending` - Received, queued for processing
- `processed` - Successfully processed
- `failed` - Processing failed after retries
- `skipped` - Skipped due to already processed
- `mismatch` - Data mismatch with local record

---

## 5. DTOS, REQUEST CLASSES & RESPONSE FORMATS

### Request Validation Class

**File:** `/app/Modules/Finance/Dng/Http/Requests/CreateDngPaymentFormRequest.php`

**Fields:**
```php
'student_id' => ['required', 'integer', 'exists:students,id'],
'campus_code' => ['required', 'string', 'max:20'],
'student_code' => ['required', 'string', 'max:50'],
'fee_type' => ['required', 'string', 'max:20'],
'item_id' => ['required', 'string', 'max:100'],
'amount' => ['required', 'numeric', 'min:1'],
'type' => ['required', 'string', 'max:20'],
'student_name' => ['required', 'string', 'max:255'],
'email' => ['required', 'email', 'max:255'],
'estimate_time' => ['required', 'string', 'max:20'],
'student_address' => ['required', 'string', 'max:500'],
'cccd' => ['nullable', 'string', 'max:20'],
'fee_types' => ['nullable', 'array'],  // For pulling QR
'fee_types.*' => ['string', 'max:20'],
```

### Response DTOs (Controller Returns)

**DngPaymentController::store() - Create Payment:**
```json
{
  "data": {
    "id": 1,
    "status": "pushed_to_dng",
    "dng_transaction_id": "TRX123",
    "dng_payment_id": "PAY123",
    "qr": null  // or QR data if fee_types provided
  }
}
```

**DngPaymentController::qr() - Get QR:**
```json
{
  "data": {
    // Full DNG response structure
  }
}
```

---

## 6. DATABASE SCHEMA

### Table: dng_payment_requests

**File:** `/database/migrations/2026_03_26_134500_create_dng_payment_requests_table.php`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| student_id | bigint FK | References students.id |
| campus_code | varchar(20) | Campus identifier |
| student_code | varchar(50) | DNG StudentId |
| fee_type | varchar(20) | Fee type |
| item_id | varchar(100) | Fee item identifier |
| amount | decimal(15,2) | Payment amount |
| status | varchar(30) | Current state (indexed) |
| dng_transaction_id | varchar(100) | DNG TransactionID or Id |
| dng_payment_id | varchar(100) | Unique, DNG PaymentId (indexed, unique) |
| payment_id | bigint FK | Bridge to canonical Payment |
| psp_code | varchar(50) | Payment service provider code |
| invoice_serial_number | varchar(255) | Invoice number from callback |
| invoice_date | timestamp | Invoice date from callback |
| paid_at | timestamp | When payment confirmed |
| push_payload | json | Full payload sent to DNG |
| push_response | json | Response from DNG push |
| qr_payload | json | QR/VA response from DNG |
| last_callback_payload | json | Last webhook callback received |
| error_message | text | Error description if failed |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:**
- Unique: `dng_payment_id`
- Index: `student_id`, `status`, `item_id`
- Composite: `(campus_code, item_id, student_code)` for reconciliation

### Table: dng_webhook_events

**File:** `/database/migrations/2026_03_26_134501_create_dng_webhook_events_table.php`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| dng_payment_id | varchar(100) | DNG PaymentId from payload (indexed) |
| event_type | varchar(50) | Type: payment_succeeded_without_invoice or payment_invoiced |
| payload_hash | varchar(64) | SHA-256 hash for dedup (unique) |
| headers | json | HTTP headers from webhook |
| payload | json | Full webhook payload |
| is_valid_checksum | boolean | Checksum verification result |
| processed_at | timestamp | When processing completed |
| processing_status | varchar(30) | Status: pending, processed, failed, mismatch (indexed) |
| dng_payment_request_id | bigint FK | Links to dng_payment_requests |
| error_message | text | Error if processing failed |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:**
- Unique: `payload_hash` (prevents duplicate processing)
- Index: `dng_payment_id`, `processing_status`

---

## 7. SERVICE LAYER

### DngClient (Outbound API)

**File:** `/app/Modules/Finance/Dng/Services/DngClient.php`

**Methods:**
1. `insertNewRecord(array $data): array` - Push debt to DNG
2. `createVirtualAccountByFeeType(array $data): array` - Get QR
3. `checkPaidOfDay(string $campusCode, string $date): array` - Reconcile
4. Private `post(string $endpoint, array $payload): array` - HTTP POST with retry

**Features:**
- Automatic retry (2 times, 1s delay)
- Error logging for all requests
- Business error detection (HTTP 200 with error code in body)
- Configurable timeout (default 30s)

### DngPaymentService (Business Logic)

**File:** `/app/Modules/Finance/Dng/Services/DngPaymentService.php`

**Methods:**
1. `createAndPush(Student $student, array $chargeData): DngPaymentRequest`
   - Creates local record first
   - Calls DNG push
   - Updates with transaction IDs

2. `pullQrCode(DngPaymentRequest $request, array $feeTypes): array`
   - Fetches QR/VA from DNG
   - Transitions status to `qr_ready`
   - Returns QR data

3. `bridgeToPayment(DngPaymentRequest $request): ?Payment`
   - Creates canonical Payment record
   - Auto-allocates to student charges
   - Idempotent with row lock

### DngWebhookService (Webhook Processing)

**File:** `/app/Modules/Finance/Dng/Services/DngWebhookService.php`

**Methods:**
1. `processEvent(DngWebhookEvent $event): void`
   - Finds matching DngPaymentRequest by PaymentId
   - Fallback: matches by item_id + student_code
   - Cross-validates amount & student
   - Updates with invoice fields if present
   - Transitions state
   - Bridges to canonical Payment
   - Marks event as processed

2. Private `crossValidate(...): bool`
   - Checks amount mismatch (>0.01 tolerance)
   - Checks student code mismatch
   - Returns false if issues found

### DngReconciliationService

**File:** `/app/Modules/Finance/Dng/Services/DngReconciliationService.php`

**Methods:**
1. `reconcileDay(string $campusCode, string $date): array`
   - Calls DNG checkPaidOfDay
   - Processes each transaction
   - Returns summary: backfilled, up_to_date, orphans, errors

2. Private `processTransaction(array $txn, string $campusCode, array &$summary): void`
   - Finds or backfills local records
   - Compares status ordering (pending < pushed < qr < paid_uninvoiced < paid_invoiced < reconciled)
   - Updates if local record is behind
   - Bridges to Payment if not yet done

### DngChecksumService

**File:** `/app/Modules/Finance/Dng/Services/DngChecksumService.php`

**Methods:**
1. `generate(string $value): string` - Compute checksum
2. `verify(string $value, string $expectedChecksum): bool` - Verify using constant-time comparison

---

## 8. JOB QUEUE

### ProcessDngWebhookJob

**File:** `/app/Modules/Finance/Dng/Jobs/ProcessDngWebhookJob.php`

**Characteristics:**
- Implements `ShouldBeUnique` (prevents concurrent processing of same event)
- Queue: `webhooks`
- Retries: 5 times
- Timeout: 60 seconds
- Backoff: [10s, 30s, 60s, 300s, 600s]
- Max exceptions: 3

**Behavior:**
- Dispatched immediately after webhook validation
- Skips if already processed
- Calls `DngWebhookService::processEvent()`
- Marks as failed if all retries exhausted

### ReconcileDngPaymentsJob

**File:** `/app/Modules/Finance/Dng/Jobs/ReconcileDngPaymentsJob.php`

**Scheduling:** Every 15 minutes (from `/routes/console.php` line 74-77)

```php
Schedule::job(new \App\Modules\Finance\Dng\Jobs\ReconcileDngPaymentsJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
```

**Behavior:**
- Iterates all campuses
- Calls reconciliation service for each
- Logs warnings for:
  - Stale pending requests (>120 minutes old)
  - Long-standing paid_uninvoiced (>24 hours)
- Queue: `reconciliation`
- Timeout: 300 seconds
- Retries: 1 (fails immediately on error)

---

## 9. WEBHOOK FLOW DIAGRAM

```
DNG Payment System
    │
    └─→ Student initiates payment (external to app)
            │
            └─→ DNG processes via bank/PSP
                    │
                    ├─→ Callback 1: Payment succeeded, no invoice
                    │       │
                    │       POST /api/webhooks/dng/payment
                    │       │
                    │       └─→ DngWebhookController::__invoke()
                    │           ├─ Validate fields (StudentId, PaymentId, Amount, CampusCode, CheckSum)
                    │           ├─ Verify checksum (HMAC-SHA1)
                    │           ├─ Compute payload hash for dedup
                    │           ├─ Try insert DngWebhookEvent (unique constraint dedup)
                    │           ├─ Return 200 immediately
                    │           └─ Dispatch ProcessDngWebhookJob
                    │                   │
                    │                   └─→ ProcessDngWebhookJob handles async
                    │                       │
                    │                       └─→ DngWebhookService::processEvent()
                    │                           ├─ Find DngPaymentRequest by PaymentId
                    │                           ├─ Cross-validate amount & student
                    │                           ├─ Transition to paid_uninvoiced
                    │                           ├─ Bridge to canonical Payment
                    │                           └─ Mark event as processed
                    │
                    └─→ Callback 2: Payment invoiced (same PaymentId)
                            │
                            └─→ (same flow as above)
                                └─→ Transition to paid_invoiced instead
                                └─→ Update invoice fields

Parallel: Every 15 minutes
    │
    └─→ ReconcileDngPaymentsJob
            │
            └─→ For each campus:
                    │
                    └─→ DngReconciliationService::reconcileDay()
                            │
                            └─→ Call DNG checkPaidOfDay
                                    │
                                    └─→ Process each transaction
                                        ├─ Match by PaymentId or fallback
                                        ├─ Compare status ordering
                                        ├─ Backfill if behind
                                        └─ Bridge to Payment if needed
```

---

## 10. CRITICAL IMPLEMENTATION NOTES

### Payload Hash Deduplication
- DNG may retry same webhook multiple times
- `payload_hash` (SHA-256 of sorted JSON) ensures idempotency
- Unique constraint on `payload_hash` prevents duplicate processing

### Two-Phase Callback Pattern
- DNG sends callback TWICE for same payment:
  1. First: payment confirmed, invoice fields null
  2. Second: same payment, now with invoice
- System handles via:
  - Same `PaymentId` identifies same transaction
  - Event type detected from presence of invoice fields
  - State machine allows only forward transitions
  - No double-creation of canonical Payment

### Event Processing Assurance
- Webhook returns 200 immediately (doesn't wait for job)
- Job processes asynchronously with retries
- Checksum verified synchronously before dispatch
- Invalid checksums return 401 immediately

### Reconciliation Fallback
- Primary: Match by `dng_payment_id`
- Secondary: Match by `(item_id, student_code, campus_code)`
- Orphan handling: Logs warning, increments orphan counter

### State Machine Constraints
- Only forward transitions allowed
- `failed` status can retry to `pending`
- `paid_uninvoiced` can advance to `paid_invoiced`
- `reconciled` is terminal

### Checksum Key Management
- `DNG_HASH_KEY` must be configured in environment
- Same key used for:
  - Verifying incoming webhooks
  - Generating outbound request signatures
- If key rotated, old payloads cannot be verified

---

## 11. FILES STRUCTURE SUMMARY

```
app/Modules/Finance/Dng/
├── Http/
│   ├── Controllers/
│   │   ├── DngPaymentController.php      [Outbound: Create payment, Get QR]
│   │   └── DngWebhookController.php      [Inbound: Receive & validate webhook]
│   └── Requests/
│       └── CreateDngPaymentFormRequest.php
├── Models/
│   ├── DngPaymentRequest.php             [Payment request state machine]
│   └── DngWebhookEvent.php               [Webhook event log]
├── Services/
│   ├── DngClient.php                     [HTTP client to DNG]
│   ├── DngChecksumService.php            [HMAC-SHA1 verification]
│   ├── DngPaymentService.php             [Business logic: create, QR, bridge]
│   ├── DngWebhookService.php             [Webhook processing logic]
│   └── DngReconciliationService.php      [Reconciliation logic]
└── Jobs/
    ├── ProcessDngWebhookJob.php          [Async webhook processing]
    └── ReconcileDngPaymentsJob.php       [Periodic reconciliation]

database/migrations/
├── 2026_03_26_134500_create_dng_payment_requests_table.php
└── 2026_03_26_134501_create_dng_webhook_events_table.php

routes/
├── api.php                               [Webhook route definition]
└── console.php                           [Reconciliation schedule]

config/
└── services.php                          [DNG configuration]

tests/Feature/Finance/Dng/
├── DngChecksumServiceTest.php
├── DngWebhookControllerTest.php
└── ProcessDngWebhookJobTest.php

specs/payment-dng/
├── dng-payment-flow-laravel.md           [Architecture doc]
└── push-debt-to-dng.php                  [Reference implementation]
```

---

## 12. UNRESOLVED QUESTIONS & NOTES

1. **Webhook Checksum String Confirmation**
   - Code assumes: `CampusCode + StudentId + PaymentId + Amount`
   - Not officially confirmed with DNG
   - Comment in controller acknowledges this uncertainty

2. **PaymentId Field Mapping**
   - Callback sends `PaymentId`
   - DNG response contains: `Id`, `OtherId`, `TransactionID`
   - Currently using response's `PaymentId` or `OtherId` from data
   - Spec doc notes this needs official DNG confirmation

3. **Callback Retry Behavior**
   - How many times does DNG retry failed callbacks?
   - What is retry interval?
   - Current code handles via `ShouldBeUnique` job

4. **Rate Limit Coordination**
   - Webhook throttled to 60/min
   - But DNG may send bursts on reconciliation days
   - May need tuning in production

5. **Timezone Handling**
   - Dates from DNG treated as-is
   - No explicit timezone conversion
   - May need verification with actual DNG data

6. **Invoice Date Format**
   - Sample: `"2023-03-20T03:08:02"` (ISO-like)
   - Cast to datetime in model (Laravel handles parsing)
   - Confirm with DNG if timezone included

---

## 13. PRODUCTION CHECKLIST

- [ ] Environment variables configured: `DNG_BASE_URL`, `DNG_ACCESS_CODE`, `DNG_HASH_KEY`, `DNG_API_CODE`, etc.
- [ ] Database migrations run: `dng_payment_requests` and `dng_webhook_events` tables created
- [ ] Queue driver configured and running for `webhooks` and `reconciliation` queues
- [ ] Scheduler running (`php artisan schedule:work` or cron)
- [ ] Webhook endpoint `/api/webhooks/dng/payment` accessible to DNG servers
- [ ] DNG API timeout acceptable (default 30s, configurable)
- [ ] Logging configured for DNG operations (channels, retention)
- [ ] Error handling & alerting configured (stale requests, orphans, failed jobs)
- [ ] Checksum verification enabled for all DNG operations
- [ ] Payment bridging to canonical `payments` table verified
- [ ] Auto-allocation logic tested end-to-end

