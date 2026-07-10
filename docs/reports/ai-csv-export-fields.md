# AI CSV Export — Field Reference

Ba artisan command export dữ liệu phẳng (flat CSV) phục vụ phân tích bằng Claude hoặc công cụ AI khác.

```bash
# Attendance + điểm theo môn/kì
./scripts/dev.sh artisan export:student-attendance-report
./scripts/dev.sh artisan export:student-attendance-report --output=storage/app/reports/my-attendance.csv

# Lifecycle status + GC level theo kì
./scripts/dev.sh artisan export:student-lifecycle-report
./scripts/dev.sh artisan export:student-lifecycle-report --output=storage/app/reports/my-lifecycle.csv

# Scholarship — 2 file CSV (roster + application)
./scripts/dev.sh artisan export:student-scholarship-report
./scripts/dev.sh artisan export:student-scholarship-report --output-dir=storage/app/reports
./scripts/dev.sh artisan export:student-scholarship-report --roster-only
./scripts/dev.sh artisan export:student-scholarship-report --application-only
```

Mặc định file ghi vào `storage/app/reports/` với timestamp.

Phạm vi: **tất cả campus**, **tất cả kì**, không lọc.

---

## 1. `student-attendance-report`

### Grain

**1 dòng = 1 sinh viên × 1 môn × 1 kì**

Nguồn chính: bảng `academic_records` (join `students`, `units`, `semesters`, `course_offerings`, `programs`, `campuses`, `syllabus_templates`, `lectures`).

### Mục đích phân tích

- Tình hình attending theo môn/kì
- Môn nào attending thấp/cao
- Học lần thứ mấy, điểm bao nhiêu, pass/fail và lý do fail

### Fields

#### Định danh sinh viên

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `student_code` | `students.student_id` | Mã sinh viên (ví dụ `AUH111885`) |
| `student_name` | `students.full_name` | Họ tên đầy đủ |
| `student_email` | `students.email` | Email |
| `program_name` | `programs.name` | Tên chương trình đào tạo |
| `campus_name` | `campuses.name` | Campus của sinh viên |
| `intake` | `students.intake` | Cohort intake (số/khóa) |

#### Môn học / kì / lớp

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `semester_code` | `semesters.code` | Mã kì học (ví dụ `FALL2025`) |
| `semester_name` | `semesters.name` | Tên kì học |
| `unit_code` | `units.code` | Mã môn (ví dụ `AU003`) |
| `unit_name` | `units.name` | Tên môn |
| `credit_points` | `units.credit_points` | Số tín chỉ của môn |
| `section_code` | `course_offerings.section_code` | Mã section/lớp (ví dụ `AUVH0101`) |
| `instructor_name` | `lectures.first_name` + `last_name` | Giảng viên phụ trách lớp mở |

#### Attending (tổng hợp theo môn)

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `attendance_percentage` | `academic_records.attendance_percentage` | % đi học = (present + late) / tổng buổi × 100 |
| `total_present` | `academic_records.total_present` | Số buổi có mặt (`present`) |
| `total_absences` | `academic_records.total_absences` | Số buổi vắng (`absent`) |
| `total_late` | `academic_records.total_late` | Số buổi đi muộn (`late`) |
| `total_not_recorded` | `academic_records.total_not_recorded` | Buổi chưa được giảng viên điểm danh |
| `total_class_sessions` | `academic_records.total_class_sessions` | Tổng số buổi học của lớp |
| `min_attendance_threshold` | `syllabus_templates.min_attendance_threshold` | Ngưỡng % attending tối thiểu của môn (mặc định 80%) |
| `meets_attendance_requirement` | `academic_records.meets_attendance_requirement` | Đạt ngưỡng attending? Giá trị: `yes` / `no` |

#### Điểm & kết quả

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `final_percentage` | `academic_records.final_percentage` | Điểm phần trăm cuối kì (0–100) |
| `final_letter_grade` | `academic_records.final_letter_grade` | Điểm chữ (`A+`, `A`, `B+`, `F`…) |
| `grade_points` | `academic_records.grade_points` | Điểm GPA thang 4.0 |
| `min_grade_threshold` | `syllabus_templates.min_grade_threshold` | Ngưỡng điểm tối thiểu để pass (mặc định 60%) |
| `grade_status` | `academic_records.grade_status` | Trạng thái chấm điểm: `in_progress`, `provisional`, `final`, `incomplete`, `withdrawn`, `failed`, `pass_no_credit`, `audit`, `transfer_credit` |
| `completion_status` | `academic_records.completion_status` | Trạng thái hoàn thành: `enrolled`, `completed`, `failed`, `withdrawn`, `incomplete`, `in_progress` |
| `is_passed` | `academic_records.is_passed` | Kết quả: `PASS` / `FAIL` / trống (chưa chốt) |
| `failure_reason` | `academic_records.failure_reason` | Lý do fail: `grade_failed`, `attendance_failed`, `both_failed`, `manual_failed` |
| `override_pass` | `academic_records.override_pass` | Admin override cho pass? `yes` / `no` |
| `override_reason` | `academic_records.override_reason` | Lý do override (nếu có) |

