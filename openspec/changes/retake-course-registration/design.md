## Context

Hệ thống đã có các mảnh liên quan:
- `CourseRegistration`: có `is_retake`, `attempt_number`, `retake_fee`, `is_retake_paid` — enrollment record, không có approval/payment lifecycle
- `AcademicRecord`: có `is_passed` (bool), `override_pass` (bool, admin override), `completion_status` (in_progress/completed/failed — grade finalization, NOT pass/fail), `attempt_number`, `original_record_id` — kết quả học tập. Pass/fail logic dùng `is_passed` + `override_pass`, KHÔNG dùng `completion_status`
- `FinanceCharge`: có `TYPE_RETAKE_FEE`, morphable `source` — sẵn sàng link với entity mới
- `Unit`: có `retake_fee` (decimal:2) — default amount cho phí học lại
- `CourseOffering`: có `section_code` (nullable varchar), `enrollment_status`, `course_status`, `registration_start_date`, `registration_end_date`, `is_active`, `campus_id`, `lecture_id` — lớp mở cho enrollment. Appended: `course_code` (from unit.code), `course_title` (from unit.name). Relations: `semester`, `campus`, `lecture`, `unit`
- `Student`: dùng `student_id` (varchar) cho MSSV, KHÔNG phải `student_code`
- `DngPaymentService`: flow tạo DNG request + push debt — tái sử dụng trực tiếp
- `DngWebhookService`: xử lý webhook thanh toán — hook thêm logic auto-enroll
- `VoidFinanceChargeAction`: void charge — tái sử dụng khi cancel

Khác biệt với `retake-exam-workflow`:
- Retake-exam: GV nominate → multi-role approval → tạo CourseOffering riêng → thi → chấm điểm (15+ states)
- Retake-course: Đào tạo đăng ký → HQ thu phí → SV enroll lớp bình thường (5 states)
- Hai workflow độc lập, không share entity

Stakeholders: Đào tạo (đăng ký + confirm), HQ Finance (tạo charge), Hệ thống (auto-enroll khi paid).

## Goals / Non-Goals

**Goals:**
- Entity `course_retake_registrations` với state machine 5 states đơn giản
- Đào tạo đăng ký + confirm 1 bước (không chia pending/approved)
- HQ tạo charge + DNG request, SV thanh toán qua DNG
- Auto-enroll khi thanh toán thành công (tạo CourseRegistration tự động)
- Tái sử dụng tối đa: FinanceCharge, DngPaymentService, CourseRegistration, downstream systems
- Registration period (start/end date) set trên từng record
- Eligibility: tất cả unit trong curriculum version, không giới hạn unit_scope

**Non-Goals:**
- Không tạo Student Portal API trong phase 1 (phase 2)
- Không tạo batch management (khác retake-exam dùng retake_batches)
- Không tạo CourseOffering riêng — enroll vào lớp đang mở bình thường
- Không auto-transition qua cron (workflow đủ đơn giản để staff thao tác thủ công)
- Không cover SV status khác ngoài `intake_course`
- Không tích hợp với retake-exam-workflow entity

## Decisions

### D1: Entity riêng `course_retake_registrations` thay vì mở rộng `course_registrations`

**Decision**: Entity riêng.

**Rationale**: `CourseRegistration` là enrollment record — downstream systems (attendance, grading, Canvas sync) đọc từ đây. Thêm approval/payment workflow vào sẽ phức tạp hóa enrollment logic. Entity riêng cho phép state machine rõ ràng, audit trail đầy đủ, và tạo `CourseRegistration` làm output cuối cùng khi workflow hoàn tất.

**Alternative considered**: Thêm status/flags vào `CourseRegistration` → bị loại vì làm ô nhiễm enrollment record với workflow state.

---

### D2: State machine 5 states

**Canonical states:**
```
approved          — Đào tạo đã đăng ký + confirm (initial state)
payment_pending   — HQ đã tạo FinanceCharge + DNG request
paid              — DNG webhook xác nhận thanh toán thành công
enrolled          — CourseRegistration đã được tạo (TERMINAL)
cancelled         — Hủy ở bất kỳ bước nào (TERMINAL)
```

**Valid transitions:**
```
approved         → payment_pending   (HQ tạo charge)
approved         → cancelled         (Đào tạo hủy trước khi có charge)
payment_pending  → paid              (DNG webhook confirm payment)
payment_pending  → cancelled         (quá hạn hoặc staff hủy → void charge + DNG)
paid             → enrolled          (auto: tạo CourseRegistration)
any_non_terminal → cancelled         (staff manual cancel với reason bắt buộc)
```

**Lưu ý**: `paid → enrolled` là auto-transition — khi DNG webhook confirm, hệ thống tự động tạo CourseRegistration và chuyển case sang enrolled. Không cần bước thủ công.

**Rationale**: So với retake-exam (15+ states, multi-role), workflow này thẳng và chỉ có 2 roles thao tác (Đào tạo, HQ). 5 states đủ để track và audit mà không over-engineer.

---

### D3: Đào tạo đăng ký + confirm 1 bước

**Decision**: Không có state `pending`. Khi Đào tạo submit form, record được tạo với status `approved` ngay.

**Rationale**: Đào tạo vừa là người đăng ký vừa là người confirm. Tách 2 bước (pending → approved) không có giá trị khi cùng 1 role. Giảm 1 state, giảm 1 UI action.

