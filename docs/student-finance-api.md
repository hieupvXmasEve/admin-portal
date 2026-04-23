# Student Finance API — Frontend Guide

**Base URL:** `GET /api/v1/student/finance`
**Auth:** Bearer token (Sanctum) — student or parent guard
**All amounts:** VND, float (e.g. `15000000.00`)

---

## Endpoints Summary

| Method | Path                             | Description                                 |
| ------ | -------------------------------- | ------------------------------------------- |
| GET    | `/overview`                      | Dashboard snapshot (1 call = full picture)  |
| GET    | `/balance`                       | Balance summary                             |
| GET    | `/charges`                       | Charge list + summary                       |
| GET    | `/charges/{id}`                  | Charge detail                               |
| GET    | `/payments`                      | Payment history + allocations               |
| GET    | `/payments/{id}`                 | Payment detail + allocation tree            |
| GET    | `/invoices`                      | Invoice list + summary                      |
| GET    | `/invoices/{id}`                 | Invoice detail (lines, payments, discounts) |
| GET    | `/dng-requests`                  | DNG payment requests                        |
| GET    | `/dng-requests/{id}`             | DNG request detail                          |
| POST   | `/dng-requests/{id}/qr`          | Get QR link (legacy, single request)        |
| POST   | `/dng-requests/{id}/installment` | Get installment link (legacy, single request) |
| POST   | `/dng/qr`                        | **Get consolidated QR link (recommended)**  |
| POST   | `/dng/installment`               | **Get consolidated installment link (recommended)** |

---

## 1. Overview (Dashboard)

```
GET /overview?semester_id=5
```

**Use for:** Dashboard screen — single API call returns full financial snapshot.

**Response:**

```json
{
    "data": {
        "balance": {
            "total_charges": 15000000,
            "total_payments": 13000000,
            "total_credits": 2000000,
            "balance": 0,
            "status": "paid"
        },
        "pending_payments": {
            "count": 2,
            "total_amount": 5000000,
            "items": [
                { "id": 10, "description": "Tuition - Spring 2026", "balance": 3000000 },
                { "id": 11, "description": "Lab Fee", "balance": 2000000 }
            ]
        },
        "recent_payments": [{ "id": 5, "amount": 10000000, "method": "gateway", "paid_at": "2026-03-25T14:00:00+07:00" }],
        "invoices_summary": {
            "total": 3,
            "paid": 2,
            "pending": 0,
            "overdue": 1,
            "nearest_due_date": "2026-04-01"
        },
        "dng_pending": {
            "count": 1,
            "total_amount": 5000000
        },
        "semester": { "id": 5, "name": "Spring 2026" }
    }
}
```

**UI Mapping:**

```
┌─────────────────────────────────────────────┐
│  [Semester Dropdown: Spring 2026 ▾]         │
│                                             │
│  ┌───────────────────────────────────────┐  │
│  │  BALANCE CARD                         │  │
│  │  Tổng phí:  15,000,000₫              │  │
│  │  Đã đóng:   13,000,000₫              │  │
│  │  Còn nợ:     0₫        [PAID ✓]      │  │
│  └───────────────────────────────────────┘  │
│                                             │
│  ┌────────────┐  ┌────────────┐             │
│  │ DNG        │  │ Hóa đơn    │             │
│  │ 1 pending  │  │ 1 overdue  │ ← RED badge │
│  │ 5,000,000₫ │  │ Due: 01/04 │             │
│  └────────────┘  └────────────┘             │
│                                             │
│  Thanh toán gần đây          [Xem tất cả →]│
│  ┌───────────────────────────────────────┐  │
│  │ 25/03  Gateway       10,000,000₫     │  │
│  │ 20/03  Cash           2,000,000₫     │  │
│  └───────────────────────────────────────┘  │
│                                             │
│  Khoản phí chưa thanh toán   [Xem tất cả →]│
│  ┌───────────────────────────────────────┐  │
│  │ Tuition - Spring 2026    3,000,000₫   │  │
│  │ Lab Fee                  2,000,000₫   │  │
│  └───────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
```

**Key UI rules:**

- `balance.status === "outstanding"` → show red badge
- `invoices_summary.overdue > 0` → red warning + show `nearest_due_date`
- `dng_pending.count > 0` → show "Thanh toán ngay" CTA
- `pending_payments.items` → top 5 unpaid charges, link to full charges list

---

## 2. Charges (Khoản phí)

