# Kế hoạch Chức năng và Nghiệp vụ cho Portal Quản trị viên

Tài liệu này mô tả các yêu cầu về chức năng và luồng nghiệp vụ cho Portal Quản trị viên của hệ thống SwinX. Mục tiêu là định nghĩa **"cái gì"** hệ thống cần làm, thay vì **"làm như thế nào"** về mặt kỹ thuật, để làm cơ sở cho việc phát triển và kiểm thử.

---

## Nguyên tắc Thiết kế Nghiệp vụ

1.  **Toàn vẹn Dữ liệu là Ưu tiên:** Mọi chức năng đều phải tuân thủ luồng dữ liệu logic đã định nghĩa (ví dụ: không thể tạo phòng học nếu chưa có tòa nhà). Hệ thống phải đảm bảo dữ liệu luôn nhất quán và chính xác.
2.  **Quản lý Tập trung:** Admin Portal là trung tâm điều hành duy nhất, cho phép quản trị viên truy cập và cấu hình mọi khía cạnh của hệ thống từ một nơi.
3.  **Phân quyền Chi tiết:** Hệ thống phải hỗ trợ phân quyền linh hoạt, cho phép giới hạn quyền truy cập và hành động của người dùng quản trị dựa trên vai trò của họ (ví dụ: nhân viên giáo vụ chỉ có thể quản lý sinh viên, không thể thay đổi cấu trúc chương trình học).
4.  **Luồng nghiệp vụ có Hướng dẫn:** Các giao diện phức tạp như tạo chương trình học hay mở lớp phải được thiết kế theo từng bước, dẫn dắt người dùng qua quy trình để tránh sai sót.

---

## Module 1: Quản lý Nền tảng & Hệ thống

Đây là nhóm chức năng lõi, thiết lập các tham số cơ bản và nền tảng cho toàn bộ hoạt động của nhà trường.

### **Chức năng 1.1: Quản lý Cơ sở & Cơ sở vật chất**

*   **Mục đích:** Quản lý và số hóa toàn bộ cơ sở vật chất của các cơ sở đào tạo.
*   **Nghiệp vụ:**
    *   **Quản lý Cơ sở (Campus):** Cho phép tạo mới, xem, và cập nhật thông tin của các cơ sở đào tạo (ví dụ: Swinburne Hà Nội, Swinburne TP.HCM). Mỗi cơ sở là một đơn vị hoạt động độc lập về mặt địa lý.
    *   **Quản lý Tòa nhà (Building):** Trong mỗi cơ sở, cho phép quản lý danh sách các tòa nhà.
    *   **Quản lý Phòng học (Room):** Trong mỗi tòa nhà, cho phép quản lý danh sách các phòng học. Mỗi phòng phải được định nghĩa rõ về loại phòng (phòng lab, phòng học lý thuyết, hội trường) và sức chứa.
*   **Liên kết & Luồng nghiệp vụ:**
    *   Từ trang chi tiết của một Cơ sở, quản trị viên phải có thể xem và điều hướng đến danh sách các Tòa nhà thuộc cơ sở đó.
    *   Tương tự, từ trang chi tiết của một Tòa nhà, phải có thể xem và quản lý danh sách các Phòng học.

### **Chức năng 1.2: Quản lý Người dùng & Phân quyền Truy cập**

*   **Mục đích:** Kiểm soát ai có thể truy cập hệ thống và họ được phép làm gì.
*   **Nghiệp vụ:**
    *   **Quản lý Vai trò (Role):** Cho phép tạo ra các vai trò nghiệp vụ trong hệ thống (Super Admin, Admin, Staff, Lecturer, Student).
    *   **Quản lý Quyền hạn (Permission):** Hệ thống phải định nghĩa sẵn một danh sách đầy đủ các quyền hạn chi tiết (ví dụ: `Xem danh sách sinh viên`, `Tạo học kỳ mới`, `Nhập điểm cho lớp học`).
    *   **Phân quyền cho Vai trò:** Giao diện cho phép gán các quyền hạn chi tiết vào một vai trò. Các quyền nên được nhóm lại theo module (ví dụ: nhóm quyền "Quản lý Sinh viên", nhóm quyền "Quản lý Học thuật") để dễ dàng quản lý.
    *   **Quản lý Người dùng:** Cung cấp một danh sách tập trung tất cả người dùng. Cho phép thực hiện các thao tác: tạo mới người dùng, cập nhật thông tin, kích hoạt/vô hiệu hóa tài khoản.
    *   **Phân quyền cho Người dùng:** Đây là nghiệp vụ cốt lõi. Từ trang thông tin của một người dùng, quản trị viên phải có thể **gán cho họ một vai trò tại một cơ sở cụ thể**. Ví dụ, một người có thể là `Giảng viên` ở `Cơ sở Hà Nội` và là `Nhân viên giáo vụ` ở `Cơ sở TP.HCM`.

