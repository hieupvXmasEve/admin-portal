## ADDED Requirements

### Requirement: Retake case entity tồn tại như source of truth

Hệ thống SHALL tạo và duy trì entity `course_retake_cases` là source of truth duy nhất cho toàn bộ lifecycle thi lại của một sinh viên với một unit trong một semester. Entity phải lưu đủ dữ liệu để audit toàn bộ lifecycle mà không cần join sang `course_registrations`.

#### Scenario: Retake case được tạo từ nomination

- **WHEN** GV nominate một sinh viên fail
- **THEN** hệ thống tạo một `course_retake_cases` với status `training_eligibility_pending`, liên kết `original_academic_record_id` tới record fail gốc, `student_id`, `unit_id`, `semester_id`, `campus_id`

#### Scenario: Không tạo duplicate case cho cùng student+unit+semester

- **WHEN** GV cố nominate sinh viên đã có case đang mở cho cùng unit+semester
- **THEN** hệ thống từ chối và thông báo case đã tồn tại

---

### Requirement: State machine enforcement

Hệ thống SHALL chỉ cho phép các transitions hợp lệ theo state machine đã định nghĩa. Mọi transition phải ghi lại timestamp và user thực hiện (hoặc system nếu là auto-transition).

Valid transitions (canonical — đây là source of truth duy nhất cho state machine):
```
# not_nominated: tạo khi GV chọn không nominate (terminal ngay lập tức)
[new] → not_nominated (TERMINAL — GV tạo để audit, không có transition tiếp)

training_eligibility_pending → training_eligibility_approved
training_eligibility_pending → training_rejected (terminal)
training_eligibility_approved → registration_opened
registration_opened → student_registered
registration_opened → repeat_course (auto: hết window, SV không đăng ký)
student_registered → finance_review_pending
finance_review_pending → charge_created
finance_review_pending → repeat_course (auto: hết window, chưa có charge)
charge_created → payment_pending
payment_pending → paid
payment_pending → repeat_course (auto: quá payment_deadline)
paid → waiting_listed
waiting_listed → roster_confirmed
roster_confirmed → scheduled
scheduled → manual_score_pending_review (khi Canvas không sync được)
scheduled → assessed (khi Canvas sync thành công)
manual_score_pending_review → assessed (Đào tạo approve manual score)
manual_score_pending_review → scheduled (Đào tạo reject → GV nhập lại)
assessed → final_pass (TERMINAL — không có transition tiếp)
assessed → final_fail (transitional → auto-transition ngay sang repeat_course)
assessed → no_show (transitional → auto-transition ngay sang repeat_course)
final_fail → repeat_course (TERMINAL — đây là lần transition duy nhất còn lại)
no_show → repeat_course (TERMINAL — đây là lần transition duy nhất còn lại)

# repeat_course là terminal duy nhất cho mọi nhánh fail
# (overdue, final_fail, no_show đều kết thúc ở đây)
repeat_course → [không có transition tiếp]

# auto_closed áp dụng cho MỌI active state (kể cả paid/waiting_listed/roster_confirmed/scheduled)
# Charge chưa paid → void charge; Charge đã paid → không rollback, staff xử lý tay
any_non_terminal_state → auto_closed (TERMINAL, khi attempt gốc được override_pass = true)

# Staff manual transition sang repeat_course (chỉ từ active states, không phải từ terminal)
any_non_terminal_state → repeat_course (manual staff với reason_code + comment bắt buộc)
```

**Lưu ý về final_fail / no_show**: Đây là transitional states (không phải terminal) — chỉ tồn tại để record kết quả thi ("đã thi và fail" / "vắng thi") trước khi tự động sang `repeat_course`. `repeat_course` là terminal duy nhất của nhánh fail, là source of truth duy nhất cho báo cáo "Học lại".

**Lưu ý về not_nominated**: Khi GV chọn không nominate, hệ thống TẠO một `RetakeCase` với status `not_nominated` ngay lập tức (terminal). Lý do: dùng lại entity sẵn có để audit quyết định GV mà không cần bảng riêng. `not_nominated` case ghi lại `nominated_by_user_id` (GV), `nominated_at`, `nomination_reason` (lý do không nominate). Case này KHÔNG đi vào workflow approval.

