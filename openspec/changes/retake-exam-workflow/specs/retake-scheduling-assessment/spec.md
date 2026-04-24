## ADDED Requirements

### Requirement: Đào tạo tạo CourseOffering thi lại sau khi roster confirmed

Hệ thống SHALL chỉ cho phép tạo CourseOffering thi lại sau khi Đào tạo đã xác nhận roster (`roster_confirmed`). CourseOffering thi lại SHALL có `is_retake = true` và `retake_batch_id` liên kết với batch tương ứng.

#### Scenario: Đào tạo tạo CourseOffering thi lại

- **WHEN** Đào tạo xác nhận roster và tạo CourseOffering
- **THEN** CourseOffering mới được tạo với `is_retake = true`, `retake_batch_id`, Đào tạo chọn GV/cán bộ phụ trách
- **AND** `retake_batches.course_offering_id` được cập nhật
- **AND** tất cả case `roster_confirmed` chuyển sang `scheduled`
- **AND** sinh viên nhận notification "Lịch thi lại đã được xếp"

#### Scenario: Không thể tạo CourseOffering khi chưa có roster_confirmed

- **WHEN** Đào tạo cố tạo CourseOffering cho batch chưa có case nào `roster_confirmed`
- **THEN** hệ thống từ chối

#### Scenario: course_registrations được tạo ngay khi tạo CourseOffering thi lại

- **WHEN** CourseOffering thi lại được tạo thành công
- **THEN** hệ thống tạo `course_registrations` với `is_retake = true` cho tất cả sinh viên có case `roster_confirmed` trong batch
- **AND** `course_registrations.attempt_number` = attempt hiện tại + 1
- **AND** `course_registrations.original_registration_id` = registration gốc của attempt fail

**Lý do bắt buộc**: `CanvasGradeSyncService` và hệ thống attendance đọc enrollment từ `CourseRegistration::where('course_offering_id')`. Không có `course_registrations` thì Canvas sync không thấy sinh viên và attendance/calendar bị lệch.

---

### Requirement: Session thi lại linh hoạt, mỗi sinh viên chỉ trong 1 session

Hệ thống SHALL cho phép Đào tạo tạo một hoặc nhiều `ClassSession` cho CourseOffering thi lại. Mỗi sinh viên trong batch chỉ được gắn vào đúng 1 session.

#### Scenario: Hệ thống đề xuất phân session

- **WHEN** Đào tạo tạo sessions với capacity cho từng session
- **THEN** hệ thống đề xuất phân sinh viên vào sessions dựa trên capacity

#### Scenario: Đào tạo chỉnh tay trước khi chốt

- **WHEN** Đào tạo muốn thay đổi phân session của sinh viên
- **THEN** có thể kéo/thả hoặc chọn lại session cho từng sinh viên, với điều kiện không vượt capacity

#### Scenario: Sinh viên thấy lịch thi trên calendar

- **WHEN** session được chốt và case ở status `scheduled`
- **THEN** sinh viên thấy lịch thi lại trên Student Portal calendar

---

### Requirement: Attendance tại exam session

Hệ thống SHALL hỗ trợ điểm danh tại session thi lại. Session với `session_type = 'exam'` là mốc xác định có mặt/vắng thi.

#### Scenario: Sinh viên điểm danh có mặt

- **WHEN** GV/cán bộ đánh dấu sinh viên có mặt tại exam session
- **THEN** attendance record được tạo
- **AND** case GIỮ nguyên ở `scheduled` — không chuyển sang `assessed`
- **AND** `assessed` chỉ được trigger khi Canvas sync thành công HOẶC manual score được Đào tạo approve

#### Scenario: Sinh viên vắng thi (no-show)

- **WHEN** GV/cán bộ finalize attendance tại exam session và sinh viên không có mặt
- **THEN** case chuyển sang `no_show`
- **AND** hệ thống tạo AcademicRecord attempt mới với `final_result = 'no_show'`, `is_passed = false`
- **AND** case tự động chuyển sang `repeat_course` với `fallback_reason_code = 'no_show'`

---

### Requirement: Canvas sync là nguồn điểm chính cho thi lại

Hệ thống SHALL sync điểm từ Canvas cho CourseOffering thi lại theo đúng pipeline hiện tại (`CanvasGradeSyncService`). Canvas được ưu tiên hơn manual input.

#### Scenario: Canvas sync thành công cho retake offering

- **WHEN** Canvas sync chạy cho CourseOffering có `is_retake = true`
- **THEN** điểm được sync vào AcademicRecord của attempt thi lại, case chuyển sang `assessed`

---

### Requirement: Manual score fallback khi Canvas không sync được

Khi Canvas không map/sync được cho CourseOffering thi lại, hệ thống SHALL cho phép GV/cán bộ nhập `final_retake_score` thủ công. Score chỉ có hiệu lực sau khi Đào tạo approve.

#### Scenario: GV nhập manual score

- **WHEN** Canvas không sync được và GV nhập `final_retake_score`
- **THEN** case chuyển sang `manual_score_pending_review`, score chờ Đào tạo review

#### Scenario: Đào tạo approve manual score

- **WHEN** Đào tạo approve score đã nhập
- **THEN** score được apply vào AcademicRecord attempt mới, case chuyển sang `assessed`

#### Scenario: Đào tạo reject manual score

- **WHEN** Đào tạo reject score
- **THEN** case trả về trạng thái chờ GV nhập lại; Đào tạo không được sửa điểm trực tiếp

---

### Requirement: Kết quả thi lại tạo AcademicRecord attempt mới

Hệ thống SHALL tạo một `academic_records` mới cho attempt thi lại, liên kết `original_record_id` về record fail gốc, không overwrite record cũ.

#### Scenario: AcademicRecord mới được tạo khi có kết quả

- **WHEN** kết quả thi lại được chốt (Canvas sync hoặc manual approved)
- **THEN** `academic_records` mới được tạo với `attempt_number = previous + 1`, `original_record_id = fail_record.id`
- **AND** `retake_case.retake_academic_record_id` được cập nhật
- **AND** record fail gốc vẫn được giữ nguyên

#### Scenario: Pass/Fail theo grade scale của unit

- **WHEN** `final_retake_score` được set
- **THEN** hệ thống áp dụng đúng passing threshold của unit (`syllabusTemplate.min_grade_threshold`) để xác định `is_passed`

#### Scenario: Final pass kết thúc workflow

- **WHEN** attempt thi lại có `is_passed = true`
- **THEN** retake case chuyển sang `final_pass` (terminal)
- **AND** sinh viên nhận notification kết quả

#### Scenario: Final fail chuyển sang repeat_course

- **WHEN** attempt thi lại có `is_passed = false`
- **THEN** retake case chuyển sang `final_fail` → `repeat_course`
- **AND** sinh viên nhận notification kết quả
