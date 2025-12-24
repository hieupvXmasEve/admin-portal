# SRS – Dynamic Form System (Survey & Query) + Portal Gate + Department Workflow

## 1. Mục tiêu

Xây dựng hệ thống form động dùng chung cho nhiều nghiệp vụ trong trường học, bao gồm:

- **Survey** (đánh giá môn học / đánh giá dịch vụ theo kỳ), có thể **bắt buộc** và **khóa portal** nếu chưa hoàn thành.
- **Query/Request** (thắc mắc/yêu cầu hỗ trợ) theo **phòng ban**, có workflow **assign/reassign** cho nhân viên xử lý.
- **Form là template dùng lại**; việc áp dụng cho từng kỳ/môn/phòng là theo từng “đợt sử dụng”.

## 2. Phạm vi

### In-scope

- Tạo **Form Template** và **Form Version** (cấu trúc câu hỏi).
- Tạo các **đợt sử dụng Form** theo ngữ cảnh (course offering / semester / department / global).
- Survey:
    - Course survey theo từng **course offering**
    - Service survey theo từng **semester**
    - Lặp lại ở các kỳ sau (mỗi kỳ là một đợt)
    - **Mandatory survey**: chưa làm → **bị khóa portal**

- Query:
    - Student gửi query theo **department**
    - Staff chỉ thấy query thuộc department của mình
    - Assign/reassign query cho staff

- Trường hợp kết hợp **Department + Semester** (áp dụng theo department nhưng chỉ trong một kỳ).

### Out-of-scope (chưa yêu cầu)

- 1 form query dùng cho **nhiều** department (UC-D2 = NO)
- SLA phức tạp, chatbot auto-reply, AI routing…
- Multi-tenant phức tạp ngoài campus/department hiện tại (nếu có sẽ tách phase)

## 3. Định nghĩa thuật ngữ

- **Form Template (Form):** Bộ câu hỏi chuẩn, có thể dùng lại nhiều lần.
- **Form Version:** Phiên bản của form (v1, v2…), phục vụ thay đổi câu hỏi mà không ảnh hưởng dữ liệu cũ.
- **Form Run / Campaign (Đợt dùng form):** Một lần “bật” form để áp dụng cho một ngữ cảnh cụ thể (môn/kỳ/phòng ban).
- **Response:** Dữ liệu trả lời form của student.
- **Survey Gate:** Cơ chế khóa portal khi còn survey bắt buộc chưa hoàn thành.
- **Query Ticket:** Một query do student tạo; có thể được assign cho staff.

> Lưu ý nghiệp vụ: Form template **không** “thuộc” course/semester/department; cái “thuộc” là **đợt dùng form**.

## 4. Actors & Roles

- **Student**: điền survey, tạo query, xem trạng thái query, nhận phản hồi.
- **Admin (System/Portal Admin)**: tạo form, tạo đợt survey/query, cấu hình mandatory, activate/close.
- **Department Head / Supervisor**: xem query của department, assign/reassign.
- **Department Staff**: xem query được assign, phản hồi, đóng query.
- **Super Admin** (nếu có): xem toàn bộ, override.

## 5. Use-cases đã xác nhận và mục tiêu nghiệp vụ

### Nhóm A – Form template

- **UC-A1**: Tạo form template độc lập (có thể chưa dùng ngay).
- **UC-A2**: 1 form template có thể dùng cho nhiều ngữ cảnh/đợt khác nhau.

### Nhóm B – Survey

- **UC-B1**: Course survey theo từng course offering.
- **UC-B2**: Survey bắt buộc → khóa portal nếu chưa hoàn thành.
- **UC-B3**: Service survey theo semester.
- **UC-B4**: Lặp lại survey cho các kỳ sau.

### Nhóm C – Query

- **UC-C1**: Student tạo query/request bằng form.
- **UC-C2**: Query thuộc department cụ thể.
- **UC-C3**: Staff chỉ thấy query của department mình.
- **UC-C4**: Workflow assign/reassign query cho staff.

