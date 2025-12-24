# 1. Nhóm định danh & snapshot ngữ cảnh (WHO / WHERE / WHEN)

### 🎯 Mục đích

- Xác định **bản ghi này thuộc về ai – học cái gì – ở đâu – trong bối cảnh nào**
- Snapshot để **sau này curriculum / program đổi vẫn không ảnh hưởng lịch sử**

```text
student_id
course_offering_id
semester_id
unit_id
program_id
campus_id
```

### Phân tích

| Field                | Dùng để                                  |
| -------------------- | ---------------------------------------- |
| `student_id`         | Ai học                                   |
| `course_offering_id` | Lớp cụ thể (section, lecturer, schedule) |
| `semester_id`        | Phục vụ transcript theo kỳ               |
| `unit_id`            | Môn học (ổn định hơn course_offering)    |
| `program_id`         | Snapshot chương trình tại thời điểm học  |
| `campus_id`          | Multi-campus transcript / reporting      |

📌 **Kết luận**: Đây là “khung xương” của transcript.

# 2. Nhóm điểm số & kết quả học tập (GRADING CORE)

### 🎯 Mục đích

- Lưu **kết quả cuối cùng** của môn học
- Dùng trực tiếp cho:
    - GPA
    - transcript
    - graduation check

```text
final_percentage
final_letter_grade
grade_points
quality_points
raw_percentage
curve_adjustment
grade_adjustment_reason
```

### Ý nghĩa chuẩn đại học

| Field                     | Ý nghĩa                     |
| ------------------------- | --------------------------- |
| `raw_percentage`          | Điểm gốc trước khi curve    |
| `curve_adjustment`        | Điều chỉnh (+/-)            |
| `final_percentage`        | Điểm sau cùng               |
| `final_letter_grade`      | A / B / C / D / F           |
| `grade_points`            | Điểm quy đổi (0–4 / 0–5)    |
| `quality_points`          | grade_points × credit_hours |
| `grade_adjustment_reason` | Lý do override              |

📌 **Kết luận**: Đây là **lõi học thuật** của hệ thống.
👉 **BẮT BUỘC GIỮ**

# 3. Nhóm tín chỉ & ảnh hưởng GPA

### 🎯 Mục đích

- Phục vụ **GPA calculation engine**
- Xử lý các case đặc biệt: pass/no-credit, audit, transfer

```text
credit_hours
credit_hours_earned
excluded_from_gpa
gpa_exclusion_reason
```

| Field                  | Dùng để                        |
| ---------------------- | ------------------------------ |
| `credit_hours`         | Tín chỉ môn                    |
| `credit_hours_earned`  | Thực sự được tính (0 nếu fail) |
| `excluded_from_gpa`    | Có tính GPA không              |
| `gpa_exclusion_reason` | Audit / Pass-Fail / Transfer   |

📌 **Kết luận**: Đây là **lõi học thuật** của hệ thống.
👉 **GIỮ NGUYÊN** – rất cần cho GPA & graduation

# 4. Nhóm trạng thái học tập & hoàn thành (STATUS ENGINE)

### 🎯 Mục đích

- Trả lời: _môn này đang ở giai đoạn nào?_

```text
grade_status
registration_status
is_passed
override_pass
override_reason
```

| Field                 | Ý nghĩa                           |
| --------------------- | --------------------------------- |
| `grade_status`        | in_progress / provisional / final |
| `registration_status` | enrolled / completed / failed     |
| `is_passed`           | Boolean kết luận                  |
| `override_pass`       | Admin override                    |
| `override_reason`     | Audit                             |

📌 **Rất đúng thiết kế**
👉 **grade_status ≠ registration_status** là điểm cộng lớn

# 5. Nhóm attendance (ảnh hưởng điểm & eligibility)

### 🎯 Mục đích

- Tổng hợp attendance từ `attendances`
- Dùng để:
    - chặn thi
    - fail do attendance

```text
attendance_percentage
total_absences
total_present
total_late
total_not_recorded
total_class_sessions
meets_attendance_requirement
```

📌 **Nhận xét**

- Đây là **aggregated data** → đúng chỗ trong academic_records
- Không nên tính realtime mỗi lần query

👉 **GIỮ**, rất hợp lý cho performance & audit

---

# 6. Nhóm repeat / retake / history

### 🎯 Mục đích

- Xử lý học lại, học cải thiện điểm
- Đảm bảo transcript đúng chuẩn

```text
is_repeat_course
attempt_number
original_record_id
```

| Field                | Dùng để          |
| -------------------- | ---------------- |
| `is_repeat_course`   | Có phải học lại  |
| `attempt_number`     | Lần thứ mấy      |
| `original_record_id` | Link attempt đầu |

📌 **Chuẩn textbook university system**
👉 **GIỮ NGUYÊN**

---

# 7. Nhóm transfer / credit by exam

### 🎯 Mục đích

- Học chuyển tiếp
- AP / Challenge exam

```text
is_transfer_credit
transfer_institution
transfer_course_code
transfer_course_title
is_advanced_placement
is_challenge_exam
is_credit_by_exam
```

📌 **Nhận xét**

- Đa số hệ thống nhỏ không có, nhưng bạn làm **enterprise-level**
  👉 **Rất nên giữ**

---

# 8. Nhóm breakdown & history (AUDIT CRITICAL)

### 🎯 Mục đích

- Lưu **chi tiết cấu phần điểm**
- Phục vụ:
    - dispute
    - accreditation
    - audit

```text
grade_breakdown (JSON)
grade_history (JSON)
last_grade_change_at
last_changed_by_lecture_id
```

📌 **Đây là “bảo hiểm pháp lý”**
👉 **KHÔNG BAO GIỜ bỏ**

---

# 9. Nhóm quyền ảnh hưởng học thuật

### 🎯 Mục đích

- Phục vụ rule engine:
    - prerequisite
    - standing
    - graduation

```text
affects_academic_standing
affects_graduation_requirement
satisfies_prerequisite
```

- Cho phép override từng môn riêng lẻ

# 10. Nhóm con người & approval workflow

### 🎯 Mục đích

- Trace trách nhiệm giảng viên

```text
instructor_id
grade_submitted_by_lecture_id
grade_approved_by_lecture_id
administrative_notes
instructor_comments
```

👉 Giữ để sau này làm:

- 2-step approval
- grade dispute

# 11. Nhóm thời gian (timeline học tập)

```text
completion_date
grade_submission_date
grade_finalized_date
```

| Field                 | Dùng cho             |
| --------------------- | -------------------- |
| grade_submission_date | Lecturer submit      |
| grade_finalized_date  | Academic office chốt |
| completion_date       | Hoàn thành           |

📌 **Có thể derive**, nhưng giữ giúp:

- query nhanh
- audit rõ ràng

# 12. Kết luận tổng thể

### 🔥 Đánh giá thẳng thắn

- `academic_records` của bạn **đã đạt mức enterprise / PeopleSoft-like**
- Không dư thừa nghiêm trọng
- Mọi field đều **có lý do tồn tại**

### 🎯 Nguyên tắc vàng

> **Nếu field đó ảnh hưởng transcript, GPA, graduation, audit → nó thuộc academic_records**

---

Nếu bạn muốn, bước tiếp theo mình có thể:

- Chuẩn hóa **ENUM values** (đang hơi trùng nghĩa)
- Đề xuất **derived fields vs stored fields**
- Viết **GPA calculation pseudo-code** dựa trực tiếp trên bảng này
- So sánh với **Canvas gradebook vs SIS transcript**

Chỉ cần nói tiếp:
👉 **“phân tích tiếp phần …”**
