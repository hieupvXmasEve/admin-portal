---
phase: 6
title: "Defer COURSES-scope + loại bỏ PARTIAL (Q-08, sửa theo validation)"
status: completed
priority: P1
effort: "1.5-2d"
dependencies: [1]
---

# Phase 6: Defer COURSES-scope + loại bỏ PARTIAL (Q-08, sửa theo validation)

## Overview

`ApplyDeferFinancePolicyAction.php:166-170` hiện chỉ implement FULL-scope
PRESERVE/FORFEIT; PARTIAL và COURSES-scope là "later slices" — nhưng validation
Academic (`StoreStudentActionRequest.php:100-158`, RULE-17) chấp nhận cả hai,
nghĩa là staff chọn được mà Finance bỏ qua âm thầm.

**Validation session 1 (2026-09-01) sửa phạm vi:** owner xác nhận **loại bỏ
bảo lưu 1 phần** — defer chỉ còn 2 hình thái: bảo lưu toàn phần (PRESERVE
FULL) hoặc bảo lưu theo môn học (COURSES-scope). PARTIAL không được implement
mà bị **loại khỏi hệ thống**: validation phải TỪ CHỐI `PARTIAL` thay vì chấp
nhận rồi bỏ qua.

## Requirements

- Functional: `StoreStudentActionRequest` từ chối `PARTIAL` (kèm message rõ
  ràng); `ApplyDeferFinancePolicyAction` chuyển nhánh PARTIAL từ "later slices"
  sang explicit rejection guard.
- Functional: defer COURSES-scope settlement thật cho các charge gắn với môn
  được chọn, theo policy PRESERVE/FORFEIT áp trên tập con đó.
- Functional (validation session 1): charge KHÔNG gắn được môn được chọn
  (semester invoice/tuition) → **loại trừ khỏi settlement + log review**.
- Non-functional: RULE-16 (charge có discount → chỉ FORFEIT + reason reviewed)
  vẫn hiệu lực; không tạo unpaid forfeit debt (RULE-15).

## Architecture

- **COURSES-scope**: bộ lọc charge theo liên kết source với môn được chọn
  (retake/resit/egc_level_fee source_ref); áp policy PRESERVE/FORFEIT trên tập
  con đó. Invoice/tuition không gắn môn: loại trừ + log review (quyết định
  validation session 1).
- **PARTIAL**: xóa khỏi enumeration hợp lệ — Academic validation reject,
  Finance guard explicit rejection (không còn "silent skip").

## Related Code Files

- Modify: `app/Modules/Finance/Actions/Operations/ApplyDeferFinancePolicyAction.php`
- Modify: `app/Modules/.../DeferCaseService.php` (đường dẫn trace khi vào phase)
- Modify: `StoreStudentActionRequest.php` — reject `PARTIAL`
- Trace-first: đọc toàn bộ `DeferCaseService::processFeePolicy()` + caller.

## Implementation Steps

1. **Trace & ghi nhận**: đọc `DeferCaseService::processFeePolicy()` toàn bộ; ghi
   vào phase file này (mục Ghi chú) toán học hiện tại cho từng policy.
2. **Gate dừng**: nếu trace phát hiện hành vi PARTIAL đã tồn tại trong toán học
   hiện tại (khác giả định "bị bỏ qua") → DỪNG, báo owner trước khi xóa nhánh
   (pattern plan 260831 phase 2).
3. **Loại PARTIAL**: validation reject + Finance guard explicit rejection.
4. **COURSES-scope**: bộ lọc charge theo môn + áp policy; loại trừ invoice không
   gắn môn + log review.
5. Viết 2 characterization test (COURSES-scope hiện trạng trước fix, sau trace)
   rồi bật xanh sau implement; thêm test RULE-16 cho COURSES-scope + test
   "không tạo unpaid forfeit debt" + test "PARTIAL bị reject".

## Todo

- [x] Trace `processFeePolicy()` ghi nhận xong
- [x] PARTIAL bị loại (validation reject + Finance guard) — owner override: still delete despite 50% math
- [x] COURSES-scope settlement thật (invoice loại trừ + log review)
- [x] Test COURSES + PARTIAL-reject + guard RULE-15/16 xanh

## Ghi chú — trace 2026-09-01 (stop-gate)

`DeferCaseService::calculatePreserveAmount()` **đã có toán PARTIAL**, không phải silent skip:

| Policy | FULL-scope | COURSES-scope |
|---|---|---|
| FORFEIT | preserve = 0; `processFeePolicy` returns null | same |
| PRESERVE | preserve = sum active positive charges in semester | sum charges linked to deferred course items |
| PARTIAL | preserve = **50% of that sum** (`* 0.5`) | **50% of course-linked charges** |

`processFeePolicy()` writes `preserve_amount` then returns `null` (no credit charge created despite the method docblock).

`ApplyDeferFinancePolicyAction::isInScope()` still excludes PARTIAL and COURSES — settlement mutation (void/preserve) **không chạy** cho hai nhánh đó. PARTIAL vẫn:

1. được Academic validation chấp nhận (`StoreStudentActionRequest` `in:PRESERVE,FORFEIT,PARTIAL`);
2. được UI liệt kê (`LifecycleFormOptions`);
3. ghi `preserve_amount = 50%` vào `defer_cases`.

Plan giả định "PARTIAL bị bỏ qua". Trace trái giả định → **dừng trước khi xóa nhánh**, chờ owner.

## Success Criteria

- [x] Staff không thể chọn PARTIAL nữa; COURSES-scope sinh kết quả tiền kiểm
      chứng được trên đúng các charge gắn môn.
- [x] Defer feature tests xanh (`tests/Feature/Finance/Defer`, related Academic defer files).
## Risk Assessment

- **Trung bình:** COURSES-scope gặp charge semester-invoice không gắn môn —
  đã chốt loại trừ + log review (validation session 1); ghi vào ADR ở Phase 8.
- **Trung bình:** defer là multi-write atomic across modules (RULE-18). Giữ mọi
  mutation trong `ApplyDeferFinancePolicyAction` hiện có.

<!-- Updated: Validation Session 1 - loại bỏ PARTIAL, COURSES invoice loại trừ + log review -->
