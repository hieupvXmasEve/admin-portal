## ADDED Requirements

### Requirement: HQ review từng case và tạo charge trong registration window

Hệ thống SHALL chỉ cho phép HQ Finance tạo charge cho case khi case ở status `finance_review_pending` và thời điểm hiện tại còn trong `retake_batch.registration_window_end`.

#### Scenario: HQ tạo charge thành công

- **WHEN** HQ mở màn hình review case `finance_review_pending` trong registration window
- **THEN** form prefill `amount` từ `unit.retake_fee`, cho phép HQ sửa `amount` và `payment_deadline`
- **AND** sau khi submit, hệ thống tạo `FinanceCharge` với `source_type = RetakeCase`, `source_id = retake_case.id`
- **AND** case chuyển sang `charge_created` rồi `payment_pending`

#### Scenario: HQ không thể tạo charge sau khi hết registration window

- **WHEN** HQ cố tạo charge cho case sau `registration_window_end`
- **THEN** hệ thống từ chối với thông báo "Đã hết thời hạn cửa sổ đăng ký"

#### Scenario: Payment deadline không vượt batch close date

- **WHEN** HQ nhập `payment_deadline` > `retake_batch.batch_close_date`
- **THEN** hệ thống từ chối và báo lỗi validation

---

### Requirement: Charge được link với retake case qua morphable source

Hệ thống SHALL tạo `FinanceCharge` với `source_type = 'App\Models\RetakeCase'` và `source_id = retake_case.id` để đảm bảo traceability giữa charge và case.

#### Scenario: Charge có đúng source

- **WHEN** HQ tạo charge cho retake case
- **THEN** `finance_charges.source_type = 'App\Models\RetakeCase'`, `finance_charges.source_id = retake_case.id`

---

### Requirement: DNG request được tạo cho charge thi lại

Hệ thống SHALL tạo DNG payment request cho charge thi lại, theo đúng flow hiện tại của `DngPaymentController`.

#### Scenario: DNG request được tạo sau charge

- **WHEN** charge được tạo cho retake case
- **THEN** hệ thống tạo DNG payment request liên kết với charge, sinh viên có thể thanh toán qua Portal

---

### Requirement: Case tự động chuyển sang paid khi DNG/payment confirmed

Hệ thống SHALL cập nhật retake case sang status `paid` → `waiting_listed` khi hệ thống nhận webhook thanh toán thành công từ DNG.

#### Scenario: Thanh toán thành công

- **WHEN** DNG webhook xác nhận thanh toán thành công cho charge của retake case
- **THEN** case chuyển từ `payment_pending` sang `paid`, `paid_at` = now()
- **AND** sau đó tự động sang `waiting_listed`
- **AND** sinh viên nhận notification "Thanh toán thành công, đang chờ xếp lịch thi"

#### Scenario: Không thanh toán đúng hạn

- **WHEN** `payment_deadline` đã qua và case vẫn ở `payment_pending`
- **AND** daily cron chạy
- **THEN** case chuyển sang `repeat_course` với `fallback_reason_code = 'payment_overdue'`

---

### Requirement: Void charge khi case auto-closed

Hệ thống SHALL tự động void charge (nếu có và chưa paid) khi retake case bị auto-closed do attempt gốc được sửa thành pass.

#### Scenario: Void charge khi auto-close

- **WHEN** retake case bị auto-closed
- **AND** case có charge ở trạng thái chưa paid
- **THEN** `VoidFinanceChargeAction` được gọi với reason `'retake_case_auto_closed'`
- **AND** DNG request tương ứng (nếu có, chưa paid) cũng được hủy
