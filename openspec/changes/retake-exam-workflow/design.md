## Context

Hệ thống hiện có các mảnh liên quan đến retake nhưng không có workflow thống nhất:
- `CourseRegistration`: có `attempt_number`, `is_retake`, `retake_fee`, `is_retake_paid` — chỉ là enrollment flags, không có lifecycle
- `AcademicRecord`: có `attempt_number`, `original_record_id` — lưu kết quả nhưng không track approval/payment
- `FinanceCharge`: có `TYPE_RETAKE_FEE`, morphable source — đã sẵn sàng để link với retake case
- `Unit`: có `retake_fee` — default amount cho HQ
- `FailedStudentsService`: có danh sách sinh viên fail — điểm bắt đầu cho nomination
- `CourseCompletionService`: finalize course — integration point cho B1
- `VoidFinanceChargeAction`: đã có — tái sử dụng cho auto-void khi auto-close

Không có `CourseRetakeService` nào còn dùng được — service cũ có schema drift, bỏ qua.

Stakeholders: GV (B1), Đào tạo (B2, B4), Sinh viên (B3), HQ Finance (B3), Hệ thống/cron (auto-transitions).

## Goals / Non-Goals

**Goals:**
- Xây dựng entity `course_retake_cases` làm source of truth với state machine đầy đủ
- Xây dựng entity `retake_batches` để gom cases cùng `unit+campus+term`
- Mỗi role chỉ cần màn hình list + filter theo status — không cần inbox riêng
- Auto-transition overdue cases qua daily cron
- Tái sử dụng tối đa: `FinanceCharge`, `VoidFinanceChargeAction`, `CourseOffering`, `ClassSession`, `CanvasGradeSyncService`, DNG payment system
- GPA dùng highest-attempt per unit; prerequisite dùng "ít nhất 1 attempt pass"

**Non-Goals:**
- Không làm inbox/task queue riêng theo role trong MVP
- Không tự động tạo workflow "Học lại" — chỉ đổi status và lưu audit
- Không cover phúc khảo/appeal điểm sau thi lại
- Không batch migration dữ liệu lịch sử
- Không cover EGC block retake discount (workflow riêng)
- Không hỗ trợ SV tự hủy đăng ký — chỉ staff mới hủy được

## Decisions

### D1: Tạo entity riêng `course_retake_cases` thay vì mở rộng `course_registrations`

**Decision**: Entity riêng.

**Rationale**: Workflow có 8+ states, nhiều role, nhiều deadline khác nhau. `course_registrations` phù hợp cho enrollment, không phù hợp để mang approval/payment/scheduling lifecycle. Entity riêng cho phép state machine rõ ràng, audit trail đầy đủ, deadline management độc lập.

**Alternative considered**: Thêm status/flags vào `course_registrations` → bị loại vì làm phức tạp enrollment logic hiện tại và không thể model được các transition conditions.

---

### D2: State machine dưới dạng enum column + transition methods trên Model

**Canonical states (đây là list duy nhất — design.md, spec.md, và code đều dùng list này):**
```
# Tạo ngay khi GV quyết định
not_nominated                  -- (TERMINAL) GV tạo để audit, không đi vào workflow

# Active states (theo thứ tự happy path)
training_eligibility_pending   -- Case vừa được tạo khi GV nominate
training_eligibility_approved  -- Đào tạo approve eligibility
registration_opened            -- Đào tạo mở registration window cho batch
student_registered             -- SV đã đăng ký
finance_review_pending         -- Chờ HQ review
charge_created                 -- HQ đã tạo charge
payment_pending                -- Chờ thanh toán
paid                           -- Đã thanh toán
waiting_listed                 -- Chờ roster confirmation
roster_confirmed               -- Đào tạo đã confirm roster
scheduled                      -- CourseOffering + session đã được tạo
manual_score_pending_review    -- GV đã nhập manual score, chờ Đào tạo review
assessed                       -- Điểm đã được chốt (Canvas sync hoặc manual approved)

# Transitional states (record kết quả trước khi auto-transition)
final_fail                     -- Thi lại không đạt → auto-transition sang repeat_course
no_show                        -- Vắng thi → auto-transition sang repeat_course

# Terminal states (không có transition tiếp)
training_rejected              -- Đào tạo reject nomination
final_pass                     -- Thi lại đạt
repeat_course                  -- Terminal duy nhất của mọi nhánh fail (overdue, final_fail, no_show, manual)
auto_closed                    -- Case tự đóng vì attempt gốc được sửa thành pass (mọi active state)

# auto_closed scope: áp dụng cho MỌI non-terminal state (kể cả paid/waiting_listed/scheduled)
# Hành động phụ thuộc state: trước paid → void charge; từ paid trở đi → không rollback, staff xử lý tay
```

Note: `lecturer_nominated` không phải state — case được tạo ngay tại thời điểm nomination, bắt đầu ở `training_eligibility_pending`.

**Rationale**: Enum column đơn giản, dễ filter theo status cho từng role screen. Transition methods trên Model encapsulate validation logic (không cho phép transition không hợp lệ).

---

### D3: Eligibility engine dùng `config/retake.php` (không phải DB config)

**Decision**: Config file PHP.

```php
// config/retake.php
return [
    'max_attempts'               => env('RETAKE_MAX_ATTEMPTS', 2),
    'min_attendance_percentage'  => env('RETAKE_MIN_ATTENDANCE', 70.0),
];
```

**Rationale**: Requirement hiện tại là global config (không khác nhau theo unit hay campus). Config file PHP đơn giản, không cần UI quản lý, dễ override qua env var. Có thể mở rộng sang per-unit JSON config sau nếu cần.

**Alternative considered**: `unit.retake_config` JSON column → overkill cho MVP, thêm complexity khi không cần.

