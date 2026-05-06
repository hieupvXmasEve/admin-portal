# Phase 1: Backend — Query + Action

## Status: pending

## Tasks

### 1.1 Create `ListDngWorklistQuery`

**File:** `app/Modules/Finance/Queries/Dng/ListDngWorklistQuery.php`

**Input params:**
- `dng_fee_type` (required) — HP, HL, PTL, KHAC
- `search` (optional) — student name/code
- `campus_id` (optional)
- `semester_id` (optional)
- `dng_status` (optional) — all, no_dng, has_active_dng
- `per_page`, `sort`, `direction`

**Query logic:**
```php
// 1. Map DNG fee_type → charge_types
$chargeTypes = self::mapFeeTypeToChargeTypes($dngFeeType);

// 2. Aggregate charges per student
FinanceCharge::query()
    ->active()
    ->charges()  // amount > 0
    ->whereIn('charge_type', $chargeTypes)
    ->select([
        'student_id',
        DB::raw('COUNT(*) as charge_count'),
        DB::raw('SUM(amount) as total_amount'),
    ])
    ->groupBy('student_id')
    // ... joins for student info, campus, etc.
```

**Balance calculation (batch-optimized):**
- Use subquery to sum `payment_applications.amount` per charge
- Use subquery to sum `invoice_discounts.amount` per charge
- `balance = total_amount - total_paid - total_discount`
- Only return students where `balance > 0`

**Active DNG status:**
- LEFT JOIN `dng_payment_requests` WHERE `fee_type = $dngFeeType` AND `status IN (pending, pushed_to_dng)` AND `student_id = charges.student_id`
- Return: `active_dng_id`, `active_dng_status`, `active_dng_amount`

**Return structure:**
```php
[
    'students' => PaginatedResponse<[
        'student_id' => int,
        'student_code' => string,
        'student_name' => string,
        'campus_name' => string,
        'charge_count' => int,
        'total_amount' => float,
        'total_paid' => float,
        'total_discount' => float,
        'balance' => float,
        'active_dng' => null | [
            'id' => int,
            'status' => string,
            'amount' => float,
            'created_at' => string,
        ],
        'charges' => [ // for expandable row
            ['id' => int, 'charge_type' => string, 'amount' => float, 'balance' => float, 'description' => string, 'semester' => string],
        ],
    ]>,
    'filters' => [...],
    'summary' => [
        'total_students' => int,
        'total_balance' => float,
        'students_with_active_dng' => int,
        'students_without_dng' => int,
    ],
]
```

**Helper: `mapFeeTypeToChargeTypes()`:**
```php
private static function mapFeeTypeToChargeTypes(string $dngFeeType): array
{
    return match ($dngFeeType) {
        'HP'   => [FinanceCharge::TYPE_TUITION_TERM, FinanceCharge::TYPE_EGC_LEVEL_FEE, FinanceCharge::TYPE_COURSE_FEE],
        'HL'   => [FinanceCharge::TYPE_RETAKE_FEE],
        'PTL'  => [FinanceCharge::TYPE_EXAM_RESIT_FEE],
        'KHAC' => [FinanceCharge::TYPE_MANUAL_FEE, FinanceCharge::TYPE_ADJUSTMENT],
        default => throw new \InvalidArgumentException("Unknown DNG fee type: {$dngFeeType}"),
    };
}
```

### 1.2 Create `CreateBatchDngFromChargesAction`

**File:** `app/Modules/Finance/Actions/CreateBatchDngFromChargesAction.php`

**Input:**
```php
[
    'student_ids' => int[],
    'dng_fee_type' => string,     // HP, HL, PTL, KHAC
    'due_date' => string,         // Y-m-d
    'semester_id' => int,
    'description' => string,
    'estimate_time' => string,    // MM/YY
    'amount_overrides' => ?array, // student_id => amount (optional)
]
```

**Logic per student (inside DB::transaction + lockForUpdate):**

```
1. Load active charges of matching charge_type(s) where balance > 0
2. Calculate total balance (or use override if provided)
3. Check existing active DNG for same fee_type + student:
   - If exists with status pending/pushed_to_dng → cancel via CancelDngPaymentRequestAction
4. Resolve campus code via DngCampusCodeResolver
5. Build DNG payload
6. Push to DNG via DngPaymentService::createAndPush()
7. Create DngPaymentRequestCharge pivot rows (1 per charge, recording amount from charge balance)
```

**Return:**
```php
['created' => int, 'failed' => int, 'cancelled_old' => int, 'errors' => string[]]
```

**Error handling:**
- Per-student errors should not abort entire batch
- Collect errors, continue with next student
- Return error details for UI display

### 1.3 Create `StoreBatchDngFromChargesRequest`

**File:** `app/Modules/Finance/Http/Requests/Dng/StoreBatchDngFromChargesRequest.php`

**Validation rules:**
```php
[
    'student_ids'      => 'required|array|min:1|max:100',
    'student_ids.*'    => 'integer|exists:students,id',
    'dng_fee_type'     => 'required|string|in:' . implode(',', DngFeeTypeOptions::values()),
    'due_date'         => 'required|date|after_or_equal:today',
    'semester_id'      => 'required|integer|exists:semesters,id',
    'description'      => 'required|string|max:255',
    'estimate_time'    => 'required|string|max:10',
    'amount_overrides' => 'nullable|array',
    'amount_overrides.*' => 'numeric|min:1',
]
```

### 1.4 Handle `storeSimple` (HL fee_type: approved retake registrations without charge)

When `dng_fee_type = HL`, the worklist query should also surface `CourseRetakeRegistration`
records in `approved` status that do NOT have a `finance_charge_id` yet.

**Query addition for HL:**
```php
// Append to students list: approved retake registrations without charges
if ($dngFeeType === 'HL') {
    $approvedWithoutCharge = CourseRetakeRegistration::query()
        ->where('status', CourseRetakeRegistration::STATUS_APPROVED)
        ->whereNull('finance_charge_id')
        ->with(['student', 'unit', 'semester', 'campus'])
        ->get()
        ->groupBy('student_id');
    // Merge into result as students with `needs_charge_creation = true`
}
```

**Action: before pushing DNG for HL, auto-create charges for approved registrations:**

In `CreateBatchDngFromChargesAction`, when fee_type = HL:
1. Check if student has `CourseRetakeRegistration` in `approved` status without charge
2. If yes → call `CreateRetakeCourseChargeSimpleAction` to create charge + transition to `payment_pending`
3. Then proceed with normal DNG push flow (charge now exists)

This absorbs `RetakeCourseChargeController::storeSimple` — no separate route needed.

### 1.5 Deprecate Manual DNG API

Mark for removal (Phase 4):
- `POST /api/v1/finance/dng/payment-requests` (`DngPaymentController::store`)
- `POST /api/v1/finance/dng/batch` (`BatchDngApiController::store`)

New DNG creation exclusively via `DngWorklistController::store` (charge-linked).

## Acceptance Criteria

- [ ] `ListDngWorklistQuery` returns correct data for each fee_type
- [ ] Balance calculation is batch-optimized (no N+1)
- [ ] `CreateBatchDngFromChargesAction` creates DNG + pivot rows
- [ ] Old active DNG auto-cancelled before creating new one
- [ ] Per-student errors don't abort batch
- [ ] HL fee_type shows approved registrations without charges
- [ ] Auto-creates charges for approved retake registrations before DNG push
