---
phase: 4
title: "Disposition hủy có kiểu + retake 2 action (Q-04, Q-07, P-04)"
status: completed
priority: P1
effort: "1.5d"
dependencies: [1]
---

# Phase 4: Disposition hủy có kiểu + retake 2 action (Q-04, Q-07, P-04)

## Overview

Hiện trạng (sau plan `260831-1213`): `CancelExamResitAttemptAction` đã có
`fee_outcome` typed (forfeit | keep_for_later) và đưa vào handoff payload, nhưng
`ProcessFinanceCancellationOperationAction.php:566-573` VẪN quyết disposition
bằng `str_contains(paidVoidReason, 'keep_for_later')` — typo là đủ để lật kết
quả tiền. Đồng thời `CancelRetakeCourseRegistrationAction` vẫn hardcode
keep-for-later (RULE-09 cũ); owner chốt retake cũng phải có 2 action tường minh.

## Requirements

- Functional: handoff payload mang trường typed; Finance quyết disposition từ
  trường typed, KHÔNG từ reason text.
- Functional: retake cancel đã trả có 2 lựa chọn giữ/mất phí, không còn hardcode.
- Non-functional: handoff đang chờ xử lý tạo TRƯỚC thay đổi vẫn xử lý đúng
  (fallback có log, không âm thầm).

## Architecture

Academic side:
- `CancelRetakeCourseRegistrationAction`: thêm param `fee_outcome` giống resit
  (bám constants `FEE_OUTCOME_FORFEIT|KEEP_FOR_LATER` của resit); xóa hardcode
  "Owner rule #4" (:51-53); FormRequest validate giá trị.
- **Bổ sung acknowledgement (red-team — Critical):** đường hủy retake hiện
  KHÔNG có logic xác nhận nào (`CancelRetakeCourseRegistrationAction` không có
  acknowledgement branch; `CancelRetakeCourseRequest.php:11-20` chỉ require
  `reason`). Khi mở `forfeit`, bắt buộc THÊM gate xác nhận "no refund" cho
  retake — mirror `CancelExamResitAttemptAction::assertCancellationAcknowledgements()`
  (RULE-11) — không phải "giữ nguyên" như giả định ban đầu.
- **Bỏ silent default trên resit (red-team):** `feeOutcome()` hiện
  `(string)($data['fee_outcome'] ?? self::FEE_OUTCOME_FORFEIT)` — thiếu field
  thì NGẦM ĐỊNH mất phí, trái quyết định "2 action tường minh". Sửa resit:
  thiếu `fee_outcome` → validation error; retake làm đúng ngay từ đầu.
- Handoff payload: thêm `fee_outcome` cho retake (resit đã có).

Finance side (`ProcessFinanceCancellationOperationAction`):
- `resolveFeeDisposition` đọc `fee_outcome` typed từ payload →
  `PaidReleaseToBalance` | `KeptPaidNoRefund`.
- Fallback: khi payload KHÔNG có `fee_outcome` (handoff cũ còn tồn đọng) →
  dùng parse text hiện có + `Log::warning` — xóa fallback sau khi tồn đọng về 0
  (ghi chú theo dõi).
- `ResumeFinanceCancellationOnPaidEvidenceAction` re-dispatch theo disposition
  typed (đã nhận `PaidReleaseToBalance` từ plan 260831 — xác nhận lại đường chạy).

## Related Code Files

- Modify: `app/Modules/Academic/Delivery/Actions/CancelRetakeCourseRegistrationAction.php`
- Modify: FormRequest + màn hình staff hủy retake (mirror 2-button pattern của resit từ plan 260831 phase 2).
- Modify: `app/Modules/Finance/Actions/ProcessFinanceCancellationOperationAction.php:566-581,301-303`
- Modify: `app/Modules/Finance/.../ResumeFinanceCancellationOnPaidEvidenceAction.php` (xác nhận, sửa nếu cần)

## Implementation Steps

1. Retake: thêm `fee_outcome` (FormRequest + action + payload + UI 2 action — tái dụng component/machinery resit) + thêm acknowledgement "no refund" cho paid-evidence (mirror resit).
2. Resit: bỏ silent default forfeit — thiếu `fee_outcome` → validation error.
3. Finance: `resolveFeeDisposition` ưu tiên typed field; fallback text-match chỉ khi field vắng + warning log.
4. Cập nhật idempotency guards nếu có nhánh chưa nhận `PaidReleaseToBalance` cho retake source.
5. Test baseline P-04 → xanh: assert typed field thắng khi reason text và typed field KHÔNG khớp (handoff fabricated hợp lệ cho backward-compat; `paid_void_reason` thực tế là constant-derived — typo runtime không xảy ra, đây là bảo vệ hồi quy tương lai). Thêm test: handoff cũ (không typed field) vẫn xử lý đúng + log warning.

## Todo

- [x] Retake 2 action (backend + UI)
- [x] Finance đọc typed field, fallback có log
- [x] Test baseline P-04 xanh + test backward-compat handoff cũ

## Success Criteria

- [x] Typo trong reason text không thể đổi kết quả tiền.
- [x] Hủy retake đã trả: staff chọn giữ hoặc mất phí; cả 2 action chạy đúng qua FinanceCancellationOperation.
- [x] Không handout tồn đọng nào xử lý sai trong quá trình chuyển tiếp.

## Risk Assessment

- **Cao (tiền):** đây là đường mutate tiền đã thu. Mọi thay đổi đi qua
  FinanceCancellationOperation hiện có, tuân `SettlementMutationGuard`; không
  viết mới logic void/release — chỉ thay nguồn quyết định (typed vs text).
- **Trung bình:** UI retake mới — mirror đúng pattern resit đã ship để tránh
  2 convention.
- **Assumption ghi rõ:** default khi UI không gửi `fee_outcome` = từ chối
  (validation), không default ngầm — 2 action phải là lựa chọn tường minh.
