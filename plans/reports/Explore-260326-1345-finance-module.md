# Finance Module & Payment Integration Exploration Report

**Date:** 2026-03-26  
**Scope:** Finance module architecture, payment models, DNG integration patterns, webhook handling, existing payment flows

---

## 1. Finance Module Architecture Overview

### Directory Structure
```
app/Modules/Finance/
├── Actions/                          # Action classes for business logic
│   ├── AllocatePaymentAction.php
│   ├── AutoAllocatePaymentsAction.php
│   ├── CreateFinanceChargeAction.php
│   ├── VoidFinanceChargeAction.php
│   ├── PreviewPaymentImportAction.php
│   ├── StorePaymentImportAction.php
│   └── Operations/
│       ├── GenerateBatchChargesAction.php
│       ├── SendPaymentRemindersAction.php
│       ├── FixBillingExceptionAction.php
│       ├── PreviewChargeGenerationQuery.php
│       └── others...
├── Services/                         # Core business services
│   ├── PaymentService.php
│   ├── SettlementService.php
│   ├── FinanceChargeService.php
│   ├── InvoiceGenerationService.php
│   ├── DeferCaseService.php
│   └── DeferChargeResolver.php
├── Queries/                          # Query objects for data retrieval
│   ├── ListPaymentsQuery.php
│   ├── GetPaymentDetailsQuery.php
│   ├── GetStudentBalanceQuery.php
│   ├── GetStudentChargesQuery.php
│   └── Operations/
├── Http/
│   ├── Api/
│   │   ├── Student/StudentFinanceController.php
│   │   └── Admin/BillingOperationsController.php
│   ├── Web/Admin/
│   │   ├── PaymentController.php
│   │   ├── BillingInvoiceController.php
│   │   ├── FinanceChargeController.php
│   │   ├── BillingSettlementController.php
│   │   └── BillingOperationsController.php
│   └── Export/
├── Providers/
│   └── FinanceServiceProvider.php
├── routes/
│   ├── api.php
│   └── web.php
├── Support/                          # Helper classes
│   ├── BillingScopeHelper.php
│   ├── DeferChargePolicy.php
│   ├── StudentChargeTimingResolver.php
│   └── VoucherDiscountAmountResolver.php
└── Exports/
    ├── GenerateChargesPreviewExport.php
    └── InvoiceExport.php
```

---

## 2. Database Schema - Payment & Invoice Tables

### 2.1 Core Payment Table: `payments`
**File:** `/database/migrations/2026_01_17_100001_create_payments_table.php`

**Purpose:** Records individual payment transactions  
**Key Columns:**
- `id` (PK)
- `student_id` (FK) - which student made the payment
- `amount` (decimal 15,2)
- `method` (enum): cash, bank_transfer, gateway, wallet, import, other
- `source` (nullable) - payment source identifier
- `external_ref` (nullable) - external transaction ID (e.g., from DNG)
- `paid_at` (timestamp)
- `status` (enum): pending, completed, refunded, cancelled
- `received_by_user_id` (FK, nullable)
- `raw_payload` (json, nullable) - webhook/import raw data
- `notes` (text, nullable)
- `timestamps` (created_at, updated_at)

**Indexes:**
- `student_id`, `status`, `paid_at`, `external_ref`
- Compound: `[student_id, status]`

### 2.2 Finance Charges Table: `finance_charges`
**File:** `/database/migrations/2026_01_17_100000_create_finance_charges_table.php`

**Purpose:** Records charges against students (tuition, fees, credits)  
**Key Columns:**
- `id` (PK)
- `student_id` (FK)
- `semester_id` (FK)
- `billing_cycle_id` (FK, nullable)
- `charge_type` (enum): tuition_term, egc_level_fee, retake_fee, course_fee, manual_fee, defer_credit, egc_exempt_credit, scholarship_credit, voucher_credit, adjustment, admission_fee
- `amount` (decimal 15,2) - positive = charge, negative = credit
- `description` (string)
- `effective_at` (datetime)
- `status` (enum): active, void
- `source_type`, `source_id` (polymorphic - for traceability)
- `created_by_user_id` (FK, nullable)
- `voided_at`, `voided_by_user_id`, `void_reason`
- `timestamps`

**Indexes:**
- `student_id`, `semester_id`, `billing_cycle_id`, `charge_type`, `status`
- Compound: `[source_type, source_id]`, `[student_id, semester_id, status]`