### Nhóm D – Tổ hợp

- **UC-D1**: Department + Semester (VD: HQ survey / HQ query chỉ áp dụng trong một kỳ).
- **UC-D2**: NO – Không cần 1 form dùng cho nhiều department.

## 6. Business Rules (luật nghiệp vụ)

### 6.1 Form Template & Version

- BR-01: Form template có thể tồn tại mà chưa cần “bật” cho bất kỳ ngữ cảnh nào.
- BR-02: Khi thay đổi nội dung câu hỏi, hệ thống phải tạo **version mới**, không chỉnh sửa version đang dùng cho dữ liệu lịch sử.

### 6.2 Đợt sử dụng Form (Form Run/Campaign)

- BR-03: Một đợt sử dụng form xác định:
    - form + version áp dụng
    - ngữ cảnh áp dụng (course offering / semester / department / global)
    - thời gian hiệu lực (start/end)
    - trạng thái (draft/active/closed)
    - optional: mandatory (chỉ áp dụng cho survey)

- BR-04: Một course offering có thể có 0 hoặc 1 course survey run (theo policy của trường; nếu cần nhiều survey run thì phải định nghĩa rõ tên/loại).

### 6.3 Survey bắt buộc và khóa portal

- BR-05: Nếu tồn tại ít nhất 1 survey run **mandatory** mà student thuộc đối tượng áp dụng và **chưa submit**, student bị khóa portal.
- BR-06: Student chỉ được “mở khóa” khi hoàn thành toàn bộ survey mandatory đang hiệu lực cho mình.

### 6.4 Service survey theo semester (lặp theo kỳ)

- BR-07: Mỗi semester nếu có service survey → tạo một survey run riêng cho semester đó.
- BR-08: Lặp kỳ sau chỉ cần tạo survey run mới, vẫn dùng chung form template.

### 6.5 Query theo department + workflow

- BR-09: Mỗi query form run thuộc đúng **1 department** (vì UC-D2 = NO).
- BR-10: Staff chỉ xem được query thuộc department mình, trừ Admin/Super Admin.
- BR-11: Mỗi query ticket có thể:
    - chưa assign
    - assign cho 1 staff
    - reassign nhiều lần (có lịch sử).

- BR-12: Query ticket có trạng thái tối thiểu: new → assigned → in_progress → answered/closed (tùy định nghĩa).

### 6.6 Department + Semester

- BR-13: Một form run thuộc department có thể có giới hạn theo semester (chỉ áp dụng student của semester đó).
- BR-14: Semester limit là cấu hình của run (không hard-code), admin chọn khi tạo/activate run.

### 6.7 Settings

- BR-15: Staff mặc định chỉ xem được ticket được assign cho mình.
- BR-16: Department có thể cấu hình danh sách user được phép:
    - xem toàn bộ ticket trong department
    - assign/reassign ticket

## 7. Luồng nghiệp vụ chính

### 7.1 Admin tạo Course Survey (B1 + B2)

1. Admin chọn form template “Course Feedback” + version
2. Admin chọn ngữ cảnh course offering X
3. Admin bật mandatory (nếu cần khóa portal)
4. Activate survey run khi course hoàn tất hoặc activate thủ công
5. Hệ thống xác định danh sách student thuộc course offering X
6. Student đăng nhập portal:
    - nếu còn survey mandatory chưa submit → bị khóa và phải submit

**Kết thúc:** survey run được closed khi hết hạn hoặc admin đóng.

### 7.2 Admin tạo Service Survey theo Semester (B3 + B4)

1. Admin chọn form template “Service Survey” + version
2. Chọn semester Y
3. (tuỳ chọn) mandatory = true
4. Activate run
5. Hệ thống xác định student thuộc semester Y và áp dụng gate nếu mandatory.

### 7.3 Student tạo Query theo Department (C1 + C2)

