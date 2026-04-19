# Luồng Tạo Dữ Liệu Hoàn Chỉnh cho Hệ Thống Giáo Dục (SwinX)

Tài liệu này mô tả chi tiết luồng tạo dữ liệu tuần tự và logic cho hệ thống SwinX, dựa trên cấu trúc database được định nghĩa trong migrations. Việc tuân thủ luồng này đảm bảo tính toàn vẹn dữ liệu (data integrity) và tránh các lỗi liên quan đến khóa ngoại (foreign key constraints).

> **Cập nhật lần cuối**: 2026-03-26
>
> **Lưu ý trạng thái**: Tài liệu này là luồng tham chiếu mức cao và có phần drift theo thời gian.
> Các phần có xác suất stale cao: Forms/Surveys và một số tên bảng/model phụ trợ.
> Giai đoạn Finance (mục 46-49) được cập nhật với settlement v2 landing 2026-03-25.
> Trước khi dùng làm spec triển khai, cần đối chiếu trực tiếp migration hiện tại trong `database/migrations/`.

---

## Giai đoạn 1: Cài đặt Hệ thống Lõi và Cơ sở (Core System & Campus Setup)

Đây là những dữ liệu nền tảng, cần được tạo trước tiên để hệ thống có thể hoạt động.

### 1. `campuses` (Cơ sở)

- **Lý do**: Là đơn vị tổ chức cấp cao nhất (ví dụ: Swinburne Hà Nội, Swinburne TP.HCM). Hầu hết các dữ liệu khác (như tòa nhà, học kỳ, người dùng) đều trực thuộc một cơ sở cụ thể.
- **Bảng liên quan**: `campuses`
- **Model**: `App\Models\Campus`

### 2. `buildings` (Tòa nhà) & `rooms` (Phòng học)

- **Lý do**: Các phòng học thuộc về các tòa nhà, và các tòa nhà thuộc về một cơ sở. Cần tạo tuần tự: `campuses` -> `buildings` -> `rooms`.
- **Bảng liên quan**: `buildings`, `rooms`
- **Model**: `App\Models\Building`, `App\Models\Room`
- **Phụ thuộc**: `buildings.campus_id` -> `campuses.id`; `rooms.building_id` -> `buildings.id`; `rooms.campus_id` -> `campuses.id`

### 3. `permissions`, `roles`, `role_permissions` (Quyền & Vai trò)

- **Lý do**: Định nghĩa hệ thống phân quyền. `permissions` là danh sách tất cả các hành động có thể có. `roles` là các nhóm vai trò (Admin, Giảng viên, Sinh viên). `role_permissions` gán các quyền cho vai trò tương ứng.
- **Bảng liên quan**: `permissions`, `roles`, `role_permissions`
- **Model**: `App\Models\Permission`, `App\Models\Role`, `App\Models\RolePermission`

### 4. `departments` (Phòng ban/Khoa)

- **Lý do**: Định nghĩa các phòng ban hoặc khoa trong trường. Người dùng có thể thuộc một hoặc nhiều phòng ban.
- **Bảng liên quan**: `departments`, `department_memberships`
- **Model**: `App\Models\Department`, `App\Models\DepartmentMembership`
- **Phụ thuộc**: `department_memberships.user_id` -> `users.id`; `department_memberships.department_id` -> `departments.id`

### 5. `users` (Người dùng)

- **Lý do**: Tạo tài khoản cho tất cả các đối tượng sẽ sử dụng hệ thống. Tại bước này, người dùng chỉ là một tài khoản đăng nhập, chưa có vai trò cụ thể.
- **Bảng liên quan**: `users`
- **Model**: `App\Models\User`
- **Phụ thuộc (tùy chọn)**: `users.department_id` -> `departments.id`

### 6. `campus_user_roles` (Phân vai trò cho người dùng tại cơ sở)

- **Lý do**: Gán vai trò (`roles`) cho người dùng (`users`) tại một cơ sở (`campuses`) cụ thể. Một người dùng có thể có nhiều vai trò ở các cơ sở khác nhau. Bước này kích hoạt chức năng của người dùng trong hệ thống.
- **Bảng liên quan**: `campus_user_roles`
- **Model**: `App\Models\CampusUserRole`
- **Phụ thuộc**: `campus_user_roles.user_id` -> `users.id`; `campus_user_roles.role_id` -> `roles.id`; `campus_user_roles.campus_id` -> `campuses.id`

## Giai đoạn 2: Cấu trúc Học thuật và Chương trình Đào tạo (Academic & Curriculum)

Sau khi có nền tảng hệ thống, chúng ta định nghĩa cấu trúc về học thuật.

### 7. `programs` (Chương trình đào tạo)

- **Lý do**: Định nghĩa các ngành học lớn mà trường cung cấp (VD: Cử nhân Công nghệ thông tin).
- **Bảng liên quan**: `programs`
- **Model**: `App\Models\Program`

