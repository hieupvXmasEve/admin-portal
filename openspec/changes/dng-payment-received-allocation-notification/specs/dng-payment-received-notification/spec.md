## ADDED Requirements

### Requirement: Publish payment received notification on first PAID transition
Khi `DngWebhookService::processEvent()` thực sự chuyển `DngPaymentRequest` sang trạng thái `STATUS_PAID_UNINVOICED` hoặc `STATUS_PAID_INVOICED` lần đầu tiên, hệ thống SHALL publish một domain event `finance.dng_payment_received` qua `PublishDomainEventAction`.

Event payload SHALL bao gồm:
- `type_key`: `"dng_payment_received"`
- `channels`: `["realtime", "email"]`
- `recipient_targets`: `[{"type": "student", "id": <student_id>}]`
- `data.student_name`, `data.student_code`, `data.amount_formatted`, `data.semester_code` (nếu có), `data.paid_at`
- `data.dng_payment_request_id` (để `HandleOutboxEventAction` load thêm data cho email)

#### Scenario: First transition to PAID_UNINVOICED
- **WHEN** webhook event type là `PAYMENT_CALLBACK` và request status chuyển sang `STATUS_PAID_UNINVOICED`
- **THEN** domain event `finance.dng_payment_received` được publish sau `event.markProcessed()`

#### Scenario: First transition to PAID_INVOICED
- **WHEN** webhook event type là `PAYMENT_INVOICED` và request status chuyển sang `STATUS_PAID_INVOICED`
- **THEN** domain event `finance.dng_payment_received` được publish sau `event.markProcessed()`

#### Scenario: Duplicate webhook skipped — no notification
- **WHEN** request status đã là PAID (hoặc cao hơn) khi webhook đến
- **THEN** `event.markSkipped()` được gọi và KHÔNG có notification nào được publish

#### Scenario: Notification failure does not break webhook processing
- **WHEN** `PublishDomainEventAction::run()` throw exception
- **THEN** exception được catch, log warning, và webhook vẫn được đánh dấu `processed` bình thường

### Requirement: Send email to student on payment received
Hệ thống SHALL gửi email xác nhận thanh toán cho sinh viên thông qua `DngPaymentReceivedEmailContent`.

Email SHALL bao gồm:
- Subject dạng: `"[Asia Việt Nam] Xác nhận thanh toán thành công"`
- Body: tên sinh viên, mã số sinh viên, số tiền (formatted), học kỳ (nếu có), ngày nhận tiền

#### Scenario: Email rendered with semester info
- **WHEN** `DngPaymentRequest` có `semester_id` không null
- **THEN** email body bao gồm tên học kỳ

#### Scenario: Email rendered without semester info
- **WHEN** `DngPaymentRequest` có `semester_id` là null
- **THEN** email body bỏ qua phần học kỳ, không hiển thị trống
