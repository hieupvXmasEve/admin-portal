## ADDED Requirements

### Requirement: GPA dùng điểm cao nhất giữa các attempt của cùng một unit

Hệ thống SHALL tính GPA dựa trên attempt có điểm cao nhất cho mỗi cặp `student_id + unit_id`, thay vì tính tất cả attempts. Column `is_gpa_contributing` (boolean) trên `academic_records` xác định record nào được tính vào GPA.

#### Scenario: Chỉ attempt điểm cao nhất ảnh hưởng GPA

- **WHEN** sinh viên có attempt gốc (điểm 45, fail) và attempt thi lại (điểm 65, pass) cho cùng unit
- **THEN** `GPACalculationService` chỉ dùng attempt điểm 65 (`is_gpa_contributing = true`)
- **AND** attempt gốc có `is_gpa_contributing = false`

#### Scenario: is_gpa_contributing được cập nhật khi có attempt mới

- **WHEN** AcademicRecord mới cho attempt thi lại được finalize
- **THEN** hệ thống so sánh `final_percentage` của tất cả attempts cho `student+unit`
- **AND** set `is_gpa_contributing = true` cho attempt có `final_percentage` cao nhất
- **AND** set `is_gpa_contributing = false` cho tất cả attempts khác của cùng `student+unit`

#### Scenario: GPA chỉ tính records có is_gpa_contributing = true

- **WHEN** `GPACalculationService` tính GPA cho sinh viên không có `gpa_calculations` snapshot hợp lệ
- **THEN** query filter `WHERE is_gpa_contributing = true AND excluded_from_gpa = false`

#### Scenario: Snapshot gpa_calculations được invalidate sau khi finalize retake

- **WHEN** `FinalizeRetakeCaseAction` chạy thành công cho một sinh viên
- **THEN** tất cả `gpa_calculations` của sinh viên đó được set `is_current = false`
- **AND** lần query GPA tiếp theo trên Portal sẽ fallback về live calculation từ `academic_records` (với `is_gpa_contributing` đã được cập nhật đúng)
- **AND** GPA snapshot mới sẽ được tạo lại khi `FinalizeSemesterGpaAction` chạy cuối kỳ

**Lý do**: `GPACalculationService.calculateCurrentGPA()` ưu tiên đọc `gpa_calculations` snapshot (`is_current = true`) trước khi tính từ `academic_records`. Nếu không invalidate snapshot, GPA hiển thị trên Portal sẽ không đổi dù `is_gpa_contributing` đã được cập nhật.

---

### Requirement: Môn được xem là đạt nếu có ít nhất 1 attempt pass

Hệ thống SHALL đánh giá môn học là "đã đạt" cho mục đích prerequisite, graduation check, và student status nếu sinh viên có ít nhất 1 `academic_records` với `is_passed = true` cho unit đó.

#### Scenario: Prerequisite được thỏa khi có 1 attempt pass

- **WHEN** sinh viên có attempt gốc fail và attempt thi lại pass cho unit A (prerequisite của unit B)
- **THEN** `PrerequisiteValidationService` xác định sinh viên đã thỏa unit A
- **AND** sinh viên có thể đăng ký unit B

#### Scenario: Graduation check dùng "ít nhất 1 attempt pass"

- **WHEN** hệ thống kiểm tra điều kiện tốt nghiệp
- **THEN** unit được tính là đạt nếu có ít nhất 1 `academic_records.is_passed = true` cho unit đó, bất kể attempt nào

#### Scenario: GPA rule và pass rule độc lập

- **WHEN** sinh viên có attempt gốc điểm 75 (pass) và attempt thi lại điểm 60 (pass)
- **THEN** `is_gpa_contributing = true` cho attempt điểm 75 (điểm cao hơn) — GPA rule
- **AND** môn vẫn được tính là đạt — pass rule không thay đổi (đã pass từ attempt gốc)
