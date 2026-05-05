## ADDED Requirements

### Requirement: Program stats page accessible from sidebar
Trang `/forms/admin/results/stats` hiển thị được qua menu "Program Stats" bên dưới "Survey Results" trong sidebar Surveys section, yêu cầu permission `view_survey_results_aggregate`.

#### Scenario: User có permission mở trang stats
- **WHEN** user có `view_survey_results_aggregate` và truy cập `/forms/admin/results/stats`
- **THEN** trang render bảng stats với rows cho từng program

#### Scenario: User không có permission
- **WHEN** user không có `view_survey_results_aggregate`
- **THEN** redirect 403

---

### Requirement: Submissions count per program
Cột "Submissions" đếm số `student_form_assignments` có `response_id IS NOT NULL`, join qua student → program, scope theo campus hiện tại.

#### Scenario: Filter all semesters
- **WHEN** không chọn semester (all)
- **THEN** đếm tất cả submissions thuộc mọi form_target của campus

#### Scenario: Filter theo 1 semester
- **WHEN** chọn semester_id cụ thể
- **THEN** chỉ đếm submissions thuộc form_target có `semester_id` đó

---

### Requirement: High-rated count per program (KQ TBC ≥ 4)
Cột "KQ TBC ≥ 4" đếm số response mà AVG của tất cả `answers.answer_number` (chỉ những answer thuộc `questions.type = 'rating'`) ≥ 4.

#### Scenario: Response có đủ rating answers, avg ≥ 4
- **WHEN** AVG rating answers của response = 4.2
- **THEN** response được đếm vào `high_rated_count`

#### Scenario: Response có đủ rating answers, avg < 4
- **WHEN** AVG rating answers của response = 3.8
- **THEN** response không được đếm vào `high_rated_count`

#### Scenario: Response không có rating answer nào
- **WHEN** form không có rating questions hoặc student bỏ trống tất cả rating
- **THEN** response không được đếm vào `high_rated_count` (LEFT JOIN → NULL avg → không thỏa ≥ 4)

---

### Requirement: Percentage display
Cột "%" hiển thị `high_rated_count / submissions * 100` làm tròn 1 chữ số thập phân, kèm ký hiệu `%`. Nếu submissions = 0, hiển thị `—`.

#### Scenario: Có submissions
- **WHEN** submissions = 200, high_rated = 160
- **THEN** hiển thị `80.0%`

#### Scenario: Không có submissions
- **WHEN** submissions = 0
- **THEN** hiển thị `—`

---

### Requirement: TOTAL row
Hàng cuối bảng hiển thị tổng cộng của tất cả programs đang hiển thị.

#### Scenario: Tổng hợp
- **WHEN** bảng có Semi(245), AI(180), BA(312), Finance(97)
- **THEN** TOTAL row = 834 submissions, tổng high_rated, % tổng

---

### Requirement: Semester filter
Dropdown chọn semester. Options: "All Semesters" + danh sách semesters theo `start_date DESC`. Submit bằng GET (không phải AJAX).

#### Scenario: Thay đổi semester
- **WHEN** user chọn semester khác và submit
- **THEN** trang reload với `?semester_id=<id>`, bảng hiển thị stats cho semester đó