### 8. `specializations` (Chuyên ngành)

- **Lý do**: Định nghĩa các chuyên ngành hẹp bên trong một chương trình đào tạo (VD: Phát triển phần mềm, An ninh mạng).
- **Bảng liên quan**: `specializations`
- **Model**: `App\Models\Specialization`
- **Phụ thuộc**: `specializations.program_id` -> `programs.id`

### 9. `units` (Môn học)

- **Lý do**: Định nghĩa danh sách tất cả các môn học có thể được giảng dạy trong trường.
- **Bảng liên quan**: `units`
- **Model**: `App\Models\Unit`

### 10. `equivalent_units` & `unit_prerequisite_groups`, `unit_prerequisite_conditions` (Môn học tương đương & Tiên quyết)

- **Lý do**: Thiết lập các mối quan hệ giữa các môn học. Môn tương đương cho phép thay thế, môn tiên quyết yêu cầu phải hoàn thành trước.
- **Bảng liên quan**: `equivalent_units`, `unit_prerequisite_groups`, `unit_prerequisite_conditions`
- **Model**: `App\Models\EquivalentUnit`, `App\Models\UnitPrerequisiteGroup`, `App\Models\UnitPrerequisiteCondition`
- **Phụ thuộc**: Cần có `units` trước. `unit_prerequisite_groups.unit_id` -> `units.id`; `unit_prerequisite_conditions.required_unit_id` -> `units.id`

### 11. `curriculum_versions` (Phiên bản chương trình đào tạo)

- **Lý do**: Một chương trình đào tạo (`programs`) có thể có nhiều phiên bản khác nhau theo thời gian. Mỗi phiên bản định nghĩa một bộ các môn học và yêu cầu riêng.
- **Bảng liên quan**: `curriculum_versions`
- **Model**: `App\Models\CurriculumVersion`
- **Phụ thuộc**: `curriculum_versions.program_id` -> `programs.id`; `curriculum_versions.specialization_id` -> `specializations.id` (tùy chọn)

### 12. `modules` & `curriculum_modules` (Module trong chương trình)

- **Lý do**: Các phiên bản chương trình đào tạo có thể được tổ chức thành các module (nhóm môn học logic như Core, Major, Minor, Elective).
- **Bảng liên quan**: `modules`, `curriculum_modules`, `module_units`
- **Model**: `App\Models\Module`, `App\Models\CurriculumModule`
- **Phụ thuộc**: `curriculum_modules.curriculum_version_id` -> `curriculum_versions.id`; `curriculum_modules.module_id` -> `modules.id`

### 13. `curriculum_units` (Các môn học trong chương trình đào tạo)

- **Lý do**: Gắn các môn học (`units`) vào một phiên bản chương trình đào tạo (`curriculum_versions`), xác định môn nào là bắt buộc, môn nào là tự chọn.
- **Bảng liên quan**: `curriculum_units`
- **Model**: `App\Models\CurriculumUnit`
- **Phụ thuộc**: `curriculum_units.curriculum_version_id` -> `curriculum_versions.id`; `curriculum_units.unit_id` -> `units.id`; `curriculum_units.curriculum_module_id` -> `curriculum_modules.id` (tùy chọn)

### 14. `graduation_requirements` (Yêu cầu tốt nghiệp)

- **Lý do**: Quy định số tín chỉ cần tích lũy để tốt nghiệp cho một phiên bản chương trình đào tạo hoặc chuyên ngành.
- **Bảng liên quan**: `graduation_requirements`
- **Model**: `App\Models\GraduationRequirement`
- **Phụ thuộc**: `graduation_requirements.curriculum_version_id` -> `curriculum_versions.id`

## Giai đoạn 3: Lên kế hoạch Học kỳ và Mở lớp (Semester & Course Offering)

Giai đoạn này tập trung vào việc chuẩn bị cho một học kỳ cụ thể.

### 15. `semesters` (Học kỳ)

- **Lý do**: Định nghĩa các kỳ học trong năm (VD: Mùa Thu 2025, Mùa Xuân 2026) cho từng cơ sở.
- **Bảng liên quan**: `semesters`
- **Model**: `App\Models\Semester`
- **Phụ thuộc**: `semesters.campus_id` -> `campuses.id`

### 16. `lectures` (Giảng viên)

- **Lý do**: Tạo hồ sơ giảng viên, liên kết với một tài khoản người dùng (`users`) đã có.
- **Bảng liên quan**: `lectures`
- **Model**: `App\Models\Lecture`
- **Phụ thuộc**: `lectures.user_id` -> `users.id`; `lectures.campus_id` -> `campuses.id`

### 17. `syllabus_templates` (Template đề cương)

- **Lý do**: Định nghĩa template đề cương cho các môn học, bao gồm learning outcomes, grading criteria, và các thành phần đánh giá.
- **Bảng liên quan**: `syllabus_templates`
- **Model**: `App\Models\SyllabusTemplate`
- **Phụ thuộc**: `syllabus_templates.unit_id` -> `units.id`; `syllabus_templates.applicable_program_id` -> `programs.id` (tùy chọn); `syllabus_templates.applicable_campus_id` -> `campuses.id` (tùy chọn)