```
GET /charges?semester_id=5&unpaid=true
```

| Param         | Type  | Description                                       |
| ------------- | ----- | ------------------------------------------------- |
| `semester_id` | int?  | Filter by semester                                |
| `unpaid`      | bool? | `true` = only show charges with remaining balance |

**Response:**

```json
{
    "data": {
        "charges": [
            {
                "id": 10,
                "description": "Tuition - Spring 2026",
                "charge_type": "tuition_term",
                "amount": 10000000,
                "is_charge": true,
                "is_credit": false,
                "paid_amount": 7000000,
                "balance": 3000000,
                "is_fully_paid": false
            }
        ],
        "summary": {
            "total_charges": 15000000,
            "total_credits": 2000000,
            "net_amount": 13000000
        }
    }
}
```

**UI:**

```
┌─────────────────────────────────────────┐
│ [Semester ▾]  [☐ Chỉ chưa thanh toán]  │
├─────────────────────────────────────────┤
│ Tuition - Spring 2026                   │
│ Phí: 10,000,000₫                       │
│ Đã đóng: 7,000,000₫  Còn: 3,000,000₫  │
│ ████████████░░░░ 70%         [UNPAID]   │
├─────────────────────────────────────────┤
│ Lab Fee                                 │
│ Phí: 3,000,000₫                        │
│ Đã đóng: 1,000,000₫  Còn: 2,000,000₫  │
│ ████░░░░░░░░░░░░ 33%         [UNPAID]   │
├─────────────────────────────────────────┤
│ Summary                                 │
│ Tổng phí: 15,000,000₫                  │
│ Giảm trừ: -2,000,000₫                  │
│ Ròng:     13,000,000₫                  │
└─────────────────────────────────────────┘
```

**Key UI rules:**

- `is_fully_paid === false` → highlight, show progress bar (`paid_amount / amount`)
- `is_credit === true` → green text, negative amount (giảm trừ)
- Toggle "Chỉ chưa thanh toán" → add `?unpaid=true`

---

## 3. Payments (Lịch sử thanh toán)

```
GET /payments?from=2026-01-01&to=2026-03-31&method=gateway
```

| Param    | Type    | Description                                                     |
| -------- | ------- | --------------------------------------------------------------- |
| `from`   | date?   | Start date (YYYY-MM-DD)                                         |
| `to`     | date?   | End date                                                        |
| `method` | string? | `cash`, `bank_transfer`, `gateway`, `wallet`, `import`, `other` |

**Response:**

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
                "paid_at": "2026-03-25T14:00:00+07:00",
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
                        "applied_at": "2026-03-25T14:00:05+07:00"
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

**UI:**

```
┌─────────────────────────────────────────┐
│ [From ▾]  [To ▾]  [Method ▾]           │
├─────────────────────────────────────────┤
│ 25/03/2026              10,000,000₫     │
│ Gateway (DNG)           [COMPLETED ✓]   │
│ → Tuition Spring 2026   10,000,000₫    │
│   INV-2026-001                          │
├─────────────────────────────────────────┤
│ Summary                                 │
│ Tổng đã đóng:    10,000,000₫           │
│ Đã phân bổ:      10,000,000₫           │
│ Chưa phân bổ:             0₫           │
└─────────────────────────────────────────┘
```

**Key UI rules:**

- `unapplied_amount > 0` → show info badge "Dư X₫ chưa phân bổ"
- `allocations[]` → expandable section under each payment
- `method` label mapping: `gateway` → "Cổng TT", `cash` → "Tiền mặt", `bank_transfer` → "Chuyển khoản"
- Tap payment row → navigate to `/payments/{id}` detail

---

## 4. Payment Detail

```
GET /payments/{id}
```

**Response:**

