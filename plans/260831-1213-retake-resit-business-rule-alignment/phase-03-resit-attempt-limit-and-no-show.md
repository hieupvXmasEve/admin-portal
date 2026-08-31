---
phase: 3
title: "Giới hạn thi lại + ghi nhận no_show"
status: done
priority: P1
effort: "1.5d"
dependencies: []
---

# Phase 3: Giới hạn thi lại + ghi nhận no_show

## Overview

Bổ sung đường ghi nhận `no_show` (thiếu duy nhất một write action) và mở khóa giới hạn thi lại để `max_attempts` hoạt động thật. Quy tắc owner #2, #8.

**Baseline thật (sau red-team — bản plan trước đây misread):**
- `IN_FLIGHT_STATUSES = [requested, approved, scheduled]` **đã tồn tại** và đã được dùng ở gate học lại (`CreateRetakeCourseRegistrationAction:118-127`).
- `no_show` **đã được wire một phần**: hằng số + cột `no_show_at` (`ExamResitAttempt.php:23,117,154`), badge "Vắng thi", filters, và các consumer Finance đã gộp no_show vào nhóm terminal/consumed (`CompleteFinanceCancellationOperationAction.php:171-172`, `AcademicFinanceChargeSourceGateway.php:365-366`, `ExamResitTimetableQuery.php:35-36`). **Chỉ thiếu write action.**
- `assertAttemptsRemaining` đã đếm lượt tiêu qua `whereNotNull('attempt_number')` (`CreateExamResitAttemptAction.php:212-214`).
- `max_attempts_snapshot` non-null, default(1) (migration `2026_06_17_100100:54`). Enforcement hiện tại là **live policy** (syllabus hiện hành tại thời điểm tạo đơn), không phải snapshot per-row.

## Requirements

- Functional:
  - Nhân viên ghi nhận được `no_show` cho đơn ở trạng thái `scheduled`. `no_show` tiêu lượt: set `attempt_number` nguyên tử bằng shared `nextAttemptNumber`.
  - `no_show` = trượt: bản ghi điểm giữ nguyên rớt; luồng học lại mở (gate học lại chấp nhận bản ghi có resit `completed` HOẶC `no_show`).
  - `no_show` KHÔNG cho tạo đơn thi lại thứ 2 khi lượt đã tiêu >= `max_attempts` hiện hành (hiện tại = 1).
  - Đơn no_show **mất phí** (forfeit, quyết định owner validation session 1): đã trả → tiền không chuyển thành dư; chưa trả → vẫn phải trả (grace policy). Khác với HỦY trước khi thi: chưa trả → void; đã trả → 2 lựa chọn do nhân viên chọn (xem Phase 2).
  - Endpoint mark-no-show phải có route gate `can:` riêng (mọi exam-resit route hiện có `can:` — `app/Modules/Academic/routes/web.php:625-660`). Đề xuất: `mark_no_show_exam_resit` hoặc tái dụng `complete_exam_resit`.
  - Ghi audit: actor + timestamp + reason. Idempotent: chỉ transition từ `scheduled`; gọi lần 2 → lỗi/no-op, KHÔNG tiêu thêm lượt.
  - **Một định nghĩa consumed duy nhất**: `attempt_number IS NOT NULL`. Cấm định nghĩa status-based song song.

## Architecture

Thiết kế tối thiểu:
1. `MarkExamResitAttemptNoShowAction` (mới): từ `scheduled` → `no_show`, `no_show_at = now()`, `attempt_number = nextAttemptNumber(...)` (dùng lại helper của `CompleteExamResitAttemptAction.php:239-249` — extract shared nếu cần), snapshot `result_snapshot` với `outcome = 'no_show'`. KHÔNG đụng `academic_records`.
2. Block-list tạo đơn: trong `assertRecordCanEnterExamResit` (`CreateExamResitAttemptAction.php:150-158`), thay `IN_FLIGHT_OR_CONSUMED_STATUSES` (chứa `completed`) bằng `IN_FLIGHT_STATUSES` + `assertAttemptsRemaining` là giới hạn duy nhất. Định nghĩa lại `IN_FLIGHT_OR_CONSUMED_STATUSES` hoặc bỏ nếu không còn consumer (grep trước khi xóa).
3. Gate học lại (`CreateRetakeCourseRegistrationAction:102-113`): đổi từ "có completed resit" thành "resit path exhausted" = có đơn `completed` hoặc `no_show`.
4. UI: nút "Vắng thi" phân biệt rõ với hủy (hủy = chưa thi, xử lý phí; no_show = đã có lịch, không dự thi, giữ phí), route với `can:`, xác nhận 2 bước.

