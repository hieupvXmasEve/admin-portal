# Hệ Thống Email SMTP – Tài liệu hướng dẫn (VI)

Tài liệu này hướng dẫn chi tiết cách cấu hình, sử dụng và vận hành hệ thống Email SMTP đã được tích hợp vào ứng dụng, bao gồm: cấu hình SMTP, quản lý template, gửi email đơn/hàng loạt, thông báo tự động, theo dõi và giám sát, cùng các lưu ý bảo mật/hiệu năng.

Mục tiêu: giúp Admin và DevOps triển khai, còn Developer/QA có thể kiểm thử và vận hành trơn tru.

---

## 1. Kiến trúc và thành phần

Hệ thống tận dụng Mailer của Laravel (Symfony Mailer) + Queue để đảm bảo hiệu năng và độ tin cậy.

- Models:
  - EmailConfiguration: cấu hình SMTP (mã hóa mật khẩu, cấu hình active)
  - EmailTemplate: template email (HTML/Text, biến động, versioning)
  - EmailLog: nhật ký gửi email (status, thời điểm, error, batch)
  - UserEmailPreference: tùy chọn nhận thông báo của user
- Services:
  - SmtpConfigurationService: CRUD cấu hình SMTP + test kết nối
  - EmailTemplateService: CRUD + render/validate template
  - EmailService: gửi email đơn/hàng loạt, thống kê, logs
  - NotificationService: gửi thông báo học thuật theo sự kiện, lên lịch
- Jobs (Queue):
  - SendSingleEmailJob: gửi 1 email, retry với backoff
  - SendBulkEmailJob: chia nhỏ recipients theo chunk, đẩy SendSingleEmailJob
  - ProcessNotificationJob: xử lý thông báo theo lịch/sự kiện
- Mail:
  - GenericEmail: Mailable hiển thị nội dung HTML/Text, đính kèm
- API Controllers (v1/admin):
  - EmailConfigurationController
  - EmailTemplateController
  - EmailController
- Routes: routes/api.php → group v1/admin trong routes/api/v1/admin.php

Sơ đồ luồng (rút gọn):

Web/UI → API (Controllers) → Services → Queue (Jobs) → Laravel Mail → SMTP Server → Người nhận

---

## 2. Cấu hình SMTP

Bảng: email_configurations (đã migrate). Mật khẩu được mã hóa ở tầng Model.

API endpoints (yêu cầu auth + role admin):
- GET /api/v1/admin/email-configurations: danh sách + thống kê
- POST /api/v1/admin/email-configurations: tạo mới
- GET /api/v1/admin/email-configurations/{id}: chi tiết
- PUT /api/v1/admin/email-configurations/{id}: cập nhật
- DELETE /api/v1/admin/email-configurations/{id}: xóa (không xóa cấu hình đang active)
- POST /api/v1/admin/email-configurations/{id}/test: test kết nối SMTP
- POST /api/v1/admin/email-configurations/{id}/activate: đặt làm active
- GET /api/v1/admin/email-configurations/{id}/export: xuất cấu hình (không gồm mật khẩu)
- POST /api/v1/admin/email-configurations/import: import cấu hình

Ví dụ tạo cấu hình (cURL):

```
curl -X POST http://localhost:8000/api/v1/admin/email-configurations \  
  -H "Authorization: Bearer {{API_TOKEN}}" \  
  -H "Content-Type: application/json" \  
  -d '{
    "name":"SMTP Gmail",
    "host":"smtp.gmail.com",
    "port":587,
    "username":"no-reply@example.com",
    "password":"{{APP_PASSWORD}}",
    "encryption":"tls",
    "from_address":"no-reply@example.com",
    "from_name":"SwinX",
    "daily_limit":1000,
    "rate_limit":60
  }'
```

Test kết nối:
```
curl -X POST http://localhost:8000/api/v1/admin/email-configurations/1/test \
  -H "Authorization: Bearer {{API_TOKEN}}"
```

Kích hoạt cấu hình:
```
curl -X POST http://localhost:8000/api/v1/admin/email-configurations/1/activate \
  -H "Authorization: Bearer {{API_TOKEN}}"
```