### 2.3 Invoice Lines Table: `invoice_lines`
**File:** `/database/migrations/2026_01_17_100003_create_invoice_lines_table.php`

**Purpose:** Links charges to invoices; snapshot of charge amounts at invoice time  
**Key Columns:**
- `id` (PK)
- `invoice_id` (FK)
- `charge_id` (FK)
- `amount_snapshot` (decimal 15,2) - frozen amount from charge at invoice time
- `description_snapshot` (text)
- `status` (string): active, voided
- `voided_at`, `void_reason`
- `timestamps`

**Recent Additions (2026-03-25):**
- Settlement-related fields added for tracking payment allocations
- Fields for: settlement_started_at, settlement_completed_at, settlement_status

### 2.4 Payment Applications Table: `payment_applications`
**File:** `/database/migrations/2026_03_25_000002_create_payment_applications_table.php`

**Purpose:** Records how payments are applied to specific invoice lines  
**Key Columns:**
- `id` (PK)
- `payment_id` (FK)
- `invoice_line_id` (FK)
- `amount` (decimal 15,2) - how much of payment applied to this line
- `entry_type` (enum): application, reversal
- `source_ref_id`, `source_ref_type` (nullable) - reference to what triggered this
- `applied_at` (timestamp)
- `created_by` (FK to users, nullable)
- `timestamps`

**Indexes:**
- `payment_id`, `invoice_line_id`
- Compound: `[payment_id, invoice_line_id]`, `[invoice_line_id, entry_type]`

### 2.5 Invoice Discounts & Allocations Tables
**Files:** 
- `/database/migrations/2026_03_25_000004_add_settlement_fields_to_invoice_discounts_table.php`
- `/database/migrations/2026_03_25_000003_create_discount_allocations_table.php`

**Purpose:** Track discount sources and their allocation to specific invoice lines  
**Key Columns in `invoice_discounts`:**
- `id`, `invoice_id`, `discount_type`, `discount_source`, `description`, `amount`, `status`
- `approved_by`, `memo`, `reference_id`

**Key Columns in `discount_allocations`:**
- `id`, `invoice_discount_id`, `invoice_line_id`, `amount`
- `entry_type` (enum): allocation, release, reversal
- `source_ref_id`, `source_ref_type`, `allocation_rule`

### 2.6 Student Invoices Table: `student_invoices`
**File:** `/database/migrations/2025_10_08_145805_create_student_invoices_table.php`

**Purpose:** Main invoice record  
**Key Columns:**
- `id` (PK)
- `invoice_number` (unique)
- `student_id` (FK)
- `billing_cycle_id` (FK, nullable)
- `semester_id` (FK)
- `due_date` (datetime, nullable)
- `status` (string): issued, paid, void, cancelled, draft, pending, overdue, partial
- `subtotal`, `discount_total`, `total_amount`, `paid_amount`, `paid_at`
- `timestamps`

**Recent Additions (2026-03-25):**
- `invoice_snapshot_*` columns for storing calculated totals

---

## 3. Existing Payment Models

### 3.1 Payment Model (`app/Models/Payment.php`)
**Status:** Active - Core payment model

```php
class Payment extends Model
{
    protected $fillable = [
        'student_id',
        'amount',
        'method',           // payment method enum
        'source',           // source identifier
        'external_ref',     // external reference (e.g., DNG PaymentId)
        'paid_at',
        'status',
        'received_by_user_id',
        'raw_payload',      // stores webhook data
        'notes',
    ];
    
    // Constants
    const METHOD_CASH = 'cash';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_GATEWAY = 'gateway';
    const METHOD_WALLET = 'wallet';
    const METHOD_IMPORT = 'import';
    const METHOD_OTHER = 'other';
    
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';
    
    // Relationships
    public function student(): BelongsTo
    public function receivedBy(): BelongsTo
    public function applications(): HasMany  // PaymentApplication
    
    // Accessors
    public function getAllocatedAmountAttribute(): float
    public function getUnappliedAmountAttribute(): float
    public function getIsFullyAllocatedAttribute(): bool
}
```

**Location:** `/app/Models/Payment.php`

### 3.2 PaymentApplication Model (`app/Models/PaymentApplication.php`)
**Status:** Active - New model for settlement tracking

```php
class PaymentApplication extends Model
{
    protected $fillable = [
        'payment_id',
        'invoice_line_id',
        'amount',
        'entry_type',       // 'application' or 'reversal'
        'source_ref_id',
        'source_ref_type',
        'applied_at',
        'created_by',
    ];
    
    public function payment(): BelongsTo
    public function invoiceLine(): BelongsTo
    public function createdBy(): BelongsTo
}
```