```json
{
    "data": {
        "id": 5,
        "amount": 10000000,
        "method": "gateway",
        "source": "dng",
        "external_ref": "DNG-12345678",
        "paid_at": "2026-03-25T14:00:00+07:00",
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
                "applied_at": "2026-03-25T14:00:05+07:00"
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

**UI:** Full payment receipt view with allocation breakdown table. Show `dng_request` section only if `source === "dng"`.

---

## 5. Invoices (Hóa đơn)

```
GET /invoices?semester_id=5&status=overdue
```

| Param         | Type    | Description                                                              |
| ------------- | ------- | ------------------------------------------------------------------------ |
| `semester_id` | int?    | Filter by semester                                                       |
| `status`      | string? | `paid`, `open`, `overdue`, `zero_amount`, `draft`, `cancelled`, `issued` |

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
                "paid_at": "2026-03-25T14:00:00+07:00",
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

**UI:**

```
┌─────────────────────────────────────────┐
│ [Semester ▾]  [Status ▾]               │
├─────────────────────────────────────────┤
│ INV-2026-001          Spring 2026       │
│ 13,000,000₫           [PAID ✓]         │
│ 3 khoản phí      Due: 01/04/2026       │
├─────────────────────────────────────────┤
│ INV-2026-002          Spring 2026       │
│ 5,000,000₫  Còn: 2,000,000₫           │
│ 2 khoản phí      Due: 15/04/2026       │
│                        [OVERDUE ⚠]      │
├─────────────────────────────────────────┤
│ Tổng: 18,000,000₫  Đã đóng: 16,000,000│
│ Còn nợ: 2,000,000₫                     │
└─────────────────────────────────────────┘
```

**Status badge colors:**

- `paid` → green
- `open` → blue
- `overdue` → red
- `zero_amount` → gray

---

## 6. Invoice Detail

```
GET /invoices/{id}
```

**Response:**

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
        "paid_at": "2026-03-25T14:00:00+07:00",
        "lines": [
            {
                "id": 10,
                "description": "Tuition - Spring 2026",
                "amount": 10000000,
                "charge_type": "tuition_term",
                "paid_amount": 10000000,
                "is_fully_paid": true,
                "payments": [{ "payment_id": 5, "amount": 10000000, "method": "gateway", "paid_at": "2026-03-25T14:00:00+07:00" }]
            }
        ],
        "discounts": [{ "id": 1, "description": "Scholarship 20%", "amount": 2000000 }]
    }
}
```

**UI:** Table layout with lines, each expandable to show payments applied. Discounts shown in separate section below.

---

## 7. DNG Payment Requests

```
GET /dng-requests
```

Pending requests are **consolidated** into one `pending` object — no need to enumerate individual records. Paid history is returned separately for display.

**Response:**

```json
{
    "data": {
        "pending": {
            "total_amount": 40620000,
            "count": 3,
            "breakdown": [
                { "fee_type": "HP",   "amount": 40500000, "description": "Học phí HK1/2026", "status": "pushed_to_dng" },
                { "fee_type": "PTL",  "amount": 100000,   "description": "Phí thi lại",       "status": "pushed_to_dng" },
                { "fee_type": "KHAC", "amount": 20000,    "description": "Phí khác",           "status": "pushed_to_dng" }
            ]
        },
        "paid_requests": [
            {
                "id": 5,
                "dng_payment_id": "87654321",
                "amount": 15000000,
                "fee_type": "HP",
                "description": "Học phí HK2/2025",
                "status": "paid_invoiced",
                "paid_at": "2026-01-10T09:00:00+07:00",
                "invoice_serial_number": "1/001;K23TF",
                "invoice_date": "2026-01-10"
            }
        ],
        "summary": {
            "total_pending": 40620000,
            "total_paid": 15000000,
            "count_pending": 3,
            "count_paid": 1
        }
    }
}
```

**Khi `pending.total_amount === 0`:** không hiển thị nút thanh toán.

**UI:**

```
┌─────────────────────────────────────────┐
│  Khoản phí đang chờ thanh toán          │
│  Tổng: 40,620,000₫                     │
│  ├ HP    40,500,000₫                   │
│  ├ PTL      100,000₫                   │
│  └ KHAC      20,000₫                   │
│                                         │
│     [Thanh toán QR]   [Trả góp]         │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┤
│  Lịch sử thanh toán                     │
│  HP  15,000,000₫  Paid 10/01/2026      │
│  Invoice: 1/001;K23TF  [INVOICED ✓]    │
└─────────────────────────────────────────┘
```

**DNG Request Detail:**

```
GET /dng-requests/{id}
```

Returns single request metadata and linked `payment` if paid.

---

### Get consolidated QR link _(recommended)_

```
POST /dng/qr
```

**Use for:** Student chooses normal online payment — BE automatically gathers all `pushed_to_dng` requests and passes all `fee_types` to DNG in one call. DNG returns a single link covering the full outstanding amount.

**No request body required.**

**Response:**

