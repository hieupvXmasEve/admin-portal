# Student Finance API - Implementation Plan

**Date:** 2026-03-26 | **Branch:** dev | **Status:** In Progress (Phases 1-4 Completed, Phase 5 Pending)

## Goal

Mở rộng student-facing finance API để sinh viên có thể:
1. Xem khoản nợ đã đẩy lên DNG (pending thanh toán)
2. Xem lịch sử thanh toán chi tiết (payment → charge allocation)
3. Xem danh sách hóa đơn (invoices) và chi tiết từng hóa đơn
4. Có cái nhìn tổng quan về tình trạng tài chính

## Current State

**Existing APIs** (`api/v1/student/finance`):
- `GET /balance` — Balance summary (tổng nợ, tổng thanh toán)
- `GET /charges` — Danh sách charges + summary
- `GET /charges/{id}` — Chi tiết charge
- `GET /payments` — Lịch sử payments (basic)

**Existing DNG APIs** (`api/v1/finance/dng`):
- `POST /payment-requests` — Tạo payment request + push DNG
- `GET /payment-requests/{id}/qr` — Lấy QR code

**Missing:**
1. Student không xem được DNG payment requests của mình (khoản nào đang chờ thanh toán trên DNG)
2. Payment history thiếu thông tin: charge nào đã được allocate, invoice nào
3. Không có API invoices cho student
4. Không có overview/dashboard endpoint
5. DNG API không scoped theo student (ai gọi cũng được)

## Phases

| # | Phase | Priority | Effort | Status |
|---|-------|----------|--------|--------|
| 1 | Student DNG Payment Requests API | High | S | Completed |
| 2 | Student Invoices API | High | S | Completed |
| 3 | Enhanced Payment History | Medium | S | Completed |
| 4 | Finance Overview (Dashboard) | Medium | S | Completed |
| 5 | Tests | High | M | Planned |

## Architecture

All endpoints under `api/v1/student/finance/*`, middleware `['web', 'auth']`, scoped by `$request->user('student')`.

```
StudentFinanceController (existing, extend)
├── balance()          — existing
├── charges()          — existing
├── chargeDetail()     — existing
├── payments()         — enhance: add allocation details
├── paymentDetail()    — NEW: single payment with full allocation tree
├── invoices()         — NEW: list invoices
├── invoiceDetail()    — NEW: invoice with lines + payments
├── dngRequests()      — NEW: list DNG payment requests
├── overview()         — NEW: financial summary dashboard
```

## Key Decisions

1. **Extend existing controller** — không tạo controller mới, keep cohesion
2. **Reuse existing services** — PaymentService, FinanceChargeService đã có sẵn
3. **No new models/tables** — tất cả data đã tồn tại, chỉ cần query + format
4. **Student scoping** — luôn filter theo `student_id` từ auth guard