### 18. `assessment_components` & `assessment_component_details` (Thành phần đánh giá)

- **Lý do**: Định nghĩa các thành phần đánh giá (Assignment, Midterm, Final) cho một syllabus template, và chi tiết của từng thành phần.
- **Bảng liên quan**: `assessment_components`, `assessment_component_details`
- **Model**: `App\Models\AssessmentComponent`, `App\Models\AssessmentComponentDetail`
- **Phụ thuộc**: `assessment_components.syllabus_template_id` -> `syllabus_templates.id`; `assessment_component_details.assessment_component_id` -> `assessment_components.id`

### 19. `course_offerings` (Lớp học được mở)

- **Lý do**: Đây là bảng trung tâm, biểu diễn một môn học (`units`) cụ thể được mở trong một học kỳ (`semesters`) và do một giảng viên (`lectures`) phụ trách. Sinh viên sẽ đăng ký vào đây.
- **Bảng liên quan**: `course_offerings`
- **Model**: `App\Models\CourseOffering`
- **Phụ thuộc**: `course_offerings.unit_id` -> `units.id`; `course_offerings.semester_id` -> `semesters.id`; `course_offerings.lecture_id` -> `lectures.id`; `course_offerings.campus_id` -> `campuses.id`; `course_offerings.syllabus_template_id` -> `syllabus_templates.id` (tùy chọn)

### 20. `canvas_integrations` & `canvas_course_mappings` (Tích hợp Canvas LMS)

- **Lý do**: Lưu thông tin kết nối với Canvas LMS và mapping giữa course offerings trong SwinX với courses trong Canvas.
- **Bảng liên quan**: `canvas_integrations`, `canvas_course_mappings`
- **Model**: `App\Models\CanvasIntegration`, `App\Models\CanvasCourseMapping`
- **Phụ thuộc**: `canvas_integrations.campus_id` -> `campuses.id`

### 21. `room_bookings` (Đặt phòng học)

- **Lý do**: Sắp xếp lịch và phòng học (`rooms`) cho các lớp học (`course_offerings`).
- **Bảng liên quan**: `room_bookings`, `room_booking_actions`
- **Model**: `App\Models\RoomBooking`, `App\Models\RoomBookingAction`
- **Phụ thuộc**: `room_bookings.room_id` -> `rooms.id`; `room_bookings.course_offering_id` -> `course_offerings.id`

## Giai đoạn 4: Vòng đời Sinh viên (Student Lifecycle)

Giai đoạn này mô tả các hoạt động của sinh viên từ lúc nhập học đến khi có kết quả học tập.

### 22. `student_applications` (Đơn đăng ký nhập học)

- **Lý do**: Lưu trữ thông tin đơn đăng ký của ứng viên trước khi trở thành sinh viên chính thức.
- **Bảng liên quan**: `student_applications`
- **Model**: `App\Models\StudentApplication`

### 23. `students` (Sinh viên)

- **Lý do**: Tạo hồ sơ sinh viên, liên kết với một tài khoản người dùng (`users`) và chương trình đào tạo (`programs`) họ theo học.
- **Bảng liên quan**: `students`
- **Model**: `App\Models\Student`
- **Phụ thuộc**: `students.user_id` -> `users.id`; `students.program_id` -> `programs.id`; `students.campus_id` -> `campuses.id`; `students.specialization_id` -> `specializations.id` (tùy chọn); `students.curriculum_version_id` -> `curriculum_versions.id` (tùy chọn); `students.intake_semester_id` -> `semesters.id` (tùy chọn)

### 24. `parents` & `parent_student` (Phụ huynh)

- **Lý do**: Lưu thông tin phụ huynh và mối quan hệ với sinh viên để hỗ trợ liên lạc và quản lý.
- **Bảng liên quan**: `parents`, `parent_student`
- **Model**: `App\Models\ParentProfile`
- **Phụ thuộc**: `parents.user_id` -> `users.id`; `parent_student.parent_id` -> `parents.id`; `parent_student.student_id` -> `students.id`

### 25. `student_settings` (Cài đặt sinh viên)

- **Lý do**: Lưu các cài đặt và preferences cá nhân của từng sinh viên.
- **Bảng liên quan**: `student_settings`
- **Model**: `App\Models\StudentSetting`
- **Phụ thuộc**: `student_settings.student_id` -> `students.id`

### 26. `enrollments` (Nhập học)

- **Lý do**: Ghi danh chính thức cho sinh viên vào một chương trình học, chuyên ngành tại một học kỳ bắt đầu.
- **Bảng liên quan**: `enrollments`
- **Model**: `App\Models\Enrollment`
- **Phụ thuộc**: `enrollments.student_id` -> `students.id`; `enrollments.program_id` -> `programs.id`; `enrollments.semester_id` -> `semesters.id`