Lưu ý bảo mật:
- Không commit mật khẩu thật vào repo. Dùng biến môi trường/secret manager.
- Mật khẩu được mã hóa tự động khi lưu (Crypt::encryptString).

---

## 3. Quản lý Template Email

Bảng: email_templates (có versioning, parent_id).

Tính năng:
- HTML và Plain Text
- Biến động dạng {{variable}}
- Versioning + rollback bằng tạo phiên bản mới
- Validate nội dung HTML cơ bản và biến động

Seeder mẫu: database/seeders/EmailTemplateSeeder.php (đã có: Welcome, Grade Notification, Course Registration, Academic Hold, Assessment Deadline, System Announcement).

API endpoints:
- GET /api/v1/admin/email-templates: danh sách + thống kê
- POST /api/v1/admin/email-templates: tạo mới
- GET /api/v1/admin/email-templates/{id}: chi tiết (parent/children)
- PUT /api/v1/admin/email-templates/{id}: cập nhật
- DELETE /api/v1/admin/email-templates/{id}: xóa
- POST /api/v1/admin/email-templates/{id}/version: tạo phiên bản mới
- POST /api/v1/admin/email-templates/{id}/preview: render thử với biến
- GET /api/v1/admin/email-templates/types: liệt kê loại template
- GET /api/v1/admin/email-templates/type/{type}: lấy theo loại
- POST /api/v1/admin/email-templates/validate: kiểm tra nội dung template

Ví dụ tạo template mới:
```
curl -X POST http://localhost:8000/api/v1/admin/email-templates \
  -H "Authorization: Bearer {{API_TOKEN}}" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Custom Notice",
    "type":"custom",
    "subject":"Thông báo: {{title}}",
    "html_content":"<p>Xin chào {{user_name}}, {{body}}</p>",
    "text_content":"Xin chào {{user_name}}, {{body}}",
    "description":"Thông báo tuỳ chỉnh"
  }'
```

Preview template:
```
curl -X POST http://localhost:8000/api/v1/admin/email-templates/10/preview \
  -H "Authorization: Bearer {{API_TOKEN}}" \
  -H "Content-Type: application/json" \
  -d '{"variables":{"user_name":"Hieu","title":"Bảo trì","body":"Hệ thống sẽ bảo trì lúc 22:00"}}'
```

---

## 4. Gửi Email

### 4.1 Gửi email đơn
Endpoint: POST /api/v1/admin/emails/send

Body:
- recipient (email, bắt buộc)
- subject (string)
- content (string) – nội dung fallback nếu không dùng template
- template_id (optional)
- attachments (multipart, optional)

Ví dụ:
```
curl -X POST http://localhost:8000/api/v1/admin/emails/send \
  -H "Authorization: Bearer {{API_TOKEN}}" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient":"user@example.com",
    "subject":"Thử gửi",
    "content":"Nội dung thử nghiệm"
  }'
```

### 4.2 Gửi email hàng loạt
Endpoint: POST /api/v1/admin/emails/send-bulk

Body:
- recipients (array email)
- subject, content
- template_id (optional)
- attachments (multipart, optional)
- chunk_size (mặc định 100, min 10, max 500)

Kết quả trả về batch_id để theo dõi.

### 4.3 Thông báo theo sự kiện & Lên lịch
- POST /api/v1/admin/emails/send-notification: truyền event_type + recipients + data để gửi theo template tương ứng (ví dụ: grades_published, course_registration …).
- POST /api/v1/admin/emails/schedule-reminder: đặt lịch gửi thông báo trong tương lai (schedule_time ISO-8601).

Ví dụ đặt lịch:
```
curl -X POST http://localhost:8000/api/v1/admin/emails/schedule-reminder \
  -H "Authorization: Bearer {{API_TOKEN}}" \
  -H "Content-Type: application/json" \
  -d '{
    "type":"assessment_deadline",
    "recipients":["student1@example.com","student2@example.com"],
    "schedule_time":"2025-08-20T10:00:00Z",
    "data":{
      "assessment_name":"Assignment 1",
      "course_name":"CS101",
      "deadline":"2025-08-21 23:59",
      "submission_link":"https://swinx.local/submit"
    }
  }'
```

### 4.4 Chạy queue worker
Để xử lý việc gửi email:
```
php artisan queue:work --queue=emails,bulk-emails,notifications
```
Khuyến nghị chạy Supervisor/PM2 cho môi trường production.

