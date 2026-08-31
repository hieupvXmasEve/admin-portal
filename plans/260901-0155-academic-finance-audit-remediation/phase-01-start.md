---
phase: 1
title: "Baseline regression test chứng minh defect"
status: completed
priority: P1
effort: "0.5d"
dependencies: []
---

# Phase 1: Baseline regression test chứng minh defect

## Overview

Viết test đỏ (characterization) chứng minh từng defect của audit trên code hiện
tại, trước khi sửa bất cứ thứ gì. Các test này là backbone đỏ→xanh cho Phase 2–4, 5, 7
và bảo đảm không fix "trên giấy".

## Requirements

- Functional: mỗi defect có ít nhất 1 test fail đúng vì defect (không fail vì setup).
- Non-functional: test đặt đúng namespace/thư mục module hiện có
  (`tests/Modules/Finance/...`, `tests/Modules/Academic/...` — bám theo pattern
  test của plan `260831-1213`).

## Architecture

Không đổi production code. Chỉ thêm test. Mỗi test mô phỏng kịch bản audit:

| Defect | Kịch bản test | Kết quả mong đợi hiện tại (ĐỎ) | Xanh khi |
|---|---|---|---|
| P-01 | Retake charge được settle qua `DngReconciliationService` (không webhook) | Bản ghi Academic vẫn `unpaid` | Phase 2 |
| P-01 | `CaptureDngProviderReceiptAction` book receipt cho resit charge | `hq_fee_status` không đổi | Phase 2 |
| P-03 | Resit đã trả (ledger settled, không webhook) → `ScheduleExamResitAttemptAction` | Bị chặn "Lệ phí thi lại chưa thanh toán" | Phase 3 |
| P-04 | Handoff có `fee_outcome` typed + `paid_void_reason` KHÔNG khớp → disposition | Quyết từ reason text thay vì typed field | Phase 4 |
| P-05 | Split installment trên retake fee charge | Được chấp nhận (sai) | Phase 5 |
| P-02 | EGC retake discount khi block fee đích ≠ 15M | Discount vẫn 7,500,000 (sai 50%) | Phase 7 |
| P-12 | Major-entry credit khi student đã trả 2 level (30M) chưa tiêu | Credit chỉ 15M phẳng | Phase 7 |

Ghi chú red-team:

- 2 test defer (Q-08) được **chuyển sang Phase 6** — không thể viết
  characterization test đáng tin cho code chưa được trace
  (`DeferCaseService::processFeePolicy()`). Phase 1 chỉ gồm 7 defect có hành vi
  hiện tại đã biết.
- P-04 không phải typo runtime — `paid_void_reason` được derive từ constants
  (`CancelExamResitAttemptAction::paidVoidReason()`), không phải staff
  free-text. Test baseline P-04 assert **typed field là nguồn quyết định** khi
  reason text và typed field không khớp (handoff fabricated hợp lệ cho
  backward-compat test); kịch bản typo chỉ là rủi ro hồi quy tương lai.

## Related Code Files

- Create: test files theo pattern hiện có trong `tests/` (tra 1 test mẫu của
  `DngReconciliationService`, `SplitChargeIntoInstallmentsAction`,
  `ApplyEgcRetakeDiscountAction` để bám convention).
- Modify: không.

## Implementation Steps

1. Xác định thư mục/namespace test hiện có cho từng action (glob theo tên class).
2. Viết 7 test baseline theo bảng trên; fixture dùng seed pricing, không đụng DB production.
3. Chạy từng test, xác nhận FAIL đúng lý do (assertion trúng hiện trạng sai, không lỗi setup).
4. Đánh dấu `@group audit-baseline` để chạy chọn lọc ở phase sau.

## Todo

- [x] 7 test baseline viết xong, mỗi test fail đúng vì defect
- [x] Ghi log đường dẫn test từng defect vào phase file này (mục Ghi chú)

## Success Criteria

- [x] `./scripts/dev.sh artisan test --compact --group=audit-baseline` chạy được, 7 đỏ với đúng assertion.
- [x] Không file production nào đổi (`git status` sạch ngoài tests/).

## Ghi chú

Laravel 13 `artisan test` không forward `--group` (thoát 255 / file-not-found).
Chạy bằng 5 file (cùng `->group('audit-baseline')`):

```bash
./scripts/dev.sh artisan test --compact \
  tests/Feature/Finance/Dng/AuditBaselineDngAcademicSyncTest.php \
  tests/Feature/Academic/ExamResit/AuditBaselineResitLiveSettlementGateTest.php \
  tests/Feature/Finance/AuditBaselineCancellationDispositionTest.php \
  tests/Feature/Finance/AuditBaselineInstallmentEligibilityTest.php \
  tests/Feature/Finance/Egc/AuditBaselineEgcPolicyTest.php
```

Kết quả 2026-09-01: **7 failed (10 assertions)** — đúng defect, không lỗi setup.

| Defect | File | Failure |
|---|---|---|
| P-01 retake | `tests/Feature/Finance/Dng/AuditBaselineDngAcademicSyncTest.php` | `hq_fee_status` = `charge_created` (want `paid`); reconcile `backfilled=1` |
| P-01 resit | cùng file | `hq_fee_status` = `charge_created` (want `paid`) |
| P-03 | `tests/Feature/Academic/ExamResit/AuditBaselineResitLiveSettlementGateTest.php` | `Lệ phí thi lại chưa thanh toán...` dù ledger settled |
| P-04 | `tests/Feature/Finance/AuditBaselineCancellationDispositionTest.php` | `kept_paid_no_refund` (want `paid_release_to_balance`) |
| P-05 | `tests/Feature/Finance/AuditBaselineInstallmentEligibilityTest.php` | `ValidationException` not thrown — split accepted |
| P-02 | `tests/Feature/Finance/Egc/AuditBaselineEgcPolicyTest.php` | discount `7500000` (want `10000000` = 50% of 20M) |
| P-12 | cùng file | credit `15000000` (want `30000000`) |

## Risk Assessment

Thấp — chỉ thêm test. Rủi ro duy nhất: fixture chưa tái tạo được kịch bản →
ghi lại vào phase tương ứng như đầu vào bắt buộc.