### 27. `course_registrations` (Đăng ký môn học)

- **Lý do**: Ghi nhận việc sinh viên (`students`) đăng ký vào một lớp học cụ thể (`course_offerings`).
- **Bảng liên quan**: `course_registrations`
- **Model**: `App\Models\CourseRegistration`
- **Phụ thuộc**: `course_registrations.student_id` -> `students.id`; `course_registrations.course_offering_id` -> `course_offerings.id`; `course_registrations.semester_id` -> `semesters.id`

### 28. `class_sessions` & `attendances` (Buổi học & Điểm danh)

- **Lý do**: Tạo các buổi học chi tiết (lý thuyết, thực hành) cho một lớp và ghi nhận sự có mặt của sinh viên.
- **Bảng liên quan**: `class_sessions`, `attendances`
- **Model**: `App\Models\ClassSession`, `App\Models\Attendance`
- **Phụ thuộc**: `class_sessions.course_offering_id` -> `course_offerings.id`; `class_sessions.lecture_id` -> `lectures.id`; `attendances.student_id` -> `students.id`; `attendances.class_session_id` -> `class_sessions.id`

### 29. `academic_records` & `assessment_component_detail_scores` (Kết quả học tập)

- **Lý do**: Sau khi kết thúc học kỳ, bảng `academic_records` ghi nhận điểm cuối cùng của sinh viên cho môn học. Bảng `assessment_component_detail_scores` lưu điểm chi tiết của từng thành phần đánh giá.
- **Bảng liên quan**: `academic_records`, `assessment_component_detail_scores`
- **Model**: `App\Models\AcademicRecord`, `App\Models\AssessmentComponentDetailScore`
- **Phụ thuộc**: `academic_records.course_registration_id` -> `course_registrations.id`; `assessment_component_detail_scores.academic_record_id` -> `academic_records.id`

### 30. `gpa_calculations` (Tính GPA)

- **Lý do**: Tính toán và lưu trữ điểm GPA theo học kỳ và GPA tích lũy cho mỗi sinh viên.
- **Bảng liên quan**: `gpa_calculations`
- **Model**: `App\Models\GpaCalculation`
- **Phụ thuộc**: `gpa_calculations.student_id` -> `students.id`; `gpa_calculations.semester_id` -> `semesters.id`

### 31. `academic_holds` (Tạm ngưng học tập)

- **Lý do**: Ghi nhận các trường hợp sinh viên bị tạm ngưng (hold) do các lý do như nợ học phí, vi phạm quy định, v.v.
- **Bảng liên quan**: `academic_holds`
- **Model**: `App\Models\AcademicHold`
- **Phụ thuộc**: `academic_holds.student_id` -> `students.id`

### 32. `academic_standings` (Xếp loại học tập)

- **Lý do**: Ghi nhận xếp loại học tập của sinh viên theo từng kỳ (Good Standing, Probation, Dismissed, v.v.).
- **Bảng liên quan**: `academic_standings`
- **Model**: `App\Models\AcademicStanding`
- **Phụ thuộc**: `academic_standings.student_id` -> `students.id`; `academic_standings.semester_id` -> `semesters.id`

### 33. `program_change_requests` (Yêu cầu đổi chương trình)

- **Lý do**: Ghi nhận các yêu cầu chuyển ngành/chương trình của sinh viên.
- **Bảng liên quan**: `program_change_requests`
- **Model**: `App\Models\ProgramChangeRequest`
- **Phụ thuộc**: `program_change_requests.student_id` -> `students.id`

### 34. `graduation_applications` (Đơn xin tốt nghiệp)

- **Lý do**: Quản lý quy trình đăng ký và xét duyệt tốt nghiệp cho sinh viên.
- **Bảng liên quan**: `graduation_applications`
- **Model**: `App\Models\GraduationApplication`
- **Phụ thuộc**: `graduation_applications.student_id` -> `students.id`

### 35. `student_changes` (Lịch sử thay đổi sinh viên)

- **Lý do**: Ghi lại lịch sử các thay đổi quan trọng của hồ sơ sinh viên để audit.
- **Bảng liên quan**: `student_changes`
- **Model**: `App\Models\StudentChange`
- **Phụ thuộc**: `student_changes.student_id` -> `students.id`
- **Current note**: bảng này là generic field-level audit. Không còn là nguồn sự thật cho lịch sử thay đổi level/stage học tập của student.

### 36. `ielts_certificates` (Chứng chỉ IELTS)

- **Lý do**: Lưu trữ thông tin chứng chỉ tiếng Anh (IELTS) của sinh viên, bao gồm điểm số và file scan, phục vụ việc xếp lớp và xét điều kiện chuyển giai đoạn học tập.
- **Bảng liên quan**: `ielts_certificates`
- **Model**: `App\Models\IeltsCertificate`
- **Phụ thuộc**: `ielts_certificates.student_id` -> `students.id`; `ielts_certificates.upload_record_id` -> `upload_records.id`

