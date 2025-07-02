# Kế hoạch phát triển Giao diện Người dùng (UI/UX) cho SwinX

Tài liệu này mô tả chi tiết kế hoạch phát triển giao diện và trải nghiệm người dùng cho các vai trò cốt lõi trong hệ thống SwinX: **Sinh viên**, **Giảng viên**, và **Phụ huynh**. Kế hoạch này được xây dựng dựa trên cấu trúc cơ sở dữ liệu và luồng dữ liệu mẫu từ Seeder, đảm bảo các tính năng được thiết kế phù hợp với nghiệp vụ thực tế.

---

## I. Nguyên tắc Thiết kế Chung

1.  **Xác thực Phân quyền:** Mỗi vai trò sẽ có một cổng thông tin (portal) riêng sau khi đăng nhập. Hệ thống sẽ sử dụng middleware để đảm bảo người dùng chỉ truy cập được vào các tài nguyên được phép.
2.  **Liên kết Phụ huynh - Sinh viên:** Tài khoản Phụ huynh sẽ được tạo bởi Admin và liên kết an toàn với một mã số sinh viên duy nhất. Phụ huynh chỉ có thể xem thông tin của sinh viên đã được liên kết.
3.  **Thiết kế Nhất quán:** Sử dụng một hệ thống thiết kế (design system) chung với các thành phần (components) tái sử dụng được (nút bấm, bảng biểu, thẻ...) để đảm bảo trải nghiệm đồng bộ, nhưng bố cục và tính năng sẽ được tùy biến cho từng vai trò.
4.  **Hướng dữ liệu (Data-Driven):** Mọi thông tin hiển thị đều được lấy từ cơ sở dữ liệu đã được định nghĩa và điền dữ liệu bởi seeder, đảm bảo tính thực tế của giao diện.

---

## II. Cổng thông tin Sinh viên (Student Portal)

Đây là trung tâm tương tác chính của sinh viên với các hoạt động học tập.

### 1. Menu Điều hướng

*   **Dashboard (Bảng điều khiển)**
*   **My Academics (Kết quả học tập)**
*   **Course Registration (Đăng ký môn học)**
*   **My Schedule (Thời khóa biểu)**
*   **My Curriculum (Chương trình học)**
*   **Attendance (Điểm danh)**
*   **Profile (Hồ sơ cá nhân)**

### 2. Chi tiết các Màn hình

#### **a. Dashboard (Bảng điều khiển)**
*   **Mục đích:** Cung cấp cái nhìn tổng quan nhanh về các thông tin quan trọng nhất trong ngày/tuần.
*   **Các thành phần:**
    *   **Thời khóa biểu hôm nay:** Hiển thị các lớp học trong ngày, bao gồm thời gian, phòng học, và tên môn. _(Dữ liệu từ `class_sessions`, `room_bookings`)_
    *   **Hạn nộp bài sắp tới:** Danh sách các bài tập, kiểm tra sắp đến hạn. _(Dữ liệu từ `assessment_components`)_
    *   **Điểm số vừa cập nhật:** Hiển thị thông báo khi có điểm mới từ kỳ học trước. _(Dữ liệu từ `academic_records`)_
    *   **Tóm tắt GPA:** Hiển thị GPA của học kỳ gần nhất và GPA tích lũy. _(Dữ liệu từ `gpa_calculations`)_
    *   **Thông báo chung:** Các thông báo từ phòng đào tạo hoặc ban quản trị campus.

#### **b. My Academics (Kết quả học tập)**
*   **Mục đích:** Cung cấp chi tiết lịch sử và kết quả học tập của sinh viên.
*   **Các thành phần:**
    *   **Bảng điểm toàn khóa:** Danh sách tất cả các môn đã học, được nhóm theo từng học kỳ.
        *   Các cột: Mã môn, Tên môn, Số tín chỉ, Điểm cuối cùng, Trạng thái (Qua/Trượt).
    *   **Xem chi tiết điểm:** Khi bấm vào một môn, sẽ hiển thị điểm của từng thành phần (Assignment 1: 8/10, Mid-term: 7.5/10...). _(Dữ liệu từ `assessment_component_detail_scores`)_
    *   **Biểu đồ tiến độ:** Biểu đồ tròn hoặc thanh hiển thị số tín chỉ đã tích lũy so với tổng số tín chỉ yêu cầu của chương trình học. _(Dữ liệu từ `graduation_requirements`)_

