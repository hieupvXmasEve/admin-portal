## ADDED Requirements

### Requirement: Email HTML gửi cho sinh viên khi tạo thành công khoản phí DNG
Khi `DngPaymentService::createAndPush()` thành công, hệ thống SHALL gửi email HTML bilingual (VI + EN) đến sinh viên theo mẫu đã duyệt, với subject format: `[Asia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí học kỳ {semester_code}`.

#### Scenario: Email có đầy đủ thông tin học phí
- **WHEN** khoản phí DNG được tạo thành công với `semester_id` và `due_date`
- **THEN** email gửi có: tên sinh viên + MSSV, tên chương trình, kỳ học, invoice code (item_id), số tiền (VND), hạn thanh toán, hướng dẫn 3 bước thanh toán qua portal

#### Scenario: Email không có hạn thanh toán khi due_date null
- **WHEN** `DngPaymentRequest.due_date` là null
- **THEN** email không hiển thị dòng "Hạn thanh toán" trong bảng chi tiết

#### Scenario: Email không có kỳ học khi semester_id null
- **WHEN** `DngPaymentRequest.semester_id` là null
- **THEN** subject bỏ phần " học kỳ {semester_code}"; body không hiển thị tên kỳ học

### Requirement: In-app notification ngắn gọn, độc lập với email
Khi khoản phí DNG được tạo thành công, hệ thống SHALL gửi realtime (in-app) notification với `title` ngắn và `body` ngắn — không derive từ email content.

#### Scenario: In-app notification hiển thị
- **WHEN** khoản phí DNG được tạo thành công
- **THEN** sinh viên nhận in-app notification với title "Yêu cầu thanh toán đã được tạo" và body có số tiền + kỳ học (nếu có) + hạn thanh toán (nếu có)

### Requirement: Event payload bổ sung dng_payment_request_id
`DngPaymentService::publishPushNotification()` SHALL đưa `dng_payment_request_id` vào `data` của payload để data builder query đủ context khi render email.

#### Scenario: Data builder có thể query DngPaymentRequest
- **WHEN** event `finance.dng_payment_pushed` được xử lý
- **THEN** `HandleOutboxEventAction` có thể query `DngPaymentRequest::with(['semester', 'student.program'])->find($data['dng_payment_request_id'])` để build email data

### Requirement: Retry email gửi đúng nội dung gốc
Khi delivery failed và được retry, email SHALL gửi lại với `rendered_subject`/`rendered_html` đã lưu từ lần render đầu tiên.

#### Scenario: Retry không thay đổi nội dung
- **WHEN** delivery email failed và RetryDeliveryAction chạy
- **THEN** email retry có cùng subject và HTML body như lần gửi đầu, kể cả khi `amount` hay `due_date` đã thay đổi trong DB
