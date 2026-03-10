---
title: "Void & Release Allocation Pattern"
status: pending
created: 2026-03-09
replaces: 2026-03-09-credit-memo-payment.md
---

# Void & Release Allocation Pattern

## Problem

Khi student chuyển từ EGC sang Major, charge âm + credit memo payment tạo ra số liệu sai:
- Total Charged phồng (75M thay vì 55.5M)
- Total Paid gộp cả credit memo (70.5M thay vì 55.5M)
- Total Discount gộp cả refund + scholarship (19.5M thay vì 4.5M)

## Solution: Void & Release

Thay vì tạo charge âm + credit memo payment, **void charge không dùng** → tự động release allocations → payment có unapplied amount → auto-allocate vào charge tiếp theo.

## Implementation Phases

### Phase 1: Update VoidFinanceChargeAction - Release Allocations

**File:** `app/Modules/Finance/Actions/VoidFinanceChargeAction.php`

**Changes:**
1. Khi void charge (bất kể dương hay âm):
   - Xoá tất cả `PaymentAllocation` liên quan đến charge đó
   - Xoá `InvoiceLine` của charge đó khỏi invoice
   - Recalculate invoice status (nếu invoice rỗng → delete invoice)
2. Giữ logic void credit memo payment cho backward compatibility

**Logic:**
```php
public function handle(int $chargeId, string $reason, ?int $userId = null): FinanceCharge
{
    return DB::transaction(function () {
        $charge = FinanceCharge::findOrFail($chargeId);

        // 1. Release all allocations for this charge
        $affectedPaymentIds = $this->releaseAllocations($charge);

        // 2. Remove from invoice
        $this->removeFromInvoice($charge);

        // 3. Void the charge
        $charge->update([...status => void...]);

        // 4. If credit charge, void credit memo payment (backward compat)
        if ($charge->amount < 0) {
            $this->voidCreditMemoPayment($charge);
        }

        // 5. Update affected invoice statuses
        $this->recalculateAffectedInvoices($charge);

        return $charge->fresh();
    });
}

protected function releaseAllocations(FinanceCharge $charge): array
{
    $paymentIds = PaymentAllocation::where('charge_id', $charge->id)
        ->pluck('payment_id')->toArray();

    PaymentAllocation::where('charge_id', $charge->id)->delete();

    return $paymentIds; // payments now have unapplied amount
}

protected function removeFromInvoice(FinanceCharge $charge): void
{
    $invoiceLines = InvoiceLine::where('charge_id', $charge->id)->get();

    foreach ($invoiceLines as $line) {
        $invoice = $line->invoice;
        $line->delete();

        // If invoice has no more lines, delete it
        if ($invoice->invoiceLines()->count() === 0) {
            $invoice->delete();
        }
    }
}
```

---

### Phase 2: Revert CreateFinanceChargeAction - Remove Credit Memo

**File:** `app/Modules/Finance/Actions/CreateFinanceChargeAction.php`

**Changes:**
1. Loại bỏ tạo credit memo payment cho charge âm
2. Charge âm (scholarships, vouchers) vẫn được assign vào invoice bình thường (vì chúng giảm trừ invoice amount)
3. Chỉ loại bỏ logic credit memo payment

**Logic mới:**
```php
public function handle(array $data): FinanceCharge
{
    return DB::transaction(function () use ($data) {
        $charge = FinanceCharge::create([...]);

        // All charges (positive & negative) get assigned to invoice
        $invoice = $this->getInvoiceForCharge($charge, $data['invoice_id'] ?? null);
        $this->assignChargeToInvoice($charge, $invoice);

        // Scholarships only for tuition charges
        if ($charge->charge_type === FinanceCharge::TYPE_TUITION_TERM) {
            $this->applyScholarship($charge, $invoice);
        }

        // NO credit memo payment creation
        return $charge->fresh(['invoiceLines.invoice']);
    });
}
```

**Xoá methods:** `createCreditMemoPayment()`

---

### Phase 3: Clean Up Payment Model

**File:** `app/Models/Payment.php`