---

## 5. Theo dõi & Giám sát

### 5.1 Nhật ký và trạng thái
- Bảng email_logs lưu: recipient, subject, status (pending, queued, sending, sent, delivered, failed, bounced, rejected), thời điểm, lỗi, retry_count, batch_id, message_id…
- Endpoint lấy logs: GET /api/v1/admin/emails/logs (lọc theo status, recipient, batch_id, khoảng thời gian; phân trang)
- Xem chi tiết: GET /api/v1/admin/emails/logs/{emailLog}
- Retry email lỗi: POST /api/v1/admin/emails/logs/{emailLog}/retry

### 5.2 Thống kê
- GET /api/v1/admin/emails/statistics?start_date=...&end_date=...
- Trả về: total, sent, failed, pending, success_rate

### 5.3 Batch (hàng loạt)
- Gửi hàng loạt trả về batch_id. Có thể liên kết batch_id để lọc logs.

---

## 6. Tùy chọn người dùng (User Preferences)

Bảng: user_email_preferences
- notification_type: welcome, grade_notification, course_registration, academic_hold, enrollment_confirmation, assessment_deadline, system_announcement, reminder, all
- frequency: immediate, daily, weekly, never
- is_enabled: cho phép nhận

NotificationService sẽ kiểm tra preference trước khi gửi (trừ system_announcement mặc định bỏ qua tùy chọn để đảm bảo thông báo quan trọng).

---

## 7. Bảo mật, hiệu năng, độ tin cậy

- Bảo mật:
  - Mật khẩu SMTP mã hóa at-rest
  - Không log plaintext mật khẩu
  - Role-based access cho API (middleware auth + role:admin)
  - Validate định dạng email, kích thước file đính kèm (10MB/file)
- Hiệu năng:
  - Queue + batch (chunk) cho gửi hàng loạt
  - Retry với backoff: 60s, 300s, 900s
  - Timeout job: 60s (đơn), 300s (hàng loạt)
- Độ tin cậy:
  - Ghi log trạng thái chi tiết
  - Xác định lỗi tạm thời/permanent để retry/hủy hợp lý

---

## 8. Quy trình QA/Debug

- Kiểm tra cấu hình SMTP bằng /test trước khi gửi thật
- Dùng logs endpoint để theo dõi lỗi cụ thể (Authentication failed, Connection timed out, v.v.)
- Đảm bảo queue worker đang chạy, và redis/DB queue hoạt động
- Kiểm tra quyền và token khi gọi API

---

## 9. Phụ lục: Danh sách file chính

- app/Models/EmailConfiguration.php
- app/Models/EmailTemplate.php
- app/Models/EmailLog.php
- app/Models/UserEmailPreference.php
- app/Services/SmtpConfigurationService.php
- app/Services/EmailTemplateService.php
- app/Services/EmailService.php
- app/Services/NotificationService.php
- app/Jobs/SendSingleEmailJob.php
- app/Jobs/SendBulkEmailJob.php
- app/Jobs/ProcessNotificationJob.php
- app/Mail/GenericEmail.php
- app/Http/Controllers/Api/V1/Admin/* (3 controller)
- routes/api/v1/admin.php, routes/api.php
- database/migrations/*email* và jobs table
- database/seeders/EmailTemplateSeeder.php

---

## 10. Câu hỏi thường gặp (FAQ)

- Không thấy email gửi ra?
  - Kiểm tra cấu hình SMTP (test), kiểm tra queue worker, kiểm tra logs lỗi.
- Gửi hàng loạt bị chậm?
  - Giảm chunk_size, tăng số lượng queue workers, kiểm tra rate-limit của SMTP.
- Biến {{...}} không thay thế?
  - Kiểm tra biến đầu vào khi render/preview; đúng tên khóa không; template có biến bắt buộc nào thiếu.
- Lỗi xác thực SMTP (Authentication failed)?
  - Kiểm tra username/password/app-password, cơ chế bảo mật (TLS/SSL), port đúng chưa.

---

Tài liệu này thuộc phạm vi dự án SwinX. Mọi góp ý xin cập nhật trực tiếp vào file docs hoặc mở PR kèm chỉnh sửa.

