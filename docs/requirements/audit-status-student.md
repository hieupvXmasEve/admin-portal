# SPEC REQUIREMENT — Student Administrative Actions (Phase 1)

## 0) Mục tiêu

1. Ghi nhận **mọi action quan trọng** liên quan đến trạng thái/biến động của student để **audit & thống kê theo thời gian**.
2. Cho phép admin **đổi status / chuyển campus** và hệ thống tự lưu lịch sử + lý do + file scan (optional).
3. Hỗ trợ **lọc, xuất báo cáo** theo thời gian/kỳ/campus/người thao tác/thiếu hồ sơ.

## 1) Phạm vi (In-scope)

### 1.1 Action types bắt buộc hỗ trợ (Phase 1)

**Academic status actions**

* `ACADEMIC_DEFER` — Bảo lưu (defer)
* `ACADEMIC_RESUME` — Quay lại học (resume/return)
* `ADMISSION_DEFERRAL` — Hoãn nhập học / no-show
* `ACADEMIC_DROPOUT` — Bỏ học (dropout)

**Administrative actions**

* `CAMPUS_TRANSFER` — Chuyển campus nội bộ (có thể xảy ra bất cứ lúc nào)

### 1.2 File upload

* Action có thể có **0..n** file scan (PDF/JPG/PNG…).
* Không bắt buộc upload ngay: có checkbox **“Bổ sung hồ sơ sau”**.

### 1.3 Audit & statistics

* Trang audit/reports: filter theo thời gian + loại action + kỳ + campus + actor + missing docs; có export.

## 2) Khái niệm & định nghĩa nghiệp vụ

### 2.1 Academic status (snapshot hiện tại trên student)

Các status tối thiểu:

* `active`
* `deferred` (bảo lưu)
* `admission_deferred` (hoãn nhập học/no-show)
* `dropout` (bỏ học)

> Snapshot chỉ để hiển thị hiện tại và phục vụ logic hệ thống. Audit/stat dựa trên Action Log.

### 2.2 Quy tắc Defer (bảo lưu)

* Defer có `from_semester` và `return_semester`.
* Ý nghĩa: **nghỉ các kỳ >= from_semester và < return_semester**.
  Ví dụ: from=Fall2025, return=Spring2026 ⇒ nghỉ Fall2025, quay lại Spring2026.

### 2.3 Quy tắc Campus transfer

* Có thể xảy ra **bất cứ lúc nào** (giữa kỳ).
* Chỉ thay đổi **campus**; giữ nguyên program/curriculum/enrollments/academic records.
* Cần lưu `from_campus`, `to_campus`, `effective_at` để audit chính xác.

## 3) Data Requirements (Business fields)

## 3.1 Common fields (áp dụng cho mọi action)

Bắt buộc:

* `student_id`
* `action_type`
* `reason` (text)
* `changed_at` (timestamp hệ thống ghi nhận)
* `changed_by_user_id` (ai thao tác)
* `missing_documents` (boolean)

Tuỳ chọn:

* `signed_at` (date — ngày ký đơn)
* `notes` (text — ghi chú nội bộ)
* `attachments[]` (0..n upload records)

## 3.2 Fields theo action type (bắt buộc/optional)

### A) `ACADEMIC_DEFER`

Bắt buộc:

* `from_semester_id`
* `return_semester_id`
* `reason`
  Optional:
* `signed_at`
* `attachments[]`
* `missing_documents`

Validation rules:

* `return_semester_id` **phải sau** `from_semester_id`

Side effects (snapshot update):

* Set `students.status = deferred`

### B) `ACADEMIC_RESUME`

Bắt buộc:

* `return_semester_id`
* `reason`
  Optional:
* `signed_at`, `attachments[]`, `missing_documents`

Validation rules:

* Student hiện tại **nên** đang `deferred` (có thể warning, không chặn cứng ở Phase 1 nếu bạn muốn linh hoạt)

Side effects:

* Set `students.status = active`

### C) `ADMISSION_DEFERRAL`

Bắt buộc:

* `intended_intake_semester_id`
* `reason`
  Optional:
* `signed_at`, `attachments[]`, `missing_documents`

Side effects:

* Set `students.status = admission_deferred`

### D) `ACADEMIC_DROPOUT`

Bắt buộc:

* `dropout_semester_id`
* `reason`
  Optional:
* `signed_at`, `attachments[]`, `missing_documents`

Side effects:

* Set `students.status = dropout`

### E) `CAMPUS_TRANSFER`

Bắt buộc:

* `from_campus_id`
* `to_campus_id`
* `effective_at` (datetime)
* `reason`
  Optional:
* `effective_semester_id` (recommended để thống kê theo kỳ)
* `signed_at`, `attachments[]`, `missing_documents`

Validation rules:

* `from_campus_id != to_campus_id`
* `from_campus_id` phải đúng với campus hiện tại của student tại thời điểm tạo action (nếu không đúng: warning hoặc chặn)

Side effects:

* Update `students.campus_id = to_campus_id`
* **Không đổi** `students.status` (giữ nguyên học vụ)

## 4) Audit Logging Rules

Mỗi lần tạo action phải:

1. Lưu 1 record action (business-level)
2. Cập nhật snapshot trên `students` theo rules ở mục 3.2
3. (Nếu đang dùng) ghi thêm `student_changes` cho các field snapshot bị đổi:

   * status (old/new)
   * campus_id (old/new)
   * kèm `reason` và user thao tác

> `student_changes` là log “field-level”, còn action log là log “nghiệp vụ-level”.

## 5) UI Requirements

## 5.1 Student Profile — Tab “Actions / History”

Hiển thị danh sách action theo thời gian (mới → cũ), mỗi item phải có:

* action_type (label rõ)
* changed_at + changed_by (ai làm)
* reason
* signed_at (nếu có)
* fields theo loại:

  * Defer: from_semester → return_semester
  * Dropout: dropout_semester
  * Admission deferral: intended_intake_semester
  * Campus transfer: from_campus → to_campus, effective_at (+ effective_semester nếu có)
* attachments (nếu có) + trạng thái `missing_documents`

Chức năng:

* View details
* Upload bổ sung tài liệu (nếu missing_documents=true)
* Download/view file scan

## 5.2 Form tạo action (Modal/Page)

* Chọn `action_type` ⇒ render đúng bộ field theo loại.
* `reason` bắt buộc.
* Checkbox: **“Bổ sung hồ sơ sau”** (default: false)
* Upload attachments (optional)

## 5.3 Trang Audit / Reports — “Student Actions Audit”

Filter:

* date range theo `changed_at`
* date range theo `signed_at` (optional)
* action_type
* semester filters:

  * from_semester / return_semester / dropout_semester / intended_intake_semester / effective_semester
* campus filters:

  * to_campus (và from_campus cho transfer)
* actor (changed_by_user_id)
* missing_documents (true/false)

Kết quả list phải show:

* student info (student_code + name)
* action_type
* changed_at, signed_at
* key fields theo loại (như tab profile)
* actor
* missing_documents

Export:
* CSV/Excel theo filter hiện tại

## 6) Permissions

* Chỉ role/permission admin có thể:
  * tạo action
  * upload attachments
  * xem audit report

## 7) Non-functional Requirements

* Query audit phải chạy tốt theo filter thời gian (index theo `changed_at`, `action_type`, `student_id`, `campus`).
* Attachments lưu theo cơ chế upload hiện có; link theo action record.
* Không được mất lịch sử khi update student snapshot.