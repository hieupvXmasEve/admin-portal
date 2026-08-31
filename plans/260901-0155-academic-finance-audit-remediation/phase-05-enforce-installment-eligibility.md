---
phase: 5
title: "Chặn trả góp theo registry (P-05, Q-06)"
status: completed
priority: P2
effort: "0.5d"
dependencies: [1]
---

# Phase 5: Chặn trả góp theo registry (P-05, Q-06)

## Overview

`ObligationTypeRegistry.php:184,199` đánh dấu `supportsInstallments = false`
cho retake_fee và exam_resit_fee, nhưng không ai đọc cờ này:
`SplitChargeIntoInstallmentsAction::assertChargeEligible` (:34-90) chỉ check
status/amount, controller (:178-183) chỉ authorize policy. Staff split được phí
mà cấu hình cấm. Owner chốt: cờ đúng, thiếu enforcement.

## Requirements

- Functional: split bị từ chối bằng ValidationException khi obligation type của
  charge có `supportsInstallments === false`.
- Non-functional: message lỗi tiếng Việt nhất quán với style lỗi hiện có;
  học phí/loại khác vẫn split bình thường.

## Architecture

Trong `assertChargeEligible` (action layer — đúng chỗ vì mọi caller đi qua),
resolve obligation type của charge từ registry và check cờ. Không thêm check
riêng ở controller/policy (một nơi, DRY).

## Related Code Files

- Modify: `app/Modules/Finance/Actions/SplitChargeIntoInstallmentsAction.php:34-90`
- Optional: front-end ẩn/vô hiệu nút split cho loại không hỗ trợ (chỉ khi charge
  payload đã expose đủ type; nếu cần thêm prop thì ghi NOT-in-scope và làm sau).

## Implementation Steps

1. Thêm check registry vào `assertChargeEligible` trước các check hiện có.
2. Test baseline P-05 → xanh; thêm testPositive: tuition charge vẫn split được.
3. Chạy file-scoped lint/pint.

## Todo

- [x] Registry check trong split path
- [x] Test split retake/resit bị chặn + tuition vẫn được

## Success Criteria

- [x] `SplitChargeIntoInstallmentsAction` từ chối retake/resit fee với lỗi rõ ràng.
- [ ] `SplitChargeIntoInstallmentsActionTest` NET-split case still fails `settlement_position.missing_currency` (tuition split itself is allowed; fixture/settlement cache, not the new registry gate).

## Risk Assessment

Thấp — thêm 1 điều kiện chặn trên đường staff-initiated. Rủi ro duy nhất:
charge tồn tại đã bị split sai trước đây → không đụng lại (reconcile path
`ReconcileChargeInstallmentsAction` ngoài scope); ghi nhận nếu phát hiện data
thực đã có split sai.
