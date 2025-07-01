# Luồng Tạo Dữ Liệu Hoàn Chỉnh cho Hệ Thống Giáo Dục (SwinX)

Tài liệu này mô tả chi tiết luồng tạo dữ liệu tuần tự và logic cho hệ thống SwinX, dựa trên cấu trúc database được định nghĩa trong migrations. Việc tuân thủ luồng này đảm bảo tính toàn vẹn dữ liệu (data integrity) và tránh các lỗi liên quan đến khóa ngoại (foreign key constraints).

## Giai đoạn 1: Cài đặt Hệ thống Lõi và Cơ sở (Core System & Campus Setup)

Đây là những dữ liệu nền tảng, cần được tạo trước tiên để hệ thống có thể hoạt động.

### 1. `campuses` (Cơ sở)
- **Lý do**: Là đơn vị tổ chức cấp cao nhất (ví dụ: Swinburne Hà Nội, Swinburne TP.HCM). Hầu hết các dữ liệu khác (như tòa nhà, học kỳ, người dùng) đều trực thuộc một cơ sở cụ thể.
- **Bảng liên quan**: `campuses`

### 2. `buildings` (Tòa nhà) & `rooms` (Phòng học)
- **Lý do**: Các phòng học thuộc về các tòa nhà, và các tòa nhà thuộc về một cơ sở. Cần tạo tuần tự: `Campuses` -> `Buildings` -> `Rooms`.
- **Bảng liên quan**: `buildings`, `rooms`
- **Phụ thuộc**: `buildings.campus_id` -> `campuses.id`; `rooms.building_id` -> `buildings.id`

### 3. `permissions`, `roles`, `role_permissions` (Quyền & Vai trò)
- **Lý do**: Định nghĩa hệ thống phân quyền. `permissions` là danh sách tất cả các hành động có thể có. `roles` là các nhóm vai trò (Admin, Giảng viên, Sinh viên). `role_permissions` gán các quyền cho vai trò tương ứng.
- **Bảng liên quan**: `permissions`, `roles`, `role_permissions`

### 4. `users` (Người dùng)
- **Lý do**: Tạo tài khoản cho tất cả các đối tượng sẽ sử dụng hệ thống. Tại bước này, người dùng chỉ là một tài khoản đăng nhập, chưa có vai trò cụ thể.
- **Bảng liên quan**: `users`

### 5. `campus_user_roles` (Phân vai trò cho người dùng tại cơ sở)
- **Lý do**: Gán vai trò (`roles`) cho người dùng (`users`) tại một cơ sở (`campuses`) cụ thể. Một người dùng có thể có nhiều vai trò ở các cơ sở khác nhau. Bước này kích hoạt chức năng của người dùng trong hệ thống.
- **Bảng liên quan**: `campus_user_roles`
- **Phụ thuộc**: `campus_user_roles.user_id` -> `users.id`; `campus_user_roles.role_id` -> `roles.id`; `campus_user_roles.campus_id` -> `campuses.id`.

## Giai đoạn 2: Cấu trúc Học thuật và Chương trình Đào tạo (Academic & Curriculum)

Sau khi có nền tảng hệ thống, chúng ta định nghĩa cấu trúc về học thuật.

### 6. `programs` (Chương trình đào tạo)
- **Lý do**: Định nghĩa các ngành học lớn mà trường cung cấp (VD: Cử nhân Công nghệ thông tin).
- **Bảng liên quan**: `programs`

### 7. `specializations` (Chuyên ngành)
- **Lý do**: Định nghĩa các chuyên ngành hẹp bên trong một chương trình đào tạo (VD: Phát triển phần mềm, An ninh mạng).
- **Bảng liên quan**: `specializations`
- **Phụ thuộc**: `specializations.program_id` -> `programs.id`

### 8. `units` (Môn học)
- **Lý do**: Định nghĩa danh sách tất cả các môn học có thể được giảng dạy trong trường.
- **Bảng liên quan**: `units`

### 9. `equivalent_units` & `unit_prerequisites` (Môn học tương đương & Tiên quyết)
- **Lý do**: Thiết lập các mối quan hệ giữa các môn học. Môn tương đương cho phép thay thế, môn tiên quyết yêu cầu phải hoàn thành trước.
- **Bảng liên quan**: `equivalent_units`, `unit_prerequisite_groups`, `unit_prerequisite_conditions`
- **Phụ thuộc**: Cần có `units` trước.

### 10. `curriculum_versions` (Phiên bản chương trình đào tạo)
- **Lý do**: Một chương trình đào tạo (`programs`) có thể có nhiều phiên bản khác nhau theo thời gian. Mỗi phiên bản định nghĩa một bộ các môn học và yêu cầu riêng.
- **Bảng liên quan**: `curriculum_versions`
- **Phụ thuộc**: `curriculum_versions.program_id` -> `programs.id`

### 11. `curriculum_units` (Các môn học trong chương trình đào tạo)
- **Lý do**: Gắn các môn học (`units`) vào một phiên bản chương trình đào tạo (`curriculum_versions`), xác định môn nào là bắt buộc, môn nào là tự chọn.
- **Bảng liên quan**: `curriculum_units`
- **Phụ thuộc**: `curriculum_units.curriculum_version_id` -> `curriculum_versions.id`; `curriculum_units.unit_id` -> `units.id`

