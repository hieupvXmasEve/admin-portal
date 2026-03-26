# Phase 1: Student DNG Payment Requests API

**Priority:** High | **Effort:** S | **Status:** Planned

## Overview

Cho phép student xem danh sách các khoản nợ đã đẩy lên DNG và trạng thái thanh toán.

## Requirements

- Student chỉ xem được DNG requests của chính mình
- Hiển thị trạng thái: đang chờ thanh toán, đã thanh toán, đã có hóa đơn
- Có thể filter theo status
- Trả về QR data nếu có

## API Endpoints

### `GET /api/v1/student/finance/dng-requests`

**Query params:**
- `status` (optional): `pending|pushed_to_dng|qr_ready|paid_uninvoiced|paid_invoiced|reconciled|failed`

**Response:**
```json
{
  "data": {
    "dng_requests": [
      {
        "id": 1,
        "dng_payment_id": "12345678",
        "amount": 5000000,
        "fee_type": "tuition",
        "item_id": "ITEM001",
        "status": "qr_ready",
        "has_qr": true,
        "paid_at": null,
        "invoice_serial_number": null,
        "invoice_date": null,
        "created_at": "2026-03-20T10:00:00Z"
      }
    ],
    "summary": {
      "total_pending": 5000000,
      "total_paid": 0,
      "count_pending": 1,
      "count_paid": 0
    }
  }
}
```

### `GET /api/v1/student/finance/dng-requests/{id}`

**Response:** Single DNG request with QR payload if available.

```json
{
  "data": {
    "id": 1,
    "dng_payment_id": "12345678",
    "amount": 5000000,
    "fee_type": "tuition",
    "item_id": "ITEM001",
    "status": "qr_ready",
    "qr_payload": { ... },
    "payment": null,
    "invoice_serial_number": null,
    "invoice_date": null,
    "created_at": "2026-03-20T10:00:00Z",
    "updated_at": "2026-03-20T10:05:00Z"
  }
}
```

## Related Code Files

**Modify:**
- `app/Modules/Finance/Http/Api/Student/StudentFinanceController.php` — add `dngRequests()`, `dngRequestDetail()`
- `app/Modules/Finance/routes/api.php` — add routes

**Read-only (reference):**
- `app/Modules/Finance/Dng/Models/DngPaymentRequest.php` — model, statuses
- `app/Models/Student.php` — relationship `dngPaymentRequests` (may need to add)

## Implementation Steps

1. Add `dngPaymentRequests()` relationship on Student model if not exists
2. Add `dngRequests()` method to StudentFinanceController
   - Query `DngPaymentRequest::where('student_id', $student->id)`
   - Optional filter by status
   - Compute summary (pending amount, paid amount)
3. Add `dngRequestDetail()` method
   - Include QR payload, linked Payment if bridged
4. Register routes in `api.php`

## Todo

- [ ] Check/add `dngPaymentRequests()` relationship on Student
- [ ] Add `dngRequests()` to controller
- [ ] Add `dngRequestDetail()` to controller
- [ ] Register routes
- [ ] Verify response format matches existing API patterns

## Success Criteria

- Student can list their DNG payment requests
- Student can see QR code for pending payments
- Student can track payment status through DNG lifecycle
- Only own data visible (student_id scoping)
