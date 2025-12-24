# 1) Data model tối thiểu cần có

Bạn đã có form engine (forms, versions, questions, responses…). Phần dưới đây là **chỉ những thứ bắt buộc để chạy được nghiệp vụ**.

## 1.1 Form Run (đợt dùng form) — dùng `form_targets`

**Mục đích:** mỗi lần “bật form” cho course/semester/department là 1 record.

Tối thiểu cần các field (nếu chưa có thì thêm cột, không cần thêm bảng mới):

- `id`
- `form_id`, `form_version_id`
- `context_type` enum: `course_offering | semester | department | global`
- `context_id` (id tương ứng theo context_type, global thì NULL)
- `status` enum: `draft | active | closed`
- `start_at`, `end_at` (để lặp theo kỳ / theo đợt)
- `is_mandatory` (chỉ meaningful khi form.type = survey)
- **(để xử lý UC-D1 department + semester mà không thêm bảng):** `semester_id` nullable
    - Chỉ dùng khi `context_type = department` và bạn muốn giới hạn student theo kỳ.

### Constraint cần có (để enforce “1 course offering chỉ có 1 survey”)

- Unique: `(context_type, context_id)` **áp dụng cho course survey**
  Thực tế MySQL khó “unique theo điều kiện”, nên enforce bằng **logic + unique key có scope**:
    - Cách tối giản: với course survey, bạn dùng **1 form_id cố định** “Course Survey Form” → unique `(form_id, context_type, context_id)` là đủ.

## 1.2 Gate assignment — `student_form_assignments` (bắt buộc cho B2)

**Mục đích:** check gate nhanh + generate trước danh sách “survey bắt buộc” cho từng student.

Tối thiểu:

- `id`
- `student_id`
- `form_target_id`
- `status` enum: `not_started | completed`
- `response_id` nullable
- `completed_at` nullable
- Unique: `(student_id, form_target_id)` (chống tạo trùng)

> Table này **chỉ cần cho survey mandatory**. Query không dùng.

## 1.3 Query workflow assignment — _tối thiểu 1 trong 2 cách_

Bạn cần UC-C4 assign/reassign + audit. Để **ít bảng nhất** nhưng vẫn đủ nghiệp vụ:

### Chốt tối thiểu (khuyến nghị): **1 bảng history assignment**

`response_assignments` (hoặc `query_assignments`) — **1 bảng duy nhất** cho audit.

Fields:

- `id`
- `response_id`
- `assigned_to_user_id`
- `assigned_by_user_id`
- `action` enum: `assign | reassign | unassign`
- `created_at`

Và trong `responses` (bạn có thể thêm cột) lưu “current assignee” để query nhanh:

- `assigned_to_user_id` nullable
- `status` enum (cho query): `new | assigned | in_progress | closed`

# 2) Quy tắc nghiệp vụ chuyển thành logic thực thi

## 2.1 Khi nào tạo form_target?

- Course survey: tạo khi course offering “completed” **hoặc** admin tạo thủ công.
- Semester survey: admin tạo mỗi kỳ (mỗi kỳ 1 target).
- Query department: admin tạo run cho department (active trong một thời gian hoặc luôn active).

## 2.2 Khi nào generate `student_form_assignments`?

Chỉ khi:

- `form_target.status` chuyển sang `active`
- và `is_mandatory = true`
- và form.type = survey

**Filter student cho từng context:**

- `context_type = course_offering`: lấy danh sách student đăng ký course offering đó
- `context_type = semester`: lấy student thuộc semester đó
- `context_type = department` + `semester_id`: lấy student thuộc semester_id đó (service survey theo phòng ban trong kỳ)

# 3) API endpoints tối thiểu

## 3.1 Admin Portal

### Forms

- `POST /admin/forms`
- `POST /admin/forms/{form_id}/versions`
- `GET /admin/forms` + `GET /admin/forms/{id}`

### Form Targets (Runs)

- `POST /admin/form-targets` (tạo draft)
- `PATCH /admin/form-targets/{id}/activate` (chuyển active + trigger generate assignments nếu mandatory survey)
- `PATCH /admin/form-targets/{id}/close`

Payload tạo target tối thiểu:

```json
{
    "form_id": 10,
    "form_version_id": 3,
    "context_type": "department",
    "context_id": 5,
    "semester_id": 202501,
    "is_mandatory": true,
    "start_at": "2025-05-01T00:00:00+07:00",
    "end_at": "2025-05-15T23:59:59+07:00"
}
```

## 3.2 Student Portal

### Bootstrap

- `GET /context`
    - trả `survey_gate.blocked` dựa trên `student_form_assignments` chưa completed
    - kèm danh sách survey bắt buộc cần làm ngay (tối thiểu 1–N record)

### Submit form

- `POST /forms/{form_id}/responses`
    - bắt buộc truyền `form_target_id`
    - nếu response thuộc 1 assignment mandatory → cập nhật assignment completed

### Query (student view)

- `GET /my/queries`
- `GET /my/queries/{response_id}`

## 3.3 Staff Portal – Query Management

### List tickets

- `GET /staff/queries`
    - Mặc định: chỉ tickets `assigned_to_user_id = current_user`
    - Nếu user có config/permission “view_department”: show toàn department

### Assign / Reassign

- `POST /staff/queries/{response_id}/assign`

```json
{ "assignee_user_id": 888 }
```

### Reply / Close

- `POST /staff/queries/{response_id}/reply`
- `POST /staff/queries/{response_id}/close`

> “Staff xem assigned-only hay dept-wide” giải bằng **permission/config**, không cần thêm nghiệp vụ.

# 4) Quy tắc phân quyền (đúng UC-C3 + câu 3 bạn chốt)

- Staff mặc định:
    - chỉ xem tickets assigned cho mình

- Nếu được cấu hình:
    - được xem toàn bộ tickets trong department

Cấu hình tối giản:

- dùng RBAC permission dạng `query.view_department` (dept-wide)
- `query.assign` (assign/reassign)
- `query.close` (close)

# 5) Cập nhật Acceptance Criteria theo câu trả lời của bạn

- AC-CourseSurvey-Unique: với 1 course offering, hệ thống không cho activate survey run thứ 2 (trùng).
- AC-Gate-Global: nếu còn 1 assignment mandatory chưa completed → mọi feature portal bị khóa (chỉ cho phép submit survey).
- AC-Staff-Visibility: staff A không có quyền dept-wide → chỉ thấy ticket assigned cho A; staff B có quyền dept-wide → thấy tất cả ticket department.
- AC-NoReopen: ticket closed không xuất hiện nút reopen cho student.