#### Học lại

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `attempt_number` | `academic_records.attempt_number` | Lần học thứ mấy (1, 2, 3…) |
| `is_repeat_course` | `academic_records.is_repeat_course` | Có phải học lại môn này? `yes` / `no` |

### Ví dụ prompt Claude

- *"Môn nào có `attendance_percentage` trung bình thấp nhất trong FALL2025?"*
- *"Liệt kê SV fail vì `failure_reason = attendance_failed`"*
- *"Pass rate của SV `attempt_number = 2` so với lần 1?"*
- *"Top 10 môn có nhiều SV `meets_attendance_requirement = no` nhất"*

---

## 2. `student-lifecycle-report`

### Grain

**1 dòng = 1 sinh viên × 1 kì** (long format)

Mỗi SV xuất hiện nhiều dòng — một dòng cho mỗi kì mà SV đã intake (`intake_semester.start_date ≤ semester.start_date`).

Nguồn chính: `students` + `student_action_logs` (status), `academic_progression_events` (GC level), `egc_blocks` (block EGC).

### Mục đích phân tích

- Trạng thái sinh viên qua từng kì
- Tiến trình GC / Pre-Uni theo kì
- Defer, dropout, chuyển trạng thái theo thời gian

### Fields

#### Định danh sinh viên

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `student_code` | `students.student_id` | Mã sinh viên |
| `student_name` | `students.full_name` | Họ tên |
| `campus_name` | `campuses.name` | Campus hiện tại |
| `program_name` | `programs.name` | Chương trình đào tạo |
| `intake_semester` | `semesters.code` (kì intake) | Mã kì nhập học |
| `intake_year` | Năm từ `intake_semester.start_date` | Năm intake |

#### Kì đang xét

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `semester_code` | `semesters.code` | Mã kì của dòng này |
| `semester_name` | `semesters.name` | Tên kì |
| `semester_start_date` | `semesters.start_date` | Ngày bắt đầu kì (`YYYY-MM-DD`) |

#### Trạng thái lifecycle

| Field | Nguồn | Ý nghĩa |
|-------|-------|---------|
| `status_at_semester` | **Tính từ** `student_action_logs` | Trạng thái SV **tại thời điểm kì đó** — replay action logs (cùng logic trang `reports/student-lifecycle-yearly`) |
| `current_status` | `students.status` | Trạng thái **hiện tại** (lặp trên mọi dòng của cùng SV) |
| `latest_action_type` | `student_action_logs` | Loại action gần nhất có hiệu lực ≤ kì đang xét |
| `latest_action_semester` | Semester liên quan của action | Kì hiệu lực của action gần nhất |
| `defer_start_semester` | Action `ACADEMIC_DEFER` | Kì **bắt đầu bảo lưu** (nếu có, tính đến kì đang xét) |
| `dropout_semester` | Action `ACADEMIC_DROPOUT` | Kì **bỏ học** (nếu có, tính đến kì đang xét) |
| `ne` | `students.user_id IS NOT NULL` | Đã có tài khoản AP/NE? `yes` / `no` |

**Giá trị status thường gặp:**

| Giá trị | Ý nghĩa |
|---------|---------|
| `pending` | Chờ xử lý / chưa NE |
| `intake_pre_uni_gc` | Đang học Pre-Uni / GC |
| `intake_course` | Đang học chính khóa |
| `deferred` | Bảo lưu |
| `dropout` | Bỏ học |
| `dropout_transfer` | Bỏ học (chuyển) |
| `graduated` | Đã tốt nghiệp |
| `pending_course_opening` | Chờ mở lớp |
| `admission_deferred` | Hoãn nhập học |

**Giá trị `latest_action_type` thường gặp:**

| Giá trị | Ý nghĩa |
|---------|---------|
| `STUDENT_ENROLLMENT_NE` | Hoàn tất NE → `intake_pre_uni_gc` |
| `STUDENT_MAJOR_ENROLLMENT` | Vào chính khóa → `intake_course` |
| `ACADEMIC_DEFER` | Bảo lưu |
| `ACADEMIC_RESUME` | Quay lại học sau bảo lưu |
| `ACADEMIC_DROPOUT` | Bỏ học |
| `CAMPUS_TRANSFER` | Chuyển campus (không đổi status) |
| `WAITING_COURSE_OPENING` | Chờ mở môn |
| `ADMISSION_DEFERRAL` | Hoãn nhập học |