### **Chức năng 1.3: Quản lý Năm học & Học kỳ**

*   **Mục đích:** Định nghĩa các khung thời gian cho hoạt động giảng dạy và học tập.
*   **Nghiệp vụ:**
    *   Cho phép tạo và quản lý các học kỳ (Semester) cho từng cơ sở.
    *   Mỗi học kỳ phải có thông tin rõ ràng: tên gọi (FALL2024), ngày bắt đầu, ngày kết thúc, và trạng thái (Sắp diễn ra, Đang diễn ra, Đã kết thúc).
    *   Hệ thống phải có cơ chế tự động hoặc thủ công để chuyển đổi trạng thái của học kỳ.

---

## Module 2: Quản lý Chương trình & Cấu trúc Học thuật

Nhóm chức năng này định nghĩa "danh mục sản phẩm" của nhà trường: các ngành học và môn học.

### **Chức năng 2.1: Quản lý Ngành học & Chuyên ngành**

*   **Mục đích:** Xây dựng danh mục các ngành và chuyên ngành đào tạo.
*   **Nghiệp vụ:**
    *   Quản lý danh sách các Ngành học (Program) lớn (ví dụ: Cử nhân Công nghệ Thông tin).
    *   Với mỗi ngành học, cho phép định nghĩa các Chuyên ngành (Specialization) hẹp hơn (ví dụ: Phát triển Phần mềm, An ninh mạng).

### **Chức năng 2.2: Quản lý Danh mục Môn học (Unit)**

*   **Mục đích:** Tạo ra một thư viện trung tâm chứa tất cả các môn học mà nhà trường có thể giảng dạy.
*   **Nghiệp vụ:**
    *   Quản lý thông tin chi tiết của từng môn học: mã môn, tên môn, mô tả, và số tín chỉ mặc định.
    *   **Thiết lập ràng buộc tiên quyết:** Một nghiệp vụ quan trọng. Với một môn học, quản trị viên phải có khả năng thiết lập các điều kiện để sinh viên được phép đăng ký, bao gồm:
        *   Yêu cầu đã qua một hoặc nhiều môn học khác.
        *   Yêu cầu đã tích lũy đủ một số lượng tín chỉ tối thiểu.
        *   Hỗ trợ các logic phức tạp (ví dụ: phải qua (Môn A VÀ Môn B) HOẶC Môn C).
    *   **Thiết lập môn học tương đương:** Cho phép định nghĩa hai hay nhiều môn học có thể thay thế cho nhau trong chương trình đào tạo.

### **Chức năng 2.3: Quản lý Khung Chương trình Đào tạo**

*   **Mục đích:** Liên kết các môn học lại với nhau để tạo thành một khung chương trình đào tạo hoàn chỉnh cho một ngành học.
*   **Nghiệp vụ:**
    *   Cho phép tạo ra các "phiên bản" khác nhau của một chương trình đào tạo (thường theo năm tuyển sinh).
    *   **Cấu trúc chương trình:** Trong mỗi phiên bản, quản trị viên phải có thể thêm các môn học từ danh mục chung vào và phân loại chúng thành:
        *   **Bắt buộc (Core/Compulsory):** Các môn sinh viên phải học.
        *   **Tự chọn (Elective):** Các môn sinh viên có thể chọn từ một danh sách cho trước.
    *   **Định nghĩa yêu cầu tốt nghiệp:** Thiết lập các quy tắc để tốt nghiệp cho phiên bản này, ví dụ: tổng số tín chỉ cần hoàn thành, tổng số tín chỉ bắt buộc.
*   **Liên kết & Luồng nghiệp vụ:** Chức năng này kết nối Ngành học và Môn học. Từ trang quản lý khung chương trình, người dùng có thể thấy danh sách môn học và ngược lại, từ một môn học, có thể xem nó đang được sử dụng trong những khung chương trình nào.

---

## Module 3: Quản lý Vận hành Học vụ

Nhóm chức năng phục vụ các hoạt động hàng ngày của phòng giáo vụ và các bộ phận liên quan trong một học kỳ cụ thể.

### **Chức năng 3.1: Quản lý Mở lớp & Phân công Giảng dạy**

*   **Mục đích:** Tạo ra các lớp học thực tế cho một học kỳ và gán giảng viên phụ trách.
*   **Nghiệp vụ:**
    *   Trong một học kỳ đã chọn, cho phép tạo các lớp học (Course Offering) bằng cách chọn một môn học từ danh mục.
    *   Phân công một giảng viên từ danh sách giảng viên để phụ trách lớp học.
    *   Thiết lập các thuộc tính của lớp học như sĩ số tối đa, lịch học dự kiến.
*   **Liên kết & Luồng nghiệp vụ:** Trang quản lý mở lớp phải có bộ lọc mạnh mẽ cho phép xem các lớp theo học kỳ, theo giảng viên, hoặc theo môn học.