```json
{
    "success": true,
    "data": {
        "payment_method": "qr",
        "payment_url": "https://third-party.example/qr-link",
        "provider_response": {
            "Code": 200,
            "Type": "success",
            "Message": "Thành công!",
            "data": {
                "PaymentUrl": "https://third-party.example/qr-link"
            }
        }
    }
}
```

**Error — no pending requests:**

```json
{
    "success": false,
    "message": "Không có khoản phí nào đang chờ thanh toán."
}
```

---

### Get consolidated installment link _(recommended)_

```
POST /dng/installment
```

**Use for:** Student chooses installment / Foxpay flow — same consolidation logic as `/dng/qr`.

**No request body required.**

**Response:**

```json
{
    "success": true,
    "data": {
        "payment_method": "installment",
        "payment_url": "https://portal-staging.foxpay.vn/payment/checkout?...",
        "provider_response": {
            "code": 200,
            "type": "success",
            "message": "Thành công!",
            "data": {
                "PaymentUrl": "https://portal-staging.foxpay.vn/payment/checkout?..."
            }
        }
    }
}
```

---

### Legacy: per-request QR / installment

```
POST /dng-requests/{id}/qr
POST /dng-requests/{id}/installment
```

These still work but are superseded by `/dng/qr` and `/dng/installment`. Response shape is the same but includes extra fields `dng_request_id` and `status`.

---

**Important error case (both flows):**

```json
{
    "success": false,
    "message": "DNG API error [400]: Campus hiện chưa hỗ trợ thanh toán FoxPay!",
    "errors": [{ "code": "SERVER_ERROR", "field": null, "detail": null }],
    "timestamp": "2026-03-30T10:10:07.806485Z"
}
```

Frontend handling:

- If `success === false`, do not redirect. Show `message` directly to the user.
- For installment flow, handle unsupported-campus (FoxPay not available) specifically.

**UI:**

```
┌─────────────────────────────────────────┐
│  Khoản phí đang chờ thanh toán          │
│  Tổng: 40,620,000₫                     │
│  (HP 40,500,000  PTL 100,000  KHAC 20k) │
│                                         │
│     [Thanh toán QR]   [Trả góp]         │
│  ↑ gọi POST /dng/qr   ↑ POST /dng/installment
└─────────────────────────────────────────┘
```

```
┌─────────────────────────────────────────┐
│ DNG-87654321              [PAID ✓]      │
│ Lab Fee              2,000,000₫         │
│ Paid: 22/03/2026                        │
│ Invoice: 1/001;K23TF                   │
└─────────────────────────────────────────┘
```

**DNG status mapping:**

- `pending`, `pushed_to_dng` → "Đang xử lý" (yellow) + show QR/installment actions
- `paid_uninvoiced` → "Đã thanh toán" (green)
- `paid_invoiced` → "Đã xuất hóa đơn" (green + invoice icon)
- `reconciled` → "Hoàn tất" (gray)
- `cancelled` → "Đã hủy" (gray) — do not show payment actions
- `failed` → "Thất bại" (red)

**Frontend action rules:**

- Use `summary.total_pending` from `/dng-requests` to display total amount.
- When user taps "Thanh toán QR" → call `POST /dng/qr`, redirect to `data.payment_url`.
- When user taps "Trả góp" → call `POST /dng/installment`, redirect to `data.payment_url`.
- Do not enumerate individual requests for payment — call the consolidated endpoints.
- If a newer DNG request replaces an older unpaid request with the same `fee_type`, the older request appears as `cancelled`.

---

## Common Patterns

### Method label mapping

```ts
const METHOD_LABELS: Record<string, string> = {
    cash: 'Tiền mặt',
    bank_transfer: 'Chuyển khoản',
    gateway: 'Cổng thanh toán',
    wallet: 'Ví điện tử',
    import: 'Import',
    other: 'Khác',
};
```

### Currency formatting

```ts
const formatVND = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
```

### Recommended navigation flow

```
Dashboard (/overview)
  ├── "Xem tất cả thanh toán" → Payments (/payments)
  │     └── Tap row → Payment Detail (/payments/{id})
  ├── "Xem hóa đơn" → Invoices (/invoices)
  │     └── Tap row → Invoice Detail (/invoices/{id})
  ├── "Khoản phí" → Charges (/charges)
  │     └── Tap row → Charge Detail (/charges/{id})
  └── "DNG Pending" card → DNG Requests (/dng-requests)
        ├── "Thanh toán QR"  → POST /dng/qr        → redirect payment_url
        └── "Trả góp"        → POST /dng/installment → redirect payment_url
```
