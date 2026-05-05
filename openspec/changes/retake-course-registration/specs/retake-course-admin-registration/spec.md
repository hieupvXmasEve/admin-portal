## ADDED Requirements

### Requirement: Đào tạo đăng ký học lại cho SV (1 bước)

Hệ thống SHALL cung cấp màn hình Admin để Đào tạo đăng ký học lại cho SV. Đăng ký + confirm là 1 bước — không cần approval riêng.

#### Scenario: Đào tạo đăng ký thành công

- **WHEN** Đào tạo trên màn hình "Đăng ký học lại":
  1. Chọn SV từ danh sách eligible (hoặc search)
  2. Chọn Unit (từ danh sách unit fail của SV)
  3. Chọn CourseOffering đang mở cho unit đó
  4. Set registration_start_date, registration_end_date (optional)
  5. Nhập notes (optional)
  6. Submit
- **THEN** hệ thống tạo `course_retake_registrations` với:
  - `status = 'approved'`
  - `retake_fee` = snapshot `unit.retake_fee`
  - `attempt_number` = tính tự động
  - `approved_by_user_id` = current user
  - `approved_at` = now()
- **AND** thông báo thành công

#### Scenario: Đào tạo đăng ký SV đã có registration active → từ chối

- **WHEN** Đào tạo đăng ký SV đã có retake registration non-terminal cho cùng unit+semester
- **THEN** hệ thống hiện lỗi "Sinh viên đã có đăng ký học lại đang xử lý cho môn này"

#### Scenario: CourseOffering đã full → vẫn cho đăng ký

- **WHEN** Đào tạo chọn CourseOffering đã full (current_enrollment >= max_capacity)
- **THEN** hệ thống hiện warning nhưng vẫn cho phép đăng ký (admin override)

---

### Requirement: Màn hình quản lý danh sách retake registrations

Hệ thống SHALL cung cấp màn hình list với filter/search cho Đào tạo quản lý retake registrations.

#### Scenario: Đào tạo xem danh sách

- **WHEN** Đào tạo truy cập trang quản lý học lại
- **THEN** thấy danh sách retake registrations với:
  - Tên SV, MSSV
  - Unit code + name
  - CourseOffering (section_code — nullable, semester, campus)
  - Status (badge)
  - Phí học lại
  - Ngày đăng ký
  - Payment deadline
- **AND** filter theo: status, semester, campus, unit
- **AND** search theo: tên SV, MSSV, unit code

#### Scenario: Đào tạo cancel registration

- **WHEN** Đào tạo bấm cancel trên case ở status `approved` hoặc `payment_pending`
- **THEN** hệ thống yêu cầu nhập reason
- **AND** thực hiện cancel (void charge nếu có)
- **AND** cập nhật list
