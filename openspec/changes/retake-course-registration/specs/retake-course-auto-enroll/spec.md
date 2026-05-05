## ADDED Requirements

### Requirement: Auto-enroll khi DNG webhook xác nhận thanh toán

Hệ thống SHALL tự động tạo `CourseRegistration` và chuyển retake registration sang `enrolled` khi nhận webhook thanh toán thành công từ DNG cho charge liên kết.

#### Scenario: Thanh toán thành công → auto-enroll

- **WHEN** DNG webhook xác nhận payment cho FinanceCharge có `source_type = CourseRetakeRegistration`
- **THEN** trong transaction:
  1. Retake registration: `status` = `paid`, `paid_at` = now()
  2. Tạo `CourseRegistration`:
     - `student_id` = retake_reg.student_id
     - `course_offering_id` = retake_reg.course_offering_id
     - `semester_id` = retake_reg.semester_id
     - `registration_status` = `confirmed`
     - `registration_date` = now()
     - `registration_method` = `admin_override`
     - `is_retake` = true
     - `attempt_number` = retake_reg.attempt_number
     - `retake_fee` = retake_reg.retake_fee
     - `is_retake_paid` = true
     - `credit_points` = unit.credit_points
     - `credit_hours` = unit.credit_hours
  3. CourseOffering: increment `current_enrollment`
  4. Retake registration: `status` = `enrolled`, `enrolled_at` = now(), `course_registration_id` = new CR id

#### Scenario: CourseOffering đã full khi auto-enroll

- **WHEN** DNG webhook arrive và CourseOffering đã full (current_enrollment >= max_capacity)
- **THEN** hệ thống vẫn enroll (admin_override) và log warning
- **AND** CourseOffering.current_enrollment vượt max_capacity

#### Scenario: Auto-enroll fail (exception)

- **WHEN** tạo CourseRegistration gặp lỗi (ví dụ DB constraint)
- **THEN** retake registration stuck ở status `paid` (payment đã ghi nhận)
- **AND** log error để staff phát hiện và enroll thủ công
- **NOTE** Payment status (paid) và enrollment status tách biệt — không rollback payment khi enrollment fail

---

### Requirement: Retake registration hiện trên danh sách sau khi enrolled

Hệ thống SHALL giữ retake registration ở status `enrolled` với link tới CourseRegistration để audit trail.

#### Scenario: Truy vết từ retake registration đến enrollment

- **GIVEN** retake registration ở status `enrolled`
- **THEN** `course_registration_id` link tới CourseRegistration đã tạo
- **AND** CourseRegistration có `is_retake = true` để downstream systems nhận biết

---

### Requirement: Downstream systems hoạt động bình thường với retake enrollment

Hệ thống SHALL đảm bảo CourseRegistration tạo từ retake course workflow hoạt động giống enrollment thông thường với tất cả downstream systems.

#### Scenario: Attendance tracking

- **GIVEN** SV được enrolled qua retake course workflow
- **THEN** SV xuất hiện trong danh sách attendance của ClassSession thuộc CourseOffering đó

#### Scenario: Grading

- **GIVEN** SV được enrolled qua retake course workflow
- **THEN** SV xuất hiện trong danh sách grading của CourseOffering
- **AND** AcademicRecord mới được tạo khi course complete với `is_repeat_course = true`, `attempt_number` tương ứng

#### Scenario: Canvas sync

- **GIVEN** SV được enrolled qua retake course workflow
- **THEN** CanvasGradeSyncService nhận diện SV qua CourseRegistration và sync bình thường
