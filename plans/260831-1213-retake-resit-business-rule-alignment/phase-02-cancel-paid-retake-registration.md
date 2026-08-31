---
title: "Hủy đã trả tiền: retake chưa xếp lớp + thi lại 2 action (mất tiền / giữ tiền)"
status: done
priority: P1
effort: "2.5d"
dependencies: []
---

# Phase 2: Hủy đã trả tiền — retake chưa xếp lớp + hai action hủy thi lại

## Overview

Hai quyết định của owner (validation session 1):
1. **Học lại `paid` chưa gắn lớp** → hủy được, tiền trở thành unapplied balance dùng cho phí sau (quy tắc #4, #6).
2. **Thi lại đã trả tiền khi hủy**: nhân viên phải có **2 action riêng biệt** — (a) hủy với tiền bị MẤT (coi như bỏ thi, forfeit), (b) hủy với tiền ĐƯỢC LƯU dùng cho phí phát sinh sau (release to balance).

**Điều chỉnh sau red-team:** cơ chế release tiền về unapplied balance tồn tại trong paid-void path — `VoidFinanceChargeAction::releaseLinePayments` (dòng 77-82) + `autoReallocate=false` (dòng 163-172). Việc hủy-retake có thể chỉ cần mở cổng Academic; việc thi-lại-giữ-tiền thì cần disposition mới được chọn theo action (không phải theo `$paid` bool) — xem Architecture.

## Requirements

- Functional:
  - **Retake:** hủy được từ `paid` khi chưa gắn lớp; tiền → unapplied balance; đã gắn lớp → chặn. Chưa trả → void như hiện tại.
  - **Resit (thi lại):** khi đơn đã trả tiền, màn hình hủy có 2 lựa chọn rõ ràng:
    * "Hủy — mất phí" (forfeit): hành vi hiện tại `KeptPaidNoRefund`, charge giữ nguyên như doanh thu.
    * "Hủy — lưu phí dùng sau" (keep-for-later): charge void + tiền đã thu → unapplied balance.
  - Chưa trả tiền → void như hiện tại (không đổi).
  - UI phải hiển thị rõ hệ quả tiền của từng lựa chọn; không hoàn tiền về DNG trong cả hai.
- Non-functional: disposition được chọn **từ action nhân viên chọn** (tham số tường minh trên handoff), không suy từ `$paid`; mọi mutation tiền qua Finance Cancellation Operation + `SettlementMutationGuard`.

## Architecture

**Retake (đơn giản):** theo red-team, paid-void path hiện có đã release về balance → thay đổi nằm ở Academic: mở `STATUS_PAID` trong `CANCELLABLE_STATUSES` (guard `course_registration_id === null`), giữ handoff + `paid_void_reason` hiện có.

**Resit hai action:** hiện `CancelExamResitAttemptAction` chỉ có một paid path (`exam_resit_cancelled_paid_no_refund`). Cần:
1. Handoff nhận tham số tường minh `fee_outcome: forfeit | keep_for_later` (đặt tên mới `paid_void_reason` thứ hai, ví dụ `exam_resit_cancelled_paid_keep_for_later`).
2. `ProcessFinanceCancellationOperationAction::resolveFeeDisposition` hiện chỉ đọc `$paid` bool (`:518-529`) — mở rộng nhận `paid_void_reason`/`fee_outcome` để trả `KeptPaidNoRefund` (forfeit) hoặc `PaidReleaseToBalance` (keep-for-later, enum case mới). `reconcileLatePayment` (`:207-250`) cũng phải tham số hóa tương tự.
3. **3 guard idempotency keyed trên `KeptPaidNoRefund` phải cập nhật** để coi `PaidReleaseToBalance` là terminal-paid: `ResumeFinanceCancellationOnPaidEvidenceAction.php:69-77`, `ProcessFinanceCancellationOperationAction.php:211-214`, `:232-235`. Làm không đủ → re-dispatch → double-release (red-team Critical).
4. Mapping Academic: `CompleteFinanceCancellationOperationAction.php:140-151` (`match` + `default`) — thêm arm cho disposition mới + hằng số tương ứng trên `ExamResitAttempt` (mirror `CANCELLATION_FEE_*` hiện có).
5. Retake giữ single action (quy tắc #4 chốt sẵn tiền → balance).

## Bước verification bắt buộc (trước khi coi là xong)

Red-team phát hiện mô tả hiện trạng trong bản plan trước đây là SAI, nên phase này bắt buộc kiểm chứng hành vi thật trước khi merge:

1. **Instrument paid-void path** (local/test): tạo retake `paid` chưa-link → cancel end-to-end → assert `Payment.unapplied_amount` tăng đúng số tiền đã phân phối và invoice line voided. Nếu assertion FAIL → DỪNG, chốt lại với owner bằng ADR trước khi code tiếp (quyết định owner validation session 1).
2. Test resit hai action: forfeit → tiền KHÔNG thành balance (charge giữ nguyên); keep-for-later → balance tăng. Bắn paid-evidence signal 2 lần → chỉ release 1 lần.
3. Kiểm tra mapping Academic `CompleteFinanceCancellationOperationAction.php:140-151` xử lý đúng cả retake `paid` → `cancelled` và resit hai outcome.
4. DNG lô đã `reconciled`: verify void không phá reconcile (chỉ đọc `DngPaymentRequest` sau cancel test).

## Related Code Files

- Modify: `app/Models/CourseRetakeRegistration.php` (CANCELLABLE_STATUSES + guard)
- Modify: `app/Modules/Academic/Delivery/Actions/CancelRetakeCourseRegistrationAction.php` (message lỗi case paid)
- Modify: `app/Modules/Academic/Delivery/Http/Web/RetakeCourseRegistrationController.php` (cảnh báo UI)
- Modify: `app/Modules/Academic/Delivery/Actions/CancelExamResitAttemptAction.php` (tham số fee_outcome) + `app/Modules/Academic/Delivery/Http/Requests/ExamResit/CancelExamResitRequest.php` (2 lựa chọn)
- Modify: `app/Modules/Finance/Actions/ProcessFinanceCancellationOperationAction.php` (`resolveFeeDisposition` + `reconcileLatePayment` tham số hóa theo fee_outcome)
- Modify: `app/Shared/Contracts/Finance/Enums/FinanceCancellationFeeDisposition.php` (case `PaidReleaseToBalance`)
- Modify: `app/Modules/Finance/Actions/ResumeFinanceCancellationOnPaidEvidenceAction.php` (guard terminal-paid)
- Modify: `app/Modules/Academic/Actions/CompleteFinanceCancellationOperationAction.php` (mapping disposition mới)
- Create: `docs/adr/00xx-cancellation-paid-fee-outcome-forfeit-vs-keep-for-later.md`
- Test: cancellation hiện có + case mới.

## Implementation Steps

1. Retake: mở `STATUS_PAID` trong `CANCELLABLE_STATUSES`; guard chưa-link; UI cảnh báo.
2. Finance: enum case `PaidReleaseToBalance`; `resolveFeeDisposition` + `reconcileLatePayment` nhận fee_outcome; cập nhật 3 guard idempotency.
3. Academic resit: 2 action hủy (forfeit / keep-for-later) qua handoff; mapping completion.
4. Chạy verification steps 1-4; chỉ merge khi assertions PASS.
5. ADR ghi quyết định.

## Success Criteria

- [ ] Retake `paid` chưa-link hủy được end-to-end; assert `Payment.unapplied_amount` tăng; không giao dịch hoàn.
- [ ] Resit paid: action "mất phí" giữ charge (tiền KHÔNG thành balance); action "lưu phí dùng sau" void charge + balance tăng.
- [ ] Paid-evidence bắn 2 lần → chỉ 1 lần release (test idempotency).
- [ ] Retake `paid` đã-link → chặn; case chưa trả tiền cả hai luồng như cũ.

## Risk Assessment

- Verification step 1 FAIL → DỪNG + ADR với owner (không tự thêm thiết kế mới — quyết định owner).
- Disposition mới là Critical blast radius: cả 3 guard idempotency + reconcileLatePayment phải cùng change-set; thiếu một → double-release. Test idempotency là acceptance bắt buộc.
- UI 2 nút hủy phải hiển thị hệ quả tiền rõ ràng để nhân viên không chọn nhầm.