#### **c. Course Registration (Đăng ký môn học)**
*   **Mục đích:** Cho phép sinh viên đăng ký môn học cho học kỳ tiếp theo một cách trực quan và được kiểm tra điều kiện ràng buộc.
*   **Các thành phần:**
    *   **Thông báo thời gian đăng ký:** Hiển thị rõ thời gian bắt đầu và kết thúc của đợt đăng ký. _(Dữ liệu từ `semesters`)_
    *   **Danh sách lớp học được mở:** Bảng danh sách các lớp học (`course_offerings`) cho kỳ tới.
        *   Thông tin: Tên môn, giảng viên, lịch học dự kiến, số lượng chỗ còn lại.
        *   **Bộ lọc:** Lọc theo môn bắt buộc, môn tự chọn, hoặc tìm kiếm theo tên/mã môn.
    *   **Kiểm tra điều kiện:**
        *   Bên cạnh mỗi lớp học, hiển thị trạng thái hợp lệ:
            *   ✅ **Đủ điều kiện:** Sinh viên đã qua môn tiên quyết.
            *   ❌ **Không đủ điều kiện:** Kèm lý do rõ ràng như "Yêu cầu qua môn PROG101", "Yêu cầu tích lũy 100 tín chỉ". _(Logic dựa trên `unit_prerequisites`, `academic_records` và `gpa_calculations`)_
    *   **Giỏ đăng ký (Registration Cart):** Cho phép sinh viên chọn các lớp học mong muốn, hệ thống sẽ tự động kiểm tra xung đột lịch học trước khi đăng ký chính thức.

#### **d. My Schedule (Thời khóa biểu)**
*   **Mục đích:** Hiển thị thời khóa biểu dưới dạng lịch.
*   **Các thành phần:**
    *   **Lịch theo Tuần/Tháng:** Giao diện lịch trực quan.
    *   **Chi tiết buổi học:** Khi bấm vào một sự kiện, hiển thị đầy đủ: Tên môn, Giảng viên, Phòng, Tòa nhà, Campus.
    *   **Tùy chọn xuất:** Nút để xuất thời khóa biểu ra file iCal hoặc thêm vào Google Calendar.

---

## III. Cổng thông tin Giảng viên (Lecturer Portal)

Giao diện tập trung vào việc quản lý lớp học và tương tác với sinh viên.

### 1. Menu Điều hướng

*   **Dashboard (Bảng điều khiển)**
*   **My Classes (Lớp học của tôi)**
*   **My Schedule (Lịch giảng dạy)**
*   **Profile (Hồ sơ)**

### 2. Chi tiết các Màn hình

#### **a. My Classes (Lớp học của tôi)**
*   **Mục đích:** Là trung tâm để giảng viên quản lý mọi hoạt động liên quan đến các lớp mình phụ trách.
*   **Các thành phần:**
    *   **Danh sách lớp học:** Hiển thị các thẻ (cards) cho mỗi lớp học (`course_offering`) trong kỳ hiện tại.
    *   Khi chọn một lớp, sẽ có các tab chức năng:
        *   **Class List (Danh sách sinh viên):** Hiển thị danh sách sinh viên đã đăng ký. Có thể xuất danh sách ra file Excel/CSV.
        *   **Attendance (Điểm danh):**
            *   Giao diện điểm danh cho từng buổi học (`class_session`).
            *   Hiển thị danh sách sinh viên, cho phép tick chọn "Có mặt", "Vắng", "Đi trễ".
            *   Thống kê tỷ lệ chuyên cần của từng sinh viên trong lớp.
        *   **Grading (Nhập điểm):**
            *   Giao diện dạng bảng (giống Excel) để nhập điểm cho từng `assessment_component`.
            *   Hệ thống tự động tính tổng điểm và đề xuất điểm cuối cùng (PASS/FAIL) dựa trên trọng số đã định nghĩa trong syllabus.
        *   **Syllabus:** Xem lại đề cương và các thành phần điểm của lớp học.