#### Scenario: Transition hợp lệ được chấp nhận

- **WHEN** user thực hiện transition từ `registration_opened` sang `student_registered`
- **THEN** hệ thống cập nhật `status`, ghi lại `student_registered_at` = now()

#### Scenario: Transition không hợp lệ bị từ chối

- **WHEN** hệ thống nhận request transition từ `charge_created` sang `final_pass` (không hợp lệ)
- **THEN** hệ thống throw exception và không thay đổi status

---

### Requirement: Auto-transition sang repeat_course khi quá deadline

Hệ thống SHALL tự động chuyển case sang `repeat_course` khi:
1. `registration_opened` + hết `retake_batch.registration_window_end` + SV chưa đăng ký
2. `student_registered` hoặc `finance_review_pending` + hết `retake_batch.registration_window_end` + chưa có charge
3. `payment_pending` + quá `payment_deadline` + chưa paid

#### Scenario: Auto-transition overdue qua daily cron

- **WHEN** daily cron `TransitionOverdueRetakeCasesCommand` chạy lúc 1:00 AM
- **THEN** tất cả cases thỏa điều kiện overdue được transition sang `repeat_course`, ghi lại `fallback_reason_code` chuẩn hóa và `fallback_at`

#### Scenario: Staff manual trigger override

- **WHEN** staff bấm "Chuyển Học lại" thủ công trên case đang overdue
- **THEN** hệ thống yêu cầu nhập `fallback_reason_code` và `fallback_comment`, sau đó transition sang `repeat_course`

---

### Requirement: Auto-close khi attempt gốc được sửa thành pass

Hệ thống SHALL tự động đóng retake case khi attempt gốc của sinh viên được override thành pass trong lúc case đang ở bất kỳ non-terminal state nào. `auto_closed` áp dụng cho mọi active state — hành động phụ thuộc vào state hiện tại của case.

#### Scenario: Auto-close với case chưa có charge hoặc charge chưa paid

- **WHEN** `academic_records.override_pass` được set = true cho record gốc của case
- **AND** case đang ở bất kỳ state nào từ `training_eligibility_pending` đến `payment_pending` (chưa paid)
- **AND** case có charge liên kết (nullable)
- **THEN** nếu có charge → void charge (`VoidFinanceChargeAction`)
- **AND** set case → `auto_closed` với `fallback_reason_code = 'original_attempt_passed'`

#### Scenario: Auto-close với case đã paid hoặc sau paid (waiting_listed, roster_confirmed, scheduled)

- **WHEN** `academic_records.override_pass` được set = true cho record gốc của case
- **AND** case đang ở state `paid`, `waiting_listed`, `roster_confirmed`, hoặc `scheduled`
- **THEN** set case → `auto_closed` với `fallback_reason_code = 'original_attempt_passed_charge_paid'`
- **AND** KHÔNG rollback tài chính — ghi chú để staff xử lý tay refund nếu cần

---

### Requirement: repeat_course branch chỉ đổi status

Hệ thống SHALL chỉ đổi status sang `repeat_course` và lưu audit trail khi case vào nhánh học lại. Hệ thống KHÔNG tự động tạo workflow học lại tiếp theo.

#### Scenario: Case vào repeat_course

- **WHEN** case được transition sang `repeat_course` (auto hoặc manual)
- **THEN** hệ thống lưu `fallback_reason_code`, `fallback_comment` (nếu manual), `fallback_at` = now()
- **AND** không tạo bất kỳ entity nào khác tự động

---

### Requirement: Audit trail đầy đủ cho mọi transition

Hệ thống SHALL lưu đủ dữ liệu audit trên entity `course_retake_cases` để reconstruct toàn bộ lịch sử mà không cần bảng audit riêng trong MVP.

#### Scenario: Transition có actor

- **WHEN** staff thực hiện một transition
- **THEN** hệ thống ghi lại `*_by_user_id` và `*_at` timestamp tương ứng với bước đó

#### Scenario: Auto-transition

- **WHEN** system tự động transition case
- **THEN** hệ thống ghi lại timestamp với `*_by_user_id = null` và `fallback_reason_code` chuẩn hóa
