---
phase: 2
title: "DNG fallback đồng bộ trạng thái Academic (P-01)"
status: completed
priority: P1
effort: "1d"
dependencies: [1]
---

# Phase 2: DNG fallback đồng bộ trạng thái Academic (P-01)

## Overview

Webhook DNG là đường duy nhất hiện gọi `RetakeRegistrationPaymentSyncer` /
`ExamResitAttemptPaymentSyncer` (`DngWebhookService.php:436,455`). Job reconcile
15 phút — sinh ra để bắt webhook mất — cùng 3 action bridge/capture khác đều
không gọi syncer: tiền về Finance nhưng sinh viên kẹt `unpaid` trong Academic.
Bổ sung lời gọi syncer vào cả 4 đường.

## Requirements

- Functional: mọi đường Finance ghi nhận tiền thật cho charge loại
  retake/resit phải kích hoạt sync projection Academic tương ứng.
- Non-functional: syncer idempotent (derive từ ledger), gọi thừa vô hại;
  không đổi logic settlement hiện có.

## Architecture

- Pattern mẫu (webhook): sau khi tiền được ghi nhận, gọi
  `$this->retakeRegistrationPaymentSyncer->runForStudent((int) $request->student_id)`
  / `$this->examResitAttemptPaymentSyncer->runForStudent(...)`.
- Syncer là contract `App\Shared\Contracts\Academic\*` — đúng biên ADR-0026
  (Finance viết projection Academic qua contract, không đụng model Eloquent
  chéo module).
- Không thêm listener sự kiện — gọi trực tiếp (scope decision: giữ 3 events
  không listener nguyên trạng).
- **Cách ly batch (red-team):** syncer ở đường webhook ném `RuntimeException`
  khi `$result['failed'] > 0` (`DngWebhookService.php:436-438,455-457`). Trong
  job reconcile xử lý NHIỀU payment, gọi thẳng pattern đó khiến 1 student lỗi
  rollback settlement của các student khác. Bắt buộc: bọc mỗi lời gọi syncer
  per-student try/catch → ghi exception row, tiếp tục; KHÔNG để 1 projection
  failure lật ngược sổ cái đã settle của student khác. Đường webhook giữ
  nguyên hành vi throw.

## Related Code Files

- Modify:
  - `app/Modules/Finance/Dng/Services/DngReconciliationService.php` — DI 2 syncer, gọi sau khi settle installment của charge có source retake/resit.
  - `app/Modules/Finance/Dng/Actions/ResolveDngReceiptExceptionAction.php` (đường dẫn tra theo tên class) — gọi sau khi resolve exception thành payment.
  - `app/Modules/Finance/Dng/Actions/BridgePaidDngRequestsForChargeAction.php` — gọi sau khi bridge request đã trả.
  - `app/Modules/Finance/Dng/Actions/CaptureDngProviderReceiptAction.php` — gọi sau khi book receipt. ⚠️ plan `260818-2139` cũng đụng file này — coordinate trước.
- Modify: DI wiring nếu constructor dính service provider.

## Implementation Steps

1. Coordinate với trạng thái plan `260818-2139` (rebase hoặc chờ land).
2. `DngReconciliationService`: inject 2 syncer; xác định điểm settlement hoàn tất trong thân; gọi `runForStudent` với student của charge BÊN TRONG try/catch per-student (syncer throw → log + exception row, không rollback batch); chỉ gọi khi charge có `source_kind` retake/resit (tránh gọi thừa).
3. Làm tương tự cho 3 action capture/bridge/resolve-exception, đặt lời gọi sau commit tiền.
4. Bật 2 test baseline P-01 (Phase 1) → xanh.
5. Thêm test: gọi syncer KHÔNG chạy khi charge không phải retake/resit.

## Todo

- [x] 4 đường sync được gọi
- [x] Test baseline P-01 xanh
- [x] Test guard không-sync cho charge loại khác xanh

## Success Criteria

- [x] Kịch bản "webhook mất → job reconcile chạy → Academic `paid` → retake tự xếp lớp / resit xếp lịch được" xanh end-to-end.
- [x] Không test DNG hiện có nào đỏ.

## Risk Assessment

- **Trung bình:** `CaptureDngProviderReceiptAction` là file dùng chung với
  `260818-2139` → mitigate: coordinate + rebase trước khi sửa.
- **Thấp (đã mitigate):** batch abort do syncer throw → try/catch per-student ở Architecture. Gọi syncer trong transaction — syncer viết projection, nếu gọi
  trong cùng DB transaction thì rollback an toàn; xác nhận syncer không tự
  mở transaction lồng gây deadlock (đọc thân syncer trước).
