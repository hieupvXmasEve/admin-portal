# 🎯 BÀI TOÁN CỦA BẠN (CHỐT LẠI)

1. Có **nhiều phòng ban** (HQ, IT, Academic, Finance…)
2. Mỗi phòng ban:
    - tạo **form query riêng**
    - chỉ **nhân viên phòng ban đó** mới thấy / quản lý
3. Student gửi query → **tạo response**
4. Query có thể:
    - được **assign cho staff cụ thể**
    - staff khác trong cùng phòng ban có thể nhận lại

5. Khi mở **Query Management**:
    - chỉ thấy query **thuộc phòng ban của user**

👉 Yêu cầu: **filter đúng – phân quyền đúng – workflow rõ**

# ✅ NGUYÊN TẮC KIẾN TRÚC (RẤT QUAN TRỌNG)

> ❗ **Form KHÔNG thuộc phòng ban**
> ❗ **Response KHÔNG thuộc phòng ban**
> ✅ **Department gắn vào “Form Target + Assignment workflow”**

👉 Nếu gắn department trực tiếp vào form → **vỡ reuse**

# 🧱 KIẾN TRÚC ĐỀ XUẤT (CHỐT)

## 1️⃣ Department = Scope mở rộng (DÙNG LẠI CƠ CHẾ TARGET)

### Bảng `departments`

```text
departments
- id
- code (HQ, IT, FIN)
- name
```

## 2️⃣ Gắn Department vào **Form Target** (KHÔNG gắn vào Form)

```text
form_targets
- id
- form_id
- scope_type = 'department'
- scope_id = department_id
- status
```

📌 Ý nghĩa:

- Form query này **được mở cho phòng ban X**
- Mọi response sinh ra **mặc định thuộc department đó**

### Ví dụ

> Form: “HQ Service Query”

```text
form_targets
- form_id = HQ_QUERY_FORM
- scope_type = 'department'
- scope_id = HQ
```

## 3️⃣ Khi student submit query → tạo `response`

```text
responses
- id
- form_id
- submitted_by_student_id
- form_target_id   ← QUAN TRỌNG
```

👉 **Response không cần department_id trực tiếp**
👉 Department suy ra từ `form_target`

## 4️⃣ Query Management – FILTER ĐÚNG PHÒNG BAN

### Staff login → có `department_id`

Query management API:

```sql
SELECT r.*
FROM responses r
JOIN form_targets ft ON ft.id = r.form_target_id
WHERE ft.scope_type = 'department'
  AND ft.scope_id = :staff_department_id;
```

✔️ Staff phòng HQ → chỉ thấy query HQ
✔️ Không lộ dữ liệu phòng khác

## 5️⃣ Workflow ASSIGN QUERY cho nhân viên

👉 Thêm **layer workflow**, KHÔNG đụng form engine

### Bảng `query_assignments`

```text
query_assignments
- id
- response_id
- assigned_to_user_id
- assigned_by_user_id
- status ('assigned', 'in_progress', 'closed')
- assigned_at
```

📌 Quy tắc:

- Chỉ user **cùng department** mới được assign
- Admin / head department có quyền assign lại

### Logic assign

```text
Response (query)
   ↓
Query Assignment
   ↓
Staff trả lời
```

## 6️⃣ Response thread (2 chiều student ↔ staff)

(Phần này bạn có thể đã có, mình chốt lại cho đủ)

```text
response_threads
- response_id
- sender_type (student / staff)
- sender_id
- message
- created_at
```

## 7️⃣ Phân quyền (CHỐT GỌN)

### Role + Department

```text
users
- id
- department_id
```

### Rule:

| User      | Quyền                          |
| --------- | ------------------------------ |
| Staff     | Xem & trả lời query phòng mình |
| Dept Head | Assign / reassign              |
| Admin     | Xem tất cả                     |

## 8️⃣ Tổng hợp FLOW HOẠT ĐỘNG

### A. Admin tạo form query

- Form type = `query`
- Chưa gắn department

### B. Admin tạo form_target

```text
scope_type = 'department'
scope_id = HQ
```

### C. Student gửi query

- Chọn form
- Response tạo ra
- Gắn `form_target_id`

### D. Staff mở Query Management

- Filter theo department (qua form_target)

### E. Assign query cho staff

- Tạo query_assignment
- Staff xử lý & trả lời

## 9️⃣ NHỮNG ĐIỀU TUYỆT ĐỐI KHÔNG LÀM

❌ Không gắn department vào form
❌ Không gắn department trực tiếp vào response
❌ Không tạo bảng `hq_queries`, `it_queries`

## 10️⃣ CÂU CHỐT (ĐỂ NHỚ)

> **Form là cấu trúc
> Target là ngữ cảnh
> Department là một loại scope
> Workflow nằm ngoài form**

# ✅ KẾT LUẬN CUỐI

Giải pháp này:

- ✔ Áp dụng trọn vẹn cho **query**
- ✔ Filter đúng theo phòng ban
- ✔ Assign linh hoạt cho staff
- ✔ Không phá dynamic form
- ✔ Scale thêm phòng ban / workflow dễ dàng