#### GC level

| Field | Nguồn | Ý nghĩa |
|-------|-------|---------|
| `gc_level_at_semester` | **Tính từ** `academic_progression_events` | Level tiếng Anh tại kì — event `PLACEMENT_INITIALIZED` hoặc `ENGLISH_LEVEL_CHANGED` mới nhất có `semester.start_date ≤ kì đang xét` → lấy `to_english_level` |
| `gc_block_1_level` | `egc_blocks.level_number` (block 1) | Level block EGC 1 trong kì |
| `gc_block_1_result` | `egc_blocks.result` (block 1) | Kết quả block 1: `pending` / `pass` / `fail` |
| `gc_block_2_level` | `egc_blocks.level_number` (block 2) | Level block EGC 2 trong kì |
| `gc_block_2_result` | `egc_blocks.result` (block 2) | Kết quả block 2 |
| `gc_current_level` | `students.gc_current_level` | Level GC **hiện tại** trên hồ sơ SV |
| `gc_starting_level` | `students.gc_starting_level` | Level GC **ban đầu** khi placement |

**Lưu ý khi đọc GC:**

- `gc_level_at_semester` = lịch sử thay đổi level (progression events)
- `gc_block_*` = dữ liệu vận hành EGC thực tế theo block trong kì
- Hai nguồn có thể khác nhau nếu dữ liệu chưa sync hoặc SV chưa có block/event
- SV không học GC → các cột GC thường **trống**

### Ví dụ prompt Claude

- *"SV nào chuyển `status_at_semester` từ `intake_pre_uni_gc` sang `intake_course` ở kì nào?"*
- *"Bao nhiêu SV `deferred` tại FALL2025 nhưng `current_status` khác?"*
- *"SV nào `gc_block_2_result = fail` nhưng `gc_current_level` vẫn tăng?"*
- *"So sánh `gc_level_at_semester` vs `gc_block_1_level` — có mismatch không?"*

---

## 3. `student-scholarship-report`

Một command, **hai file CSV** — grain khác nhau nên tách riêng:

| File output | Grain | Mục đích |
|-------------|-------|----------|
| `student-scholarship-roster-{timestamp}.csv` | **1 dòng = 1 sinh viên** | Ai được gán học bổng gì, định nghĩa HB, thời hạn |
| `student-scholarship-application-{timestamp}.csv` | **1 dòng = 1 sinh viên × 1 kì** (có dòng giảm giá HB trên hóa đơn) | HB đã áp dụng vào invoice kì nào, số tiền, trạng thái |

**Tại sao 2 file?** Roster lấy từ `student_scholarship_awards` (tối đa 1 award/SV). Application lấy từ `invoice_discounts` where `discount_type = scholarship` — một SV có thể có nhiều dòng theo từng kì có hóa đơn được giảm HB.

### 3a. Roster — `student-scholarship-roster-*.csv`

#### Grain

**1 dòng = 1 sinh viên** (mọi SV trong hệ thống; SV chưa có award vẫn có dòng với `has_scholarship = no`)

Nguồn chính: `students` LEFT JOIN `student_scholarship_awards` LEFT JOIN `scholarship_definitions`.

Ràng buộc DB: `student_scholarship_awards.student_id` unique → mỗi SV tối đa **một** học bổng được gán.

#### Mục đích phân tích

- Danh sách SV có/không có học bổng
- Loại HB (%, cố định), hạn hiệu lực, trạng thái định nghĩa
- So sánh roster vs application (SV có award nhưng chưa áp invoice kì nào)

#### Fields

##### Định danh sinh viên

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `student_code` | `students.student_id` | Mã sinh viên |
| `student_name` | `students.full_name` | Họ tên |
| `student_email` | `students.email` | Email |
| `campus_name` | `campuses.name` | Campus |
| `program_name` | `programs.name` | Chương trình đào tạo |
| `intake` | `students.intake` | Cohort intake (số/khóa) |

