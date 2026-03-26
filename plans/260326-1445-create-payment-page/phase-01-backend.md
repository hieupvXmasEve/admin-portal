---
phase: 1
title: "Backend: Route, Controller, FormRequest, Permission"
status: pending
effort: 1.5h
---

# Phase 1 — Backend

## Context Links

- Controller: `app/Modules/Finance/Http/Web/Admin/PaymentController.php`
- Service: `app/Modules/Finance/Services/PaymentService.php`
- Routes: `app/Modules/Finance/routes/web.php`
- Model: `app/Models/Payment.php`
- Pattern ref: `FinanceChargeController::create()` + `store()`

## Overview

Add `create` and `store` controller methods, a FormRequest, a new permission, and an API endpoint for fetching student outstanding charges.

## Related Code Files

### Files to modify
- `app/Modules/Finance/Http/Web/Admin/PaymentController.php` — add `create()`, `store()`
- `app/Modules/Finance/routes/web.php` — add create/store routes
- `config/permission.php` — add `create_finance_payment`

### Files to create
- `app/Modules/Finance/Http/Requests/StorePaymentRequest.php`

## Implementation Steps

### 1. Add permission `create_finance_payment`

In `config/permission.php`, add to the finance section alongside existing payment permissions:

```php
'create_finance_payment' => 'create_finance_payment',
```

Then run seeder or sync permissions (document for deployer).

### 2. Create `StorePaymentRequest`

Path: `app/Modules/Finance/Http/Requests/StorePaymentRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create_finance_payment');
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in([
                Payment::METHOD_CASH,
                Payment::METHOD_BANK_TRANSFER,
                Payment::METHOD_GATEWAY,
                Payment::METHOD_WALLET,
                Payment::METHOD_OTHER,
            ])],
            'source' => ['nullable', 'string', 'max:255'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'auto_allocate' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Amount must be greater than 0.',
            'paid_at.before_or_equal' => 'Payment date cannot be in the future.',
        ];
    }
}
```

Notes:
- `method` excludes `import` (import has its own flow)
- `auto_allocate` boolean flag — if true, run `autoAllocatePayment()` after recording
- Authorization via `can('create_finance_payment')`

### 3. Add `create()` method to PaymentController

```php
public function create(Request $request)
{
    return Inertia::render('Finance/Payments/Create', [
        'paymentMethods' => collect([
            Payment::METHOD_CASH,
            Payment::METHOD_BANK_TRANSFER,
            Payment::METHOD_GATEWAY,
            Payment::METHOD_WALLET,
            Payment::METHOD_OTHER,
        ])->map(fn (string $m) => [
            'value' => $m,
            'label' => ucwords(str_replace('_', ' ', $m)),
        ]),
    ]);
}
```

Minimal props — student data and charges fetched via API after selection (keeps page load fast).

### 4. Add `store()` method to PaymentController

```php
use App\Modules\Finance\Http\Requests\StorePaymentRequest;

public function store(StorePaymentRequest $request, PaymentService $paymentService)
{
    $validated = $request->validated();

    $payment = $paymentService->recordPayment([
        'student_id' => $validated['student_id'],
        'amount' => $validated['amount'],
        'method' => $validated['method'],
        'source' => $validated['source'] ?? 'manual',
        'external_ref' => $validated['external_ref'] ?? null,
        'paid_at' => $validated['paid_at'],
        'notes' => $validated['notes'] ?? null,
        'received_by_user_id' => $request->user()->id,
        'status' => Payment::STATUS_COMPLETED,
    ]);

    if ($validated['auto_allocate'] ?? false) {
        $paymentService->autoAllocatePayment($payment->id);
    }

    return redirect()
        ->route('finance.payments.show', $payment->id)
        ->with('success', 'Payment recorded successfully.');
}
```

### 5. Add API endpoint for student outstanding charges + balance

Add to `PaymentController`:

```php
public function getStudentCharges(int $studentId, PaymentService $paymentService)
{
    $student = \App\Models\Student::findOrFail($studentId);

    $balance = $paymentService->getStudentBalance($studentId);
    $charges = $paymentService->getOutstandingCharges($studentId)
        ->map(fn ($charge) => [
            'id' => $charge->id,
            'description' => $charge->description,
            'charge_type' => $charge->charge_type,
            'amount' => (float) $charge->amount,
            'balance' => (float) $charge->balance,
            'semester' => $charge->semester?->name,
        ]);

    return response()->json([
        'balance' => $balance,
        'charges' => $charges->values(),
    ]);
}
```

### 6. Add routes

In `app/Modules/Finance/routes/web.php`, inside the payments prefix group, add **before** the `{payment}` wildcard route:

```php
Route::get('/create', [PaymentController::class, 'create'])
    ->middleware('can:create_finance_payment')
    ->name('create');
Route::post('/', [PaymentController::class, 'store'])
    ->middleware('can:create_finance_payment')
    ->name('store');
Route::get('/{student}/charges', [PaymentController::class, 'getStudentCharges'])
    ->middleware('can:create_finance_payment')
    ->name('student-charges');
```

**IMPORTANT**: These must be placed before `Route::get('/{payment}', ...)` to avoid route conflict.

## Todo List

- [ ] Add `create_finance_payment` permission to `config/permission.php`
- [ ] Create `StorePaymentRequest.php` FormRequest
- [ ] Add `create()` method to `PaymentController`
- [ ] Add `store()` method to `PaymentController`
- [ ] Add `getStudentCharges()` method to `PaymentController`
- [ ] Register 3 new routes in `web.php` (before wildcard)
- [ ] Run `php artisan permission:sync` or equivalent seeder
- [ ] Verify routes via `php artisan route:list --name=finance.payments`

## Success Criteria

- `GET /finance/payments/create` renders Inertia page
- `POST /finance/payments` creates payment record, redirects to Show
- `GET /finance/payments/{student}/charges` returns JSON with balance + outstanding charges
- Unauthorized users get 403
- Invalid data returns validation errors

## Risk Assessment

- **Route ordering**: `{payment}` wildcard can swallow `/create` — mitigate by placing create route first
- **Permission sync**: New permission needs to be assigned to roles — document for admin
