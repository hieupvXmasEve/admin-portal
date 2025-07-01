# Kế hoạch Chi tiết Triển khai Database Seeder

**Mục tiêu:** Tạo một bộ Seeder toàn diện để khởi tạo dữ liệu mẫu cho hệ thống SwinX, bám sát theo luồng logic đã định nghĩa trong `tasks/database.md`. Bộ seeder này sẽ bao phủ các trường hợp nghiệp vụ phức tạp liên quan đến sinh viên như qua môn, trượt môn (do điểm thấp, vắng), và kiểm tra điều kiện đăng ký môn học.

**Cấu trúc File Seeder:**
Các seeder sẽ được tạo trong thư mục `database/seeders/`. Chúng ta sẽ tạo các seeder riêng lẻ cho từng nhóm logic và gọi chúng theo thứ tự trong `DatabaseSeeder.php`.

---

### Kế hoạch Chi tiết

#### **Phase 1: Seeder cho Hệ thống Lõi và Cơ sở (Core & Campus)**

1.  **`CorePermissionSeeder.php`**
    *   **Mục đích:** Tạo các `Permissions` và `Roles` cơ bản.
    *   **Chi tiết:**
        *   Đọc danh sách quyền từ một file cấu hình hoặc một hằng số trong code để tạo `permissions`.
        *   Tạo các vai trò chính: `Super Admin`, `Admin`, `Staff`, `Lecturer`, `Student`.
        *   Gán toàn bộ quyền cho `Super Admin`.

2.  **`CampusSeeder.php`**
    *   **Mục đích:** Tạo dữ liệu cho `Campuses`, `Buildings`, và `Rooms`.
    *   **Chi tiết:**
        *   Tạo 2-3 cơ sở (VD: 'Swinburne Vietnam - Hanoi Campus', 'Swinburne Vietnam - HCMC Campus').
        *   Với mỗi cơ sở, tạo vài tòa nhà (Building A, B).
        *   Với mỗi tòa nhà, tạo nhiều phòng học với các loại khác nhau (Lecture Hall, Tutorial Room, Lab).

3.  **`UserSeeder.php`**
    *   **Mục đích:** Tạo các tài khoản người dùng (`users`) mẫu và gán vai trò.
    *   **Chi tiết:**
        *   Tạo 1 `Super Admin`.
        *   Tạo vài `Admin` cho mỗi campus.
        *   Tạo vài `Staff` cho mỗi campus.
        *   Tạo một lượng lớn `Lecturer` và `Student` (khoảng 20 giảng viên, 100 sinh viên) để có dữ liệu đa dạng.
        *   Sử dụng `CampusUserRoles` để gán vai trò tương ứng cho từng user tại campus của họ.

---

#### **Phase 2: Seeder cho Cấu trúc Học thuật (Academic & Curriculum)**

1.  **`CurriculumSeeder.php`**
    *   **Mục đích:** Tạo cấu trúc chương trình đào tạo hoàn chỉnh.
    *   **Chi tiết:**
        *   **Programs:** Tạo các ngành học (VD: Bachelor of IT, Bachelor of Business).
        *   **Specializations:** Tạo chuyên ngành cho IT (VD: Software Development, Cybersecurity).
        *   **Units:** Tạo danh sách khoảng 30-40 môn học.
            *   **Tất cả các môn học sẽ được gán cố định `credit_points` là 12.5.**
            *   Ít nhất 5-7 môn có quan hệ tiên quyết với nhau (VD: `PROG101` -> `PROG201` -> `PROG301`).
            *   Tạo 1-2 cặp môn học tương đương (`equivalent_units`).
            *   **Tạo một nhóm môn học đặc biệt yêu cầu tín chỉ tích lũy:**
                *   `CAPSTONE_PROJECT`: Yêu cầu tối thiểu 100 tín chỉ tích lũy.
                *   `INTERNSHIP`: Yêu cầu tối thiểu 87.5 tín chỉ tích lũy (tương đương 7 môn).
                *   `ADVANCED_RESEARCH`: Yêu cầu tối thiểu 125 tín chỉ tích lũy (tương đương 10 môn).
                *   Lưu điều kiện này vào bảng `unit_prerequisite_groups` với loại điều kiện là `credit_requirement`.
        *   **Curriculum & Graduation:**
            *   Tạo các phiên bản chương trình đào tạo (`curriculum_versions`).
            *   Sử dụng `curriculum_units` để thêm các môn học vào từng phiên bản, định rõ môn bắt buộc/tự chọn.
            *   Định nghĩa `graduation_requirements` (tổng số tín chỉ, tín chỉ bắt buộc...).

---

#### **Phase 3: Seeder cho Học kỳ và Lớp học (Semester & Offering)**

1.  **`AcademicOfferingSeeder.php`**
    *   **Mục đích:** Chuẩn bị dữ liệu cho các học kỳ và mở lớp.
    *   **Chi tiết:**
        *   **Semesters:** Tạo 3 học kỳ:
            *   Một học kỳ đã kết thúc (VD: `FALL2024`).
            *   Một học kỳ hiện tại (VD: `SPRING2025`).
            *   Một học kỳ tương lai (VD: `SUMMER2025`).
        *   **Lecturers:** Liên kết các user có vai trò `Lecturer` với bảng `lecturers`.
        *   **CourseOfferings:**
            *   Mở nhiều lớp học trong học kỳ đã kết thúc (`FALL2024`) và học kỳ hiện tại (`SPRING2025`).
            *   Mỗi lớp học được gán cho một giảng viên.
        *   **Syllabus & Assessments:**
            *   Với mỗi `course_offering`, tạo `syllabus` và các `assessment_components` (VD: Assignment 1 (20%), Mid-term (30%), Final Exam (50%)).

