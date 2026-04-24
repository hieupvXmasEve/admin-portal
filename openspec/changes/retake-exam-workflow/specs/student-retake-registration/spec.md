## ADDED Requirements

### Requirement: Student Portal hiển thị CTA đăng ký thi lại đúng điều kiện

Hệ thống SHALL chỉ hiển thị nút/thông báo đăng ký thi lại cho sinh viên khi:
1. Sinh viên có retake case ở status `registration_opened`
2. Thời điểm hiện tại nằm trong `retake_batch.registration_window_start` đến `registration_window_end`

#### Scenario: Sinh viên thấy CTA đăng ký

- **WHEN** sinh viên đăng nhập portal
- **AND** có case ở status `registration_opened` trong registration window hiện tại
- **THEN** portal hiển thị thông báo và nút "Đăng ký thi lại" cho unit tương ứng

#### Scenario: Sinh viên không thấy CTA ngoài registration window

- **WHEN** sinh viên có case `registration_opened` nhưng chưa đến `registration_window_start`
- **THEN** portal KHÔNG hiển thị CTA đăng ký

---

### Requirement: Sinh viên đăng ký và không thể tự hủy

Hệ thống SHALL cho phép sinh viên đăng ký một lần qua Portal. Sau khi đăng ký, sinh viên không có quyền tự hủy — chỉ staff mới có quyền hủy/withdraw.

#### Scenario: Sinh viên đăng ký thành công

- **WHEN** sinh viên bấm đăng ký trong registration window
- **THEN** case chuyển sang `student_registered` rồi ngay lập tức sang `finance_review_pending`
- **AND** sinh viên nhận notification xác nhận đã đăng ký

#### Scenario: Sinh viên không thấy nút hủy sau đăng ký

- **WHEN** sinh viên đã đăng ký (case ở `finance_review_pending` hoặc sau)
- **THEN** portal không hiển thị nút hủy, chỉ hiển thị trạng thái hiện tại

#### Scenario: Đăng ký sau khi hết registration window bị từ chối

- **WHEN** sinh viên cố đăng ký sau `registration_window_end`
- **THEN** hệ thống trả về lỗi "Đã hết thời hạn đăng ký"

---

### Requirement: Sinh viên theo dõi trạng thái retake case trên Portal

Hệ thống SHALL cung cấp API cho Student Portal để sinh viên xem trạng thái hiện tại của retake case, charge, và payment status.

#### Scenario: Sinh viên xem trạng thái case

- **WHEN** sinh viên truy cập trang lịch sử học tập / thi lại trên portal
- **THEN** thấy danh sách retake cases với status hiện tại, số tiền (nếu có charge), payment deadline, và trạng thái thanh toán

#### Scenario: Sinh viên thấy charge sau khi HQ tạo

- **WHEN** HQ đã tạo charge cho case của sinh viên
- **THEN** portal hiển thị số tiền phải trả, due date, và link/QR để thanh toán qua DNG
