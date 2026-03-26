# Student Finance & DNG Payment Module Exploration Report

**Date:** 2026-03-26 | **Working Directory:** `/Users/hunt2412/hieupvdev/project/swinx`

---

## 1. DNG Module Structure

**Location:** `app/Modules/Finance/Dng/`

### 1.1 Models
- **DngPaymentRequest** (`Dng/Models/DngPaymentRequest.php`)
  - Tracks DNG payment requests through lifecycle
  - Status machine: `pending` → `pushed_to_dng` → `qr_ready` → `paid_uninvoiced` → `paid_invoiced` → `reconciled`
  - Can fail/retry: `failed` → `pending`
  - Key fields: `student_id`, `dng_payment_id`, `payment_id` (bridges to canonical Payment), `amount`, `fee_type`, `item_id`
  - Stores: push_payload, push_response, qr_payload, last_callback_payload
  - Relationships: `student()`, `payment()`, `webhookEvents()`
  
- **DngWebhookEvent** (`Dng/Models/DngWebhookEvent.php`)
  - Event log for DNG callbacks
  - Statuses: `pending`, `processed`, `failed`, `skipped`, `mismatch`
  - Event types: `payment_succeeded_without_invoice`, `payment_invoiced`
  - Deduplication via `payload_hash` (SHA-256)
  - Relationships: `dngPaymentRequest()`

### 1.2 Services
- **DngPaymentService** (`Dng/Services/DngPaymentService.php`)
  - `createAndPush()`: Create local request + push to DNG
  - `pullQrCode()`: Fetch QR/virtual account from DNG
  - `bridgeToPayment()`: Convert confirmed DNG payment to canonical Payment model (idempotent)
  
- **DngWebhookService** (`Dng/Services/DngWebhookService.php`)
  - `processEvent()`: Handle webhook callback
  - Cross-validates amount & student code
  - Auto-determines event type from payload fields
  - Transitions DngPaymentRequest state
  - Calls `bridgeToPayment()` when confirmed
  
- **DngClient** (`Dng/Services/DngClient.php`)
  - HTTP client for DNG API
  - Methods: `insertNewRecord()`, `createVirtualAccountByFeeType()`
  - Checksum generation via `DngChecksumService`
  - Config from `services.dng.*`
  
- **DngChecksumService** (`Dng/Services/DngChecksumService.php`)
  - Generate/verify checksums
  
- **DngReconciliationService** (`Dng/Services/DngReconciliationService.php`)
  - Reconciliation logic

### 1.3 Controllers
- **DngPaymentController** (`Dng/Http/Controllers/DngPaymentController.php`)
  - `store()`: POST `/api/v1/finance/dng/payment-requests` - Create DNG payment request
  - `qr()`: GET `/api/v1/finance/dng/payment-requests/{id}/qr` - Fetch QR code
  
- **DngWebhookController** (`Dng/Http/Controllers/DngWebhookController.php`)
  - `__invoke()`: Handle DNG callbacks
  - Validates checksum, deduplicates by payload_hash
  - Dispatches async job: `ProcessDngWebhookJob`

### 1.4 Jobs
- **ProcessDngWebhookJob** - Async queue for webhook processing
- **ReconcileDngPaymentsJob** - Periodic reconciliation

---

## 2. Payment Model (Canonical)

**Location:** `app/Models/Payment.php`

```php
// Fields
- student_id (FK)
- amount (decimal:2)
- method: enum['cash', 'bank_transfer', 'gateway', 'wallet', 'import', 'other']
- source: string (payment source identifier)
- external_ref: string (external transaction ID)
- paid_at: timestamp
- status: enum['pending', 'completed', 'refunded', 'cancelled']
- received_by_user_id (FK, user who recorded it)
- raw_payload: json (webhook/import raw data)
- notes: text
```

**Relationships:**
- `student()`: BelongsTo Student
- `receivedBy()`: BelongsTo User
- `applications()`: HasMany PaymentApplication

**Accessors:**
- `allocated_amount`: computed via SettlementService
- `unapplied_amount`: computed via SettlementService
- `is_fully_allocated`: boolean flag