##### Học bổng được gán

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `has_scholarship` | **Tính từ** `ssa.scholarship_code` | SV có award? `yes` nếu có `scholarship_code`, ngược lại `no` |
| `scholarship_code` | `student_scholarship_awards.scholarship_code` | Mã định nghĩa HB (ví dụ `SCH-MERIT`). Trống nếu chưa gán |
| `scholarship_name` | `scholarship_definitions.name` | Tên hiển thị của HB |
| `scholarship_type` | `scholarship_definitions.type` | Cách tính: `percentage` (% trên subtotal) hoặc `fixed_amount` (số tiền cố định) |
| `scholarship_amount` | `scholarship_definitions.amount` | Giá trị HB: % (ví dụ `30`) hoặc số tiền VND tùy `scholarship_type` |
| `total_amount` | `scholarship_definitions.total_amount` | Trần tổng tiền giảm (nếu có cap). Trống = không giới hạn |
| `total_terms` | `scholarship_definitions.total_terms` | Số kì tối đa được áp HB. Trống = không giới hạn theo kì |
| `valid_from` | `scholarship_definitions.valid_from` | Ngày bắt đầu hiệu lực định nghĩa (`YYYY-MM-DD`) |
| `valid_until` | `scholarship_definitions.valid_until` | Ngày hết hiệu lực định nghĩa (`YYYY-MM-DD`) |
| `scholarship_is_active` | `scholarship_definitions.is_active` | Định nghĩa HB còn active? `yes` / `no` / trống (chưa gán) |
| `awarded_at` | `student_scholarship_awards.awarded_at` | Ngày gán HB cho SV (`YYYY-MM-DD`). Trống nếu chưa gán |
| `award_notes` | `student_scholarship_awards.notes` | Ghi chú nội bộ khi gán award |

**Giá trị `scholarship_type`:**

| Giá trị | Ý nghĩa |
|---------|---------|
| `percentage` | Giảm theo % subtotal hóa đơn (`scholarship_amount` = %, ví dụ 30 = 30%) |
| `fixed_amount` | Giảm số tiền cố định mỗi lần áp (`scholarship_amount` = VND) |

#### Ví dụ prompt Claude

- *"Bao nhiêu SV `has_scholarship = yes` theo từng `campus_name`?"*
- *"HB nào `scholarship_is_active = no` nhưng SV vẫn còn award?"*
- *"SV có `total_terms` giới hạn — đã dùng hết bao nhiêu kì?"* (cần join với file application)

---

### 3b. Application — `student-scholarship-application-*.csv`

#### Grain

**1 dòng = 1 sinh viên × 1 kì** — chỉ các dòng có `invoice_discounts.discount_type = scholarship`

Nguồn chính: `invoice_discounts` → `student_invoices` → `semesters`, join `students`; LEFT JOIN award/definition và `finance_discount_entitlements`.

**Lưu ý:** `scholarship_code` / `scholarship_name` / `scholarship_type` trên file này lấy từ award hiện tại của SV (`student_scholarship_awards`), không từ dòng `invoice_discounts` — dùng để đối chiếu context award khi phân tích từng kì.

#### Mục đích phân tích

- HB đã trừ bao nhiêu tiền trên hóa đơn kì nào
- Invoice đã thanh toán chưa sau khi giảm
- Entitlement lifecycle (nếu discount gắn `finance_discount_entitlements`)

#### Fields

##### Định danh sinh viên & kì

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `student_code` | `students.student_id` | Mã sinh viên |
| `student_name` | `students.full_name` | Họ tên |
| `campus_name` | `campuses.name` | Campus |
| `program_name` | `programs.name` | Chương trình |
| `semester_code` | `semesters.code` | Mã kì của hóa đơn (ví dụ `FALL2025`) |
| `semester_name` | `semesters.name` | Tên kì |
| `semester_start_date` | `semesters.start_date` | Ngày bắt đầu kì (`YYYY-MM-DD`) |

##### Học bổng (context từ award)

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `scholarship_code` | `student_scholarship_awards.scholarship_code` | Mã HB đang gán cho SV (có thể trống nếu award bị xóa sau khi tạo discount) |
| `scholarship_name` | `scholarship_definitions.name` | Tên HB |
| `scholarship_type` | `scholarship_definitions.type` | `percentage` / `fixed_amount` |

##### Dòng giảm giá trên hóa đơn

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `discount_amount` | `invoice_discounts.amount` | Số tiền giảm HB trên hóa đơn kì này (VND, 2 chữ số thập phân) |
| `discount_status` | `invoice_discounts.status` | Trạng thái dòng discount |
| `discount_description` | `invoice_discounts.description` | Mô tả trên hóa đơn (ví dụ `Scholarship: Merit Scholarship`) |

**Giá trị `discount_status`:**

| Giá trị | Ý nghĩa |
|---------|---------|
| `active` | Đang có hiệu lực trên hóa đơn |
| `reversed` | Đã đảo/hủy (không còn tính vào tổng) |
| `expired` | Hết hiệu lực |