**Location:** `/app/Models/PaymentApplication.php`

### 3.3 FinanceCharge Model (`app/Models/FinanceCharge.php`)
**Purpose:** Represents charges (positive amount) and credits (negative amount) against a student

**Charge Types:**
- `TYPE_TUITION_TERM` - semester tuition
- `TYPE_EGC_LEVEL_FEE` - English level fee
- `TYPE_RETAKE_FEE` - course retake fee
- `TYPE_COURSE_FEE` - individual course fee
- `TYPE_MANUAL_FEE` - manually added fee
- `TYPE_ADMISSION_FEE` - admission fee
- `TYPE_DEFER_CREDIT` - deferment credit
- `TYPE_EGC_EXEMPT_CREDIT` - EGC exemption credit
- `TYPE_SCHOLARSHIP_CREDIT` - scholarship discount
- `TYPE_VOUCHER_CREDIT` - voucher discount
- `TYPE_ADJUSTMENT` - manual adjustment

**Location:** `/app/Models/FinanceCharge.php`

### 3.4 InvoiceLine Model (`app/Models/InvoiceLine.php`)
**Purpose:** Links charge to invoice; captures snapshot of amount/description at invoice time

**Key Method:**
- Relationships to: invoice, charge, paymentApplications, discountAllocations
- Accessors: isCharge (amount > 0), isCredit (amount < 0)

**Location:** `/app/Models/InvoiceLine.php`

### 3.5 StudentInvoice Model (`app/Models/StudentInvoice.php`)
**Purpose:** Main invoice document

**Key Statuses:**
- issued, paid, void, cancelled, draft, pending, overdue, partial

**Key Methods:**
- `deriveInvoiceSnapshot()` - calculates current snapshot
- `recalculateTotals()` - recalculates invoice totals after changes

**Location:** `/app/Models/StudentInvoice.php`

### 3.6 DiscountAllocation & InvoiceDiscount Models
**Purpose:** Track discount sources and allocation

**Location:** 
- `/app/Models/DiscountAllocation.php`
- `/app/Models/InvoiceDiscount.php`

---

## 4. Existing Service Classes

### 4.1 PaymentService (`app/Modules/Finance/Services/PaymentService.php`)
**Purpose:** Manage payment recording and domain events

**Key Methods:**
```php
public function recordPayment(array $data): Payment
    // Creates payment record with provided data
    // Triggers domain event 'finance.invoice_paid'

public function allocatePayment(Payment $payment, FinanceCharge $charge, float $amount): array
    // Allocates payment to specific charges
    
public function getStudentBalance(int $studentId, ?int $semesterId = null): float
    // Calculates outstanding balance
```

**Location:** `/app/Modules/Finance/Services/PaymentService.php` (8.7 KB)

### 4.2 SettlementService (`app/Modules/Finance/Services/SettlementService.php`)
**Purpose:** Core settlement logic - applying payments to invoices, calculating balances

**Key Methods:**
```php
public function deriveInvoiceSnapshot(StudentInvoice $invoice): array
    // Returns: gross, discount, net, paid, remaining, status

public function getPaymentAllocatedAmount(Payment $payment): float
    // Sum of PaymentApplication amounts

public function getPaymentUnappliedAmount(Payment $payment): float
    // Remaining unallocated amount

public function getChargePaidAmount(int $chargeId): float
    // Total paid against a charge

public function getLineDiscountAmount(InvoiceLine $line): float
public function getLineOutstandingAmount(InvoiceLine $line): float
public function createPaymentApplication(
    Payment $payment,
    InvoiceLine $line,
    float $amount,
    string $entryType,
    int $userId,
    string $sourceClass,
    ?int $sourceRefId
): PaymentApplication
```

**Location:** `/app/Modules/Finance/Services/SettlementService.php` (14.5 KB)

### 4.3 FinanceChargeService (`app/Modules/Finance/Services/FinanceChargeService.php`)
**Purpose:** Create and manage charges

