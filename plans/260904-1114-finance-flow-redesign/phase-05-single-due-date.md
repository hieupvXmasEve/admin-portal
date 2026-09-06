---
phase: 5
title: "Một ngày hạn nộp duy nhất"
status: completed
priority: P1
effort: "0.5-1d"
dependencies: []
---

# Phase 5: Một ngày hạn nộp duy nhất

## Overview

Red team 2026-09-06 lật đổ tiền đề "ngày `now()+30d` chỉ lấp cột NOT NULL":
`student_invoices.due_date` **chính là** ngày sinh viên nhìn thấy và ngày mọi
phép tính quá hạn dùng. Staff đã chọn hạn nộp ở Batch Studio DNG commit nhưng
ngày đó **không bao giờ đến được invoice**. Phase này làm một ngày duy nhất có
thẩm quyền, **không** tạo thực thể đợt thu (quyết định #13 giữ nguyên).

Đây là tiền đề của Phase 4 (`Quá hạn`) và Phase 6 (biến `due_date` trên thông
báo học phí). Không có phase này, hai phase kia dựng trên ngày không ai chọn.

## Key Insights

- Portal đọc invoice due_date: `app/Modules/Finance/Queries/GetStudentFinancePresentationQuery.php:445`
  — `return $invoice->due_date?->isPast() ? 'overdue' : 'open';`; `nearest_due_date`
  sắp từ cùng nguồn (`:93-96`, phát ở `:189`, `:404`).
- Aging/overdue của báo cáo cũng vậy:
  `app/Modules/Finance/Queries/Reporting/ListCollectionProgressQuery.php:250-251`
  → nuôi `aging_bucket` (`:332`) và `overdue_total`/`overdue_count` trong
  `GetCollectionProgressSummaryQuery.php:37,44`.
- Trạng thái invoice lật `overdue` từ cùng ngày: `CreateFinanceChargeAction.php:420`.
- **Ngày staff chọn có thật**: `app/Modules/Finance/Http/Requests/Batch/CommitBatchDngRequest.php:25`
  (`due_date` required, `after_or_equal:today`), truyền ở
  `BatchStudioController.php:220`. Nó đi vào `dng_payment_requests.due_date`,
  **không** đi vào invoice.
- Ngày mặc định `now()+30d` có **5 site**, không phải một:
  `BatchStudioController.php:134`, `CreateFinanceChargeAction.php:332`,
  `GenerateBatchChargesAction.php:41`, `GenerateEgcChargesAction.php:35`,
  `InvoiceGenerationService.php:47`.
- **Ngày không bao giờ được cập nhật lên invoice đã tồn tại**:
  `InvoiceGenerationService.php:43-47` chỉ gán `due_date` trong nhánh
  `if (! $invoice->exists)`; `GenerateBatchChargesAction::findReusableInvoice():457-465`
  tái dùng invoice cũ ⇒ ngày của batch **đầu tiên** là vĩnh viễn.
- Nhắc nợ đang dùng hai ngày khác nhau trong cùng một tính năng:
  `SendDueItemRemindersAction.php:131` dùng `$dngRequest->due_date`, `:209` dùng
  `$invoice->due_date`.

## Requirements

- Functional: ngày hạn nộp staff chọn tại Batch Studio commit phải là ngày ghi
  trên invoice — kể cả khi invoice đã tồn tại từ batch trước.
- Functional: một quy tắc duy nhất, viết thành tài liệu, cho câu hỏi "khoản này
  hạn ngày nào" — dùng chung portal, worklist staff, báo cáo, thông báo.
- Functional: khi không có ngày nào do người chọn, hệ thống **không** bịa ngày;
  khoản đó không có trạng thái quá hạn (xem Phase 4 truth table).
- Non-functional: không tạo bảng mới, không thực thể đợt thu (#13).
- Non-functional: không đổi số tiền; đây là thay đổi ngày, không phải kế toán.

## Architecture

1. **Ngày có thẩm quyền = ngày staff chọn tại commit.** `InvoiceGenerationService`
   nhận `$dueDate` và **cập nhật** invoice đang tồn tại khi ngày mới do người
   chọn (không ghi đè bằng giá trị mặc định). Gọi `markChanged()` khi đổi.
2. **Bỏ 5 mặc định `now()+30d`.** Ba đường sinh charge (major/EGC/non-academic)
   và Batch Studio phải nhận ngày từ người dùng; thiếu ngày ⇒ lỗi validate, không
   im lặng bịa. `CreateFinanceChargeAction::createInvoiceForSemester` giữ tham số
   `?Carbon $dueDate` nhưng null ⇒ `due_date` để **null**, không phải +30d.
3. **`due_date` nullable.** Kiểm migration `student_invoices` trước: nếu cột đang
   NOT NULL thì cần migration nới; nếu đã nullable thì không cần. Mọi consumer
   đọc `due_date` đã dùng `?->` (`GetStudentFinancePresentationQuery.php:445`,
   `ListCollectionProgressQuery.php:250`) nên null an toàn — **phải xác minh** từng
   consumer trước khi land.
4. **Hợp nhất nhắc nợ**: `SendDueItemRemindersAction` dùng một nguồn ngày, không
   phải `:131` một kiểu `:209` một kiểu.

## Related Code Files

- Modify: `app/Modules/Finance/Services/InvoiceGenerationService.php:38-47,69-72`
- Modify: `app/Modules/Finance/Http/Web/Admin/BatchStudioController.php:134,286,291,320`
- Modify: `app/Modules/Finance/Actions/CreateFinanceChargeAction.php:332`
- Modify: `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php:41,457-465`
- Modify: `app/Modules/Finance/Actions/Egc/GenerateEgcChargesAction.php:35`
- Modify: `app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php:131,209`
- Create (chỉ nếu cột NOT NULL): migration nới `student_invoices.due_date` nullable
- Modify: `docs/` — ghi quy tắc ngày hạn nộp (surface nhỏ nhất sở hữu nó)

## Implementation Steps

1. Test đỏ: commit batch với `due_date` staff chọn lên một SV **đã có invoice**
   → khẳng định invoice giữ ngày cũ (bug hiện tại).
2. Kiểm schema `student_invoices.due_date` nullable hay không; ghi kết quả vào
   phase này trước khi viết migration.
   **Kết quả:** NOT NULL — `database/migrations/2025_10_08_145805_create_student_invoices_table.php` (`$table->date('due_date')`). Migration nới: `2026_09_06_180000_make_student_invoices_due_date_nullable.php`.
3. `InvoiceGenerationService`: cập nhật `due_date` trên invoice đã tồn tại khi
   nhận ngày do người chọn.
4. Bỏ 5 mặc định `now()+30d`. Charge commit Batch Studio **không** thêm field
   `due_date` (không có picker); thiếu ngày ⇒ `due_date` null, không validate
   bắt buộc. Ngày có thẩm quyền = DNG commit (`CommitBatchDngRequest`).
5. Hợp nhất nguồn ngày trong `SendDueItemRemindersAction`.
6. Test xanh + hồi quy: invoice cũ có `due_date` không đổi nghĩa; portal
   `overdue` badge đúng ngày staff chọn.

## Todo

- [x] Test đỏ: invoice tái dùng giữ ngày batch đầu
- [x] Kiểm nullable của `student_invoices.due_date` (ghi kết quả vào phase)
- [x] Cập nhật ngày lên invoice đã tồn tại
- [x] Bỏ 5 site `now()->addDays(30)`
- [x] Hợp nhất nguồn ngày trong nhắc nợ
- [x] Test hồi quy portal/aging/báo cáo

## Success Criteria

- [x] `grep -rn "addDays(30)" app/Modules/Finance` trả 0.
- [x] Commit batch lần 2 với ngày mới lên SV đã có invoice → invoice mang ngày mới.
- [x] Portal `overdue` và `aging_bucket` tính theo ngày staff chọn.
- [x] Khoản không có ngày do người chọn → không hiện quá hạn ở bất kỳ bề mặt nào.
- [x] Không có bảng mới, không có `billing_cycles` writer mới.

## Risk Assessment

- **Cao:** đổi ngày trên invoice **đang tồn tại** đổi trạng thái `overdue` của dữ
  liệu thật. **Land count:** migration + code **không** rewrite `due_date` hiện
  có — 0 invoice tự lật overdue↔open lúc deploy. Lật chỉ xảy ra khi staff
  commit DNG với ngày mới lên invoice đó.
- **Trung bình:** `due_date` null là trạng thái mới với mọi consumer; phải duyệt
  từng consumer, không giả định `?->` đã phủ hết.
- **Trung bình:** `InvoiceGenerationService` nằm trong `SettlementMutationGuard`;
  ghi `due_date` phải gọi `markChanged()` đúng, nếu không settlement version lệch.
- **Collision:** một invoice (thường student+semester) có **một** `due_date`.
  DNG commit ghi ngày lên mọi invoice chứa line đang đẩy. Hai loại phí chung
  invoice → **commit sau thắng** (cùng quy tắc "commit lần 2 mang ngày mới").
  `applyStaffChosenDueDate` đóng guard trước `ReserveAndPushSingleFeeDngAction`
  — không bọc chung với push.

## Security Considerations

Không thêm bề mặt mới. Ngày hạn nộp là dữ liệu người học nhìn thấy — không được
lộ ngày của campus khác qua đường tái dùng invoice.

## Next Steps

Phase 4 dùng ngày này làm input cho `Quá hạn`; Phase 6 dùng làm biến `due_date`
trên thông báo học phí.