### 37. `academic_progression_events` (Tiến trình học tập)

- **Lý do**: Ghi nhận các sự kiện quan trọng trong lộ trình học tập của sinh viên như xếp lớp ban đầu (`placement`), thay đổi trình độ tiếng Anh (`english_level`), chuyển giai đoạn (`course_stage`).
- **Bảng liên quan**: `academic_progression_events`
- **Model**: `App\Models\AcademicProgressionEvent`
- **Phụ thuộc**: `academic_progression_events.student_id` -> `students.id`; `academic_progression_events.semester_id` -> `semesters.id`; `academic_progression_events.ielts_certificate_id` -> `ielts_certificates.id`
- **Current note**: đây là bảng canonical cho log nghiệp vụ học tập:
  - `ENGLISH_LEVEL_CHANGED` cho thay đổi level EGC, gồm cả manual và auto progression
  - `COURSE_STAGE_CHANGED` cho chuyển stage học tập như `intake_pre_uni_gc -> intake_course`

### 38. `student_actions` & `student_action_attachments` (Hành động hành chính)

- **Lý do**: Ghi nhận và audit các quyết định hành chính quan trọng liên quan đến trạng thái sinh viên như bảo lưu (`defer`), bỏ học (`dropout`), chuyển cơ sở (`transfer`) hoặc hoãn nhập học. Kèm theo đó là các tài liệu minh chứng.
- **Bảng liên quan**: `student_actions`, `student_action_attachments`
- **Model**: `App\Models\StudentAction`, `App\Models\StudentActionAttachment`
- **Phụ thuộc**: `student_actions.student_id` -> `students.id`; `student_actions.changed_by_user_id` -> `users.id`

---

## Giai đoạn 5: Tài chính (Finance)

Quản lý học phí, thanh toán, và hỗ trợ tài chính cho sinh viên.

### 39. `scholarship_definitions` & `student_scholarship_awards` (Học bổng)

- **Lý do**: Định nghĩa các loại học bổng và gán học bổng cho sinh viên.
- **Bảng liên quan**: `scholarship_definitions`, `student_scholarship_awards`
- **Model**: `App\Models\ScholarshipDefinition`, `App\Models\StudentScholarshipAward`
- **Phụ thuộc**: `student_scholarship_awards.student_id` -> `students.id`; `student_scholarship_awards.scholarship_definition_id` -> `scholarship_definitions.id`

### 40. `student_wallets` & `gold_transactions` (Ví điểm Gold)

- **Lý do**: Quản lý ví điểm thưởng (Gold) và lịch sử giao dịch điểm của sinh viên.
- **Bảng liên quan**: `student_wallets`, `gold_transactions`
- **Model**: `App\Models\StudentWallet`, `App\Models\GoldTransaction`
- **Phụ thuộc**: `student_wallets.student_id` -> `students.id`; `gold_transactions.wallet_id` -> `student_wallets.id`

### 41. `voucher_definitions` & `voucher_applications` (Voucher/Mã giảm giá) ⭐ SIMPLIFIED

- **Lý do**: Định nghĩa các loại voucher. Admin có thể tạo, chỉnh sửa, xem chi tiết và áp dụng voucher cho sinh viên. Không còn hỗ trợ import/delete flows.
- **Bảng liên quan**: `voucher_definitions`, `voucher_applications`
- **Model**: `App\Models\VoucherDefinition`, `App\Models\VoucherApplication`
- **Fields `voucher_applications`** (replaces legacy `voucher_redemptions`): `voucher_definition_id`, `student_id`, `applied_at`, `applied_by_user_id`
- **Phụ thuộc**: `voucher_applications.voucher_definition_id` -> `voucher_definitions.id`; `voucher_applications.student_id` -> `students.id`
- **Admin flows**: Create → Edit → Show (with inline apply card) → Apply. Import/delete workflows removed (2026-03-20).
- **Finance integration**: Voucher applications now flow through `invoice_discounts` + `discount_allocations` in settlement v2.

### 42. `tuition_plans` & `tuition_plan_terms` (Kế hoạch học phí)

- **Lý do**: Định nghĩa các kế hoạch học phí và các kỳ thanh toán.
- **Bảng liên quan**: `tuition_plans`, `tuition_plan_terms`
- **Model**: `App\Models\TuitionPlan`, `App\Models\TuitionPlanTerm`
- **Phụ thuộc**: `tuition_plan_terms.tuition_plan_id` -> `tuition_plans.id`

### 43. `billing_cycles` (Chu kỳ thanh toán)

- **Lý do**: Định nghĩa các chu kỳ thanh toán cho việc quản lý hóa đơn.
- **Bảng liên quan**: `billing_cycles`
- **Model**: `App\Models\BillingCycle`

### 45. `student_invoices` & `invoice_discounts` (Hóa đơn)