**Key Methods:**
```php
public function createCharge(array $data): FinanceCharge
public function generateTuitionCharge(int $studentId, int $semesterId, float $amount, ...): FinanceCharge
public function generateEgcLevelCharge(int $studentId, int $semesterId, int $level, float $amount): FinanceCharge
public function generateRetakeCharge(CourseRegistration $registration): ?FinanceCharge
public function generateDeferCredit(DeferCase $deferCase): ?FinanceCharge
public function generateScholarshipCredit(int $studentId, int $semesterId, float $amount, ...): FinanceCharge
public function getStudentCharges(int $studentId, ?int $semesterId = null): Collection
public function getTotalCharges(int $studentId, ?int $semesterId = null): float
public function getTotalCredits(int $studentId, ?int $semesterId = null): float
public function getNetAmount(int $studentId, ?int $semesterId = null): float
public function hasActiveCharges(int $studentId, int $semesterId): bool
```

**Location:** `/app/Modules/Finance/Services/FinanceChargeService.php` (7.3 KB)

### 4.4 InvoiceGenerationService
**Purpose:** Generate invoices from charges

**Location:** `/app/Modules/Finance/Services/InvoiceGenerationService.php` (6.9 KB)

### 4.5 DeferCaseService & DeferChargeResolver
**Purpose:** Handle deferment cases and related charges

**Location:** 
- `/app/Modules/Finance/Services/DeferCaseService.php` (8.7 KB)
- `/app/Modules/Finance/Services/DeferChargeResolver.php` (2.3 KB)

---

## 5. Action Classes

### 5.1 AllocatePaymentAction
**Purpose:** Allocate single payment to single charge with validation

```php
public function run(Payment $payment, FinanceCharge $charge, float $amount, int $userId): PaymentApplication
    // Validates amount, charge existence, student match
    // Creates PaymentApplication record
```

**Location:** `/app/Modules/Finance/Actions/AllocatePaymentAction.php` (63 lines)

### 5.2 AutoAllocatePaymentsAction
**Purpose:** Auto-allocate all unallocated payments to charges based on priority

```php
public const DEFAULT_PRIORITY_ORDER = [
    FinanceCharge::TYPE_TUITION_TERM,
    FinanceCharge::TYPE_EGC_LEVEL_FEE,
    FinanceCharge::TYPE_RETAKE_FEE,
    FinanceCharge::TYPE_MANUAL_FEE,
];

public function run(array $priorityOrder, ?int $userId): array
    // Returns: students_processed, allocations_created, total_allocated_amount, invoices_updated

public function runForStudents(array $studentIds, array $priorityOrder, ?int $userId): array
```

**Location:** `/app/Modules/Finance/Actions/AutoAllocatePaymentsAction.php` (190 lines)

### 5.3 CreateFinanceChargeAction
**Purpose:** Create new finance charge with validation

**Location:** `/app/Modules/Finance/Actions/CreateFinanceChargeAction.php` (224 lines)

### 5.4 VoidFinanceChargeAction
**Purpose:** Void an existing charge

**Location:** `/app/Modules/Finance/Actions/VoidFinanceChargeAction.php` (150 lines)

### 5.5 Payment Import Actions
**Purpose:** Preview and store imported payments (bulk payment entry)

**Locations:**
- `/app/Modules/Finance/Actions/PreviewPaymentImportAction.php` (126 lines)
- `/app/Modules/Finance/Actions/StorePaymentImportAction.php` (43 lines)

### 5.6 Operations Actions
**Purpose:** Billing operations like batch charge generation, settlement, reminders

**Locations:**
- `/app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php` (432 lines)
- `/app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php` (17 lines)
- `/app/Modules/Finance/Actions/Operations/FixBillingExceptionAction.php` (17 lines)

---

## 6. DNG Payment Integration - Specs & Examples

### 6.1 DNG Documentation
**File:** `/specs/payment-dng/dng-payment-flow-laravel.md` (681 lines)

**This is a comprehensive specification document covering:**

#### DNG API Endpoints:
1. **InsertNewRecord** - `https://googleauthensite02.fpt.edu.vn:90/api/apiv2/InsertNewRecord`
   - Push debt (charge) to DNG system
   - Used to create payment order on DNG side

2. **createvirtualaccountbyfeetype** - `https://googleauthensite02.fpt.edu.vn:90/api/dng/createvirtualaccountbyfeetype`
   - Get QR code / virtual account for payment
   - Used for student payment display

3. **checkpaidofday** - `https://googleauthensite02.fpt.edu.vn:90/api/checkpaid/checkpaidofday`
   - Reconciliation API to check payments from DNG side
   - Should be called periodically (scheduler job)

#### Checksum Mechanism:
- HMAC-SHA1 + Base64 encoding
- Replace `=` with `%3d` and space with `+`
- Secret key: `2CabGHY9XaBCyeTOXU48tlajCC5NrLE32G7pWoW3Jrtsw7FFGX7hMqFQC1IdMlRmFJL2hE2J`

