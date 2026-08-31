---
phase: 8
title: "Dead code, comment, tài liệu (P-08, P-09, P-11)"
status: pending
priority: P3
effort: "2-2.5d"
dependencies: [2, 3, 4, 5, 6, 7]
---

# Phase 8: Dead code, comment, tài liệu (P-08, P-09, P-11)

## Overview

Dọn dẹp cuối: xóa dead code đã xác nhận zero-callers, sửa comment sai sự thật,
cập nhật tài liệu audit/ADR, ghi nhận quyết định giữ events, và chạy sweep
regression toàn diện.

## Requirements

- Functional: xóa đúng những gì đã xác nhận dead; không xóa gì đang có caller.
- Functional: tài liệu phản ánh hiện trạng sau remediation.
- Non-functional: phải chạy SAU tất cả phase khác (xóa có thể đụng usages mà
  các phase trước đã thay đổi).

## Related Code Files

- Delete:
  - `app/Modules/Finance/Actions/CancelFinanceObligationAction.php` (+ binding `FinanceServiceProvider.php:96` + interface `FinanceObligationCancellationContract` nếu không còn implementer/user khác — grep trước)
  - `app/Modules/Finance/Actions/CreateRetakeCourseChargeSimpleAction.php` —
    **không phải "vài test reference"** (red-team — Critical): 11 test file + 2
    shared helper phụ thuộc (`tests/Feature/Academic/ExamResit/helpers.php:56`,
    `tests/Feature/Finance/Operations/exam_resit_due_helpers.php:11`,
    `CancelRetakeCourseRegistrationActionTest.php:17,237`,
    `CancelExamResitAttemptActionTest.php:22,267`, ExamResitChargeActionTest,
    ExamResitAttemptControllerTest, ListExamResitAttemptsQueryTest,
    SyncPaidExamResitAttemptsActionTest, CancellationOperationTest,
    RetakeResitHqWorklistTest, FeeMonitorRetakeResitInferenceTest). Bắt buộc:
  - `app/Modules/Finance/Actions/CreateExamResitChargeSimpleAction.php` — như trên.
  - `GenerateEgcChargesAction::CHARGE_AMOUNT` (hằng deprecated, :21-24)
- Modify:
  - `CreateRetakeCourseRegistrationAction.php:27-29` — comment "later through the DNG/fee worklist" → mô tả tạo synchronous qua `FinanceIntakeContract`.
- Create (test fixture migration): trait/factory test
  `tests/Feature/Support/CreatesRetakeResitCharges.php` (hoặc tương đương theo
  convention test hiện có) — wrap `FinanceIntakeContract` path thật để thay
  thế vai trò fixture của 2 Simple action; migrate 11 test file + 2 helper
  sang factory mới TRƯỚC khi xóa action. (Phương án ADR-0026 §5.5 "convert
  thành idempotent reconcile command" bị loại — test không cần production
  command, chỉ cần setup factory.)
- Docs:
  - `docs/audit-academic-finance.md` — cập nhật trạng thái từng P-xx (fixed / accepted-as-is kèm link plan này).
  - ADR: RULE-09 revision (retake 2 action) + COURSES-scope semester-invoice handling (Phase 6) — ADR mới hoặc bổ sung ADR-0026 tùy quy ước `docs/adr/`.
  - P-09 (Q-02): ghi chú grace 14 ngày là reference data informational trong runbook thi lại phù hợp dưới `docs/features/`.

## Implementation Steps

1. Với từng item xóa: grep caller cuối cùng; nếu còn caller (do phase trước tạo) → bỏ khỏi danh sách xóa, ghi lý do.
2. Tạo test factory + migrate 11 test file + 2 helper; chạy suite touched xanh.
3. Xóa 2 Simple action + `CancelFinanceObligationAction` + hằng; xóa test của item; pint.
4. Sửa comment P-11.
5. Cập nhật tài liệu; `./scripts/check-docs.sh` + `./scripts/check-docs-freshness.sh`.
6. Sweep cuối: `./scripts/dev.sh artisan test --compact --filter=Retake|Resit|Defer|Egc|Installment|Dng`; `composer exec pint -- --dirty --format agent`.

## Todo

- [ ] Test factory tạo + 11 file + 2 helper migrated, suite touched xanh
- [ ] Dead code xóa sạch (hoặc ghi lý do giữ)
- [ ] Comment P-11 sửa
- [ ] Audit doc + ADR + runbook cập nhật
- [ ] Sweep test + pint + check-docs xanh

## Success Criteria

- [ ] `grep -r "CancelFinanceObligationAction\|ChargeSimpleAction\|CHARGE_AMOUNT" app/` trả 0 (hoặc ghi chú lý do).
- [ ] Toàn bộ filter test xanh; docs check xanh.
- [ ] `docs/audit-academic-finance.md` phản ánh trạng thái remediation.

## Risk Assessment

Thấp. Quyết định đã chốt trong plan.md Scope decisions: 3 events Finance
KHÔNG listener được GIỮ (dispatch từ code tiền sống; P-01 fix bằng syncer trực
tiếp; Q-09 không cần alert) — chỉ ghi chú docblock, không xóa.
