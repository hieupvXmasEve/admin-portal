# Phase 2: Student Invoices API

**Priority:** High | **Effort:** S | **Status:** Completed

## Overview

Cho student xem danh sách hóa đơn (invoices) và chi tiết từng hóa đơn gồm những khoản phí gì, đã thanh toán bao nhiêu.

## API Endpoints

### `GET /api/v1/student/finance/invoices`

**Query params:**
- `semester_id` (optional): filter theo kỳ
- `status` (optional): `draft|pending|paid|overdue|cancelled`

**Response:**
```json
{
  "data": {
    "invoices": [
      {
        "id": 1,
        "invoice_number": "INV-2026-001",
        "semester": { "id": 5, "name": "Spring 2026" },
        "subtotal": 15000000,
        "discount_total": 2000000,
        "total_amount": 13000000,
        "paid_amount": 13000000,
        "remaining": 0,
        "status": "paid",
        "due_date": "2026-04-01",
        "paid_at": "2026-03-25T14:00:00Z",
        "line_count": 3
      }
    ],
    "summary": {
      "total_invoiced": 13000000,
      "total_paid": 13000000,
      "total_outstanding": 0
    }
  }
}
```

### `GET /api/v1/student/finance/invoices/{id}`

**Response:** Invoice with lines, each line showing charge detail and payment applications.

```json
{
  "data": {
    "id": 1,
    "invoice_number": "INV-2026-001",
    "semester": { "id": 5, "name": "Spring 2026" },
    "subtotal": 15000000,
    "discount_total": 2000000,
    "total_amount": 13000000,
    "paid_amount": 13000000,
    "status": "paid",
    "due_date": "2026-04-01",
    "paid_at": "2026-03-25T14:00:00Z",
    "lines": [
      {
        "id": 10,
        "description": "Tuition - Spring 2026",
        "amount": 10000000,
        "charge_type": "tuition_term",
        "paid_amount": 10000000,
        "is_fully_paid": true,
        "payments": [
          {
            "payment_id": 5,
            "amount": 10000000,
            "method": "gateway",
            "paid_at": "2026-03-25T14:00:00Z"
          }
        ]
      }
    ],
    "discounts": [
      {
        "id": 1,
        "description": "Scholarship 20%",
        "amount": 2000000
      }
    ]
  }
}
```

## Related Code Files

**Modify:**
- `app/Modules/Finance/Http/Api/Student/StudentFinanceController.php` — add `invoices()`, `invoiceDetail()`
- `app/Modules/Finance/routes/api.php` — add routes

**Read-only:**
- `app/Models/StudentInvoice.php` — model, relationships
- `app/Models/InvoiceLine.php` — line items
- `app/Models/PaymentApplication.php` — payment allocations per line

## Implementation Steps

1. Add `invoices()` method to controller
   - Query `StudentInvoice::where('student_id', $student->id)`
   - Eager load `semester`, count lines
   - Optional filter by semester_id, status
   - Compute summary
2. Add `invoiceDetail()` method
   - Eager load `invoiceLines.charge`, `invoiceLines.paymentApplications.payment`, `discounts`
   - Compute paid_amount per line from payment applications
3. Register routes

## Todo

- [x] Add `invoices()` to controller
- [x] Add `invoiceDetail()` to controller
- [x] Register routes
- [x] Compute `remaining` = total_amount - paid_amount
- [x] Include payment method info in line-level payments

## Success Criteria

- Student sees all invoices grouped by semester
- Invoice detail shows line-by-line breakdown with payments applied
- Discount information visible
- Outstanding amount clear per invoice
