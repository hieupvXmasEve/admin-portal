# Scout Report: Create Payment Page Implementation Patterns

**Date:** 2026-03-26  
**Project:** swinx (Laravel + Vue + Inertia)

---

## 1. PaymentController.php
**Path:** `app/Modules/Finance/Http/Web/Admin/PaymentController.php`

### Existing Methods
- `index()` - Lists payments with filters (search, source, status, date_range, per_page, sort, direction)
- `show(id)` - Returns payment details with GetPaymentDetailsQuery
- `import()` - Renders Import page
- `previewImport()` - POST validation & preview for file import
- `storeImport()` - Batch store from import with validation
- `allocate(id)` - Single payment allocation to charge
- `previewAutoAllocate()` - Preview auto-allocation
- `autoAllocate()` - Execute auto-allocation with priority order

### Pattern for Create Endpoint
No dedicated `create()` method exists. For "Create Payment", would need:
```php
public function create() {
    return Inertia::render('Finance/Payments/Create', [
        // props here
    ]);
}

public function store(Request $request, PaymentService $service) {
    $payment = $service->recordPayment($request->validated());
    return redirect()->route('finance.payments.show', $payment->id)
        ->with('success', 'Payment recorded successfully');
}
```

### Import Pattern
```php
use Inertia\Inertia;
return Inertia::render('Finance/Payments/Create', [
    'payment' => $query->handle($id),
]);
```

---

## 2. PaymentService.php
**Path:** `app/Modules/Finance/Services/PaymentService.php`

### Key Method Signatures

**recordPayment(array $data): Payment**
```php
$payment = Payment::create([
    'student_id' => $data['student_id'],
    'amount' => $data['amount'],
    'method' => $data['method'] ?? Payment::METHOD_OTHER,
    'source' => $data['source'] ?? null,
    'external_ref' => $data['external_ref'] ?? null,
    'paid_at' => $data['paid_at'] ?? now(),
    'status' => $data['status'] ?? Payment::STATUS_COMPLETED,
    'received_by_user_id' => $data['received_by_user_id'] ?? currentUserId(),
    'raw_payload' => $data['raw_payload'] ?? null,
    'notes' => $data['notes'] ?? null,
]);
```
- Publishes domain event for invoice_paid notification
- Returns created Payment model

**allocatePayment(int $paymentId, array $allocations, ?int $userId = null): Collection**
- `$allocations` format: `['charge_id' => amount, ...]`
- Creates PaymentApplication records via SettlementService
- Returns Collection<PaymentApplication>
- Wraps in DB transaction

**autoAllocatePayment(int $paymentId, string $strategy = 'oldest_first'): Collection**
- Allocates unapplied_amount using oldest-first strategy
- Returns created allocations
- Requires unapplied_amount > 0

**getOutstandingCharges(int $studentId, ?int $semesterId = null): Collection**
- Returns charges where balance > 0
- Filters: status = ACTIVE, amount > 0
- Optional semester filter

**getStudentBalance(int $studentId, ?int $semesterId = null): array**
- Delegates to GetStudentBalanceQuery
- Returns balance summary array

### Constructor Dependencies
```php
public function __construct(
    protected GetStudentBalanceQuery $getStudentBalanceQuery,
    protected PublishDomainEventAction $publishDomainEventAction,
    protected SettlementService $settlementService,
) {}
```

---

## 3. Routes (web.php)
**Path:** `app/Modules/Finance/routes/web.php`

### Payment Routes Structure
```
/finance/payments/
├─ GET  /                          → index()    [view_finance_payments]
├─ GET  /import                    → import()   [import_finance_payments]
├─ POST /import/preview            → previewImport()
├─ POST /import/store              → storeImport()
├─ POST /auto-allocate/preview     → previewAutoAllocate()
├─ POST /auto-allocate             → autoAllocate()
├─ GET  /{payment}                 → show()     [view_finance_payment_details]
└─ POST /{payment}/allocate        → allocate() [allocate_finance_payment]
```

### Expected Routes for Create
```
GET  /finance/payments/create      → create()   [create_finance_payments]
POST /finance/payments             → store()    [create_finance_payments]
```
**Permission likely:** `create_finance_payments` (not yet defined in permission.php)

---

## 4. Payment Model
**Path:** `app/Models/Payment.php`

### Constants
```php
// Methods
const METHOD_CASH = 'cash';
const METHOD_BANK_TRANSFER = 'bank_transfer';
const METHOD_GATEWAY = 'gateway';
const METHOD_WALLET = 'wallet';
const METHOD_IMPORT = 'import';
const METHOD_OTHER = 'other';

// Statuses
const STATUS_PENDING = 'pending';
const STATUS_COMPLETED = 'completed';
const STATUS_REFUNDED = 'refunded';
const STATUS_CANCELLED = 'cancelled';

// Array
const PAYMENT_METHODS = [
    self::METHOD_CASH,
    self::METHOD_BANK_TRANSFER,
    self::METHOD_GATEWAY,
    self::METHOD_WALLET,
    self::METHOD_IMPORT,
    self::METHOD_OTHER,
];
```

