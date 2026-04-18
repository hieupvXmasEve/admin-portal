## ADDED Requirements

### Requirement: EmailContentProvider contract
Mọi loại email content SHALL implement `EmailContentProvider` interface với 3 method: `subject(array $data): string`, `htmlBody(array $data): string`, `textBody(array $data): ?string`. Content class KHÔNG ĐƯỢC query DB.

#### Scenario: Render subject
- **WHEN** `subject($data)` được gọi với data hợp lệ
- **THEN** trả về string non-empty, không null

#### Scenario: Render html body
- **WHEN** `htmlBody($data)` được gọi với data hợp lệ
- **THEN** trả về string HTML non-empty

#### Scenario: Text body optional
- **WHEN** `textBody($data)` được gọi
- **THEN** trả về null nếu provider không cần plain-text; SMTP client gửi HTML-only khi null

### Requirement: EmailContentRegistry map type_key đến content class
`EmailContentRegistry` SHALL map mỗi `type_key` đến đúng một `EmailContentProvider` class. Registry SHALL expose `resolve(string $typeKey): EmailContentProvider` và `has(string $typeKey): bool`.

#### Scenario: Resolve type_key đã đăng ký
- **WHEN** `resolve('dng_payment_pushed')` được gọi
- **THEN** trả về instance của `DngPaymentPushedEmailContent`

#### Scenario: Resolve type_key chưa đăng ký
- **WHEN** `has('unknown_type')` được gọi
- **THEN** trả về false

### Requirement: Render tại HandleOutboxEventAction, lưu vào delivery
Khi `type_key` tồn tại trong `EmailContentRegistry`, `HandleOutboxEventAction` SHALL render email content và lưu `rendered_subject`, `rendered_html`, `rendered_text` vào `NotificationDelivery` trước khi dispatch job.

#### Scenario: Render thành công
- **WHEN** outbox event có `type_key` đã đăng ký trong registry
- **THEN** delivery record có `rendered_subject` NOT NULL và `rendered_html` NOT NULL

#### Scenario: Type_key không có trong registry
- **WHEN** outbox event có `type_key` chưa đăng ký
- **THEN** delivery record có `rendered_subject` NULL — email đi qua EmailChannelAdapter cũ

### Requirement: RenderedEmailChannelAdapter gửi từ rendered content
`RenderedEmailChannelAdapter` SHALL đọc `rendered_subject`, `rendered_html`, `rendered_text` từ delivery record và gửi email. KHÔNG gọi lại content provider.

#### Scenario: Gửi email thành công
- **WHEN** delivery có `rendered_subject` NOT NULL và email gửi thành công
- **THEN** delivery status cập nhật thành `sent`, `email_log_id` được lưu

#### Scenario: Retry gửi lại đúng nội dung cũ
- **WHEN** delivery failed và được retry
- **THEN** email gửi với cùng `rendered_subject`/`rendered_html` đã lưu từ lần đầu, không re-render

### Requirement: Routing trong SendNotificationDeliveryJob
`SendNotificationDeliveryJob` SHALL route dựa vào `delivery->rendered_subject !== null`: nếu có → `RenderedEmailChannelAdapter`, nếu null → `EmailChannelAdapter` cũ.

#### Scenario: Routing sang adapter mới
- **WHEN** delivery channel là 'email' và `rendered_subject` IS NOT NULL
- **THEN** `RenderedEmailChannelAdapter::send()` được gọi

#### Scenario: Routing sang adapter cũ (backward compat)
- **WHEN** delivery channel là 'email' và `rendered_subject` IS NULL
- **THEN** `EmailChannelAdapter::send()` được gọi
