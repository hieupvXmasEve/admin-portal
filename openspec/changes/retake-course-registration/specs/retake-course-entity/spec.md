## ADDED Requirements

### Requirement: Entity `course_retake_registrations` là source of truth cho workflow học lại

Hệ thống SHALL tạo và duy trì entity `course_retake_registrations` để track toàn bộ lifecycle đăng ký học lại từ lúc Đào tạo đăng ký đến khi SV chính thức enroll vào lớp.

#### Scenario: Record được tạo khi Đào tạo đăng ký

- **WHEN** Đào tạo submit form đăng ký học lại cho SV
- **THEN** hệ thống tạo `course_retake_registrations` với status `approved`, liên kết `student_id`, `unit_id`, `original_academic_record_id` (record fail), `course_offering_id` (lớp mở), `semester_id`, `campus_id`
- **AND** `retake_fee` = snapshot từ `unit.retake_fee` tại thời điểm tạo
- **AND** `approved_by_user_id` = user hiện tại, `approved_at` = now()

#### Scenario: Không tạo duplicate cho cùng student+unit+semester đang active

- **WHEN** Đào tạo đăng ký SV đã có record non-terminal (approved/payment_pending/paid) cho cùng unit+semester
- **THEN** hệ thống từ chối và thông báo đã tồn tại
- **NOTE** Nếu record cũ đã cancelled, cho phép tạo record mới

---

### Requirement: State machine enforcement

Hệ thống SHALL chỉ cho phép transitions hợp lệ. Mọi transition phải ghi timestamp.

**Canonical states:**
```
approved          — Đào tạo đã đăng ký + confirm (initial state)
payment_pending   — HQ đã tạo FinanceCharge + DNG request
paid              — DNG webhook xác nhận thanh toán
enrolled          — CourseRegistration đã được tạo (TERMINAL)
cancelled         — Hủy bất kỳ bước nào (TERMINAL)
```

**Valid transitions:**
```
approved         → payment_pending
approved         → cancelled
payment_pending  → paid
payment_pending  → cancelled       (void charge + cancel DNG)
paid             → enrolled        (auto: tạo CourseRegistration)
```

**Cancel scope:**
- `approved`: chỉ set cancelled
- `payment_pending`: void FinanceCharge + cancel DNG request trước khi set cancelled
- `paid` / `enrolled`: KHÔNG cho phép cancel

#### Scenario: Transition hợp lệ

- **WHEN** HQ tạo charge cho case ở status `approved`
- **THEN** case chuyển sang `payment_pending`, ghi `charge_created_by_user_id` + `charge_created_at`

#### Scenario: Transition không hợp lệ

- **WHEN** hệ thống nhận request transition từ `approved` sang `enrolled` (skip payment)
- **THEN** hệ thống throw exception, không thay đổi status

#### Scenario: Cancel case đang payment_pending

- **WHEN** staff cancel case ở `payment_pending`
- **THEN** hệ thống void FinanceCharge (`VoidFinanceChargeAction`), cancel DNG request nếu còn pending
- **AND** set status = `cancelled`, ghi `cancelled_by_user_id`, `cancelled_at`, `cancellation_reason` (bắt buộc)

#### Scenario: Không cho cancel case đã paid

- **WHEN** staff cố cancel case ở status `paid` hoặc `enrolled`
- **THEN** hệ thống từ chối — staff phải xử lý CourseRegistration/refund bằng tools hiện tại

---

### Requirement: Audit trail trên entity

Hệ thống SHALL lưu đủ timestamp + actor cho mỗi bước workflow trực tiếp trên entity.

#### Scenario: Full lifecycle audit

- **GIVEN** case đi qua approved → payment_pending → paid → enrolled
- **THEN** entity có đủ: `approved_by_user_id` + `approved_at`, `charge_created_by_user_id` + `charge_created_at`, `paid_at`, `enrolled_at` + `course_registration_id`
