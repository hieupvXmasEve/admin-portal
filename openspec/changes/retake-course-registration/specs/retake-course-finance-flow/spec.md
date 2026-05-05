## ADDED Requirements

### Requirement: HQ review case approved và tạo charge

Hệ thống SHALL cho phép HQ Finance review retake registrations đã approved và tạo FinanceCharge + DNG request.

#### Scenario: HQ tạo charge thành công

- **WHEN** HQ mở màn hình review retake registrations ở status `approved`
- **THEN** form hiện thông tin SV, unit, CourseOffering, phí học lại (prefill từ `retake_fee`)
- **AND** HQ có thể sửa `amount` nếu cần (override), set `payment_deadline`
- **AND** sau khi submit:
  1. Tạo `FinanceCharge`:
     - `charge_type` = `TYPE_RETAKE_FEE`
     - `source_type` = `App\Models\CourseRetakeRegistration`
     - `source_id` = retake registration ID
     - `student_id`, `semester_id` = từ retake registration
     - `amount` = số tiền HQ xác nhận
     - `status` = `active`
  2. Tạo DNG payment request qua `DngPaymentService::createAndPush()`
  3. Cập nhật retake registration:
     - `status` = `payment_pending`
     - `finance_charge_id` = charge ID
     - `charge_created_by_user_id` = current user
     - `charge_created_at` = now()
     - `payment_deadline` = từ form

#### Scenario: HQ không thể tạo charge cho case không ở approved

- **WHEN** HQ cố tạo charge cho case ở status khác `approved`
- **THEN** hệ thống từ chối

---

### Requirement: Payment deadline validation

Hệ thống SHALL validate payment_deadline khi HQ tạo charge.

#### Scenario: Payment deadline trong tương lai

- **WHEN** HQ nhập payment_deadline >= today
- **THEN** hệ thống chấp nhận

#### Scenario: Payment deadline trong quá khứ

- **WHEN** HQ nhập payment_deadline < today
- **THEN** hệ thống từ chối với lỗi validation

---

### Requirement: Void charge khi cancel

Hệ thống SHALL tự động void charge khi retake registration bị cancel ở status `payment_pending`.

#### Scenario: Cancel với charge chưa paid

- **WHEN** staff cancel case ở `payment_pending`
- **THEN** gọi `VoidFinanceChargeAction` với reason `'retake_course_cancelled'`
- **AND** cancel DNG request nếu còn pending
- **AND** set case status = `cancelled`

---

### Requirement: HQ xem danh sách retake registrations cần tạo charge

Hệ thống SHALL cung cấp view cho HQ Finance xem danh sách retake registrations ở status `approved` chờ tạo charge.

#### Scenario: HQ xem worklist

- **WHEN** HQ truy cập trang retake course finance
- **THEN** thấy danh sách cases ở `approved` với: tên SV, unit, CourseOffering, retake_fee, ngày đăng ký
- **AND** filter theo: campus, semester