#### DNG Callback Payload Example:
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

#### Proposed Payment Order States:
- `pending` - local payment created
- `pushed_to_dng` - sent to DNG
- `qr_ready` - QR/VA obtained
- `paid_uninvoiced` - callback received, but no invoice yet
- `paid_invoiced` - callback with invoice info received
- `reconciled` - verified via reconciliation job
- `failed` - error state

#### Proposed Table Schema (from spec):

**payment_orders:**
- id, student_id, student_name, payment_id (unique), item_id, fee_type, amount, campus_code
- psp_code, status, invoice_serial_number, invoice_date, paid_at
- dng_created_payload (json), dng_created_response (json), last_callback_payload (json)
- created_at, updated_at

**payment_webhook_events:**
- id, provider (default 'dng'), payment_id, event_type
- payload_hash (unique), headers (json), payload (json), is_valid_checksum
- processed_at, processing_status, error_message
- created_at, updated_at

---

### 6.2 DNG Example Code Files

**File:** `/specs/payment-dng/push-debt-to-dng.php` (172 lines)

**PushDebtToDngClient class with three static methods:**

```php
class PushDebtToDngClient
{
    // Endpoints
    const PUSH_DEBT_ENDPOINT = 'https://...InsertNewRecord';
    const PULL_QR_ENDPOINT = 'https://...createvirtualaccountbyfeetype';
    const CHECK_PAID_OF_DAY_ENDPOINT = 'https://...checkpaidofday';
    
    public static function push(array $data): array
        // Params: access_code, student_id, campus_code, type, amount, item_id, 
        //         student_name, email, estimate_time, student_address, cccd, api_code, login
        // Returns: [status, response, payload]

    public static function pullQr(array $data): array
        // Params: access_code, student_code, campus_code, fee_types, api_code
        // Returns: [status, response, payload]

    public static function checkPaidOfDay(array $data): array
        // Params: access_code, campus_code, date, client_code
        // Returns: [status, response, payload]

    private static function postJson(string $endpoint, array $payload): array
        // Uses cURL to post JSON
}
```

**Key Constants:**
- DEFAULT_API_CODE = 'HC_SWB'
- DEFAULT_CLIENT_CODE = 'HC_ASIA'
- DEFAULT_LOGIN = 'HC_SWB'

**GetCheckSum.php:**
- Provides `GetCheckSum::getCheckSum(string $value): string` method
- Uses HMAC-SHA1 with hardcoded hash key

**Files:**
- `/specs/payment-dng/example.php` - example of `push()`
- `/specs/payment-dng/example-pull-qr.php` - example of `pullQr()`
- `/specs/payment-dng/example-check-paid.php` - example of `checkPaidOfDay()`

---

## 7. Existing HTTP API Routes

### 7.1 Student-Facing Routes
**File:** `/app/Modules/Finance/routes/api.php`

```php
Route::prefix('api/v1/student/finance')
    ->middleware(['web', 'auth'])
    ->name('api.student.finance.')
    ->group(function () {
        Route::get('/balance', [StudentFinanceController::class, 'balance'])->name('balance');
        Route::get('/charges', [StudentFinanceController::class, 'charges'])->name('charges');
        Route::get('/charges/{chargeId}', [StudentFinanceController::class, 'chargeDetail'])->name('charges.show');
        Route::get('/payments', [StudentFinanceController::class, 'payments'])->name('payments');
    });
```

**StudentFinanceController** methods:
- `balance(Request $request): JsonResponse` - returns student balance
- `charges(Request $request): JsonResponse` - returns student charges with summary
- `chargeDetail(int $chargeId): JsonResponse` - single charge detail
- `payments(Request $request): JsonResponse` - payment history

**Location:** `/app/Modules/Finance/Http/Api/Student/StudentFinanceController.php` (3.8 KB)

### 7.2 Admin/Operations Routes
**File:** `/app/Modules/Finance/routes/api.php`

```php
Route::prefix('api/v1/finance/operations')
    ->middleware(['web', 'auth'])
    ->name('api.finance.operations.')
    ->group(function () {
        Route::post('/preview-charges', [BillingOperationsController::class, 'previewCharges'])->name('preview-charges');
        Route::post('/export-preview-charges', [BillingOperationsController::class, 'exportPreviewCharges'])->name('export-preview-charges');
        Route::post('/run-generate', [BillingOperationsController::class, 'runGenerate'])->name('run-generate');
        Route::post('/exceptions/{exceptionId}/fix', [BillingOperationsController::class, 'fixException'])->name('fix-exception');
        Route::post('/send-reminders', [BillingOperationsController::class, 'sendReminders'])->name('send-reminders');
    });
```