**Scopes:**
- `completed()`: where status = 'completed'
- `forStudent(int $id)`: filter by student_id

---

## 3. Student Model

**Location:** `app/Models/Student.php`

**Guard:** `'student'` (defined in `config/auth.php`)

**Key Finance-Related Relationships:**
- `payments()`: HasMany Payment
- `invoices()`: HasMany StudentInvoice
- `financeCharges()`: HasMany FinanceCharge
- `voucherApplications()`: HasMany VoucherApplication
- `deferCases()`: HasMany DeferCase
- `wallet()`: HasOne StudentWallet
- `cashWallet()`: HasOne StudentCashWallet
- `scholarshipAward()`: HasOne StudentScholarshipAward

**Status Constants:**
- `BLOCKED_STATUSES`: ['inactive', 'dropout', 'dropout_transfer', 'graduated', 'pending']
- `FINANCIAL_STATUSES`: ['intake_pre_uni_gc', 'intake_course', 'intake_major']

**Key Methods:**
- `isActive()`: not in BLOCKED_STATUSES
- `canRegisterForCourses()`: isActive() && !hasActiveHolds()
- `scopeActive()`: query scope

---

## 4. Student-Facing Finance APIs

**Base Middleware:** `['web', 'auth']` (session-based)

### 4.1 Finance Balance & Charges (Protected)
**Route Prefix:** `api/v1/student/finance`

```
GET    /balance
       Query: semester_id (optional)
       Returns: { balance, semester_id }
       
GET    /charges
       Query: semester_id (optional)
       Returns: { charges[], summary{total_charges, total_credits, net_amount} }
       
GET    /charges/{chargeId}
       Returns: { charge{...} }
       
GET    /payments
       Returns: { payments[], unapplied_credit }
```

**Controller:** `StudentFinanceController`
- Requires `$request->user('student')`
- Uses `FinanceChargeService` & `PaymentService`
- Appends computed attributes: `is_charge`, `is_credit`, `paid_amount`, `balance`, `is_fully_paid`

### 4.2 DNG Payment Gateway
**Route Prefix:** `api/v1/finance/dng`

```
POST   /payment-requests
       Body: {student_id, campus_code, student_code, fee_type, item_id, amount, 
              type, student_name, email, estimate_time, student_address, cccd?, fee_types[]}
       Returns: { id, status, dng_transaction_id, dng_payment_id, qr }
       
GET    /payment-requests/{dngPaymentRequest}/qr
       Returns: { data{...} }
```

---

## 5. Student Authentication

**Config:** `config/auth.php`

```php
'guards' => [
    'student' => [
        'driver' => 'sanctum',
        'provider' => 'students',
    ],
]

'providers' => [
    'students' => [
        'driver' => 'eloquent',
        'model' => App\Models\Student::class,
    ],
]
```

**Student Login APIs** (Identity Module, `app/Modules/Identity/routes/api.php`)
```
POST   /v1/student/auth/login
       Body: {email, password}
       Returns: {access_token, student, ...}
       
POST   /v1/student/auth/login/google
       Body: {id_token}
       
POST   /v1/student/auth/logout
POST   /v1/student/auth/refresh
GET    /v1/student/context
```

---

## 6. Finance Module - Additional Models & Relationships

### 6.1 StudentInvoice
**Location:** `app/Models/StudentInvoice.php`

```php
// Fields
- invoice_number: string (unique)
- student_id (FK)
- billing_cycle_id (FK)
- semester_id (FK)
- subtotal, discount_total, total_amount, paid_amount: decimal:2
- status: enum['draft', 'pending', 'paid', 'overdue', 'cancelled']
- due_date: date
- paid_at: timestamp
```

**Relationships:**
- `student()`: BelongsTo
- `semester()`: BelongsTo
- `billingCycle()`: BelongsTo
- `invoiceLines()`: HasMany InvoiceLine
- `discounts()`: HasMany InvoiceDiscount
- `charges()`: HasManyThrough FinanceCharge

**Constants:**
- `NON_REUSABLE_FOR_CHARGE_GENERATION_STATUSES`: ['issued', 'paid', 'void', 'cancelled']

### 6.2 FinanceCharge
**Location:** `app/Models/FinanceCharge.php`