### Fillable Fields
```php
protected $fillable = [
    'student_id',
    'amount',
    'method',
    'source',
    'external_ref',
    'paid_at',
    'status',
    'received_by_user_id',
    'raw_payload',
    'notes',
];
```

### Casts
```php
protected $casts = [
    'amount' => 'decimal:2',
    'paid_at' => 'datetime',
    'raw_payload' => 'array',
];
```

### Appended Attributes
- `allocated_amount` - via SettlementService
- `unapplied_amount` - via SettlementService
- `is_fully_allocated` - boolean

### Relationships
```php
public function student(): BelongsTo
public function receivedBy(): BelongsTo  // User
public function applications(): HasMany   // PaymentApplication
```

### Scopes
- `completed()` - WHERE status = STATUS_COMPLETED
- `forStudent(int $studentId)` - WHERE student_id = $studentId

---

## 5. Charges/Create.vue - Reference Pattern
**Path:** `resources/js/pages/Finance/Charges/Create.vue`

### Layout Structure
```
Header (Back button + Title + Subtitle)
├─ Main Content Grid (lg:grid-cols-3, 2 cols main + 1 col sidebar)
│  ├─ Card: Student Selection (StudentCombobox)
│  ├─ Card: Charge Details (Form fields)
│  └─ Sidebar: Actions Card + Guidance Card
└─ Form with submit handler
```

### Script Setup Imports
```ts
import { useStudentSearch } from '@/composables';
import { Button, Card, Input, Label, Select, Textarea } from '@/components/ui/';
import { useForm } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
```

### useForm Pattern
```ts
const form = useForm({
    student_id: null,
    charge_type: '',
    description: '',
    amount: null,
    semester_id: null,
    due_date: '',
    invoice_id: null,
});

const handleSubmit = () => {
    form.post(route('finance.charges.store'), {
        preserveScroll: true,
        onSuccess: () => toast.success('Success message'),
        onError: () => toast.error('Error message'),
    });
};
```

### Props Pattern
```ts
interface Props {
    chargeTypes: { value: string; label: string }[];
    semesters: Semester[];
    student?: StudentBasic;
}
```

### Error Display
```ts
<p v-if="form.errors.field_name" class="text-sm text-red-500">
    {{ form.errors.field_name }}
</p>
```

### Sidebar Action Card
```ts
<Card>
    <CardHeader>
        <CardTitle>Action Title</CardTitle>
    </CardHeader>
    <CardContent class="space-y-3">
        <Button type="submit" class="w-full" :disabled="form.processing || conditions">
            Submit
        </Button>
        <Link :href="route('finance.payments.index')">
            <Button type="button" variant="outline" class="w-full">Cancel</Button>
        </Link>
    </CardContent>
</Card>
```

---

## 6. StudentCombobox.vue - Component Interface
**Path:** `resources/js/components/StudentCombobox.vue`

### Props
```ts
interface Props {
    modelValue?: string | number | null;
    placeholder?: string;
    disabled?: boolean;
    errorMessage?: string;
    required?: boolean;
    status?: 'active' | 'inactive' | 'suspended';
    class?: string;
    inDialog?: boolean;
}
```

### Emits
```ts
emit('update:modelValue', student.id);  // v-model sync
emit('select', student);                 // Student object on selection
emit('select', null);                    // Null on clear
```

### API Integration
```ts
const api = useApi();
const response = await api.get<StudentSearchData>('/api/students/search', {
    page: '1',
    limit: '20',
    query: query.trim(),
});

// Response structure
{
    success: boolean;
    data: {
        items: Student[];
        pagination: { current_page, per_page, total, last_page, has_more_pages };
    };
}
```

### Key Behaviors
- Requires 2+ characters to search (debounced 300ms)
- Returns Student[] with: id, full_name, student_id, email, status, program
- Handles loading, error, empty states
- Max 20 results
- Clear button resets form

### Usage Example
```vue
<StudentCombobox 
    v-model="form.student_id" 
    @select="selectedStudent = $event"
    :error-message="form.errors.student_id"
/>
```

---

## 7. useApi Composable
**Path:** `resources/js/composables/useApiRequest.ts`

### Methods
```ts
const api = useApi();

// POST
await api.post<T>(url: string, data: Record<string, any>)

// PUT
await api.put<T>(url: string, data: Record<string, any>)

// PATCH
await api.patch<T>(url: string, data: Record<string, any>)

// DELETE
await api.delete<T>(url: string, data?: Record<string, any>)

// GET with query params
await api.get<T>(url: string, params?: Record<string, any>, options?: { signal })
```

### Response Type
```ts
export interface ApiResponse<T = any> {
    success: boolean;
    message: string;
    data: T;
    error?: string;
    errors?: string[];
}

// Usage
const response = await api.get<StudentSearchData>('/api/students/search', params);
const responseData = response.data.value;
if (responseData?.success) {
    // handle responseData.data
}
```