### 7.3 Web Admin Routes (Implied from Controllers)
**Controller Files:**
- `/app/Modules/Finance/Http/Web/Admin/PaymentController.php` - Manage payments
- `/app/Modules/Finance/Http/Web/Admin/BillingInvoiceController.php` - Manage invoices
- `/app/Modules/Finance/Http/Web/Admin/FinanceChargeController.php` - Manage charges
- `/app/Modules/Finance/Http/Web/Admin/BillingSettlementController.php` - Settlement operations
- `/app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php` - Batch operations

---

## 8. HTTP Client Patterns in Project

### 8.1 Canvas HTTP Client Pattern
**File:** `/app/Services/Canvas/CanvasHttpClient.php` (100+ lines)

**Pattern Example:**
```php
class CanvasHttpClient
{
    private Client $client;  // Guzzle HTTP client
    private CanvasIntegration $integration;
    
    public function __construct(CanvasIntegration $integration, ?CanvasTokenService $tokenService)
    {
        $this->client = new Client([
            'base_uri' => rtrim($integration->canvas_url, '/') . '/',
            'timeout' => config('services.canvas.timeout', 120),
            'connect_timeout' => 15,
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
    }
    
    public function get(string $endpoint, array $params = []): array
    public function post(string $endpoint, array $data = []): array
    public function put(string $endpoint, array $data = []): array
    public function delete(string $endpoint): array
    
    private function request(string $method, string $endpoint, array $options = []): array
        // Handles retries, token refresh on 401, logging
}
```

**Key Features:**
- Guzzle-based HTTP client
- Token refresh on 401
- Retry on timeout
- Connection validation before requests
- Structured error handling with custom exceptions
- Uses `App\Exceptions\CanvasConnectionException`

**This is a good pattern to follow for DNG client implementation.**

---

## 9. Notification/Event Patterns

### 9.1 Domain Event System
**File:** `/app/Modules/Notification/Actions/PublishDomainEventAction.php`

**Pattern:**
- Domain events published when business actions occur
- Example: `finance.invoice_paid` event published after payment recording
- Events are enqueued in `notification_event_outbox` table
- Later processed by queue worker

**Used in PaymentService:**
```php
private function publishInvoicePaidDomainEvent(Payment $payment): void
{
    if (! (bool) config('notification.v2_enabled', false)) {
        return;
    }
    
    $envelope = new DomainEventEnvelope(
        eventId: (string) Str::uuid(),
        eventName: 'finance.invoice_paid',
        eventVersion: 1,
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'payment',
        aggregateId: (string) $payment->id,
        campusId: (int) $student->campus_id,
        actorUserId: $this->currentUserId(),
        payload: [
            'type_key' => 'invoice_paid',
            'student_id' => (int) $student->id,
            'channels' => ['email', 'realtime'],
            'recipient_targets' => [['type' => 'student', 'id' => (int) $student->id]],
            'data' => [
                'title' => 'Payment received',
                'body' => 'Your payment has been recorded successfully.',
                'payment_id' => (int) $payment->id,
                'amount' => (float) $payment->amount,
                'paid_at' => $payment->paid_at?->toDateTimeString(),
                'method' => (string) $payment->method,
            ],
        ],
    );
    
    $this->publishDomainEventAction->runAfterCommit($envelope);
}
```

### 9.2 Outbox Pattern
**Table:** `notification_event_outbox`
**Migration:** `/database/migrations/2026_03_03_120000_create_notification_event_outbox_table.php`

**Pattern Used:**
- All domain events written to outbox table
- Queue worker polls outbox
- After successful processing, event marked as processed
- Guarantees delivery even if app crashes

---

## 10. Query Objects Pattern

### 10.1 Query Pattern
**Examples:**
- `GetStudentBalanceQuery` - calculates student balance
- `GetPaymentDetailsQuery` - fetches payment details
- `ListPaymentsQuery` - lists payments with filters
- `GetStudentChargesQuery` - lists charges for student
- Various operations queries (preview, list settlement, etc.)

**Pattern:** Query objects encapsulate read operations, separating them from write operations (Actions).

---

## 11. Current Payment Flow - What's Implemented

