# 📄 **YÊU CẦU TÍNH NĂNG: TỰ ĐỘNG GẮN SURVEY CHO MÔN KHI KẾT THÚC**

# 📌 1. MỤC TIÊU

Triển khai hệ thống đánh giá môn học tự động. Khi một môn (**course_offering**) kết thúc, hệ thống phải:

1. Tự động gắn **form survey mặc định** vào môn học đó.
2. Tự động tạo nhiệm vụ survey cho **tất cả sinh viên** đã đăng ký môn đó.
3. Trên Student Portal (FE riêng), hiển thị **popup bắt buộc** yêu cầu sinh viên trả lời survey.
4. Chỉ khi sinh viên hoàn thành **tất cả survey pending**, popup mới biến mất.
5. Sinh viên có thể xem lại lịch sử các survey đã hoàn thành.

# 📌 2. PHẠM VI ÁP DỤNG

Áp dụng cho tất cả:****

- Course offerings (mọi ngành, mọi chương trình, mọi campus, loại trừ unit có type là egc)
- Mọi student đã đăng ký môn (course_registrations hoặc academic_records/enrollments)
- Một form survey mặc định áp dụng chung cho tất cả môn

Không áp dụng cho:
- Survey tùy môn, tùy lecturer
- Survey tự chọn hoặc survey không liên quan đến môn học

# 📌 3. KHÁI NIỆM & ĐỊNH NGHĨA

- **Form Survey mặc định**: mẫu đánh giá môn học được admin cấu hình một lần trong phần Settings.
- **Form Survey Mapping**: bản ghi gắn form ↔ course_offering.
- **Student Survey Task**: nhiệm vụ survey của từng sinh viên trong môn.
- **Pending Survey**: survey sinh viên bắt buộc phải hoàn thành.

# 📌 4. NGHIỆP VỤ CHÍNH

## 4.1. Khi môn học kết thúc

Khi `course_offering.status = 'completed'`, hệ thống phải tự động:

1. Kiểm tra xem tính năng survey có đang bật không (`survey_enabled`).
2. Lấy form mặc định (`default_course_survey` – form_id).
3. Gắn form đó vào course_offering → sinh bản ghi **form_surveys**.
4. Lấy danh sách toàn bộ student đã đăng ký trong môn.
5. Tạo **student_form_surveys** cho từng student:
    - status = `not_started`
    - survey chưa hoàn thành

Quy tắc:

- Không tạo lại nếu đã có survey trước đó (idempotent).
- Nếu sinh viên có nhiều môn completed → có nhiều survey pending.

## 4.2. Khi student đăng nhập Student Portal

Frontend phải:

1. Gọi API: `GET /student/surveys/pending`
2. Nếu kết quả > 0 → hiện **popup bắt buộc**
3. Popup phải:
    - Không được đóng
    - Không có nút cancel
    - Không ẩn được
    - Luôn xuất hiện lại nếu student chưa làm survey

Student chỉ có thể tắt popup bằng cách **hoàn thành tất cả survey pending**.

## 4.3. Popup bắt buộc (Forced Modal)

Popup hiển thị:

- Danh sách survey pending
- Tên môn
- Tên form
- Nút “Start Survey”

Nếu sinh viên có 3 survey pending → popup hiển thị cả 3.

## 4.4. Hoàn thành survey

Khi student submit:

1. Backend lưu responses vào bảng responses/answers
2. Update student_form_surveys.status = `completed`
3. FE gọi lại API pending:
    - Nếu còn survey → popup tiếp tục hiển thị
    - Nếu hết → popup đóng vĩnh viễn

---

## 4.5. Trang lịch sử survey

Student portal có 1 trang:

URL: `/student/surveys/history`

Nội dung:

- Danh sách survey đã hoàn thành
- Tên môn, ngày hoàn thành
- “View responses” để xem lại câu trả lời của chính mình

# 📌 5. YÊU CẦU CHỨC NĂNG

## 5.1. ADMIN SETTINGS (Chỉ 1 trang)

- Bật/tắt hệ thống survey tự động
    
    → `survey_enabled`
    
- Chọn form survey mặc định
    
    → `default_course_survey` (form_id)
    
- Nút preview form survey

## 5.2. BACKEND API

Backend cung cấp tối thiểu:

### 1. Danh sách survey chưa làm

`GET /api/student/surveys/pending`

### 2. Nội dung survey

`GET /api/student/surveys/{survey_id}`

### 3. Submit survey

`POST /api/student/surveys/{survey_id}/submit`

### 4. Lịch sử survey đã làm

`GET /api/student/surveys/completed`

### 5. Xem lại câu trả lời

`GET /api/student/surveys/{survey_id}/responses`

---

# 📌 6. YÊU CẦU PHI CHỨC NĂNG

### 6.1. Hiệu năng

- Gắn survey cho lớp 200 sinh viên phải < 2 giây.
- API pending phải trả về kết quả trong < 300ms.

### 6.2. Bảo mật

- Student chỉ xem được survey của chính mình.
- Student không được sửa câu trả lời sau khi đã completed.
- Admin không được xem câu trả lời cá nhân (optional rule tùy trường).

### 6.3. Tính ổn định

- Không được tạo duplicate survey.
- Không crash dù course được cập nhật trạng thái nhiều lần.

# 📌 7. RÀNG BUỘC NGHIỆP VỤ

1. **Survey chỉ gắn khi course hoàn thành**
2. Student phải hoàn thành **tất cả survey pending** → mới tắt popup
3. Hệ thống chỉ có **một form mặc định** cho tất cả môn
4. Không hỗ trợ nhiều form theo từng môn
5. Nếu survey bị tắt → không tạo nhiệm vụ survey nữa
6. Nếu student mới đăng ký môn đã completed trước đó
    
    → cần quy định rõ:
    
    - (A) Gắn survey cho student mới → YES (khuyên dùng)
    - (B) Không gắn (nếu đã quá hạn)
        
        *(Bạn chọn A hay B thì nói tôi để ghi vào tài liệu)*
        

# 📌 8. CÁC TRƯỜNG HỢP NGOẠI LỆ

- Form mặc định bị xóa → phải hiển thị cảnh báo trong settings.
- Course bị completed → reverted về in_progress rồi completed lại
    
    → không được tạo survey trùng.
    
- Student bị chuyển môn / đổi lớp
    
    → xử lý theo rule “có gắn survey bổ sung hay không” (chờ bạn chọn).
    

# 📌 9. HOÀN THÀNH TÍNH NĂNG KHI

1. Môn completed → tự tạo survey
2. FE hiển thị popup bắt buộc
3. Student làm survey → cập nhật completed
4. Popup biến mất sau khi hoàn thành tất cả survey pending
5. Student xem được lịch sử survey
6. Admin xem được cấu hình + thao tác set form mặc định