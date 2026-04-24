## ADDED Requirements

### Requirement: Retake batch gom các case cùng unit+campus+semester

Hệ thống SHALL duy trì entity `retake_batches` để nhóm các `course_retake_cases` có cùng `unit_id + campus_id + semester_id` vào một đợt thi lại chung. Mỗi sinh viên chỉ có 1 retake case per unit+semester, và case phải thuộc về đúng batch tương ứng.

#### Scenario: Batch được đề xuất khi có case nomination đầu tiên

- **WHEN** GV nominate sinh viên đầu tiên cho unit+campus+semester chưa có batch
- **THEN** hệ thống tự động tạo `retake_batches` với status `proposed`, không cần Đào tạo thao tác

#### Scenario: Case tiếp theo tự động gắn vào batch đang tồn tại

- **WHEN** GV nominate sinh viên thứ hai cho cùng unit+campus+semester đã có batch
- **THEN** retake case mới được gắn `retake_batch_id` vào batch đã có (không tạo batch mới)

---

### Requirement: Đào tạo mở batch và nhập registration window

Hệ thống SHALL yêu cầu Đào tạo mở batch thủ công và nhập `registration_window_start`, `registration_window_end`, `batch_close_date` trước khi sinh viên có thể đăng ký.

#### Scenario: Đào tạo mở batch

- **WHEN** Đào tạo mở một batch ở trạng thái `proposed`
- **THEN** hệ thống yêu cầu nhập `registration_window_start`, `registration_window_end`, `batch_close_date`
- **AND** batch chuyển sang status `open`
- **AND** tất cả case trong batch đang ở `training_eligibility_approved` được chuyển sang `registration_opened`

#### Scenario: Không cho mở batch khi chưa có case được approved eligibility

- **WHEN** Đào tạo cố mở batch nhưng không có case nào ở status `training_eligibility_approved`
- **THEN** hệ thống từ chối và hiển thị thông báo

---

### Requirement: Batch close date là hard deadline cho charge và payment

Hệ thống SHALL enforce `batch_close_date` là hard ceiling cho mọi deadline trong batch.

#### Scenario: Payment deadline không được vượt batch close date

- **WHEN** HQ nhập `payment_deadline` khi tạo charge cho case trong batch
- **AND** `payment_deadline` > `batch_close_date`
- **THEN** hệ thống từ chối và yêu cầu nhập lại

#### Scenario: Batch đóng sau batch_close_date

- **WHEN** daily cron chạy và `batch_close_date` đã qua
- **AND** batch đang ở status `open`
- **THEN** hệ thống đóng batch (status → `closed`) và trigger auto-transition tất cả case chưa `paid` sang `repeat_course`

---

### Requirement: Batch có CourseOffering thi lại sau khi roster confirmed

Hệ thống SHALL liên kết `retake_batches.course_offering_id` với CourseOffering thi lại được tạo ở bước B4. Một batch chỉ có một CourseOffering thi lại.

#### Scenario: CourseOffering được tạo và gắn vào batch

- **WHEN** Đào tạo xác nhận roster và tạo CourseOffering thi lại
- **THEN** `retake_batches.course_offering_id` được cập nhật với ID của CourseOffering mới
