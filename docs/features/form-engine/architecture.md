# Form Engine Documentation

Tài liệu này giải thích cấu trúc cơ sở dữ liệu và luồng nghiệp vụ của hệ thống **Form Engine** trong Swinx.

## 1. Tổng quan kiến trúc (Architecture Overview)

Form Engine được thiết kế theo hướng **Template & Instance**.

- Bạn định nghĩa một khuôn mẫu (Form & Questions).
- Bạn triển khai khuôn mẫu đó vào thực tế (Form Target - Runs).
- Người dùng thực hiện gửi phản hồi (Form Response & Answers).

---

## 2. Các thành phần chính (Database Schema)

### Nhóm định nghĩa (Definition Group)

- **`forms`**: Chứa thông tin cơ bản của Form (Tên, mã, loại: `survey` hoặc `query`).
- **`form_versions`**: Quản lý phiên bản. Một Form có thể có nhiều phiên bản, nhưng tại một thời điểm chỉ có một phiên bản được `published`.
- **`form_sections`**: Nhóm các câu hỏi lại theo từng phần để hiển thị đẹp hơn.
- **`questions`**: Chi tiết câu hỏi (loại: text, single choice, v.v.), có các field quan trọng:
    - `validation_json`: Chứa các quy tắc validate (min, max, required).
    - `visibility_condition_json`: Logic ẩn/hiện câu hỏi dựa trên câu trả lời trước đó.
- **`options`**: Các lựa chọn cho câu hỏi trắc nghiệm (Single/Multi Choice).

### Nhóm triển khai (Deployment Group)

- **`form_targets` (Runs)**: Xác định "Đợt" mở form. Một form có thể được mở nhiều lần.
    - `scope_type`: Phạm vi (`global`, `campus`, `semester`, `course`, `department`).
    - `is_mandatory`: Cưỡng ép sinh viên phải làm (ví dụ: Survey khảo sát môn học).
    - `start_at` / `end_at`: Thời gian hiệu lực.

### Nhóm kết quả (Result Group)

- **`responses`**: Ghi nhận một lần nộp form.
    - `submitted_by_student_id`: Ai nộp.
    - `query_status`: Trạng thái xử lý (nếu là form loại `query`).
    - `assigned_to_user_id`: Nhân viên được giao xử lý.
- **`answers`**: Dữ liệu chi tiết của từng câu hỏi trong lần nộp đó.
- **`form_result_visibility`**: Kiểm soát ai (Role nào) được xem cái gì.
- **`upload_records`**: Lưu trữ các file đính kèm. Có 2 trường hợp:
    - Đi kèm với câu hỏi loại `file` trong lúc Student nộp form (liên kết qua `response_id`).
    - Đi kèm với lời nhắn trao đổi trong Ticket (liên kết qua `reply_id`).

### Nhóm xử lý Query (Query Handling Group)

- **`query_topics`**: Danh mục chủ đề (ví dụ: Học vụ, Tài chính).
- **`queries_tickets`**: Nếu form là loại `query`, hệ thống sẽ tự động tạo một Ticket tương ứng để theo dõi tiến độ trao đổi.
- **`queries_replies`**: Các tin nhắn trao đổi qua lại giữa Student và Staff trên Ticket. Các tin nhắn này có thể đính kèm file (UploadRecord).

---

## 3. Luồng nghiệp vụ (Workflow)

### Bước 1: Khởi tạo Form (Admin)

1. Tạo `Form` mới.
2. Tạo phiên bản `FormVersion`.
3. Soạn thảo bộ câu hỏi `Question` và `Option`.
4. Khi hoàn tất, Admin bấm **Publish**.

### Bước 2: Thiết lập Đợt chạy (Form Target / Runs)

1. Admin chọn một Form đã publish.
2. Thiết lập đối tượng (ví dụ: Chỉ dành cho Campus Hồ Chí Minh, Kỳ Spring 2024) và thời gian hiệu lực.

### Bước 3: Thu thập phản hồi (Student)

1. Student truy cập Student Portal. Hệ thống kiểm tra các `FormTarget` đang active và phù hợp với Student.
2. Student trả lời các câu hỏi.
3. **Đính kèm file:** Nếu form có câu hỏi yêu cầu upload file, dữ liệu file được lưu vào `upload_records` và link với `response_id`.
4. **Tạo Ticket:** Nếu là form Query, hệ thống tự động tạo `queries_tickets`.

### Bước 4: Xử lý và Phân quyền (Admin/Staff)

1. Admin/Staff vào Inbox để xem các Response.
2. **Assignment:** Giao cho nhân viên xử lý thông qua `assigned_to_user_id`.
3. **Trao đổi & Theo dõi (Query Ticketing):** Staff và Student có thể chat qua lại trong Ticket.
4. **Phản hồi kèm file:** Khi Staff trả lời (Reply), họ có thể đính kèm tài liệu giải thích. File này cũng được lưu vào `upload_records` (link qua `reply_id`).

---

## 4. Các mối quan hệ quan trọng (Relationships)

- `Form` -> (1:N) -> `FormVersion` -> (1:N) -> `Question`.
- `FormTarget` -> (N:1) -> `FormVersion`.
- `FormResponse` -> (N:1) -> `FormTarget`.
- `FormResponse` -> (1:1) -> `QueryTicket` -> (1:N) -> `QueryReply`.
- `Answer` -> (N:1) -> `FormResponse` & `Question`.
- `UploadRecord` -> (N:1) -> `FormResponse` **HOẶC** `QueryReply`.

---

## 5. Lưu ý cho Developer mới

1. **Quản lý File:** Hệ thống sử dụng `ImageUploadService` để xử lý upload. Tất cả file đính kèm (dù là ở form hay ở chat) đều tập trung về bảng `upload_records`.
2. **Config Upload:** Kiểm tra `config('uploads.contexts.form_attachment')` để biết giới hạn dung lượng và định dạng file cho phép trong Form Engine.
3. **Đừng bao giờ sửa Question trực tiếp khi đã có Response**: Hãy tạo một `FormVersion` mới để đảm bảo tính toàn vẹn dữ liệu cũ.
4. **Phân biệt `status` của response và `query_status`**:
    - `status`: Là trạng thái kỹ thuật (submitted, approved, rejected).
    - `query_status`: Là trạng thái nghiệp vụ (open, pending, answered, closed).
5. **Form Target (Runs)** là chìa khóa để điều khiển form xuất hiện ở đâu. Nếu sinh viên không thấy form, hãy kiểm tra bản ghi tương ứng trong bảng này đầu tiên.