```php
// Fields
- student_id (FK)
- semester_id (FK)
- billing_cycle_id (FK, nullable)
- charge_type: enum[tuition_term, egc_level_fee, retake_fee, course_fee, manual_fee,
                    admission_fee, defer_credit, egc_exempt_credit, scholarship_credit,
                    voucher_credit, adjustment]
- amount: decimal (positive = charge, negative = credit)
- description: string
- effective_at: datetime
- status: enum['active', 'void']
- source_type, source_id: polymorphic (traceability)
- created_by_user_id (FK)
- voided_at, voided_by_user_id, void_reason
```

**Relationships:**
- `student()`: BelongsTo
- `semester()`: BelongsTo
- `billingCycle()`: BelongsTo
- `source()`: MorphTo

**Accessors:**
- `balance`: computed (charge amount - paid amount)
- `is_charge`: boolean (amount > 0)
- `is_credit`: boolean (amount < 0)
- `paid_amount`: computed
- `is_fully_paid`: boolean

### 6.3 InvoiceLine
**Location:** `app/Models/InvoiceLine.php`

```php
// Fields
- invoice_id (FK → StudentInvoice)
- charge_id (FK → FinanceCharge)
- amount_snapshot: decimal:2
- description_snapshot: string
- status: string
- voided_at, void_reason
```

**Relationships:**
- `invoice()`: BelongsTo StudentInvoice
- `charge()`: BelongsTo FinanceCharge
- `paymentApplications()`: HasMany PaymentApplication
- `discountAllocations()`: HasMany DiscountAllocation

### 6.4 PaymentApplication
**Location:** `app/Models/PaymentApplication.php`

```php
// Fields
- payment_id (FK)
- invoice_line_id (FK)
- amount: decimal:2
- entry_type: enum['application', 'reversal']
- source_ref_id, source_ref_type (nullable, traceability)
- applied_at: timestamp
- created_by (FK → User)
```

**Relationships:**
- `payment()`: BelongsTo
- `invoiceLine()`: BelongsTo
- `createdBy()`: BelongsTo User

---

## 7. Database Schema Summary

### Core Finance Tables
| Table | Key Fields | Purpose |
|-------|-----------|---------|
| `payments` | student_id, amount, method, source, external_ref, paid_at, status | Canonical payment records |
| `dng_payment_requests` | student_id, dng_payment_id, payment_id, amount, status, fee_type, item_id | DNG payment lifecycle |
| `dng_webhook_events` | dng_payment_id, event_type, payload_hash, processing_status, is_valid_checksum | DNG callback log (dedup key: payload_hash) |
| `student_invoices` | student_id, invoice_number, billing_cycle_id, semester_id, total_amount, paid_amount, status | Student invoices |
| `finance_charges` | student_id, semester_id, billing_cycle_id, charge_type, amount, status, effective_at | Individual charges/credits |
| `invoice_lines` | invoice_id, charge_id, amount_snapshot | Invoice line items (snapshot of charges) |
| `payment_applications` | payment_id, invoice_line_id, amount, entry_type, applied_at | Payment-to-charge allocations |
| `invoice_discounts` | invoice_id, amount, description | Discounts applied to invoices |

**Indexes (Notable):**
- `dng_payment_requests`: unique(dng_payment_id), idx on student_id, status, item_id
- `dng_webhook_events`: unique(payload_hash), idx on dng_payment_id, processing_status
- `payment_applications`: idx on (payment_id, invoice_line_id), (invoice_line_id, entry_type)

---

## 8. Key Services & Workflows

### 8.1 Payment Service Workflow
**Location:** `app/Modules/Finance/Services/PaymentService.php`

```php
// Record a payment
recordPayment(array $data): Payment
  ├─ Creates Payment record
  ├─ Publishes 'finance.invoice_paid' domain event
  └─ Returns Payment

// Auto-allocate payment to outstanding charges
autoAllocatePayment(int $paymentId): Collection
  ├─ Gets unapplied_amount
  ├─ Gets outstanding charges (oldest_first strategy)
  ├─ Allocates payment to charges until exhausted
  └─ Returns PaymentApplication[]

// Manual allocation
allocatePayment(int $paymentId, array $allocations): Collection
  └─ Allocates payment to specific charges via invoiceLines

// Query methods
getStudentBalance(int $studentId, ?int $semesterId)
getPaymentHistory(int $studentId)
getUnappliedCredits(int $studentId)
getOutstandingCharges(int $studentId, ?int $semesterId): Collection
```