### Auto-Features
- CSRF token injection from `<meta name="csrf-token">`
- Content-Type: application/json
- Accept: application/json
- X-Requested-With: XMLHttpRequest
- Credentials: include (for session auth)
- Error logging on failure

---

## 8. Payments/Index.vue - Header Location
**Path:** `resources/js/pages/Finance/Payments/Index.vue`

### Header Section (Lines 111-124)
```vue
<div class="flex items-center justify-between">
    <h2 class="text-xl leading-tight font-semibold text-gray-800">Payments</h2>
    <div class="flex gap-2">
        <Link :href="route('finance.operations.settlement.index')">
            <Button variant="outline">
                <Sparkles class="mr-2 h-4 w-4" />
                Settlement Worklist
            </Button>
        </Link>
        <Link :href="route('finance.payments.import')">
            <Button> Import Payments </Button>
        </Link>
    </div>
</div>
```

### Add "Create" Button Here
```vue
<Link :href="route('finance.payments.create')">
    <Button> Create Payment </Button>
</Link>
```

### Stats Cards Pattern (Lines 127-154)
- Total Paid (total_paid, payment_count)
- Applied (total_applied)
- Unapplied (total_unapplied)

### Filters Pattern
- Search input
- Source select (all, import, manual, gateway, bank_transfer)
- Status select (all, completed, pending, cancelled)

---

## 9. Permissions (config/permission.php)
**Path:** `config/permission.php`

### Finance Permissions Block (Lines 322-339)
```php
'finances' => [
    'view_finance_operations_dashboard',
    'view_finance_operations_generate_charges',
    'view_finance_operations_exceptions',
    'view_finance_operations_due_calendar',
    // Invoices
    'view_finance_invoices',
    'view_finance_export_invoices',
    // Charges
    'view_finance_charges',
    'create_finance_charges',        // Existing pattern
    'void_finance_charges',
    // Payments
    'view_finance_payments',
    'import_finance_payments',
    'allocate_finance_payment',
    'view_finance_payment_details',
    // ADD HERE:
    'create_finance_payments',       // New permission needed
],
```

### Permission Registration Pattern
Follow existing pattern: `create_` prefix for creation permissions

---

## 10. Payments/Show.vue - Type Reference
**Path:** `resources/js/pages/Finance/Payments/Show.vue`

### Payment Data Structure
```ts
interface Payment {
    id: number;
    amount: number;
    paid_at: string | null;
    source: string | null;           // manual, gateway, bank_transfer, import
    external_ref: string | null;
    status: string;                   // completed, pending, cancelled, refunded
    method: string;                   // cash, bank_transfer, gateway, wallet, import, other
    notes: string | null;
    student: {
        id: number;
        full_name: string;
        student_id: string;
    } | null;
    unapplied_amount: number;
    allocated_amount: number;
    applications: PaymentApplication[];
    received_by?: { name: string } | null;
}

interface PaymentApplication {
    id: number;
    entry_type: string;               // application, credit, etc.
    amount: number;
    applied_at: string | null;
    invoice_line: {
        id: number;
        description_snapshot: string;
        amount_snapshot: number;
        invoice?: { id, invoice_number, status };
        charge?: { id, description, amount, semester };
    } | null;
}
```

### Section Layout
- Payment Information Card (Amount, Date, Method/Source, Ref, Status, Balance)
- Student Information Card (Name, ID, Notes)
- Payment Applications Table (Date, Invoice, Charge, Semester, Entry, Amount)
- Manual Allocation Form (for unapplied_amount > 0)

---

## Summary

### Create Payment Implementation Checklist

1. **Route (web.php)**
   - Add GET `/finance/payments/create` → `create()` method
   - Add POST `/finance/payments` → `store()` method
   - Add middleware: `can:create_finance_payments`

2. **Controller (PaymentController.php)**
   - `create()`: Return Inertia with payment methods, student data
   - `store()`: Validate, call `PaymentService->recordPayment()`, redirect to show

3. **Permission (config/permission.php)**
   - Add `'create_finance_payments'` to finances section

4. **Vue Page (Create.vue)**
   - Mirror Charges/Create.vue layout
   - Use StudentCombobox for student selection
   - Fields: student_id, amount, method, source, external_ref, paid_at, status, notes
   - Sidebar: Submit/Cancel buttons + Guidance card

5. **Validation (Controller or Form Request)**
   - student_id: required|exists:students,id
   - amount: required|numeric|min:0.01
   - method: required|in:cash,bank_transfer,gateway,wallet,other
   - paid_at: required|datetime
   - status: optional|in:pending,completed,refunded,cancelled
   - source: optional|string
   - external_ref: optional|string
   - notes: optional|string

6. **Index.vue Update**
   - Add "Create Payment" button next to "Import Payments"

---

## Unresolved Questions

1. Should payment method "source" be required or optional? (Currently optional in service)
2. Auto-populate `paid_at` with current date/time or require user input?
3. Auto-populate `received_by_user_id` from Auth::user() or allow selection?
4. Should create immediately allocate payment to charges, or defer to Show page?
5. Need to validate if status must be COMPLETED on creation or allow PENDING?