**Changes:**
1. Giữ `SOURCE_CREDIT_MEMO` constant cho backward compat (dữ liệu cũ)
2. Giữ `creditCharge()` relationship
3. Giữ `is_credit_memo` accessor
4. Không xoá gì, chỉ deprecated

---

### Phase 4: Update FinanceCharge Controller - Void UI

**File:** `app/Modules/Finance/Http/Web/Admin/FinanceChargeController.php`

**Changes:**
- Void action response thêm thông tin về allocations đã release
- Flash message: "Charge voided. X allocations released, Y payments now have unapplied balance."

---

### Phase 5: Update UI - Fee Summary Stats

**Files:**
- `resources/js/pages/students/AcademicSummary/FeeTab.vue`
- `resources/js/pages/Finance/Operations/Dashboard.vue`

**Changes:**
- Total Paid = chỉ payments thật (exclude `source = 'credit_memo'`)
- Hoặc tốt hơn: backend query đã chỉ gửi đúng data

**Backend queries cần check:**
- `GetStudentFeeSummaryQuery` → verify Total Paid calculation
- `GetBillingDashboardStudentsQuery` → verify stats

---

### Phase 6: Data Cleanup Migration

**File:** New migration

**Changes:**
1. Tìm tất cả credit memo payments (`source = 'credit_memo'`)
2. Xoá allocations của chúng
3. Đánh dấu status = 'cancelled'
4. Tìm charge âm liên quan (non-scholarship) → void chúng
5. Xoá invoice lines & invoices trống
6. Recalculate tất cả invoice statuses bị ảnh hưởng

**IMPORTANT:** Migration phải reversible hoặc có dry-run mode

---

### Phase 7: Tests

**Files:**
- `tests/Feature/Finance/VoidFinanceChargeTest.php` (new)
- Update `tests/Feature/Finance/CreditMemoPaymentTest.php`
- Update `tests/Feature/Finance/AutoAllocatePaymentsTest.php`

**Test cases:**
1. Void charge → releases allocations
2. Void charge → payment has unapplied amount
3. Void charge → removes from invoice
4. Void charge → deletes empty invoice
5. Auto-allocate picks up released payments
6. E2E: create 2 EGC charges → pay → void 1 → auto-allocate to tuition

---

## Workflow Example (E2E)

```
1. Create Charge EGC L4: 15M → Invoice #911
2. Create Charge EGC L5: 15M → Invoice #911
3. Payment 30M → Allocate 15M to L4, 15M to L5
4. Invoice #911: status=paid ✅

--- Student chuyển Major ---

5. Void Charge EGC L5:
   - Delete allocation (15M from Payment #29)
   - Remove InvoiceLine from Invoice #911
   - Invoice #911 recalculate: total=15M, paid=15M → still paid ✅
   - Payment #29: unapplied = 15M

6. Create Charge Tuition: 45M → Invoice #947
   - Auto scholarship: -4.5M
   - Invoice #947: total=40.5M, paid=0 → pending

7. Payment 25.5M → Allocate to Tuition

8. Auto-allocate:
   - Payment #29 unapplied 15M → Charge Tuition
   - Invoice #947: paid = 25.5M + 15M = 40.5M → paid ✅

Result:
  Total Charged: 15M + 45M = 60M
  Total Discount: 4.5M (scholarship only)
  Net Due: 55.5M
  Total Paid: 55.5M (30M + 25.5M, all real money)
  Outstanding: 0đ ✅
```

## Risks

1. **Existing credit memo data** - Cần migration cleanup cẩn thận
2. **Concurrent void + allocate** - DB transaction handles
3. **Audit trail** - Void reason + released allocation logs

## Success Criteria

- [ ] Void charge releases allocations correctly
- [ ] Payment unapplied amount updates after void
- [ ] Invoice recalculates after charge removed
- [ ] Auto-allocate picks up released payments
- [ ] UI shows correct Total Charged/Paid/Discount
- [ ] No credit memo payments created for new charges
- [ ] Existing data cleaned up via migration
- [ ] All tests pass