### 11.1 Payment Recording Flow
1. User makes payment (cash, bank, import, etc.)
2. `PaymentService::recordPayment()` creates Payment record
3. Status set to `COMPLETED`
4. Domain event `finance.invoice_paid` published
5. No automatic allocation - manual or via auto-allocate action

### 11.2 Payment Allocation Flow
1. Manual: `AllocatePaymentAction::run()` allocates single payment to single charge
2. Auto: `AutoAllocatePaymentsAction::run()` allocates all unallocated payments
3. Creates `PaymentApplication` record
4. Updates invoice status based on payment totals

### 11.3 Charge Generation Flow
1. `FinanceChargeService::createCharge()` creates charge
2. Charges attached to invoices via `InvoiceLine`
3. Invoice created with snapshot amounts from lines
4. Settlement service calculates paid/outstanding amounts

### 11.4 Settlement/Reconciliation
1. `SettlementService::deriveInvoiceSnapshot()` - derives current state
2. Calculates: gross, discount, net, paid, remaining, status
3. `PaymentApplication` tracks which payments applied to which lines
4. `DiscountAllocation` tracks how discounts applied to lines

---

## 12. What's NOT Yet Implemented

### 12.1 Missing - DNG Payment Order Integration
- No `PaymentOrder` model/table yet (only in spec)
- No `PaymentWebhookEvent` model/table
- No DNG HTTP client implemented
- No webhook receiver endpoint for DNG callbacks
- No checksum verification service
- No reconciliation job for `checkpaidofday`
- No state machine for payment lifecycle (pending -> pushed_to_dng -> paid_uninvoiced -> paid_invoiced)

### 12.2 Missing - Webhook Handling
- No webhook controller for DNG callbacks
- No webhook event logging table
- No deduplication mechanism (payload_hash)
- No validation for incoming webhooks
- No queue job for processing webhooks asynchronously

### 12.3 Missing - Configuration
- No `config/dng.php` or similar for DNG credentials
- No `.env` variables for DNG API access code, hash key
- DNG example files use hardcoded values

### 12.4 Missing - Error Handling
- No exception classes for DNG-specific errors
- No logging for DNG API calls
- No monitoring/alerting for failed payments

---

## 13. Key Insights & Recommendations

### 13.1 Architecture Insights
1. **Service Layer is Strong** - PaymentService, SettlementService, FinanceChargeService are well-structured
2. **Action Pattern Used** - Business logic encapsulated in Action classes
3. **Query Pattern Used** - Read operations separated from writes
4. **Domain Events** - Payment events already published to notification system
5. **Settlement Foundation** - InvoiceLine, PaymentApplication tables designed for flexible settlement

### 13.2 DNG Integration Will Require:
1. New `PaymentOrder` model (business-level payment tracking)
2. New `PaymentWebhookEvent` model (audit trail for callbacks)
3. `DngClient` service (HTTP API calls)
4. `DngChecksumService` (checksum generation/verification)
5. `DngPaymentService` (orchestrates order creation)
6. `DngWebhookService` (processes callbacks)
7. `DngReconciliationService` (reconciliation job)
8. Webhook receiver controller
9. State machine logic for payment lifecycle
10. Configuration for DNG credentials

### 13.3 Implementation Path
1. **Phase 1:** Create PaymentOrder model and table
2. **Phase 2:** Create PaymentWebhookEvent model and table
3. **Phase 3:** Create DNG HTTP client and checksum service
4. **Phase 4:** Create webhook receiver and processing jobs
5. **Phase 5:** Integrate with existing payment flow
6. **Phase 6:** Create reconciliation job
7. **Phase 7:** Testing and monitoring

### 13.4 Risk Areas to Address
1. **Idempotency** - DNG may callback multiple times with same data
2. **Timing Issues** - Callback may arrive before local payment commit
3. **State Transitions** - Must prevent invalid state changes
4. **Reconciliation** - Must have fallback for missed webhooks
5. **Checksum Verification** - Must validate all incoming data
6. **External Reference Mapping** - PaymentId mapping must be consistent and unique

---

## 14. File Inventory

### Models (7 files)
- `/app/Models/Payment.php`
- `/app/Models/PaymentApplication.php`
- `/app/Models/FinanceCharge.php`
- `/app/Models/InvoiceLine.php`
- `/app/Models/StudentInvoice.php`
- `/app/Models/InvoiceDiscount.php`
- `/app/Models/DiscountAllocation.php`

