## Why

Khi admin tạo thành công một khoản phí DNG cho sinh viên, hệ thống hiện tại gửi notification nhưng email chỉ dùng plain text từ `title`/`body` của in-app message — không có HTML layout, subject riêng, hay nội dung hướng dẫn thanh toán. User (phòng tài chính) đã cung cấp mẫu email chuẩn cần áp dụng.

## What Changes

- Thêm `rendered_subject`, `rendered_html`, `rendered_text` vào bảng `notification_deliveries` để lưu email content đã render trước khi dispatch job.
- Tạo `EmailContentProvider` contract và `EmailContentRegistry` trong Notification module để quản lý tập trung nội dung email theo `type_key`.
- Implement `DngPaymentPushedEmailContent` class với HTML template bilingual (VI + EN) theo mẫu `create_fee_notice.html`, sử dụng dữ liệu từ `DngPaymentRequest` (semester, due_date, item_id, amount) và `Student.program`.
- Thêm `RenderedEmailChannelAdapter` — gửi email từ `rendered_subject`/`rendered_html` đã lưu trong delivery, giữ nguyên `EmailChannelAdapter` cũ.
- Modify `HandleOutboxEventAction` để gọi registry, render content, và lưu vào delivery trước khi dispatch `SendNotificationDeliveryJob`.
- Routing trong `SendNotificationDeliveryJob`: nếu `rendered_subject IS NOT NULL` → `RenderedEmailChannelAdapter`, ngược lại → `EmailChannelAdapter` (backward compatible).
- In-app (realtime) notification vẫn dùng `title`/`body` ngắn, độc lập với email.
- Bổ sung `dng_payment_request_id` vào `data` payload của event `finance.dng_payment_pushed` để data builder query đủ context.

## Capabilities

### New Capabilities

- `email-content-layer`: Contract `EmailContentProvider`, `EmailContentRegistry`, và luồng render-before-dispatch cho email channel trong Notification module.
- `dng-payment-pushed-notification`: Email HTML + in-app notification khi tạo thành công khoản phí DNG, với content đúng mẫu phòng tài chính.

### Modified Capabilities

<!-- Không có spec-level behavior change trên capability hiện tại — chỉ bổ sung render path mới, backward compatible. -->

## Impact

- **Migration**: `notification_deliveries` — thêm 3 nullable columns (`rendered_subject TEXT`, `rendered_html LONGTEXT`, `rendered_text TEXT`).
- **`HandleOutboxEventAction`** — thêm bước render email content trước `PersistIntentAction`.
- **`PersistIntentAction`** — nhận thêm rendered fields để lưu vào delivery.
- **`SendNotificationDeliveryJob`** — routing logic thay đổi.
- **`DngPaymentService::publishPushNotification()`** — bổ sung `dng_payment_request_id` vào payload.
- **Không breaking**: `EmailChannelAdapter` cũ giữ nguyên, chỉ bị bypass khi `rendered_subject` có giá trị.