---

#### **Phase 4: Seeder cho Vòng đời Sinh viên và Các Kịch bản (Student Lifecycle & Scenarios)**

Đây là seeder phức tạp và quan trọng nhất, cần được thực thi cuối cùng.

1.  **`StudentLifecycleSeeder.php`**
    *   **Mục đích:** Tạo dữ liệu học tập chi tiết của sinh viên, bao gồm các kịch bản qua, trượt, và điều kiện đăng ký.
    *   **Chi tiết:**
        *   **Enrollment:** Ghi danh (`enrollments`) cho tất cả sinh viên đã tạo vào các chương trình học.
        *   Chia 100 sinh viên thành các nhóm để giả lập kịch bản:

        **A. Kịch bản trong Học kỳ đã kết thúc (`FALL2024`)**
        *   **Nhóm 1: Sinh viên xuất sắc (20 SV)**
            *   Đăng ký (`course_registrations`) 3-4 môn học, bao gồm cả môn sẽ là tiên quyết cho học kỳ sau.
            *   Tạo `attendances` đầy đủ.
            *   Tạo điểm `assessment_component_detail_scores` cao -> điểm `academic_records` tổng kết là `PASS`.
            *   Tính `gpa_calculations`.
        *   **Nhóm 2: Sinh viên trượt do điểm thấp (10 SV)**
            *   Đăng ký 3-4 môn.
            *   Tạo `attendances` đầy đủ.
            *   Tạo điểm `assessment_component_detail_scores` thấp -> điểm `academic_records` tổng kết là `FAIL`.
        *   **Nhóm 3: Sinh viên trượt do vắng (10 SV)**
            *   Đăng ký 2 môn.
            *   Tạo `attendances` dưới mức yêu cầu (VD: < 80%).
            *   Điểm `academic_records` tổng kết là `FAIL` (do vắng mặt).
        *   **Nhóm 4: Sinh viên bình thường (40 SV)**
            *   Đăng ký môn, có môn qua môn trượt ngẫu nhiên.

        **B. Kịch bản trong Học kỳ hiện tại (`SPRING2025`)**
        *   **Kiểm tra điều kiện đăng ký:**
            *   **Sinh viên đủ điều kiện:** Lấy sinh viên từ **Nhóm 1**, đăng ký cho họ một môn học nâng cao (`PROG201`) mà có môn tiên quyết (`PROG101`) họ đã `PASS` ở kỳ trước.
            *   **Sinh viên không đủ điều kiện (do trượt môn tiên quyết):** Lấy sinh viên từ **Nhóm 2** hoặc **Nhóm 3**, thiết lập dữ liệu để thể hiện họ đang cố gắng đăng ký môn `PROG201` nhưng đã `FAIL` môn `PROG101`. Seeder sẽ tạo ra trạng thái này để hệ thống có thể kiểm tra và chặn.
            *   **Sinh viên không đủ điều kiện (do chưa học môn tiên quyết):** Lấy một sinh viên mới, chưa học `PROG101`, và tạo dữ liệu để kiểm tra logic khi họ đăng ký `PROG201`.
            *   **Sinh viên không đủ điều kiện (do không đủ tín chỉ tích lũy):**
                *   Chọn một sinh viên từ **Nhóm 1** đã qua 4 môn ở kỳ trước (tổng cộng `4 * 12.5 = 50` tín chỉ).
                *   Tạo các kịch bản đăng ký cho các môn yêu cầu tín chỉ:
                    *   Sinh viên có 50 tín chỉ cố gắng đăng ký `CAPSTONE_PROJECT` (yêu cầu 100 tín chỉ) -> bị từ chối
                    *   Sinh viên có 87.5 tín chỉ (7 môn) cố gắng đăng ký `ADVANCED_RESEARCH` (yêu cầu 125 tín chỉ) -> bị từ chối
                    *   Sinh viên có 100 tín chỉ (8 môn) đăng ký `INTERNSHIP` (yêu cầu 87.5 tín chỉ) -> được chấp nhận
        *   **Nhóm 5: Sinh viên đang học (20 SV còn lại)**
            *   Đăng ký (`course_registrations`) vào các lớp trong học kỳ hiện tại.
            *   Tạo một vài bản ghi `attendances`.
            *   Chưa có `academic_records` và `gpa_calculations`.

---

#### **Cập nhật `DatabaseSeeder.php`**

File `DatabaseSeeder.php` sẽ được cập nhật để gọi các seeder theo đúng thứ tự logic:
```php
public function run(): void
{
    $this->call([
        // Phase 1
        CorePermissionSeeder::class,
        CampusSeeder::class,
        UserSeeder::class, // Phụ thuộc vào Roles và Campuses

        // Phase 2
        CurriculumSeeder::class,

        // Phase 3
        AcademicOfferingSeeder::class, // Phụ thuộc vào curriculum, users, semesters...

        // Phase 4
        StudentLifecycleSeeder::class, // Phụ thuộc vào tất cả các seeder trước đó
    ]);
}
``` 
