---
phase: 1
title: "Giá học lại theo bảng giá Finance"
status: done
priority: P1
effort: "1.5d"
dependencies: []
---

# Phase 1: Giá học lại theo bảng giá Finance

## Overview

Bỏ rào cản `unit.retake_fee` khỏi luồng đăng ký học lại và đổi nguồn hiển thị giá sang bảng giá Finance, đúng quy tắc owner: giá học lại chỉ đến từ `FinancePricingCatalog`.

## Requirements

- Functional:
  - Đăng ký học lại KHÔNG còn bị chặn khi `unit.retake_fee` = 0/null, miễn là bảng giá Finance có rule active khớp.
  - Nếu bảng giá không có rule khớp → chặn tạo với thông báo yêu cầu cấu hình Pricing Operations (parity với luồng thi lại, xem `CreateExamResitAttemptAction.php:105-113`).
  - Màn hình quản lý học lại (index/create) hiển thị số tiền theo rule giá Finance sẽ áp dụng, không còn hiển thị `unit.retake_fee`.
- Non-functional: giữ idempotency intake theo source triple; không đổi contract `FinanceIntakeContract`.

## Architecture

Hiện tại: `CreateRetakeCourseRegistrationAction::run()` (dòng 56-61) chặn `unit.retake_fee <= 0`; dòng 172-173 snapshot `$unit->retake_fee` vào `CourseRetakeRegistration.retake_fee`; `FinanceIntakeContract.request()` → `RequestFinanceDebitAction` → `FinancePricingCatalog.price(FinanceIntakeData)` là nguồn tiền thật (baseline 1.500.000 VND, rule `facts_match` per-unit được ưu tiên). **Chú ý API thật:** `price()` nhận typed `FinanceIntakeData` (không phải loose facts) và khi thiếu rule **throw `RuntimeException`** (`FinancePricingCatalog.php:26, 70-77`) — không bao giờ trả null.

Thiết kế (một nguồn sự thật, sau red-team):
1. Bỏ gate `unit.retake_fee` (dòng 56-61).
2. KHÔNG thêm pre-flight resolve riêng. Giữ `FinanceIntakeContract.request()` là lần resolve duy nhất trong transaction; catch `RuntimeException` có message "No active Finance pricing catalog item" → ném `ValidationException` tiếng Việt "Chưa cấu hình giá học lại cho môn này..." (parity chính xác với resit: `CreateExamResitAttemptAction.php:104-113`).
3. **Không lưu snapshot cục bộ mới** (quyết định owner validation session 1: "lấy giá trị ở Finance đã lưu"): UI hiển thị giá đọc từ dữ liệu Finance đã lưu cho source_ref (pricing snapshot của `FinanceObligation`/`FinanceCharge`) qua contract/reader hiện có — hiển thị luôn = số tiền obligation thực tế, không có nguồn thứ hai có thể lệch.
4. UI: `RetakeCourseRegistrationController` + view create/index đọc giá từ dữ liệu Finance. Copy "Môn chưa cấu hình phí học lại" trong Unit management không còn là điều kiện học lại.
5. Kiểm tra `ListRetakeCourseEligibleStudentsQuery` có lọc theo `unit.retake_fee` không — bỏ nếu có.

## Related Code Files

- Modify: `app/Modules/Academic/Delivery/Actions/CreateRetakeCourseRegistrationAction.php` (bỏ gate `unit.retake_fee`; catch RuntimeException intake)
- Modify: `app/Modules/Academic/Delivery/Queries/ListRetakeCourseEligibleStudentsQuery.php` (bỏ lọc retake_fee nếu có)
- Modify: `app/Modules/Academic/Delivery/Http/Web/RetakeCourseRegistrationController.php` + views create/index (nguồn hiển thị giá)
- Modify: `app/Modules/Academic/Support/AcademicObligationSettlement.php` hoặc `app/Shared/Contracts/Finance/` (reader trả amount/pricing snapshot theo source_ref — dùng cái có sẵn, không tạo contract mới nếu không cần)
- Test: `tests/` — tìm test hiện có của CreateRetakeCourseRegistrationAction và cập nhật.

## Implementation Steps

1. Bỏ gate `unit.retake_fee`; bọc `FinanceIntakeContract.request()` bằng try/catch `RuntimeException` ("No active Finance pricing catalog item") → `ValidationException` tiếng Việt.
2. UI hiển thị giá: đọc amount từ dữ liệu Finance đã lưu theo source_ref; capture `FinanceIntakeResult::amount` lúc intake vào cột `retake_fee` hiện có (projection hiển thị, không tạo cột/migration mới) — `markFinanceObligationCreated($userId, float $retakeFee)` (finding C red-team).
3. Cập nhật Query/Controller/View hiển thị.
4. Cập nhật test + thêm case: môn không có `unit.retake_fee` nhưng catalog có rule per-unit → tạo OK, fee = số catalog; catalog thiếu rule → chặn với message tiếng Việt.

## Success Criteria

- [ ] Đăng ký học lại thành công với môn `unit.retake_fee = 0` nhưng catalog có rule.
- [ ] UI hiển thị giá = số tiền obligation thực tế (nguồn: dữ liệu Finance đã lưu, so khớp `finance_pricing_catalog_items`).
- [ ] Catalog thiếu rule → chặn với message rõ ràng, KHÔNG tạo registration.
- [ ] Test xanh; Pint sạch.

## Risk Assessment

- Không còn lệch snapshot-vs-charge và không có cột snapshot cục bộ: UI đọc trực tiếp dữ liệu Finance đã lưu cho source_ref.
- RuntimeException chưa bắt từ intake sẽ rollback transaction Academic (registration không tạo) — đúng hành vi mong muốn, chỉ cần chuyển thành message 422 dễ hiểu.