### 12. `graduation_requirements` (Yêu cầu tốt nghiệp)
- **Lý do**: Quy định số tín chỉ cần tích lũy để tốt nghiệp cho một phiên bản chương trình đào tạo hoặc chuyên ngành.
- **Bảng liên quan**: `graduation_requirements`
- **Phụ thuộc**: `graduation_requirements.curriculum_version_id` -> `curriculum_versions.id`

## Giai đoạn 3: Lên kế hoạch Học kỳ và Mở lớp (Semester & Course Offering)

Giai đoạn này tập trung vào việc chuẩn bị cho một học kỳ cụ thể.

### 13. `semesters` (Học kỳ)
- **Lý do**: Định nghĩa các kỳ học trong năm (VD: Mùa Thu 2025, Mùa Xuân 2026) cho từng cơ sở.
- **Bảng liên quan**: `semesters`
- **Phụ thuộc**: `semesters.campus_id` -> `campuses.id`

### 14. `lecturers` (Giảng viên)
- **Lý do**: Tạo hồ sơ giảng viên, liên kết với một tài khoản người dùng (`users`) đã có.
- **Bảng liên quan**: `lecturers`
- **Phụ thuộc**: `lecturers.user_id` -> `users.id`

### 15. `course_offerings` (Lớp học được mở)
- **Lý do**: Đây là bảng trung tâm, biểu diễn một môn học (`units`) cụ thể được mở trong một học kỳ (`semesters`) và do một giảng viên (`lecturers`) phụ trách. Sinh viên sẽ đăng ký vào đây.
- **Bảng liên quan**: `course_offerings`
- **Phụ thuộc**: `course_offerings.unit_id` -> `units.id`; `course_offerings.semester_id` -> `semesters.id`; `course_offerings.lecturer_id` -> `lecturers.id`

### 16. `syllabus` & `assessment_components` (Đề cương & Thành phần đánh giá)
- **Lý do**: Tạo đề cương chi tiết cho mỗi lớp học được mở (`course_offerings`), bao gồm các thành phần điểm (bài tập, thi giữa kỳ, thi cuối kỳ).
- **Bảng liên quan**: `syllabus`, `assessment_components`, `assessment_component_details`
- **Phụ thuộc**: `syllabus.course_offering_id` -> `course_offerings.id`

### 17. `room_bookings` (Đặt phòng học)
- **Lý do**: Sắp xếp lịch và phòng học (`rooms`) cho các lớp học (`course_offerings`).
- **Bảng liên quan**: `room_bookings`
- **Phụ thuộc**: `room_bookings.room_id` -> `rooms.id`; `room_bookings.course_offering_id` -> `course_offerings.id`

## Giai đoạn 4: Vòng đời Sinh viên (Student Lifecycle)

Giai đoạn cuối cùng mô tả các hoạt động của sinh viên từ lúc nhập học đến khi có kết quả học tập.

### 18. `students` (Sinh viên)
- **Lý do**: Tạo hồ sơ sinh viên, liên kết với một tài khoản người dùng (`users`) và chương trình đào tạo (`programs`) họ theo học.
- **Bảng liên quan**: `students`
- **Phụ thuộc**: `students.user_id` -> `users.id`; `students.program_id` -> `programs.id`

### 19. `enrollments` (Nhập học)
- **Lý do**: Ghi danh chính thức cho sinh viên vào một chương trình học, chuyên ngành tại một học kỳ bắt đầu.
- **Bảng liên quan**: `enrollments`
- **Phụ thuộc**: `enrollments.student_id` -> `students.id`; `enrollments.program_id` -> `programs.id`, `enrollments.semester_id` -> `semesters.id`.

### 20. `course_registrations` (Đăng ký môn học)
- **Lý do**: Ghi nhận việc sinh viên (`students`) đăng ký vào một lớp học cụ thể (`course_offerings`).
- **Bảng liên quan**: `course_registrations`
- **Phụ thuộc**: `course_registrations.student_id` -> `students.id`; `course_registrations.course_offering_id` -> `course_offerings.id`

### 21. `class_sessions` & `attendances` (Buổi học & Điểm danh)
- **Lý do**: Tạo các buổi học chi tiết (lý thuyết, thực hành) cho một lớp và ghi nhận sự có mặt của sinh viên.
- **Bảng liên quan**: `class_sessions`, `attendances`
- **Phụ thuộc**: `attendances.student_id` -> `students.id`; `attendances.class_session_id` -> `class_sessions.id`

### 22. `academic_records` & `assessment_component_detail_scores` (Kết quả học tập)
- **Lý do**: Sau khi kết thúc học kỳ, bảng `academic_records` ghi nhận điểm cuối cùng của sinh viên cho môn học. Bảng `assessment_component_detail_scores` lưu điểm chi tiết của từng thành phần đánh giá.
- **Bảng liên quan**: `academic_records`, `assessment_component_detail_scores`
- **Phụ thuộc**: `academic_records.course_registration_id` -> `course_registrations.id`

### 23. `gpa_calculations` (Tính GPA)
- **Lý do**: Tính toán và lưu trữ điểm GPA theo học kỳ và GPA tích lũy cho mỗi sinh viên.
- **Bảng liên quan**: `gpa_calculations`
- **Phụ thuộc**: `gpa_calculations.student_id` -> `students.id`; `gpa_calculations.semester_id` -> `semesters.id`