---

## IV. Cổng thông tin Phụ huynh (Parent Portal)

Giao diện được thiết kế tối giản, tập trung vào việc theo dõi và hỗ trợ quá trình học tập của sinh viên. **Tất cả các tính năng đều ở chế độ chỉ xem (read-only).**

### 1. Menu Điều hướng

*   **Overview (Tổng quan)**
*   **Academic Results (Kết quả học tập)**
*   **Attendance (Chuyên cần)**
*   **Schedule (Thời khóa biểu)**

### 2. Chi tiết các Màn hình

#### **a. Overview (Tổng quan)**
*   **Mục đích:** Cung cấp cho phụ huynh một cái nhìn tổng thể và nhanh chóng về tình hình học tập của con em mình.
*   **Các thành phần:**
    *   **Thẻ thông tin sinh viên:** Hiển thị tên, ngành học, và GPA tích lũy.
    *   **Các môn đang học:** Danh sách các môn sinh viên đăng ký trong kỳ hiện tại.
    *   **Tóm tắt chuyên cần:** Một biểu đồ đơn giản hiển thị tỷ lệ đi học chung của kỳ này (ví dụ: 95%).
    *   **Kết quả gần nhất:** Hiển thị điểm tổng kết của các môn từ kỳ học vừa kết thúc.

#### **b. Academic Results (Kết quả học tập)**
*   **Mục đích:** Cho phép phụ huynh xem lại lịch sử học tập.
*   **Các thành phần:**
    *   Giao diện giống với của sinh viên nhưng được đơn giản hóa: chỉ hiển thị bảng điểm tổng kết theo từng kỳ với các cột: Tên môn, Tín chỉ, Điểm cuối cùng.
    *   **Không hiển thị điểm thành phần chi tiết** để đảm bảo sự riêng tư ở mức độ nhất định cho sinh viên.

#### **c. Attendance (Chuyên cần)**
*   **Mục đích:** Giúp phụ huynh nắm được tình hình chuyên cần.
*   **Các thành phần:**
    *   Hiển thị bảng thống kê số buổi vắng cho từng môn học trong kỳ hiện tại.
    *   Cảnh báo trực quan (ví dụ: tô màu đỏ) nếu môn nào có tỷ lệ vắng vượt ngưỡng cho phép.

#### **d. Schedule (Thời khóa biểu)**
*   **Mục đích:** Giúp gia đình biết lịch học của sinh viên.
*   **Các thành phần:**
    *   Một lịch xem theo tuần, hiển thị thời gian và tên môn học.
    *   Không cần hiển thị chi tiết về phòng học hay giảng viên để giữ giao diện gọn gàng.

---

## V. Cổng thông tin Quản trị viên (Admin Portal)

Đây là giao diện toàn diện nhất, cho phép quản trị viên (Admin/Staff) cấu hình và quản lý mọi khía cạnh của hệ thống. Giao diện sẽ tận dụng tối đa các thành phần UI có sẵn như bảng dữ liệu (data tables) với chức năng tìm kiếm, phân trang, bộ lọc và các hành động (thêm, sửa, xóa).

### 1. System Management (Quản lý Hệ thống)

*   **Mục đích:** Cấu hình các yếu tố nền tảng của hệ thống.
*   **Chi tiết các màn hình:**
    *   **Users:**
        *   **Giao diện:** Bảng danh sách người dùng với các cột: Tên, Email, Vai trò chính, Trạng thái (Hoạt động/Vô hiệu hóa).
        *   **Hành động:** Thêm người dùng mới, chỉnh sửa thông tin người dùng, gán vai trò (`campus_user_roles`), và reset mật khẩu.
    *   **Roles & Permissions:**
        *   **Giao diện:** Danh sách các vai trò (Super Admin, Admin, Staff...). Khi chọn một vai trò, hiển thị một danh sách các quyền (`permissions`) được nhóm theo chức năng (VD: Quản lý sinh viên, Quản lý học thuật).
        *   **Hành động:** Tạo vai trò mới, chỉnh sửa tên vai trò, và gán/thu hồi quyền cho từng vai trò bằng các hộp kiểm (checkboxes).
    *   **Academic Terms (Học kỳ):**
        *   **Giao diện:** Bảng danh sách các học kỳ (`semesters`) được nhóm theo campus, với các cột: Tên học kỳ, Mã, Ngày bắt đầu, Ngày kết thúc, Trạng thái (Sắp tới/Đang diễn ra/Kết thúc).
        *   **Hành động:** Tạo học kỳ mới, chỉnh sửa thông tin, và kích hoạt/vô hiệu hóa học kỳ.
    *   **Campuses & Departments (Cơ sở & Tòa nhà):**
        *   **Giao diện:** Danh sách các cơ sở (`campuses`). Khi chọn một campus, hiển thị danh sách các tòa nhà (`buildings`) và phòng học (`rooms`) thuộc campus đó.
        *   **Hành động:** Thêm/sửa/xóa campus, building, và room.

