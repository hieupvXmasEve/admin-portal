# Phase 4: Finance Overview (Dashboard)

**Priority:** Medium | **Effort:** S | **Status:** Planned

## Overview

Endpoint tổng quan tài chính cho student — 1 API call trả về toàn bộ snapshot.

## API Endpoint

### `GET /api/v1/student/finance/overview`

**Query params:**
- `semester_id` (optional): filter theo kỳ

**Response:**
```json
{
  "data": {
    "balance": {
      "total_charges": 15000000,
      "total_credits": 2000000,
      "total_payments": 13000000,
      "net_balance": 0,
      "status": "paid"
    },
    "pending_payments": {
      "count": 0,
      "total_amount": 0,
      "items": []
    },
    "recent_payments": [
      {
        "id": 5,
        "amount": 10000000,
        "method": "gateway",
        "paid_at": "2026-03-25T14:00:00Z"
      }
    ],
    "invoices_summary": {
      "total": 1,
      "paid": 1,
      "pending": 0,
      "overdue": 0
    },
    "dng_pending": {
      "count": 0,
      "total_amount": 0
    },
    "semester": {
      "id": 5,
      "name": "Spring 2026"
    }
  }
}
```

## Implementation Steps

1. Add `overview()` method to controller
2. Compose from existing services:
   - `PaymentService::getStudentBalance()` for balance
   - `PaymentService::getOutstandingCharges()` for pending
   - `PaymentService::getPaymentHistory()` → take latest 5
   - `StudentInvoice::where(student_id)` → group by status count
   - `DngPaymentRequest::where(student_id)` → pending count/amount
3. Register route

## Todo

- [ ] Add `overview()` to controller
- [ ] Compose data from existing services (no new queries)
- [ ] Register route
- [ ] Limit recent_payments to 5

## Success Criteria

- Single API call gives full financial snapshot
- Reuses existing service methods
- No N+1 queries