## Related Code Files

- Modify: `app/Models/ExamResitAttempt.php` (định nghĩa lại/bỏ `IN_FLIGHT_OR_CONSUMED_STATUSES`; helper shared nếu cần)
- Modify: `app/Modules/Academic/Delivery/Actions/CreateExamResitAttemptAction.php` (block-list = IN_FLIGHT; limit = assertAttemptsRemaining)
- Create: `app/Modules/Academic/Delivery/Actions/MarkExamResitAttemptNoShowAction.php`
- Modify: `app/Modules/Academic/Delivery/Http/Web/ExamResitAttemptController.php` + request (endpoint mark-no-show, `can:` gate, audit actor/reason)
- Modify: `app/Modules/Academic/routes/web.php` (route mới + permission)
- Modify: `app/Modules/Academic/Delivery/Actions/CreateRetakeCourseRegistrationAction.php` (gate nhận no_show)
- Modify: `app/Modules/Academic/Delivery/Queries/ListExamResitEligibleStudentsQuery.php` + `ListExamResitBlockedStudentsQuery.php` (khớp write guard)
- Test: tests của các action/query trên.

## Implementation Steps

1. Extract shared `nextAttemptNumber` (nếu chưa có helper dùng chung giữa Complete và NoShow).
2. `MarkExamResitAttemptNoShowAction` + endpoint (`can:` + audit + idempotent scheduled-only).
3. Sửa block-list tạo đơn = `IN_FLIGHT_STATUSES`; `assertAttemptsRemaining` là giới hạn duy nhất (live policy — ghi chú đúng trong test).
4. Gate học lại nhận no_show.
5. Đồng bộ eligibility/blocked queries.
6. Test: no_show → học lại OK; no_show → tạo resit lần 2 bị chặn (limit = 1); double-fire mark-no-show không tiêu thêm lượt; syllabus `max_attempts` > 1 → lần 2 cho phép (live-policy semantics: raise syllabus max = raise limit, không cần migration).

## Success Criteria

- [ ] Ghi no_show được từ UI với permission riêng; `attempt_number` bị tiêu đúng 1 lần; bản ghi điểm không đổi.
- [ ] Sinh viên no_show đăng ký học lại thành công (kể cả failure_reason = grade_failed).
- [ ] Không tạo được đơn thi lại thứ 2 khi lượt đã tiêu >= max_attempts hiện hành.
- [ ] Khi syllabus max_attempts > 1, tạo đơn lần 2 thành công (không sửa code).
- [ ] Eligibility list / blocked list / write guard cùng một định nghĩa consumed.
- [ ] Audit log có actor + timestamp + reason cho mỗi lần mark-no-show.

## Risk Assessment

- Nới block-list → nguy cơ double-register nếu limit check thiếu lock: giữ pattern hiện có của `CreateExamResitAttemptAction` (transaction + count trong guard); test race cơ bản.
- Staff abuse (mark no_show để đẩy sinh viên sang học lại có phí): mitigate bằng `can:` permission riêng + audit; không làm maker-checker trong scope này (YAGNI).
- `IN_FLIGHT_OR_CONSUMED_STATUSES` có nhiều consumer — phải grep toàn bộ consumer trước khi đổi/bỏ; consumer nào đang phụ thuộc `completed` trong danh sách phải chuyển sang consumed-count.
