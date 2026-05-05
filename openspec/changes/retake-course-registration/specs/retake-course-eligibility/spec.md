## ADDED Requirements

### Requirement: Xác định SV đủ điều kiện đăng ký học lại

Hệ thống SHALL cung cấp query lọc danh sách sinh viên đủ điều kiện đăng ký học lại, dùng cho màn hình Đào tạo.

**Điều kiện đủ:**
1. `Student.status = 'intake_course'`
2. SV có `AcademicRecord` với `is_passed = false`, `completion_status != 'in_progress'` (finalized), và `override_pass` không phải `true` cho unit thuộc `curriculum_version` của SV
3. Unit đó có `CourseOffering` đang mở (`is_active = true`, `enrollment_status = 'open'`, `course_status` NOT IN `completed`/`cancelled`) trong kỳ hiện tại hoặc kỳ đăng ký
4. SV chưa có `course_retake_registrations` ở trạng thái non-terminal (approved/payment_pending/paid) cho cùng unit + semester đó

#### Scenario: SV fail 1 môn, có lớp mở → hiện trong danh sách

- **GIVEN** SV status = `intake_course`, có AcademicRecord failed cho Unit A
- **AND** Unit A có CourseOffering đang mở trong kỳ hiện tại
- **AND** SV chưa có retake registration active cho Unit A
- **THEN** SV xuất hiện trong danh sách eligible với Unit A và danh sách CourseOffering khả dụng

#### Scenario: SV fail nhưng không có lớp mở → không hiện

- **GIVEN** SV có AcademicRecord failed cho Unit B
- **AND** Unit B không có CourseOffering đang mở
- **THEN** SV không xuất hiện cho Unit B (hoặc hiện với note "Không có lớp mở")

#### Scenario: SV đã có retake registration active → không hiện

- **GIVEN** SV có AcademicRecord failed cho Unit A
- **AND** SV đã có `course_retake_registrations` status `approved` cho Unit A trong kỳ này
- **THEN** SV không xuất hiện cho Unit A (đã đăng ký)

#### Scenario: SV đã cancel retake registration → hiện lại cho kỳ mới

- **GIVEN** SV có retake registration `cancelled` cho Unit A kỳ trước
- **AND** Unit A có CourseOffering mở kỳ mới
- **THEN** SV xuất hiện cho Unit A (được đăng ký lại)

---

### Requirement: Tính attempt_number tự động

Hệ thống SHALL tự động tính `attempt_number` dựa trên số lần SV đã học unit đó (bao gồm lần gốc + các lần retake trước).

#### Scenario: Lần đầu học lại

- **GIVEN** SV fail Unit A lần đầu (AcademicRecord.attempt_number = 1)
- **WHEN** Đào tạo đăng ký học lại
- **THEN** `course_retake_registrations.attempt_number = 2`

#### Scenario: Lần thứ 2 học lại

- **GIVEN** SV đã fail Unit A 2 lần (attempt 1 + attempt 2 đều fail)
- **WHEN** Đào tạo đăng ký học lại lần 3
- **THEN** `course_retake_registrations.attempt_number = 3`
