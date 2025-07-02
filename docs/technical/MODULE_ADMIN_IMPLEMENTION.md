# Module Admin Implemention

## **1. System Management**

Quản lý hệ thống, người dùng, vai trò, học kỳ, cơ sở vật chất.

| Chức năng | Bảng liên quan |
| --- | --- |
| Quản lý người dùng | `users`, `campus_user_roles` |
| Quản lý vai trò & quyền | `roles`, `permissions`, `role_permissions` |
| Phân quyền theo cơ sở | `campus_user_roles`, `campuses` |
| Quản lý cơ sở vật chất | `campuses`, `buildings`, `rooms` |
| Quản lý học kỳ | `semesters` |


## **2. Curriculum & Courses**

Ngành, chuyên ngành, chương trình, môn học.

| Chức năng | Bảng liên quan |
| --- | --- |
| Ngành học (Program) | `programs` |
| Chuyên ngành | `specializations` |
| Môn học (Unit) | `units` |
| Môn học tương đương | `equivalent_units` |
| Khung chương trình | `curriculum_versions`, `curriculum_units` |
| Điều kiện tiên quyết | `unit_prerequisite_groups`, `unit_prerequisite_conditions` |
| Chuẩn đầu ra, đề cương | `syllabus`, `assessment_components`, `assessment_component_details` |



## **3. Course Offerings & Registration**

Mở lớp, lịch học, đăng ký môn học.

| Chức năng | Bảng liên quan |
| --- | --- |
| Mở lớp (Course Offering) | `course_offerings` |
| Lịch lớp học (Session) | `class_sessions`, `room_bookings` |
| Đăng ký môn học | `course_registrations` |
| Ghi danh học kỳ | `enrollments` |



## **4. Student Management**

Thông tin sinh viên, học tập, học lực.

| Chức năng | Bảng liên quan |
| --- | --- |
| Hồ sơ sinh viên | `students` |
| Ghi chú học vụ (Hold) | `academic_holds` |
| Học lực & bảng điểm | `academic_records` |
| GPA & học lực | `gpa_calculations` |



## **5. Lecturer Management**

Giảng viên và phân công giảng dạy.

| Chức năng | Bảng liên quan |
| --- | --- |
| Hồ sơ giảng viên | `lectures` |
| Lịch giảng dạy | `class_sessions` (qua `instructor_id`) |
| Phân công lớp học | `course_offerings.lecture_id` |



## **6. Attendance Management**

Điểm danh, tracking, báo cáo.

| Chức năng | Bảng liên quan |
| --- | --- |
| Điểm danh từng buổi | `attendances` |
| Buổi học cụ thể (Class) | `class_sessions` |
| GPS / check-in | Trường `latitude`, `longitude`, `recording_method` trong `attendances` |



## **7. Assessments & Grading**

Cấu trúc điểm, nhập điểm, kết quả.

| Chức năng | Bảng liên quan |
| --- | --- |
| Thành phần điểm | `assessment_components`, `assessment_component_details` |
| Điểm chi tiết | `assessment_component_detail_scores` |
| Nhập điểm & kết quả | `academic_records` (tổng kết) |
| Ghi chú, appeals, rubrics | Các trường `rubric_scores`, `appeal_*`, `score_history`... |



## **8. Academic Summary**

Kết quả tổng hợp, tốt nghiệp.

| Chức năng | Bảng liên quan |
| --- | --- |
| GPA lịch sử & phân loại tốt nghiệp | `gpa_calculations` |
| Điều kiện tốt nghiệp | `graduation_requirements` |
| Phân loại văn bằng | Xử lý từ logic `gpa`, `honors_eligible` trong `gpa_calculations` |



## **9. Reports & Analytics**

| Chức năng | Bảng liên quan |
| --- | --- |
| Thống kê sinh viên | `students`, `enrollments`, `programs` |
| Phân tích học lực | `academic_records`, `gpa_calculations` |
| Báo cáo điểm danh | `attendances`, `class_sessions` |



## **10. Program Transfers & Retakes**

| Chức năng | Bảng liên quan |
| --- | --- |
| Chuyển ngành, học lại | `academic_records.is_repeat_course`, `course_registrations.is_retake` |
| Mapping môn tương đương | `equivalent_units` |
| Công nhận tín chỉ | `academic_records.is_transfer_credit`, `transfer_*` fields |