- **Lý do**: Tạo và quản lý hóa đơn học phí cho sinh viên, cùng header-level discount metadata. Chi tiết dòng hóa đơn đã chuyển sang `invoice_lines`.
- **Bảng liên quan**: `student_invoices`, `invoice_discounts`
- **Model**: `App\Models\StudentInvoice`, `App\Models\InvoiceDiscount`
- **Phụ thuộc**: `student_invoices.student_id` -> `students.id`; `student_invoices.semester_id` -> `semesters.id`; `invoice_discounts.invoice_id` -> `student_invoices.id`

### 46. `finance_charges` (Sổ cái phí - Finance Ledger) ⭐ NEW

- **Lý do**: Sổ cái trung tâm ghi nhận mọi khoản thu/phí (dương) và khoản giảm/bảo lưu (âm) của sinh viên. Mỗi record là một sự kiện tài chính, có thể truy vết nguồn phát sinh (polymorphic).
- **Bảng liên quan**: `finance_charges`
- **Model**: `App\Models\FinanceCharge`
- **Fields chính**:
    - `student_id`, `semester_id`, `billing_cycle_id` (nullable)
    - `charge_type`: enum (tuition_term, egc_level_fee, retake_fee, course_fee, manual_fee, defer_credit, egc_exempt_credit, scholarship_credit, voucher_credit, adjustment)
    - `amount`: decimal (dương = thu, âm = credit/giảm)
    - `effective_at`, `status` (active/void)
    - `source_type`, `source_id`: polymorphic để truy vết nguồn (CourseRegistration, DeferCase, manual...)
    - `created_by_user_id`, `voided_at`, `voided_by_user_id`, `void_reason`
- **Phụ thuộc**: `finance_charges.student_id` -> `students.id`; `finance_charges.semester_id` -> `semesters.id`

### 47. `payments` & `payment_applications` (Thanh toán & Phân bổ) ⭐ SETTLEMENT V2

- **Lý do**: Ghi nhận tất cả các lần thanh toán của sinh viên (`payments`) và gắn chúng vào từng dòng hóa đơn cụ thể (`payment_applications`). Nguồn sự thật cho tiền mặt (cash application) đã chuyển từ `payment_allocations` sang `payment_applications`.
- **Bảng liên quan**: `payments`, `payment_applications`
- **Model**: `App\Models\Payment`, `App\Models\PaymentApplication`
- **Fields `payments`**: `student_id`, `amount`, `method` (cash/bank_transfer/gateway/wallet/import), `source`, `external_ref`, `paid_at`, `status`, `received_by_user_id`, `raw_payload`
- **Fields `payment_applications`** (replacing legacy `payment_allocations`): `payment_id`, `invoice_line_id`, `allocated_amount`, `allocated_at`, `allocated_by_user_id`
- **Phụ thuộc**: `payments.student_id` -> `students.id`; `payment_applications.payment_id` -> `payments.id`; `payment_applications.invoice_line_id` -> `invoice_lines.id`
- **Định nghĩa**: "Unapplied credit" = `payment.amount - sum(payment_applications.allocated_amount)`, dùng để cấn trừ kỳ sau
- **Legacy note**: `payment_allocations` has been removed from the active schema; all settlement operations read from `payment_applications`.

### 48. `invoice_lines` (Dòng hóa đơn từ Charge) ⭐ SETTLEMENT V2

- **Lý do**: Thay thế hoàn toàn cách tiếp cận polymorphic cũ của `invoice_items`. Mỗi dòng hóa đơn gắn trực tiếp với một `finance_charges`, tạo snapshot tại thời điểm xuất hóa đơn. Dòng hóa đơn là điểm neo (anchor point) cho settlement v2.
- **Bảng liên quan**: `invoice_lines`
- **Model**: `App\Models\InvoiceLine`
- **Fields chính**: `invoice_id`, `charge_id`, `amount_snapshot`, `description_snapshot`, `status` (active|void), `voided_at`, `void_reason`
- **Phụ thuộc**: `invoice_lines.invoice_id` -> `student_invoices.id`; `invoice_lines.charge_id` -> `finance_charges.id`
- **Lifecycle**: Khi charge bị void, `invoice_lines` status thay đổi từ `active` -> `void` và `voided_at`, `void_reason` được ghi. Cách tính net/outstanding bao gồm kiểm tra `status` để loại trừ các dòng void.

### 48b. `discount_allocations` (Phân bổ giảm giá dòng hóa đơn) ⭐ SETTLEMENT V2