---

### D4: `retake_batches` — system đề xuất, Đào tạo xác nhận mở

**Decision**: Batch được system tự động đề xuất khi có ≥1 case được nominated trong cùng `unit+campus+semester`. Đào tạo mở batch thủ công khi thấy đủ điều kiện.

**Batch states**: `proposed → open → closed`

**Rationale**: Giảm tải cho Đào tạo (không cần tạo batch từ đầu), đồng thời vẫn đảm bảo Đào tạo kiểm soát thời điểm mở.

---

### D5: Auto-void charge khi auto-close case

**Decision**: Khi `AcademicRecord.override_pass` được set = true và retake case đang mở:
- Case chưa `paid`: gọi `VoidFinanceChargeAction` (tái sử dụng), set case → `auto_closed`, log reason_code = `original_attempt_passed`
- Case đã `paid`: set case → `auto_closed`, log reason_code = `original_attempt_passed_charge_paid`, **không** rollback tài chính — staff xử lý tay

**Implementation**: `AcademicRecordObserver` observe `updated` event, check `override_pass` changed.

---

### D6: GPA dùng highest-attempt, tracked qua `is_gpa_contributing` flag

**Decision**: Thêm column `is_gpa_contributing` (boolean, default true) vào `academic_records`. Khi có attempt retake mới cho cùng unit+student, hệ thống:
1. Set `is_gpa_contributing = false` cho tất cả attempt cũ của unit đó
2. Set `is_gpa_contributing = true` cho attempt có điểm cao nhất

`GPACalculationService` filter `WHERE is_gpa_contributing = true`.

**Rationale**: Không cần recalculate toàn bộ GPA mỗi lần query; flag là source of truth tại thời điểm finalize.

---

### D7: Retake CourseOffering

Thêm 2 fields vào `course_offerings`:
- `is_retake` (boolean, default false)
- `retake_batch_id` (FK nullable → `retake_batches`)

**Không thêm `parent_course_offering_id`** — trace về offering gốc đã có qua: `retake_case.original_academic_record_id → academic_records.course_offering_id`. Dual source of truth là không cần thiết.

**Bắt buộc tạo `course_registrations`** khi tạo retake CourseOffering: `CanvasGradeSyncService` đọc enrollment từ `CourseRegistration::where('course_offering_id')`. Không có `course_registrations` thì Canvas sync không thấy sinh viên và attendance/calendar bị lệch. `CreateRetakeCourseOfferingAction` phải tạo `course_registrations` cho tất cả sinh viên có case ở `roster_confirmed`.

Cho phép Canvas sync, attendance, grade input tái sử dụng hoàn toàn.

---

### D8: GPA snapshot invalidation khi finalize retake

`GPACalculationService.calculateCurrentGPA()` ưu tiên đọc từ `GpaCalculation` snapshot table (`is_current = true`). Snapshot được tạo bởi `FinalizeSemesterGpaAction` chạy cuối kỳ.

**Decision**: Khi `FinalizeRetakeCaseAction` chạy, đánh dấu tất cả `gpa_calculations` của student đó thành `is_current = false`. Điều này buộc `GPACalculationService` fallback về live calculation (từ `academic_records` với `is_gpa_contributing = true`) cho đến khi `FinalizeSemesterGpaAction` chạy lại để tạo snapshot mới với dữ liệu đúng.

**Alternative considered**: Tái chạy `FinalizeSemesterGpaAction` ngay lập tức cho từng student → tốn I/O không cần thiết, phức tạp dependency injection; bị loại.

---

### D9: Manual score fallback — GV nhập, Đào tạo approve/reject

Khi CourseOffering thi lại không thể sync Canvas:
- GV/cán bộ phụ trách nhập `final_retake_score` → case vào `manual_score_pending_review`
- Đào tạo approve → score được apply, tạo AcademicRecord mới
- Đào tạo reject → trả về GV nhập lại

Quyền Đào tạo: chỉ approve/reject, không sửa điểm trực tiếp.

## Risks / Trade-offs

**[Risk] State machine phức tạp, dễ bị invalid state** → Mitigation: Tất cả transitions đi qua methods trên Model có validate; không cho phép update trực tiếp cột `status`; viết unit tests cho từng transition.

**[Risk] Daily cron auto-transition có thể fail silently** → Mitigation: Log rõ ràng từng case được transition, alert nếu job không chạy; cho staff manual trigger từ UI.

**[Risk] GPA recalculation khi có nhiều retake attempts** → Mitigation: `is_gpa_contributing` flag được update tại thời điểm finalize result, không cần job background.

**[Risk] Auto-void charge khi case đã có DNG request đang pending** → Mitigation: Khi void charge, kiểm tra DNG request status; nếu DNG chưa paid → void DNG request trước; nếu DNG đã paid → escalate to manual.

**[Trade-off] Config file thay vì DB config** → Không cần UI để thay đổi max_attempts/min_attendance trong MVP. Nếu tương lai cần per-unit config, sẽ cần migration thêm JSON column vào `units`.

## Migration Plan

1. **Deploy**: Additive only — tất cả migrations tạo bảng/column mới, không alter existing behavior
2. **Feature flag**: Có thể wrap toàn bộ nomination UI sau flag `retake_workflow_enabled` trong `config/features.php` (nếu cần rollout dần)
3. **Rollback**: Drop 2 bảng mới + drop 4 columns mới — không ảnh hưởng data hiện tại
4. **Cron**: Thêm `TransitionOverdueRetakeCasesCommand` vào `routes/console.php` (không phải `Kernel.php` — repo này schedule tại đây), chạy daily 1:00 AM
5. **Observer**: Register `AcademicRecordObserver` trong `AppServiceProvider`