### Services (6 files in Finance module)
- `/app/Modules/Finance/Services/PaymentService.php` (8.7 KB)
- `/app/Modules/Finance/Services/SettlementService.php` (14.5 KB)
- `/app/Modules/Finance/Services/FinanceChargeService.php` (7.3 KB)
- `/app/Modules/Finance/Services/InvoiceGenerationService.php` (6.9 KB)
- `/app/Modules/Finance/Services/DeferCaseService.php` (8.7 KB)
- `/app/Modules/Finance/Services/DeferChargeResolver.php` (2.3 KB)

### Action Classes (7 files)
- `/app/Modules/Finance/Actions/AllocatePaymentAction.php`
- `/app/Modules/Finance/Actions/AutoAllocatePaymentsAction.php`
- `/app/Modules/Finance/Actions/CreateFinanceChargeAction.php`
- `/app/Modules/Finance/Actions/VoidFinanceChargeAction.php`
- `/app/Modules/Finance/Actions/PreviewPaymentImportAction.php`
- `/app/Modules/Finance/Actions/StorePaymentImportAction.php`
- `/app/Modules/Finance/Actions/Operations/` (3 files)

### Controllers (6 files)
- `/app/Modules/Finance/Http/Api/Student/StudentFinanceController.php`
- `/app/Modules/Finance/Http/Api/Admin/BillingOperationsController.php`
- `/app/Modules/Finance/Http/Web/Admin/PaymentController.php`
- `/app/Modules/Finance/Http/Web/Admin/BillingInvoiceController.php`
- `/app/Modules/Finance/Http/Web/Admin/FinanceChargeController.php`
- `/app/Modules/Finance/Http/Web/Admin/BillingSettlementController.php`

### Queries (10+ files)
- `/app/Modules/Finance/Queries/ListPaymentsQuery.php`
- `/app/Modules/Finance/Queries/GetPaymentDetailsQuery.php`
- `/app/Modules/Finance/Queries/GetStudentBalanceQuery.php`
- `/app/Modules/Finance/Queries/GetStudentChargesQuery.php`
- And operations queries...

### Migrations (Finance-related, 11 files)
- `2026_01_17_100000_create_finance_charges_table.php`
- `2026_01_17_100001_create_payments_table.php`
- `2026_01_17_100002_create_payment_allocations_table.php`
- `2026_01_17_100003_create_invoice_lines_table.php`
- `2025_10_08_145805_create_student_invoices_table.php`
- `2025_10_08_145812_create_invoice_items_table.php`
- `2025_10_08_145820_create_invoice_discounts_table.php`
- `2026_03_25_000002_create_payment_applications_table.php`
- `2026_03_25_000003_create_discount_allocations_table.php`
- `2026_03_25_000001_add_settlement_fields_to_invoice_lines_table.php`
- `2026_03_25_000004_add_settlement_fields_to_invoice_discounts_table.php`

### DNG Integration Specs (6 files)
- `/specs/payment-dng/dng-payment-flow-laravel.md` (681 lines) ⭐ **CRITICAL**
- `/specs/payment-dng/push-debt-to-dng.php` (172 lines) ⭐ **REFERENCE**
- `/specs/payment-dng/GetCheckSum.php` (reference)
- `/specs/payment-dng/example.php`
- `/specs/payment-dng/example-pull-qr.php`
- `/specs/payment-dng/example-check-paid.php`

---

## 15. Summary

### Strengths of Current Architecture
✅ Well-structured service layer  
✅ Clear separation of concerns (Models, Services, Actions, Queries)  
✅ Domain event system in place  
✅ Settlement foundation (PaymentApplication, DiscountAllocation)  
✅ Payment allocation logic implemented  
✅ Invoice generation logic implemented  
✅ HTTP client patterns available (Canvas example)

### What Needs to Be Built
❌ PaymentOrder model (DNG business-level tracking)  
❌ PaymentWebhookEvent model (webhook audit trail)  
❌ DNG HTTP client  
❌ DNG checksum service  
❌ Webhook receiver controller  
❌ Webhook processing job  
❌ DNG reconciliation job  
❌ Payment state machine  
❌ Configuration management  
❌ Error handling and logging

### Critical Documentation
📄 `/specs/payment-dng/dng-payment-flow-laravel.md` - Complete specification document with:
- API endpoints and request/response formats
- Checksum calculation rules
- Proposed data schema
- Full flow diagrams
- Idempotency rules
- Error handling scenarios
- Testing recommendations

This is an excellent foundation document that should guide the DNG integration implementation.

---

**End of Report**
