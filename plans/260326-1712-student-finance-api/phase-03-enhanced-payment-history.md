# Phase 3: Enhanced Payment History

**Priority:** Medium | **Effort:** S | **Status:** Planned

## Overview

Nâng cấp payment history hiện tại để student thấy rõ từng khoản thanh toán được phân bổ vào charge/invoice nào.

## Current State

`GET /payments` hiện trả về payments + computed `allocated_amount`, `unapplied_amount` nhưng **không trả về chi tiết allocation** (charge nào, invoice nào).

## API Changes

### `GET /api/v1/student/finance/payments` (enhance existing)

**New query params:**
- `from` (optional): date filter
- `to` (optional): date filter
- `method` (optional): `cash|bank_transfer|gateway|wallet|import|other`

**Enhanced response** — thêm `allocations` vào mỗi payment:

```json
{
  "data": {
    "payments": [
      {
        "id": 5,
        "amount": 10000000,
        "method": "gateway",
        "source": "dng",
        "external_ref": "DNG-12345678",
        "paid_at": "2026-03-25T14:00:00Z",
        "status": "completed",
        "allocated_amount": 10000000,
        "unapplied_amount": 0,
        "is_fully_allocated": true,
        "allocations": [
          {
            "charge_description": "Tuition - Spring 2026",
            "charge_type": "tuition_term",
            "invoice_number": "INV-2026-001",
            "amount": 10000000,
            "applied_at": "2026-03-25T14:00:05Z"
          }
        ]
      }
    ],
    "unapplied_credit": 0,
    "summary": {
      "total_paid": 10000000,
      "total_allocated": 10000000,
      "total_unapplied": 0
    }
  }
}
```

### `GET /api/v1/student/finance/payments/{id}` (NEW)

Single payment with full allocation tree.

```json
{
  "data": {
    "id": 5,
    "amount": 10000000,
    "method": "gateway",
    "source": "dng",
    "external_ref": "DNG-12345678",
    "paid_at": "2026-03-25T14:00:00Z",
    "status": "completed",
    "notes": null,
    "allocated_amount": 10000000,
    "unapplied_amount": 0,
    "allocations": [
      {
        "id": 1,
        "charge_description": "Tuition - Spring 2026",
        "charge_type": "tuition_term",
        "semester": "Spring 2026",
        "invoice_number": "INV-2026-001",
        "amount": 10000000,
        "entry_type": "application",
        "applied_at": "2026-03-25T14:00:05Z"
      }
    ],
    "dng_request": {
      "id": 1,
      "dng_payment_id": "12345678",
      "status": "paid_invoiced",
      "invoice_serial_number": "1/001;K23TF"
    }
  }
}
```

## Related Code Files

**Modify:**
- `app/Modules/Finance/Http/Api/Student/StudentFinanceController.php` — enhance `payments()`, add `paymentDetail()`
- `app/Modules/Finance/routes/api.php` — add payment detail route

**Read-only:**
- `app/Models/Payment.php` — relationships: `applications.invoiceLine.charge`, `applications.invoiceLine.invoice`
- `app/Models/PaymentApplication.php` — entry_type, amount, applied_at
- `app/Modules/Finance/Dng/Models/DngPaymentRequest.php` — linked DNG data

## Implementation Steps

1. Enhance `payments()` — add date/method filters, add allocations via eager load
2. Add `paymentDetail()` — single payment with full allocation + DNG request link
3. Format allocations: join PaymentApplication → InvoiceLine → Charge + Invoice
4. Add DNG request info if source = 'dng'
5. Register payment detail route

## Todo

- [ ] Add date/method filters to `payments()`
- [ ] Include allocations in payment list response
- [ ] Add `paymentDetail()` method
- [ ] Link DNG request data for gateway payments
- [ ] Register route `GET /payments/{id}`
- [ ] Add summary totals

## Success Criteria

- Each payment shows which charges it was allocated to
- Payment detail shows invoice number for each allocation
- DNG gateway payments link back to DNG request
- Filterable by date range and method