---

### D4: Auto-enroll khi paid

**Decision**: Khi DNG webhook xác nhận thanh toán:
1. Case chuyển `payment_pending → paid`
2. Hệ thống tạo `CourseRegistration` mới:
   - `student_id` = case.student_id
   - `course_offering_id` = case.course_offering_id
   - `semester_id` = case.semester_id
   - `registration_status` = 'confirmed'
   - `is_retake` = true
   - `attempt_number` = case.attempt_number
   - `original_registration_id` = original record's registration (nếu có)
   - `registration_method` = 'admin_override'
   - `credit_points` / `credit_hours` = từ unit
3. Case chuyển `paid → enrolled`, ghi `enrolled_at` + `course_registration_id`
4. CourseOffering `current_enrollment` được increment

**Rationale**: Đào tạo đã approve, HQ đã tạo charge, SV đã trả tiền — không cần chặn enrollment thêm. Auto-enroll giảm manual work và đảm bảo SV không bị delay enrollment sau khi đã thanh toán.

**Edge case**: Nếu CourseOffering đã full khi webhook arrive → enroll anyway (admin_override method), log warning. Đào tạo đã chọn lớp này khi đăng ký, lớp full sau đó là trường hợp hiếm và staff handle thủ công.

---

### D5: Cancel logic và void charge

**Decision**: Khi cancel:
- Nếu case ở `approved` (chưa có charge): chỉ set cancelled, không cần void gì
- Nếu case ở `payment_pending` (có charge chưa paid): void charge (`VoidFinanceChargeAction`) + cancel DNG request
- Case đã `paid` hoặc `enrolled`: KHÔNG cho phép cancel — staff phải xử lý tay CourseRegistration/refund

**Rationale**: Sau khi paid, CourseRegistration đã tồn tại. Cancel lúc này sẽ cần rollback enrollment + refund — quá phức tạp cho MVP. Staff dùng existing tools để handle.

---

### D6: Unique constraint student+unit+semester

**Decision**: `UNIQUE(student_id, unit_id, semester_id)` — chỉ 1 retake registration cho cùng SV+unit trong 1 kỳ.

**Rationale**: Không có lý do SV đăng ký học lại cùng 1 unit 2 lần trong 1 kỳ. Nếu cancel, SV có thể đăng ký lại kỳ sau.

**Edge case**: Nếu Đào tạo cancel rồi muốn đăng ký lại cùng kỳ → unique constraint sẽ block. Giải pháp: unique constraint chỉ apply cho non-terminal states (partial unique index hoặc soft check trong Action).

---

### D7: FinanceCharge integration

**Decision**: Dùng `FinanceCharge` morphable source:
- `charge_type` = `FinanceCharge::TYPE_RETAKE_FEE`
- `source_type` = `App\Models\CourseRetakeRegistration`
- `source_id` = retake registration ID
- `amount` = snapshot từ `unit.retake_fee` tại thời điểm tạo charge
- `student_id`, `semester_id` = từ retake registration

DNG request tạo qua `DngPaymentService::createAndPush()` với `fee_type = 'retake_fee'`.

**Rationale**: Tái sử dụng hoàn toàn infrastructure tài chính hiện tại. Không cần thêm charge type mới.

---

### D8: Registration period trên từng record

**Decision**: Mỗi `course_retake_registration` có `registration_start_date`, `registration_end_date`, `payment_deadline` riêng. Không dùng batch entity.

**Rationale**: Khác retake-exam (cần batch để gom cases cùng unit+campus+term), retake-course là từng case riêng lẻ. Đào tạo set deadline khi tạo record. Đơn giản, không cần thêm entity.

## Risks / Trade-offs

**[Risk] CourseOffering full khi auto-enroll** → Mitigation: Enroll anyway với `registration_method = 'admin_override'`, log warning. Đào tạo đã chọn lớp này — hiếm khi lớp full giữa chừng.

**[Risk] DNG webhook fail → paid nhưng không enroll** → Mitigation: Tách 2 steps trong webhook handler: update status trước (trong transaction), tạo CourseRegistration sau. Nếu step 2 fail, case stuck ở `paid` — staff thấy trên list và enroll thủ công.

**[Risk] Unique constraint block re-registration sau cancel** → Mitigation: Partial unique index `WHERE status NOT IN ('cancelled')` hoặc soft check trong Action (cho phép tạo mới nếu existing record là cancelled).

**[Trade-off] Không có Student Portal trong phase 1** → SV phải liên hệ Đào tạo để đăng ký. Chấp nhận được vì đây là admin workflow, phase 2 sẽ thêm API.

**[Trade-off] Không auto-cancel overdue cases qua cron** → Staff phải cancel thủ công khi quá hạn. Chấp nhận được vì volume thấp và workflow đơn giản.

## Migration Plan

1. **Deploy**: Additive only — 1 migration tạo bảng mới, không alter existing tables
2. **Feature flag**: Có thể wrap UI sau `retake_course_registration_enabled` trong `config/features.php` nếu cần rollout dần
3. **Rollback**: Drop 1 bảng — không ảnh hưởng data hiện tại
4. **DNG webhook**: Thêm handler trong `DngWebhookService` cho source_type = CourseRetakeRegistration
