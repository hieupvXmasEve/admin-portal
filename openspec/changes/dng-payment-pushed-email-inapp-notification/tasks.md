## 1. Database Migration

- [x] 1.1 Tạo migration thêm `rendered_subject TEXT NULL`, `rendered_html LONGTEXT NULL`, `rendered_text TEXT NULL` vào bảng `notification_deliveries`
- [x] 1.2 Cập nhật `$fillable` và `$casts` trong `NotificationDelivery` model

## 2. Email Content Layer — Contract & Registry

- [x] 2.1 Tạo `app/Modules/Notification/EmailContent/Contracts/EmailContentProvider.php` (interface: `subject`, `htmlBody`, `textBody`)
- [x] 2.2 Tạo `app/Modules/Notification/EmailContent/EmailContentRegistry.php` với map `type_key → class` và methods `resolve()`, `has()`
- [x] 2.3 Bind `EmailContentRegistry` vào service container trong `NotificationServiceProvider`

## 3. DngPaymentPushedEmailContent

- [x] 3.1 Tạo `app/Modules/Notification/EmailContent/Types/DngPaymentPushedEmailContent.php` implement `EmailContentProvider`
- [x] 3.2 Implement `subject($data)`: format `[Asia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí học kỳ {semester_code}` (bỏ phần semester nếu null)
- [x] 3.3 Implement `htmlBody($data)`: HTML bilingual (VI + EN) theo mẫu `data/email_template_example/create_fee_notice.html`, interpolate: `student_name`, `student_code`, `semester_code`, `program_name`, `invoice_code`, `amount`, `due_date` (ẩn dòng due_date nếu null)
- [x] 3.4 Implement `textBody($data)`: return null (gửi HTML-only)
- [x] 3.5 Đăng ký `'dng_payment_pushed' => DngPaymentPushedEmailContent::class` vào `EmailContentRegistry`

## 4. Data Builder trong HandleOutboxEventAction

- [x] 4.1 Thêm `buildEmailData(NotificationIntent $intent, DomainEventEnvelope $envelope): array` vào `HandleOutboxEventAction` — query `DngPaymentRequest::with(['semester', 'student.program'])->find($data['dng_payment_request_id'])` và map ra array chuẩn
- [x] 4.2 Modify `HandleOutboxEventAction::run()`: trước `PersistIntentAction`, nếu channel là 'email' và `EmailContentRegistry::has($typeKey)` → render và truyền rendered fields

## 5. PersistIntentAction — Lưu Rendered Content

- [x] 5.1 Cập nhật `PersistIntentAction::run()` nhận thêm `array $renderedEmail = []` chứa `rendered_subject`, `rendered_html`, `rendered_text`
- [x] 5.2 Lưu rendered fields vào `NotificationDelivery` khi tạo record

## 6. RenderedEmailChannelAdapter

- [x] 6.1 Tạo `app/Modules/Notification/Channels/RenderedEmailChannelAdapter.php` implement `ChannelAdapter`
- [x] 6.2 Implement `send()`: đọc `delivery->rendered_subject`, `rendered_html`, `rendered_text`; resolve SMTP qua `EmailConfiguration::getActiveForCampus($message->campus_id)`; gọi `emailService->sendSingleEmail()` với HTML body; return `['email_log_id' => ...]`

## 7. Routing trong SendNotificationDeliveryJob

- [x] 7.1 Inject `RenderedEmailChannelAdapter` vào `SendNotificationDeliveryJob`
- [x] 7.2 Thêm routing: nếu channel 'email' và `delivery->rendered_subject !== null` → `RenderedEmailChannelAdapter::send()`, ngược lại → `EmailChannelAdapter::send()`

## 8. Cập nhật DngPaymentService

- [x] 8.1 Bổ sung `dng_payment_request_id` vào `data` của payload trong `DngPaymentService::publishPushNotification()`
- [x] 8.2 Xác nhận batch push (`createAndPushBatch`) cũng truyền `dng_payment_request_id` nếu cần

## 9. Tests

- [x] 9.1 Unit test `DngPaymentPushedEmailContent`: subject với/không có semester, htmlBody ẩn due_date khi null, textBody trả null
- [x] 9.2 Unit test `EmailContentRegistry`: resolve đúng class, has() false cho type chưa đăng ký
- [x] 9.3 Feature test `HandleOutboxEventAction`: delivery có `rendered_subject` NOT NULL khi type_key đã đăng ký; NULL khi chưa đăng ký
- [x] 9.4 Feature test routing trong `SendNotificationDeliveryJob`: gọi đúng adapter theo `rendered_subject`
- [ ] 9.5 Feature test end-to-end: `DngPaymentService::createAndPush()` → email delivery có rendered content