##### Hóa đơn

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `invoice_number` | `student_invoices.invoice_number` | Số hóa đơn |
| `invoice_status` | `student_invoices.status` | Trạng thái thanh toán hóa đơn |
| `invoice_subtotal` | `student_invoices.cached_subtotal` | Tổng trước giảm giá (snapshot cache) |
| `invoice_discount_total` | `student_invoices.cached_discount_total` | Tổng giảm giá mọi loại trên hóa đơn |
| `invoice_total_amount` | `student_invoices.cached_total_amount` | Số tiền phải trả sau giảm |

**Giá trị `invoice_status`:**

| Giá trị | Ý nghĩa |
|---------|---------|
| `draft` | Nháp, chưa phát hành |
| `pending` | Đã phát hành, chờ thanh toán |
| `partial` | Đã thanh toán một phần |
| `paid` | Đã thanh toán đủ |
| `overdue` | Quá hạn |
| `cancelled` | Đã hủy |

##### Entitlement (tùy chọn)

Chỉ có giá trị khi dòng discount liên kết `finance_discount_entitlement_id`.

| Field | Nguồn DB | Ý nghĩa |
|-------|----------|---------|
| `entitlement_lifecycle_status` | `finance_discount_entitlements.lifecycle_status` | Vòng đời entitlement |
| `entitlement_allocation_status` | `finance_discount_entitlements.allocation_status` | Mức độ đã phân bổ vào invoice |
| `entitlement_amount` | `finance_discount_entitlements.amount` | Tổng số tiền entitlement được cấp |

**Giá trị `entitlement_lifecycle_status`:**

| Giá trị | Ý nghĩa |
|---------|---------|
| `requested` | Đang chờ duyệt |
| `approved` | Đã duyệt |
| `rejected` | Từ chối |
| `cancelled` | Đã hủy |
| `revoked` | Thu hồi |
| `expired` | Hết hạn |

**Giá trị `entitlement_allocation_status`:**

| Giá trị | Ý nghĩa |
|---------|---------|
| `available` | Chưa phân bổ |
| `partially_allocated` | Phân bổ một phần |
| `fully_allocated` | Đã phân bổ hết |
| `released` | Đã giải phóng (không còn gắn invoice) |

#### Ví dụ prompt Claude

- *"Tổng `discount_amount` theo `semester_code` và `scholarship_code`?"*
- *"SV nào có roster `has_scholarship = yes` nhưng không có dòng application kì gần nhất?"*
- *"Invoice `pending` nhưng `discount_status = active` — còn bao nhiêu tiền chưa thu?"*
- *"Entitlement `fully_allocated` nhưng `invoice_status != paid`?"*

---

## So sánh các file

| | Attendance | Lifecycle | Scholarship roster | Scholarship application |
|--|------------|-----------|-------------------|------------------------|
| **1 dòng là** | SV × môn × kì | SV × kì | SV | SV × kì (có discount HB) |
| **Trọng tâm** | Đi học, điểm, pass/fail | Trạng thái hồ sơ, GC level | Award HB trên hồ sơ SV | HB áp vào hóa đơn từng kì |
| **Bảng chính** | `academic_records` | `students` + `student_action_logs` | `student_scholarship_awards` | `invoice_discounts` |
| **Dữ liệu tính toán** | Ít | Nhiều (`status_at_semester`, GC) | `has_scholarship` | Ít (chủ yếu snapshot) |

Các file **bổ sung nhau**: lifecycle cho hành trình SV; attendance cho chi tiết môn; scholarship roster cho “ai được HB”; application cho “HB trừ bao nhiêu kì nào”. Join qua `student_code`; thêm `semester_code` khi ghép với attendance/lifecycle/application.

---

## Code references

| Command | Query / Action class |
|---------|---------------------|
| `export:student-attendance-report` | `App\Modules\Academic\Queries\ExportStudentAttendanceReportQuery` |
| `export:student-lifecycle-report` | `App\Modules\Academic\Queries\ExportStudentLifecycleReportQuery` |
| `export:student-scholarship-report` (roster) | `App\Modules\Finance\Queries\Reporting\ExportStudentScholarshipRosterQuery` → `ExportStudentScholarshipRosterCsvAction` |
| `export:student-scholarship-report` (application) | `App\Modules\Finance\Queries\Reporting\ExportStudentScholarshipApplicationQuery` → `ExportStudentScholarshipApplicationCsvAction` |

Lifecycle status logic tái sử dụng `App\Modules\Academic\Queries\Reporting\GetStudentStatusBySemesterQuery` (cùng với trang `reports/student-lifecycle-yearly`).