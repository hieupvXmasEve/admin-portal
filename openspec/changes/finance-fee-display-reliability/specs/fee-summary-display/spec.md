## MODIFIED Requirements

### Requirement: Fee summary query đọc stored snapshot, không tự tính lại
`GetStudentFeeSummaryQuery` SHALL đọc giá trị `discount_total` từ stored column trên StudentInvoice, không dùng `max(stored, face_value)` để compensate stale data.

#### Scenario: discount_total stored đúng (normal path)
- **GIVEN** invoice có `discount_total = 500_000` (từ DiscountAllocation thực tế)
- **AND** InvoiceDiscount có `amount = 600_000` (face value)
- **WHEN** fee summary query chạy
- **THEN** discount hiển thị = 500_000 (stored value, không phải 600_000)

#### Scenario: discount chưa được allocate
- **GIVEN** InvoiceDiscount tồn tại nhưng chưa có DiscountAllocation nào
- **AND** `discount_total = 0` trên invoice
- **WHEN** fee summary query chạy
- **THEN** discount hiển thị = 0 (đúng thực tế — discount chưa được apply)

#### Scenario: refreshInvoiceFromCharges void line không orphan payments
- **GIVEN** invoice có line với PaymentApplication đang active
- **WHEN** `refreshInvoiceFromCharges()` void line đó
- **THEN** `releaseLinePayments()` và `releaseLineDiscounts()` được gọi trước khi void
- **AND** không có orphaned PaymentApplication sau khi void

---

### Requirement: payment_status trong checklist chỉ dùng actual applied discounts
`buildTuitionPlanChecklist` SHALL tách `amount_due` (actual) khỏi `projected_amount_due` (estimated). `payment_status` SHALL chỉ được tính từ `amount_due` thực tế, không dùng estimated scholarship discount.

#### Scenario: Student có scholarship nhưng chưa được apply (status không phải intake_course)
- **GIVEN** student có ScholarshipAward nhưng `intake_course` status chưa đạt
- **AND** chưa có InvoiceDiscount nào cho semester này
- **WHEN** checklist được build
- **THEN** `amount_due = charge.amount` (không trừ estimated scholarship)
- **AND** `payment_status = 'unpaid'` (hoặc theo actual paid_amount)
- **AND** `projected_amount_due` có giá trị estimate (nullable)
- **AND** `is_estimated_discount = true`

#### Scenario: Student có scholarship đã được apply
- **GIVEN** InvoiceDiscount đã tồn tại và đã có DiscountAllocation
- **WHEN** checklist được build
- **THEN** `amount_due` = charge.amount - allocated discount
- **AND** `payment_status` tính từ `amount_due` thực tế
- **AND** `projected_amount_due = null`
- **AND** `is_estimated_discount = false`

#### Scenario: Student không có scholarship
- **GIVEN** student không có ScholarshipAward cho semester này
- **WHEN** checklist được build
- **THEN** `amount_due = charge.amount`
- **AND** `projected_amount_due = null`
- **AND** `is_estimated_discount = false`

---

### Requirement: Vue discount display không fallback về face value
Frontend SHALL hiển thị `allocated_amount` thực tế, không fallback về `discount.amount` khi `allocated_amount = 0`.

#### Scenario: Discount chưa được allocate
- **GIVEN** InvoiceDiscount có `amount = 600_000`, `allocated_amount = 0`
- **WHEN** FeeTab.vue render discount row
- **THEN** hiển thị "0" hoặc "-" (không hiển thị "600,000")

#### Scenario: Discount đã được allocate một phần
- **GIVEN** InvoiceDiscount có `amount = 600_000`, `allocated_amount = 400_000`
- **WHEN** FeeTab.vue render discount row
- **THEN** hiển thị "400,000" (allocated amount thực tế)