### 2. Student Management (Quản lý Sinh viên)

*   **Mục đích:** Quản lý toàn bộ vòng đời và hồ sơ học tập của sinh viên.
*   **Chi tiết các màn hình:**
    *   **Student List:** Bảng danh sách sinh viên (`students`) với bộ lọc theo chương trình học, chuyên ngành, trạng thái.
        *   Khi click vào một sinh viên, điều hướng đến trang chi tiết hồ sơ của sinh viên đó, bao gồm thông tin cá nhân, lịch sử học tập, các ghi chú...
    *   **Academic Records:** Giao diện tra cứu và quản lý bảng điểm chi tiết (`academic_records`) của sinh viên.
    *   **Enrollments & Holds:** Quản lý việc nhập học (`enrollments`) và các ghi chú chặn (holds) học tập hoặc tài chính của sinh viên.

### 3. Lecturer Management (Quản lý Giảng viên)

*   **Mục đích:** Quản lý thông tin và phân công giảng dạy cho giảng viên.
*   **Chi tiết các màn hình:**
    *   **Lecturer List:** Bảng danh sách giảng viên (`lecturers`) với thông tin liên lạc và chuyên môn.
    *   **Teaching Assignments:** Giao diện cho phép gán giảng viên vào các lớp học được mở (`course_offerings`) cho mỗi học kỳ.

### 4. Curriculum & Courses (Chương trình học & Môn học)

*   **Mục đích:** Xây dựng và quản lý cấu trúc chương trình đào tạo.
*   **Chi tiết các màn hình:**
    *   **Programs & Specializations:** Giao diện cây hoặc danh sách lồng nhau để tạo và quản lý các ngành học và chuyên ngành.
    *   **Units (Courses):** Bảng quản lý danh sách tất cả các môn học. Tại đây có thể cấu hình các mối quan hệ như môn tiên quyết, môn tương đương, và các yêu cầu đặc biệt (tín chỉ tích lũy tối thiểu).
    *   **Curriculum Versions:** Cho phép tạo các phiên bản chương trình đào tạo, gắn các môn học vào phiên bản đó và định rõ môn bắt buộc/tự chọn.

### 5. Course Offerings & Registration (Mở lớp & Đăng ký môn)

*   **Mục đích:** Tổ chức các lớp học cho mỗi học kỳ và quản lý việc đăng ký của sinh viên.
*   **Chi tiết các màn hình:**
    *   **Course Offering List:** Giao diện chính để tạo các lớp học (`course_offerings`) cho một học kỳ, chọn môn học, và gán giảng viên.
    *   **Course Registration:** Giao diện cho phép nhân viên giáo vụ xem danh sách sinh viên đăng ký vào từng lớp, hoặc thực hiện đăng ký thủ công cho sinh viên nếu cần.

### 6. Reports & Analytics (Báo cáo & Phân tích)

*   **Mục đích:** Cung cấp các báo cáo thống kê để hỗ trợ việc ra quyết định.
*   **Chi tiết các màn hình:**
    *   **Student Statistics:** Báo cáo về số lượng sinh viên theo ngành, theo khóa, theo trạng thái.
    *   **Academic Performance Summary:** Biểu đồ phân tích tỷ lệ qua/trượt, phân bổ điểm GPA.
    *   **Attendance Summary:** Báo cáo chuyên cần theo lớp, theo sinh viên.