1. Student chọn “HQ Query”
2. Điền form và submit
3. Hệ thống tạo query ticket (response) gắn với department HQ
4. Ticket hiển thị trong Query Management của HQ

### 7.4 Staff/Head assign & trả lời Query (C3 + C4)

1. Head/staff mở Query Management: chỉ thấy ticket của department mình
2. Head assign ticket cho staff A
3. Staff A trả lời (có thể nhiều lần)
4. Head có thể reassign nếu cần
5. Ticket được đóng khi xử lý xong.

### 7.5 Department + Semester (D1)

Áp dụng như 7.3/7.2 nhưng khi tạo run admin chọn thêm giới hạn semester:

- Ticket/survey chỉ cho student thuộc semester đã chọn (hoặc trong time window của semester) thấy và tạo.

## 8. Yêu cầu chức năng (Functional Requirements)

### 8.1 Admin Portal – Form

- FR-01: CRUD Form template (survey/query)
- FR-02: CRUD Form version
- FR-03: Xem lịch sử version và trạng thái publish (nội bộ admin)

### 8.2 Admin Portal – Form Run/Campaign

- FR-04: Tạo run cho course offering
- FR-05: Tạo run cho semester
- FR-06: Tạo run cho department (query)
- FR-07: Activate/Close run
- FR-08: Cấu hình mandatory (chỉ với survey)
- FR-09: Cấu hình hiệu lực (start/end) và giới hạn semester nếu cần

### 8.3 Student Portal

- FR-10: Khi login/boot app, hệ thống kiểm tra survey gate
- FR-11: Nếu bị gate, student phải submit survey bắt buộc trước khi dùng tính năng khác
- FR-12: Student có thể tạo query và theo dõi trạng thái

### 8.4 Staff Portal – Query Management

- FR-13: Staff xem danh sách ticket thuộc department mình
- FR-14: Assign/reassign ticket (theo quyền)
- FR-15: Trả lời ticket và đóng ticket
- FR-16: Audit lịch sử assign

## 9. Yêu cầu phi chức năng (Non-functional)

- NFR-01: Check gate khi login phải nhanh (mục tiêu: 1–2 query nhẹ, có thể cache).
- NFR-02: Phân quyền chặt: staff không lộ dữ liệu department khác.
- NFR-03: Auditability: ai tạo run, ai activate, ai assign ticket.
- NFR-04: Data integrity: versioning không làm mất dữ liệu cũ.
- NFR-05: Khả năng mở rộng: thêm loại run/ngữ cảnh trong tương lai mà không phá kiến trúc.

## 10. Permission Matrix (mức nghiệp vụ)

- Admin:
    - quản lý form, version, run
    - xem tất cả query

- Department Head:
    - xem query trong department
    - assign/reassign
    - đóng ticket

- Staff:
    - xem ticket được assign hoặc toàn department (tuỳ chính sách)
    - trả lời

- Student:
    - submit survey
    - tạo query, xem ticket của mình

## 11. Acceptance Criteria (tiêu chí nghiệm thu)

- AC-01: Tạo 1 form template và dùng lại cho 2 course offering khác nhau bằng 2 run khác nhau.
- AC-02: Course survey mandatory: student chưa submit → bị khóa portal; submit xong → vào portal bình thường.
- AC-03: Service survey theo semester: tạo run cho Spring 2025 và Fall 2025 mà không sửa form template.
- AC-04: Student tạo query HQ → staff HQ thấy, staff IT không thấy.
- AC-05: Head HQ assign query cho staff A, reassign sang staff B; lịch sử assign được lưu/hiển thị.
- AC-06: Department + semester: run thuộc HQ nhưng chỉ student của semester đã chọn mới thấy/submit/tạo ticket.

## 12. Open Questions

1. **Mỗi course offering chỉ có 1 course survey**
2. **Gate khóa toàn portal**
3. **Staff mặc định chỉ thấy ticket assigned**, nhưng **có thể cấu hình** để thấy **tất cả ticket trong department**
4. **Ticket closed là kết thúc**, không reopen