- **Lý do**: Lưu trữ sự thật về giảm giá ở mức dòng hóa đơn. Thay thế việc chỉ lưu giảm giá ở header level trong `invoice_discounts`. Mỗi dòng hóa đơn có thể có giảm giá được áp dụng riêng.
- **Bảng liên quan**: `discount_allocations` (cùng với `invoice_discounts` ở mức header)
- **Model**: `App\Models\DiscountAllocation`
- **Fields chính**: `invoice_line_id`, `discount_id` (FK to `invoice_discounts` nếu có), `discount_amount`, `reason`, `created_by_user_id`
- **Phụ thuộc**: `discount_allocations.invoice_line_id` -> `invoice_lines.id`; `discount_allocations.discount_id` -> `invoice_discounts.id` (optional)
- **Workflow**: Nguồn sự thật để tính "net due" là tất cả `discount_allocations` cho tất cả dòng hóa đơn, không chỉ tổng `invoice_discounts`.

- **Lý do**: Lưu phạm vi và chính sách phí khi sinh viên bảo lưu. Gắn 1-1 với `student_action_logs` (action_type = defer). Tách biệt logic tài chính khỏi audit học vụ.
- **Bảng liên quan**: `defer_cases`, `defer_case_items`
- **Model**: `App\Models\DeferCase`, `App\Models\DeferCaseItem`
- **Fields `defer_cases`**:
    - `student_action_log_id` (unique, 1-1)
    - `student_id`, `semester_id`
    - `scope_type`: enum (FULL = toàn kỳ, COURSES = theo môn)
    - `fee_policy`: enum (PRESERVE = giữ nguyên, FORFEIT = mất, PARTIAL = một phần)
    - `preserve_amount`, `effective_at`, `signed_at`, `upload_record_id`, `changed_by_user_id`
- **Fields `defer_case_items`**: `defer_case_id`, `course_registration_id`, `fee_policy` (override), `preserve_amount`
- **Phụ thuộc**: `defer_cases.student_action_log_id` -> `student_action_logs.id`; `defer_case_items.course_registration_id` -> `course_registrations.id`
- **Cơ chế**: Khi `fee_policy` = PRESERVE/PARTIAL, hệ thống tự động tạo `finance_charges` âm với `charge_type = defer_credit`; các charges này sau đó tạo `invoice_lines` và có thể được phân bổ trong settlement workflow.

---

## Giai đoạn 6: Forms, Surveys & Query Tickets

Hệ thống quản lý biểu mẫu, khảo sát và hỗ trợ sinh viên.

### 46. `forms` & `form_versions` (Biểu mẫu)

- **Lý do**: Định nghĩa các biểu mẫu động có thể tái sử dụng và các phiên bản của chúng.
- **Bảng liên quan**: `forms`, `form_versions`, `form_sections`, `questions`, `options`
- **Model**: `App\Models\Form`, `App\Models\FormVersion`, `App\Models\FormSection`, `App\Models\Question`, `App\Models\Option`
- **Phụ thuộc**: `form_versions.form_id` -> `forms.id`; `form_sections.form_id` -> `forms.id`; `questions.form_section_id` -> `form_sections.id`

### 47. `form_targets` & `form_result_visibility` (Đối tượng và quyền xem kết quả)

- **Lý do**: Xác định ai cần hoàn thành biểu mẫu và ai có quyền xem kết quả.
- **Bảng liên quan**: `form_targets`, `form_visibility_roles`, `form_result_visibility`
- **Model**: `App\Models\FormTarget`, `App\Models\FormResultVisibility`
- **Phụ thuộc**: `form_targets.form_id` -> `forms.id`

### 48. `responses`, `answers`, `answer_options` (Phản hồi biểu mẫu)

- **Lý do**: Lưu trữ các phản hồi của người dùng cho các biểu mẫu.
- **Bảng liên quan**: `responses`, `answers`, `answer_options`
- **Model**: `App\Models\FormResponse`, `App\Models\Answer`, `App\Models\AnswerOption`
- **Phụ thuộc**: `responses.form_id` -> `forms.id`; `responses.form_version_id` -> `form_versions.id`; `answers.response_id` -> `responses.id`

### 49. `student_form_assignments` (Phân công biểu mẫu cho sinh viên)

- **Lý do**: Ghi nhận các biểu mẫu được phân công cho sinh viên cụ thể.
- **Bảng liên quan**: `student_form_assignments`
- **Model**: `App\Models\StudentFormAssignment`
- **Phụ thuộc**: `student_form_assignments.student_id` -> `students.id`; `student_form_assignments.form_target_id` -> `form_targets.id`

### 50. `form_surveys` & `student_form_surveys` (Khảo sát đánh giá giảng viên)

- **Lý do**: Quản lý các đợt khảo sát đánh giá môn học/giảng viên và theo dõi việc hoàn thành của sinh viên.
- **Bảng liên quan**: `form_surveys`, `student_form_surveys`
- **Model**: `App\Models\FormSurvey`, `App\Models\StudentFormSurvey`
- **Phụ thuộc**: `form_surveys.form_id` -> `forms.id`; `student_form_surveys.form_survey_id` -> `form_surveys.id`; `student_form_surveys.student_id` -> `students.id`

### 51. `query_topics`, `queries_tickets`, `query_replies`, `query_assignments` (Hỗ trợ sinh viên)