### **Chuc năng 3.2: Quản lý Đề cương và Trọng số điểm**

*   **Mục đích:** Định nghĩa cách một sinh viên sẽ được đánh giá trong một lớp học cụ thể.
*   **Nghiệp vụ:**
    *   Sau khi một lớp học được tạo, quản trị viên phải có khả năng xây dựng đề cương (Syllabus) cho lớp đó.
    *   Việc này bao gồm việc thêm các "thành phần điểm" (Assessment Component) như Bài tập lớn, Thi giữa kỳ, Thi cuối kỳ, và gán trọng số (%) cho mỗi thành phần.

### **Chức năng 3.3: Quản lý Hồ sơ Sinh viên & Giảng viên**

*   **Mục đích:** Cung cấp một cái nhìn 360 độ về toàn bộ quá trình học tập và giảng dạy của cá nhân.
*   **Nghiệp vụ - Hồ sơ Sinh viên:**
    *   Tra cứu và xem thông tin chi tiết của một sinh viên.
    *   Xem lịch sử học tập: bảng điểm đầy đủ qua các kỳ, bao gồm điểm số, trạng thái (Qua/Trượt).
    *   Xem lịch sử ghi danh và chương trình đang theo học.
    *   Quản lý các "ghi chú chặn" (Holds) - ví dụ: chặn đăng ký môn học do vấn đề tài chính.
*   **Nghiệp vụ - Hồ sơ Giảng viên:**
    *   Tra cứu và xem thông tin giảng viên.
    *   Xem lịch sử phân công giảng dạy: danh sách tất cả các lớp học đã và đang phụ trách.

### **Chức năng 3.4: Quản lý Đăng ký môn học**

*   **Mục đích:** Quản lý việc ghi danh của sinh viên vào các lớp học.
*   **Nghiệp vụ:**
    *   Xem danh sách sinh viên đã đăng ký vào một lớp học cụ thể.
    *   **Đăng ký thủ công:** Cung cấp chức năng cho phép nhân viên giáo vụ ghi danh thủ công một sinh viên vào một lớp học. Đây là một nghiệp vụ quan trọng để xử lý các trường hợp ngoại lệ hoặc yêu cầu đặc biệt.
*   **Liên kết & Luồng nghiệp vụ:** Chức năng này có thể được truy cập từ trang quản lý lớp học (xem danh sách lớp rồi thêm sinh viên) hoặc từ trang hồ sơ sinh viên (chọn đăng ký môn cho sinh viên này).

---

## Module 4: Báo cáo & Phân tích

Nhóm chức năng trích xuất, tổng hợp và trực quan hóa dữ liệu để hỗ trợ việc ra quyết định.

### **Chức năng 4.1: Báo cáo Thống kê & Nhân khẩu học**

*   **Mục đích:** Cung cấp cái nhìn tổng quan về quần thể sinh viên và giảng viên.
*   **Nghiệp vụ:**
    *   **Thống kê sinh viên:** Lập báo cáo về số lượng sinh viên theo nhiều chiều: theo ngành, theo chuyên ngành, theo khóa tuyển sinh, theo trạng thái (đang học, đã tốt nghiệp, tạm dừng...).
    *   **Thống kê giảng viên:** Báo cáo về số lượng giảng viên, chuyên môn, và tải lượng giảng dạy.

### **Chức năng 4.2: Báo cáo Kết quả Học tập & Chất lượng Đào tạo**

*   **Mục đích:** Phân tích hiệu suất học tập và chất lượng giảng dạy.
*   **Nghiệp vụ:**
    *   **Phân tích tỷ lệ Qua/Trượt:** Thống kê tỷ lệ sinh viên qua/trượt theo từng môn học, từng lớp, hoặc từng giảng viên.
    *   **Phân tích phổ điểm:** Vẽ biểu đồ phân bổ điểm GPA của sinh viên theo từng kỳ hoặc theo ngành để đánh giá chất lượng đầu ra.

### **Chức năng 4.3: Báo cáo Vận hành**

*   **Mục đích:** Theo dõi các chỉ số hoạt động hàng ngày.
*   **Nghiệp vụ:**
    *   **Báo cáo chuyên cần:** Tổng hợp tỷ lệ tham dự lớp học của sinh viên, có thể xem chi tiết theo lớp hoặc theo cá nhân sinh viên.
    *   **Báo cáo tình hình đăng ký môn học:** Thống kê số lượng sinh viên đăng ký vào các lớp học mỗi kỳ, giúp xác định các lớp có nhu cầu cao.

*   **Yêu cầu chung cho Báo cáo:** Tất cả các báo cáo phải có bộ lọc linh hoạt (theo thời gian, theo cơ sở, theo ngành...) và phải có chức năng xuất dữ liệu ra các định dạng phổ biến như CSV hoặc Excel.
