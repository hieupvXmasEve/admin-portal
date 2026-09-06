---
phase: 6
title: "Thông báo học phí"
status: completed
priority: P2
effort: "1-1.5d"
dependencies: [3, 5]
---

# Phase 6: Thông báo học phí

## Overview

Owner chốt chứng từ gồm **thông báo học phí** (Phase này) và phiếu thu
(Phase 3). Hôm nay Finance chỉ có *email nhắc nợ* thủ công — không có văn bản
"thông báo học phí" nói rõ: em nợ những khoản gì, tổng bao nhiêu, hạn nào, trả
kiểu gì. Phase này thêm loại thông báo đó.

**Nhắc nợ vẫn thủ công** (quyết định #3) — phase này **không** thêm cron, không
thêm thang bậc; chỉ thêm loại thông báo mới do staff phát. Hàng đợi "cần nhắc"
làm ở Phase 8.

**Không có đợt thu** (quyết định #13): phạm vi phát thông báo là **tập sinh
viên staff chọn** trên worklist hiện có (Collection Progress / Due Items), lọc
theo `semesterId` + loại phí như hôm nay. Không thêm chiều báo cáo mới.

## Key Insights

- **Ba cổng phải xử lý trước khi tin là "đã gửi" (red team 2026-09-06, Critical):**
  1. `config/notification.php:4` — `NOTIFICATION_V2_ENABLED` mặc định **false**.
     `NotificationDomainEventPublisher::isEnabled()` (`:38-44,47-58`) khi đó là
     **no-op câm**: không lỗi, không log, không email.
  2. Template thiếu cho campus ⇒ `DbEmailContentProvider::resolveTemplate():95-103`
     ném `RuntimeException`, nhưng `HandleOutboxEventAction.php:171-188` **nuốt**
     thành `Log::warning` và trả `[]`; delivery vẫn đi tiếp và
     `EmailChannelAdapter.php:28-29` gửi tiêu đề/nội dung boilerplate **tiếng Anh**
     hardcode ở `PublishReminderNotificationAction.php:50-51` — không khoản mục,
     không số tiền — rồi đánh dấu đã gửi.
  3. `PaymentService.php:81-83` chấp `['dual','v2_only']` còn publisher
     (`NotificationDomainEventPublisher.php:57`) chấp `['dual','v2','v2_only']`;
     giá trị ship là `v2` (`config/notification.php:6`, `.env.example:200`) ⇒ hai
     allowlist lệch nhau, `finance.invoice_paid` không bao giờ phát.
- **Seed template gần như tự động** (nhẹ hơn plan trước tưởng):
  `NotificationEmailTemplateProvisioner::defaults()` + `CampusObserver.php:22`
  tự cấp cho campus mới; `EmailContentRegistry::has():56-63` nhận mọi enum case
  khi `use_db_templates=true`, không cần provider class.
  **Gotcha thật:** `config/permission.php:504-512` **không có** permission tạo
  template ⇒ row phải đến từ provisioner + **data migration**, không phải
  `database/seeders` (mẫu: `database/migrations/2026_08_02_010000_seed_scholarship_confirmation_email_template.php`).
- Đường thông báo hiện có: `app/Modules/Finance/Actions/Operations/PublishReminderNotificationAction.php`
  (nhận `aggregate_type` / `aggregate_id` dạng chuỗi tự do, `:17-18,31-39`)
  → `DomainEventPublisher::publishAfterCommit()` (`finance.payment_reminder_requested`)
  → `app/Modules/Notification/Actions/HandleOutboxEventAction.php`
  → `SendNotificationDeliveryJob` → `EmailChannelAdapter` → `SmtpEmailTransport`.
- Template: bảng `notification_email_templates`
  (`app/Modules/Notification/Models/NotificationEmailTemplate.php`), unique
  `(campus_id, type_key)`; biến hợp lệ khai báo trong
  `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php::availableVariables()`.
  Enum **đóng**, hiện 8 key: `payment_reminder, parent_payment_reminder,
  installment_payment_reminder, parent_installment_payment_reminder,
  dng_payment_pushed, dng_payment_received,
  scholarship_adjustment_confirmation_requested,
  scholarship_adjustment_tuition_deferred`.
- Registry chọn provider: `app/Modules/Notification/EmailContent/EmailContentRegistry.php:42-48`
  (DB template khi `config('notifications.use_db_templates', true)`).
- Hợp đồng template: `docs/features/email/template-contract.md` (placeholder
  `{{var}}`, escape bằng `htmlspecialchars`).
- Không có thư viện PDF (`composer.json:14`) ⇒ bản in của thông báo dùng trang
  in HTML như Phase 3, không thêm dependency.
- Danh sách gửi đã có: `app/Modules/Finance/Queries/Operations/ListDueItemsQuery.php`;
  4 action gửi: `SendPaymentRemindersAction`, `SendParentPaymentRemindersAction`,
  `SendDueItemRemindersAction`, `SendDueItemParentRemindersAction` (dedup bằng
  `student_invoices.last_reminder_at`, migration `2026_04_07_000001`).
- Email phụ huynh: `GuardianAccessGrantReader` (2 action parent hiện có đã dùng).
- Báo cáo tiến độ **không đụng tới**: `GetCollectionProgressSummaryQuery.php:65-179`
  đã có `by_fee_type`, `by_intake`, aging bucket, balance_state, lọc theo
  `semesterId` — đủ trả lời "%thu / còn ai chưa nộp" (quyết định #13).

## Requirements

- Functional: staff phát **thông báo học phí** cho tập SV đã chọn trên worklist,
  gửi cho SV **và** phụ huynh (PH có grant active; không có PH thì bỏ qua, không lỗi).
- Functional: nội dung liệt kê từng khoản còn phải thu (tên tiếng Việt), tổng
  phải thu, hạn nộp, cách thanh toán; có bản in HTML.
- Functional: ghi nhận đã phát cho ai, khi nào (trả lời "ai đã nhận thông báo").
- Functional: phát lại **cùng nội dung** cho cùng người không tạo email trùng;
  nội dung đã đổi (ledger đổi) thì phát lại được; **gửi thất bại thì phát lại
  được** — dedup không được khoá vĩnh viễn một lần gửi hỏng.
- Functional: campus thiếu template ⇒ hành động của staff **thất bại rõ ràng**,
  không gửi boilerplate tiếng Anh rồi báo thành công.
- Non-functional: mọi số tiền đọc từ Settlement Position; không tính lại.
- Non-functional: tuân hợp đồng template (biến khai báo trong enum, escape).

## Architecture

1. **2 type_key mới** trong `NotificationTemplateTypeKey`:
   `tuition_notice` và `parent_tuition_notice`, kèm `availableVariables()`
   (`student_code`, `student_name`, `semester_name`, `due_date`, `total_due`,
   `items_summary`, `payment_instruction`).
   **Bỏ `items_table` (red team, High — XSS).** `HasTemplateRendering::renderContentEscaped():57-66`
   escape **mọi** giá trị bằng `htmlspecialchars(ENT_QUOTES)`, không có opt-out;
   một biến chứa HTML hoặc hiện ra dạng markup thô (email hỏng), hoặc implementer
   chuyển sang `renderContent():33-40` (thay thế RAW) — tắt escape cho **toàn bộ**
   biến của template đó. Mô tả charge và `notes` là free-text staff
   (`RecordManualPaymentRequest.php:23`, `max:1000`) ⇒ HTML/link injection đi
   thẳng tới hộp thư SV và PH trong email mang hướng dẫn thanh toán của trường.
   ⇒ `items_summary` là **chuỗi phẳng** (nối bằng xuống dòng), hoặc dựng markup
   dòng trong content provider từ trường đã escape, giữ template DB chỉ nhận
   scalar. `due_date` lấy từ ngày Phase 5 làm ra.
   Đây là thay đổi **cross-module**
   (Finance → Notification) ⇒ phải theo `docs/features/email/template-contract.md`
   và thêm 2 entry vào `NotificationEmailTemplateProvisioner::defaults()` + một
   **data migration** (không phải seeder — không có permission tạo template).
2. **Action Finance mới** `SendTuitionNoticeAction` (+ cờ recipient cho bản
   parent) dùng lại `PublishReminderNotificationAction` với
   `aggregate_type = 'student'`, `aggregate_id = student.id`.
3. **KHÔNG tạo bảng mới (red team, High).** Bảng `finance_tuition_notices` đề
   xuất trước trùng hoàn toàn với hạ tầng notification mà chính phase này tái dùng:
   `snapshot` ≈ `notification_messages.data`
   (`2026_03_03_120100_create_notification_messages_table.php:19`);
   `recipient_type`/"ai đã nhận" ≈ `notification_messages.recipient_user_id` (`:17`);
   nội dung bất biến đã in ≈ `notification_deliveries.rendered_subject/rendered_html/rendered_text`
   (`2026_04_18_133427_add_rendered_content_to_notification_deliveries.php:12-14`,
   ghi ở `PersistIntentAction.php:72-74` — thêm vào đúng để đóng băng nội dung đã gửi);
   dedup ≈ unique `(event_id, type_key, recipient_user_id)` (`:31`).
   Hai bảng ghi "đã gửi" **sẽ lệch**: gửi hỏng thì `notification_deliveries.status
   = failed` trong khi bảng Finance đã ghi "đã phát" ⇒ trả lời "ai đã nhận" sai
   đúng lúc cần nhất; và trang in render snapshot Finance còn hộp thư SV giữ
   `rendered_html` — hai bản của cùng một văn bản.
   ⇒ "Ai đã nhận" = query `notification_messages` join `notification_deliveries`
   lọc `type_key IN ('tuition_notice','parent_tuition_notice')`.
3b. **Dedup đúng chỗ**: outbox hiện **cố ý** phá dedup bằng
   `(string) Str::uuid()` trong `deduplicationKey`
   (`PublishReminderNotificationAction.php:29-36`). Đó là **một dòng** cần sửa
   (dedup key ổn định = `type_key + student_id + recipient + content_hash`), không
   phải một bảng mới. Dedup phải gắn với **kết quả gửi**, không phải ý định: một
   delivery `failed` không được chặn lần phát lại.
3c. **Cổng trước khi publish**: kiểm template tồn tại cho campus
   (`EmailContentRegistry`) và `NOTIFICATION_V2_ENABLED` đang bật; thiếu một
   trong hai ⇒ **fail hành động staff** kèm danh sách campus thiếu template.
   Không publish, không ghi "đã phát".
4. **Trang in thông báo**: `GET /finance/students/{student}/tuition-notices/{messageId}`
   render `notification_deliveries.rendered_html`, dùng **layout in dùng chung do
   Phase 3 xây** (repo hiện có 0 `@media print` — xem Phase 3 Architecture 3).

## Related Code Files

- Modify: `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php`
- Modify: `app/Modules/Notification/Support/NotificationEmailTemplateProvisioner.php` (`defaults()` + 2 entry)
- Create: data migration seed 2 template (mẫu `database/migrations/2026_08_02_010000_seed_scholarship_confirmation_email_template.php`)
- Modify: `app/Modules/Finance/Actions/Operations/PublishReminderNotificationAction.php:29-36` (dedup key ổn định, bỏ `Str::uuid()`)
- Modify: `app/Modules/Notification/Actions/HandleOutboxEventAction.php:171-188` (không nuốt lỗi template thành warning cho type_key mới)
- Kiểm/hoà giải: `app/Modules/Finance/Services/PaymentService.php:81-83` vs `app/Modules/Notification/Support/NotificationDomainEventPublisher.php:57` (2 allowlist write-mode lệch nhau)
- Reuse: layout in dùng chung của Phase 3
- Kiểm test hiện có: `tests/Unit/Notification/Enums/NotificationTemplateTypeKeyVariablesTest.php`, `tests/Feature/Notification/EmailContent/DbEmailContentParityTest.php`, `DbEmailContentProviderTest.php`, `NotificationTemplatePreviewTest.php`, `tests/Unit/Notification/Models/NotificationEmailTemplateRenderTest.php`
- Create: `app/Modules/Finance/Actions/Operations/SendTuitionNoticeAction.php`
- Create: trang in thông báo học phí + route trong `app/Modules/Finance/routes/web.php`
- Reuse: `app/Modules/Finance/Actions/Operations/PublishReminderNotificationAction.php`
- Reuse: `app/Modules/Finance/Queries/Operations/ListDueItemsQuery.php` (chọn tập SV)
- Modify: `docs/features/email/template-contract.md` (nếu contract yêu cầu liệt kê type_key)

## Implementation Steps

1. Test đỏ: không có cách nào phát "thông báo học phí".
2. Thêm 2 type_key + `availableVariables()` + seed template mặc định; chạy trang
   admin template (`routes/web/notifications.php`) xác nhận hiển thị.
3. Sửa dedup key ở outbox (bỏ `Str::uuid()`), gắn với kết quả gửi.
4. Cổng trước publish: template đủ cho campus + V2 đang bật; thiếu ⇒ fail rõ ràng.
5. `SendTuitionNoticeAction`: nhận tập student id, dựng dữ liệu từ Settlement
   Position (scalar đã escape), publish cho SV + PH.
6. Trang in đọc `notification_deliveries.rendered_html` qua layout in Phase 3.
7. Test xanh: phát 2 lần cùng nội dung không gửi trùng; đổi ledger rồi phát lại
   được; **gửi hỏng rồi phát lại được**; campus thiếu template ⇒ 422 không gửi.

## Todo

- [x] Test đỏ: không phát được thông báo học phí
- [x] 2 type_key + biến + seed template mọi campus (cross-module, theo contract)
- [x] Dedup key ổn định ở outbox (không bảng mới)
- [x] Cổng template/V2 trước publish
- [x] `SendTuitionNoticeAction` cho SV + PH (chỉ biến scalar, không `items_table`)
- [x] Trang in đọc `rendered_html` qua layout in Phase 3
- [x] Test xanh + `pint --dirty` + eslint/prettier file FE đã sửa

## Success Criteria

- [x] Phát thông báo cho tập SV: SV và PH đều nhận email; bản ghi phát hành tồn
      tại; in được.
- [x] Phát lại cùng nội dung không tạo email trùng; ledger đổi thì phát lại được.
- [x] Gửi thất bại (SMTP down, hết attempt) → staff phát lại được.
- [x] Campus thiếu template → hành động fail rõ ràng, **không** gửi email tiếng Anh boilerplate.
- [x] `NOTIFICATION_V2_ENABLED=false` → hành động fail rõ ràng, không báo "đã phát".
- [x] **Không** có bảng mới nào được tạo trong phase này.
- [x] Mô tả charge chứa `<a href>` hiện dạng **escape** trong body email (test riêng).
- [x] Số tiền trong thông báo khớp Settlement Position tại thời điểm phát
      (snapshot), không tự tính lại.
- [x] **Không** có cron/job tự gửi nhắc nợ nào được thêm.
- [x] **Không** thêm chiều báo cáo mới vào `GetCollectionProgressSummaryQuery`.

## Risk Assessment

- **Cao:** email gửi ra ngoài mà nội dung sai/không escape là không thu hồi được.
  Test escape là bắt buộc, không phải tuỳ chọn.
- **Trung bình:** enum đóng của Notification là cross-module — 20 file / 71 tham
  chiếu. Seed cho campus mới **đã tự động** (`CampusObserver.php:22`); rủi ro thật
  là campus **đang tồn tại** chưa có row, và không có permission tạo tay.
- **Trung bình:** thông báo cho phụ huynh cần email PH — dùng
  `GuardianAccessGrantReader` như 2 action parent hiện có; SV không có PH thì
  bỏ qua, không lỗi. **Lưu ý N+1**: `SendParentPaymentRemindersAction.php:34,36,44-46`
  gọi `accountsForStudent()` + `deriveInvoiceSnapshot()` **trong vòng lặp**; chọn
  500 SV = 500+ query. Dùng dạng batch `studentIdsWithAccounts()`
  (`EloquentGuardianAccessGrantReader.php:101`). Cũng ở đó: `:85-87` nuốt mọi
  `Throwable` vào biến đếm **không log** — thêm `Log::warning`.
- **Thấp:** snapshot làm thông báo lệch số hiện tại nếu ledger đổi sau khi phát
  — đúng chủ ý, phải nói rõ trên UI ("số liệu tại thời điểm phát hành").

## Security Considerations

Thông báo chứa dữ liệu tài chính cá nhân. Route in phải kiểm campus + permission.

**Sửa tuyên bố sai (red team, High).** Plan trước viết "email chỉ gửi tới địa chỉ
**đã xác thực**". Cơ chế đó **không tồn tại**: `EloquentGuardianAccessGrantReader.php:85-98`
chỉ lọc `guardian_access_grants.status`, `users.type`, `users.status` — không có
`email_verified_at` ở bất kỳ đâu trong Notification/Identity/Finance (cột có tồn
tại: `0001_01_01_000000_create_users_table.php:20`). Viết như đã có ⇒ implementer
không build.
Chọn một, ghi rõ: (a) thêm `whereNotNull('users.email_verified_at')` sau một
config flag — biết trước là sẽ **chặn bớt** người nhận hiện tại; hoặc (b) không
làm, và ghi nhận đây là rủi ro đã chấp nhận: dữ liệu tài chính của SV có thể tới
hộp thư PH chưa xác minh (email nhập sai lúc tuyển sinh).

Escape: xem Architecture 1 — `items_table` bị loại chính vì lý do này.

## Next Steps

Phase 8 đưa hàng đợi "cần nhắc hôm nay" vào Finance Inbox và cập nhật docs-site.