- **Lý do**: Hệ thống ticket hỗ trợ sinh viên gửi câu hỏi/yêu cầu và nhận phản hồi từ nhân viên.
- **Bảng liên quan**: `query_topics`, `queries_tickets`, `query_replies`, `query_assignments`
- **Model**: `App\Models\QueryTopic`, `App\Models\QueryTicket`, `App\Models\QueryReply`, `App\Models\QueryAssignment`
- **Phụ thuộc**: `queries_tickets.student_id` -> `students.id`; `queries_tickets.query_topic_id` -> `query_topics.id`; `query_replies.query_id` -> `queries_tickets.id`; `query_assignments.query_id` -> `queries_tickets.id`

---

## Giai đoạn 7: Sự kiện & Hoạt động ngoại khóa (Events & Clubs)

Quản lý sự kiện và câu lạc bộ sinh viên.

### 52. `clubs` & `club_members` (Câu lạc bộ)

- **Lý do**: Quản lý các câu lạc bộ sinh viên và thành viên của chúng.
- **Bảng liên quan**: `clubs`, `club_members`, `club_member_roles_history`
- **Model**: `App\Models\Club`, `App\Models\ClubMember`, `App\Models\ClubMemberRoleHistory`
- **Phụ thuộc**: `clubs.campus_id` -> `campuses.id`; `club_members.club_id` -> `clubs.id`; `club_members.student_id` -> `students.id`

### 53. `events` & `event_participants` (Sự kiện)

- **Lý do**: Quản lý các sự kiện và danh sách người tham gia.
- **Bảng liên quan**: `events`, `event_participants`
- **Model**: `App\Models\Event`, `App\Models\EventParticipant`
- **Phụ thuộc**: `event_participants.event_id` -> `events.id`; `event_participants.student_id` -> `students.id` (tùy chọn)

---

## Giai đoạn 8: Thông báo & Email

Hệ thống gửi thông báo và email.

### 54. `notifications` (Thông báo)

- **Lý do**: Lưu trữ các thông báo gửi đến người dùng.
- **Bảng liên quan**: `notifications`
- **Current note**: bảng legacy vẫn tồn tại trong schema nhưng flow academic student-facing mới phải đi qua Notification V2 (`notification_event_outbox`, `notification_messages`, `notification_deliveries`).
- **Model**: `App\Models\Notification`

### 55. `email_configurations`, `email_templates`, `email_logs` (Cấu hình Email)

- **Lý do**: Quản lý cấu hình gửi email, template email và lịch sử gửi email.
- **Bảng liên quan**: `email_configurations`, `email_templates`, `email_logs`
- **Model**: `App\Models\EmailConfiguration`, `App\Models\EmailTemplate`, `App\Models\EmailLog`

### 56. `user_email_preferences` (Tùy chọn email người dùng)

- **Lý do**: Lưu trữ tùy chọn nhận email của từng người dùng.
- **Bảng liên quan**: `user_email_preferences`
- **Model**: `App\Models\UserEmailPreference`
- **Phụ thuộc**: `user_email_preferences.user_id` -> `users.id`

---

## Giai đoạn 9: Upload & Audit

Hệ thống quản lý file upload và audit log.

### 57. `upload_records` (Bản ghi upload)

- **Lý do**: Lưu trữ thông tin về các file được upload lên hệ thống.
- **Bảng liên quan**: `upload_records`
- **Model**: `App\Models\UploadRecord`
- **Phụ thuộc**: `upload_records.user_id` -> `users.id` (tùy chọn); `upload_records.student_id` -> `students.id` (tùy chọn)

### 58. `activity_log` (Nhật ký hoạt động)

- **Lý do**: Ghi lại tất cả các hoạt động quan trọng trong hệ thống để audit (sử dụng package spatie/laravel-activitylog).
- **Bảng liên quan**: `activity_log`
- **Phụ thuộc**: N/A (Package-managed)

---

## Sơ đồ phụ thuộc tổng quan

```
campuses
├── buildings -> rooms
├── semesters
├── canvas_integrations
├── clubs
└── campus_user_roles

users
├── campus_user_roles
├── department_memberships
├── lectures -> course_offerings -> course_registrations
├── students
│   ├── enrollments
│   ├── course_registrations -> academic_records -> gpa_calculations
│   ├── ielts_certificates -> academic_progression_events
│   ├── student_actions
│   ├── attendances
│   ├── student_wallets
│   ├── student_invoices
│   ├── club_members
│   ├── event_participants
│   ├── queries_tickets
│   └── form_responses
└── parents -> parent_student

programs
├── specializations
├── curriculum_versions
│   ├── curriculum_modules
│   └── curriculum_units
└── graduation_requirements

units
├── syllabus_templates -> assessment_components -> assessment_component_details
├── curriculum_units
└── course_offerings

forms
├── form_versions
├── form_sections -> questions -> options
├── form_targets
└── responses -> answers
```
