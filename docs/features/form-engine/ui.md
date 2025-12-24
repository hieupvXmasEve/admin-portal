Dưới đây là **bộ trang UI cho Admin Portal (duy nhất)**, trong đó **cả “staff xử lý query” cũng làm ngay trong Admin Portal** (thông qua permission đã có). Mình chia theo module + mục đích rõ ràng để bạn map vào menu & permission nhanh.

## 1) Form Templates (đã có, không cần thực hiện task này)

### 1.1 Forms Library

**Mục đích:** quản lý **Form Template** (Survey/Query) dạng “kho mẫu” để reuse
**Chức năng chính:**

- List/search/filter theo `type` (survey/query)
- View form detail + versions
- Duplicate form (nếu cần)

### 1.2 Form Builder / Version Editor

**Mục đích:** thiết kế câu hỏi + tạo version mới
**Chức năng chính:**

- Edit cấu trúc (sections/questions/options/validation)
- Publish version (chỉ để admin quản trị, không trigger chạy)

## 2) Form Runs (Targets/Campaigns)

### 2.1 Runs List

**Mục đích:** quản lý các “đợt chạy” của form theo ngữ cảnh
**Chức năng chính:**

- List + filter theo:
    - context (course offering / semester / department / global)
    - status (draft/active/closed)
    - time window

- Quick actions: Activate / Close

### 2.2 Create / Edit Run

**Mục đích:** tạo 1 đợt chạy từ template
**Chức năng chính:**

- chọn form + version
- chọn context (course offering / semester / department)
- set start/end
- tick **mandatory** (chỉ với survey)
- (nếu có) giới hạn semester cho department-run

### 2.3 Run Detail / Monitoring

**Mục đích:** theo dõi tiến độ theo từng run
**Chức năng chính:**

- Survey run: completion stats, danh sách chưa hoàn thành (lọc/export nếu cần)
- Query run (department): tổng ticket theo status, ticket chưa assigned
- Action: Close run

## 3) Survey Gate Management (phục vụ “khóa toàn portal”)

### 3.1 Mandatory Surveys Dashboard

**Mục đích:** admin nhìn nhanh các survey đang **gating** portal
**Chức năng chính:**

- Danh sách survey runs mandatory đang active
- Số lượng student pending theo run
- Drill-down danh sách student pending (để support)

> Trang này giúp vận hành thực tế: “tại sao SV bị khóa?” / “còn bao nhiêu SV chưa làm?”

## 4) Survey Results / Reporting

### 4.1 Survey Results List

**Mục đích:** xem kết quả theo run (course offering / semester)
**Chức năng chính:**

- Filter theo semester/course
- Xem thống kê + distribution + comments
- Export (nếu cần)

### 4.2 Response Browser (optional nhưng rất hữu ích)

**Mục đích:** xem response “thô” để audit/trace
**Chức năng chính:**

- Search by student code
- View response detail theo form version

## 5) Query Management (thay thế Staff Portal)

> Đây là nơi “staff xử lý query” nhưng nằm trong **Admin Portal**, và bạn dùng permission để chỉ mở cho user được phép.

### 5.1 Query Inbox (Assigned to Me)

**Mục đích:** nhân viên vào admin portal chỉ thấy ticket được assign cho mình
**Chức năng chính:**

- List tickets assigned_to_me
- Filter status (new/assigned/in_progress/closed)
- Quick actions: reply / close

### 5.2 Department Inbox (permission-based)

**Mục đích:** chỉ user có quyền mới xem toàn bộ ticket của department
**Chức năng chính:**

- List all tickets in department
- Assign/reassign cho nhân viên
- Lọc unassigned để điều phối

### 5.3 Query Detail (Ticket Detail)

**Mục đích:** xử lý 1 ticket end-to-end
**Chức năng chính:**

- Timeline/thread trao đổi
- Reply (comment)
- Change status, close
- Assign/reassign (nếu có quyền)
- Audit: lịch sử assign & thao tác

## 6) Department & Routing Settings (trong Admin)

### 6.1 Departments

**Mục đích:** quản lý phòng ban (HQ/IT/Finance/Academic…)
**Chức năng chính:**

- CRUD department
- Set managers/heads (nếu cần)

### 6.2 Department Access Rules (gắn với permission sẵn có của bạn)

**Mục đích:** cấu hình “ai được xem gì trong query”
**Chức năng chính:**

- Toggle policy:
    - **assigned-only** (mặc định)
    - **department-wide** (nếu được cấp quyền)

- Mapping user → department (nếu user thuộc nhiều dept thì xác định primary)

> Vì bạn nói admin đã có permission config cho page access, phần này chỉ cần thêm rule/flag cho query visibility (nếu bạn muốn tách “được vào trang” và “được xem dept-wide”).

# Gợi ý menu tối giản (để không rối)

- **Forms**
    - Forms Library
    - Builder / Versions

- **Runs**
    - Runs List
    - Create Run
    - Run Detail

- **Surveys**
    - Mandatory Gate Dashboard
    - Survey Results

- **Queries**
    - Assigned to Me
    - Department Inbox (permission)
    - Ticket Detail

- **Settings**
    - Departments
    - Department Access Rules
