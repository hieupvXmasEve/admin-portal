---
phase: 3
title: "Gate thi lại dùng live settlement (P-03, P-10)"
status: completed
priority: P1
effort: "1d"
dependencies: [1, 2]
---

# Phase 3: Gate thi lại dùng live settlement (P-03, P-10)

## Overview

`ScheduleExamResitAttemptAction.php:154-161` và
`CompleteExamResitAttemptAction.php:196-224` đều đọc cờ cache `hq_fee_status`
trong phương thức `resolvePaymentGate()` riêng của từng action — mâu thuẫn
ADR-0026 ("projection không bao giờ là điều kiện duy nhất của hard gate") và
docblock của Complete tự nhận "canonical-derived paid state". Gộp 2 bản sao
thành 1 helper đọc live ledger.

## Requirements

- Functional: 2 gate chuyển sang live Finance settlement check qua
  `AcademicObligationSettlement` — API đã xác minh:
  `forExamResit(ExamResitAttempt): ObligationSettlementResult` +
  `isExamResitSettled(...)` (`app/Modules/Academic/Support/AcademicObligationSettlement.php:24-49`,
  wrap `ObligationSettlementReader` contract).
- Functional: giữ nguyên escape hatch `allow_unpaid_sitting` (snapshot lúc tạo
  attempt, `CreateExamResitAttemptAction.php:206`) + reason bắt buộc có stamp
  staff + timestamp.
- Non-functional: thông báo lỗi giữ nguyên "Lệ phí thi lại chưa thanh toán…";
  `hq_fee_status` chỉ còn là projection hiển thị.

## Architecture

Create 1 final class dùng chung, ví dụ
`app/Modules/Academic/Delivery/Support/ResitFeeGate.php` (đặt theo nơi module
đang gom support class — tra trước khi tạo):

```
ResitFeeGate::evaluate(ExamResitAttempt $attempt): ObligationSettlementResult
  - đọc live settlement qua AcademicObligationSettlement::forExamResit()
  - caller tự quyết: settled → allow
    chưa settled && snapshot allow_unpaid_sitting → allow-with-reason (bắt buộc reason, stamp)
    chưa settled && !allow_unpaid_sitting → deny (message hiện có)

```
Không tạo VO mới (red-team): helper trả về `ObligationSettlementResult`
(DTO contract hiện có); nhánh allow/deny/reason nằm ở caller.

Cả 2 action thay `resolvePaymentGate()` riêng bằng helper này; xóa 2 bản sao.

## Related Code Files

- Create: `app/Modules/Academic/Delivery/Support/ResitFeeGate.php` (tên/thư mục bám convention module).
- Modify: `ScheduleExamResitAttemptAction.php`, `CompleteExamResitAttemptAction.php` (gọi helper; sửa docblock Complete:37-38 cho khớp hành vi mới).
- Delete: 2 phương thức `resolvePaymentGate()` trùng lặp.

## Implementation Steps

1. Trace pattern đọc live ledger trong `AutoEnrollRetakeCourseAction` + `AcademicObligationSettlement` để dùng đúng contract.
2. Tạo `ResitFeeGate` + unit test riêng (settled / unpaid+blocked / unpaid+allowed+reason).
3. Thay 2 gates; giữ message + stamp hành vi.
4. Test baseline P-03 (Phase 1) → xanh; thêm test "lost webhook nhưng đã reconcile (Phase 2) → schedule + complete thành công".

## Todo

- [x] Helper tạo + unit test xanh
- [x] 2 action dùng helper, bản sao xóa
- [x] Test baseline P-03 xanh + test end-to-end với Phase 2

## Success Criteria

- [x] Sinh viên đã trả qua đường không-webhook (sau Phase 2) xếp lịch và ghi kết quả thi không cần staff.
- [x] Unpaid không hatch → vẫn bị chặn với message cũ; unpaid + hatch → bắt buộc reason.
- [x] `grep resolvePaymentGate` trả 0 kết quả.

## Risk Assessment

- **Trung bình:** live check mỗi lần schedule/complete thêm 1 query ledger — chấp nhận được (tần suất thấp, staff-initiated).
- **Thấp:** hành vi đổi với sinh viên đã trả nhưng cache chưa cập nhật (fix đúng chủ đích); với sinh viên THẬT SỰ chưa trả — không đổi.