### 8.2 DNG Payment Service Workflow
**Location:** `app/Modules/Finance/Dng/Services/DngPaymentService.php`

```php
// Create and push debt to DNG
createAndPush(Student $student, array $chargeData): DngPaymentRequest
  ├─ Create local DngPaymentRequest (status: pending)
  ├─ Push to DNG (insertNewRecord)
  ├─ Update status to pushed_to_dng
  └─ Returns updated request

// Pull QR code
pullQrCode(DngPaymentRequest $request, array $feeTypes): array
  ├─ Call DNG API (createVirtualAccountByFeeType)
  ├─ Store response in qr_payload
  └─ Transition to qr_ready (if allowed)

// Bridge DNG payment to canonical Payment
bridgeToPayment(DngPaymentRequest $request): ?Payment
  ├─ Guard: return if already bridged (idempotent)
  ├─ Lock row for update (prevent race)
  ├─ Create Payment record (method: gateway, source: dng)
  ├─ Auto-allocate payment
  └─ Returns Payment
```

### 8.3 DNG Webhook Workflow
**Location:** `app/Modules/Finance/Dng/Services/DngWebhookService.php`

```
DNG Callback → DngWebhookController
  ├─ Validate checksum
  ├─ Compute payload_hash (SHA-256)
  ├─ Create DngWebhookEvent (dedup by hash)
  └─ Dispatch ProcessDngWebhookJob

ProcessDngWebhookJob
  └─ DngWebhookService.processEvent()
      ├─ Find DngPaymentRequest (by dng_payment_id, fallback to item_id+student_code)
      ├─ Cross-validate amount & student
      ├─ Resolve event_type (has invoice? → PAID_INVOICED : PAID_UNINVOICED)
      ├─ Transition DngPaymentRequest state
      ├─ Call bridgeToPayment()
      └─ Mark event as processed
```

### 8.4 Finance Charge Service
**Location:** `app/Modules/Finance/Services/FinanceChargeService.php`

```php
// Generate charges
generateTuitionCharge(studentId, semesterId, amount)
generateEgcLevelCharge(studentId, semesterId, level, amount)
generateRetakeCharge(CourseRegistration)

// Student charge queries
getStudentCharges(int $studentId, ?int $semesterId): Collection
getTotalCharges(int $studentId, ?int $semesterId): float
getTotalCredits(int $studentId, ?int $semesterId): float
getNetAmount(int $studentId, ?int $semesterId): float
```

---

## 9. Admin Finance Routes (Web)

**Base Route:** `/finance` (requires `auth` + permission middleware)

```
Operations Dashboard
  GET /operations/dashboard
  GET /operations/generate-charges
  GET /operations/exceptions
  GET /operations/due-calendar

Settlement
  GET /settlement
  POST /settlement/apply

Invoices
  GET /invoices/
  GET /invoices/{invoice}
  POST /invoices/{invoice}/lines/{line}/void

Charges
  GET /charges/
  POST /charges/
  GET /charges/{charge}
  POST /charges/{charge}/void

Payments
  GET /payments/
  POST /payments/
  GET /payments/{student}/dng-data
  POST /payments/import
  POST /payments/import/preview
  POST /payments/import/store
  POST /payments/auto-allocate
  POST /payments/auto-allocate/preview
  GET /payments/{payment}
  POST /payments/{payment}/allocate
```

---

## 10. Key Implementation Details

