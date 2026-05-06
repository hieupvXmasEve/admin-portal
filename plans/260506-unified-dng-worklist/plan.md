---
title: Unified DNG Creation Page — Fee-Type Centric Worklist
status: pending
priority: high
created: 2026-05-06
blockedBy: []
blocks: []
---

# Unified DNG Creation Page — Fee-Type Centric Worklist

## Overview

Gộp 3 trang tạo DNG (Settlement DNG UI, BatchDng, RetakeCourse charge) thành 1 trang duy nhất.
Giải quyết allocation lệch charge và enforce DNG constraint 1 record/fee_type/student.

**Root cause:** Settlement + BatchDng tạo DNG mà không link charge. Webhook payment allocate vào charge sai. DNG bên ngoài chỉ giữ 1 active record per fee_type per student — tạo duplicate sẽ mất data.

**Solution:** 1 unified page query từ `finance_charges`, fee_type-centric UX, bắt buộc charge↔DNG linking qua pivot `dng_payment_request_charges`.

## Phases

| # | Phase | Status | File |
|---|-------|--------|------|
| 1 | Backend: Query + Action | done | [phase-01](phase-01-backend-query-action.md) |
| 2 | Backend: Controller + Routes | done | [phase-02](phase-02-controller-routes.md) |
| 3 | Frontend: DNG Worklist Page | done | [phase-03](phase-03-frontend-page.md) |
| 4 | Settlement + Deprecated Pages Cleanup | in-progress | [phase-04](phase-04-cleanup.md) |
| 5 | Legacy Data Migration | done | [phase-05](phase-05-legacy-migration.md) |
| 6 | Tests + Verification | done | [phase-06](phase-06-tests.md) |

> **Thứ tự:** Phase 1→2→3 (new page) → Phase 4 (cleanup) → Phase 5 (migration) → Phase 6 (tests)

## Key Files

**New:**
- `app/Modules/Finance/Queries/Dng/ListDngWorklistQuery.php`
- `app/Modules/Finance/Actions/CreateBatchDngFromChargesAction.php`
- `app/Modules/Finance/Http/Web/Admin/DngWorklistController.php`
- `app/Modules/Finance/Http/Requests/Dng/StoreBatchDngFromChargesRequest.php`
- `resources/js/pages/Finance/Operations/DngWorklist.vue`
- `database/migrations/*_link_existing_dng_to_charges.php` (migration script)

**Modified:**
- `resources/js/pages/Finance/Operations/Settlement.vue` — remove DNG dialog/button
- `app/Modules/Finance/routes/web.php` — add new route
- Navigation menu — update links

**Deprecated (remove after verified):**
- `resources/js/pages/Finance/Operations/BatchDng.vue`
- `resources/js/pages/Finance/RetakeCourse/Index.vue`
- `resources/js/pages/Finance/Payments/Create.vue`
- `app/Modules/Finance/Http/Web/Admin/BatchDngPageController.php`
- `app/Modules/Finance/Http/Web/Admin/RetakeCourseChargeController.php` (index + store methods; storeSimple logic absorbed)
- `app/Modules/Finance/Dng/Http/Controllers/DngPaymentController.php` (manual DNG API)
- `app/Modules/Finance/Dng/Http/Controllers/BatchDngApiController.php` (replaced by charge-linked action)

## Architecture

### DNG Fee Type ↔ Charge Type Mapping

```
DNG Fee Type   ←   Charge Type(s)
─────────────────────────────────────
HP             ←   tuition_term, egc_level_fee, course_fee
HL             ←   retake_fee
PTL            ←   exam_resit_fee
KHAC           ←   manual_fee, adjustment
```

### UX Flow

```
[Select DNG Fee Type ▾] → [Student list: balance > 0] → [Batch select] → [Set due_date + semester] → [Push DNG]
                              ↓
                     [Expand row: charge breakdown per student]
```

### Create DNG Flow (Backend)

```
1. Input: student_ids[], dng_fee_type, due_date, semester_id, description
2. Map dng_fee_type → charge_types via DngFeeTypeOptions
3. Per student (in transaction):
   a. Load active charges of matching charge_type(s) with balance > 0
   b. Check existing active DNG for same fee_type
      → if exists: cancel old via CancelDngPaymentRequestAction
   c. Sum charge balances → DNG amount
   d. Push to DNG API via DngPaymentService::createAndPush()
   e. Create DngPaymentRequestCharge pivot rows (1 per charge)
4. Return { created, failed, cancelled_old }
```

## Dependencies

- `DngPaymentService::createAndPush()` — exists ✓
- `DngPaymentRequestCharge` pivot model — exists ✓
- `DngFeeTypeOptions::fromChargeType()` — exists ✓
- `CancelDngPaymentRequestAction` — exists ✓
- `FinanceCharge` balance calculation — exists, needs batch optimization

## Absorbed Features

### 1. `storeSimple` (RetakeCourse charge-only creation)

`CreateRetakeCourseChargeSimpleAction` tạo charge cho `approved` retake registration mà chưa có charge.
Logic này gộp vào DNG worklist page:

- Khi admin chọn fee_type = HL, query cũng show `CourseRetakeRegistration` với `status=approved` (chưa có charge)
- Admin có thể "Tạo charge" cho những registration này → charge created → registration transitions to `payment_pending`
- Sau đó registration có charge → hiện lên worklist chính → admin push DNG

**Alternative (đơn giản hơn):** Nếu academic flow đã auto-create charge khi approve, thì storeSimple không cần thiết. Cần verify current flow.

### 2. `Payments/Create` (manual DNG page) → Removed

Trang `/finance/payments/create` bị loại bỏ hoàn toàn.
Mọi DNG creation đi qua DNG Worklist (charge-linked, enforced).
API route `POST /api/v1/finance/dng/payment-requests` cũng deprecated.

## Non-Goals

- Không sửa settlement logic (apply allocation giữ nguyên)
- Không sửa webhook allocation flow (đã dùng pivot khi pivot exists)
- Không tách menu structure (ngoài scope)

## Risks

1. **Balance N+1**: `FinanceCharge::getBalanceAttribute()` calls SettlementService per row → query phải dùng subquery aggregate
2. **Retake registration state**: charges tạo khi registration approved, nhưng page mới show charges regardless. Cần verify retake flow vẫn đúng.
3. **Cancel old DNG race condition**: concurrent requests pushing same fee_type/student → need lockForUpdate
4. **Payments/Create removal**: any external link pointing to `/finance/payments/create` (e.g., from student profile, settlement page prefill links) → must redirect or update

## Resolved Questions

1. ~~`storeSimple` — giữ nguyên hay gộp?~~ → **Gộp** vào DNG worklist, hỗ trợ tạo charge cho approved retake registrations
2. ~~`Payments/Create` — enforce charge linking?~~ → **Loại bỏ hoàn toàn**, mọi DNG qua unified page