### 10.1 DNG Payment Request State Machine
```
PENDING
  ├─→ PUSHED_TO_DNG (on successful push)
  │   ├─→ QR_READY (on QR pull)
  │   │   ├─→ PAID_UNINVOICED (on callback without invoice)
  │   │   └─→ PAID_INVOICED (on callback with invoice)
  │   ├─→ PAID_UNINVOICED (direct, on callback)
  │   └─→ PAID_INVOICED (direct, on callback)
  ├─→ FAILED (on push error)
  └─→ FAILED (from QR_READY, PAID_UNINVOICED, PAID_INVOICED)

PAID_UNINVOICED
  └─→ PAID_INVOICED (when invoice issued)
      └─→ RECONCILED (final state)

PAID_INVOICED
  └─→ RECONCILED (final state)

FAILED
  └─→ PENDING (retry)
```

### 10.2 Deduplication Strategy
- **DNG Webhook Events:** Uses `payload_hash` (SHA-256 of sorted payload keys)
- **Payment Bridges:** Uses lock-for-update + guard check (idempotent)
- **Callback Matching:** Primary (dng_payment_id) + fallback (item_id + student_code)

### 10.3 Cross-Validation
```
DNG Webhook Validation:
  ✓ Amount matches (tolerance: 0.01)
  ✓ Student code matches
  ✓ Checksum valid
```

### 10.4 Settlement (Payment Application)
```
Payment → InvoiceLine (via FinanceCharge snapshot)
  ├─ Creates PaymentApplication record
  ├─ Tracks amount, entry_type (application/reversal)
  ├─ Supports auto-allocation (oldest_first) & manual
  └─ Updates FinanceCharge balance & InvoiceLine paid_amount
```

---

## 11. Configuration

**DNG Service Config** (`config/services.php` or env vars):
```
SERVICES_DNG_BASE_URL
SERVICES_DNG_ACCESS_CODE
SERVICES_DNG_API_CODE
SERVICES_DNG_CLIENT_CODE
SERVICES_DNG_LOGIN
SERVICES_DNG_TIMEOUT (default: 30)
```

**Auth Config** (`config/auth.php`):
```
AUTH_GUARD (default: 'web')
AUTH_PASSWORD_BROKER (default: 'users')
Student guard: sanctum + Student model provider
```

---

## 12. Unresolved Questions & Notes

**Clarifications Needed:**
1. **Webhook Authentication:** DNG callback checksum generation - exact string order confirmed?
   - Current assumption: `CampusCode.StudentId.PaymentId.Amount`
   
2. **Student Portal Existence:** Is there a student-facing dashboard/portal beyond API endpoints?
   - Found only API routes in `/api/v1/student/finance/*`
   - No web routes found for student dashboard
   
3. **Parent Access:** Parent model exists (ParentProfile) with student access - does finance API extend to parents?
   - Parent auth exists in Identity module
   - Finance API uses `auth` middleware (session-based), not parent-specific
   
4. **Settlement vs. Payment Application:** Distinction between SettlementService and PaymentApplication?
   - SettlementService seems to handle computed properties (allocated_amount, unapplied_amount)
   - PaymentApplication is the actual database record
   
5. **Fee Type Classification:** What are valid `fee_types` for DNG requests?
   - Not constrained in CreateDngPaymentFormRequest rules
   - Appears to be free-form string
   
6. **Billing Cycle:** Purpose and usage of BillingCycle model?
   - Referenced in StudentInvoice, FinanceCharge, but generation logic not explored
   
7. **Voucher/Defer/Scholarship Credits:** How are these credits allocated?
   - Models exist (VoucherApplication, DeferCase, StudentScholarshipAward)
   - Charge generation explored briefly in FinanceChargeService
   
8. **DNG Reconciliation:** What is the reconciliation process?
   - ReconcileDngPaymentsJob exists but service logic not fully explored

---

## Summary

The **student finance and DNG payment system** is a well-structured modular architecture with:

- **DNG Gateway Integration:** Complete lifecycle from debt push → QR generation → payment callback → canonical Payment bridge
- **Payment Allocation:** Flexible manual + auto-allocation (oldest_first) to outstanding charges
- **Student-Facing APIs:** RESTful endpoints for balance, charges, payments, and DNG payment creation
- **Robust Deduplication:** Payload hashing, idempotent bridges, state machine guards
- **Audit Trail:** Raw payloads, domain events, user tracking

The system cleanly separates **DNG-specific logic** from the **canonical payment system**, allowing future gateway integrations without architectural changes.

