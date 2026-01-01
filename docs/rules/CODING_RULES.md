# CODING RULES

**Laravel 12 + Vue 3 + InertiaJS (Modular Monolith)**

## 0. Mục tiêu của rules này

- Giảm cognitive load
- Dễ quay lại đọc sau 6–12 tháng
- Sửa module A không làm vỡ module B
- Không “sáng tạo” mỗi người một kiểu

> **Consistency > Cleverness**

## 1. QUY TẮC TỔNG THỂ (BẮT BUỘC)

### R1. Không viết logic nghiệp vụ trong Controller

- Controller chỉ là Adapter.
- Logic nằm trong **Action** (Write) hoặc **Query** (Read).

### R2. Không để FE tự quyết định nghiệp vụ

- Trả về status/state từ Backend, FE chỉ hiển thị dựa theo state đó.

### R3. Mỗi bảng DB có **1 module “owner”**

- Module khác chỉ được read-only hoặc gọi qua interface của module chủ quản.

### R4. Inertia Page KHÔNG gọi API trực tiếp

- Hạn chế axios/fetch trong Page component.
- Ưu tiên truyền data qua Props từ Controller.

### R5. Không join Eloquent cross-module

- Tuyệt đối không query `Join` giữa các Model thuộc 2 module khác nhau.
- Thay vào đó dùng Query Builder hoặc API/Service nếu cần lấy dữ liệu liên đới.

### R6. Action chia theo Nghiệp vụ, KHÔNG chia theo Actor

- Tránh duplicate logic: `StudentUpdateProfileAction` vs `AdminUpdateStudentAction` => gom lại thành `UpdateStudentProfileAction`.
- Phân quyền (Who can do this) xử lý ở **Policy/Gate**.

---

## 2. CẤU TRÚC FOLDER & ARCHITECTURE

Mọi quy định về cấu trúc thư mục được tập trung tại:  
👉 **[RULES_STRUCTURE.md](RULES_STRUCTURE.md)**

---

## 3. NAMING RULES – BACKEND

### 3.1 Module name

| Đúng       | Sai             |
| ---------- | --------------- |
| `Academic` | `Academics`     |
| `Finance`  | `BillingSystem` |
| `Identity` | `AuthStuff`     |

👉 **Danh từ, số ít, PascalCase, domain-level.**

### 3.2 Model

- `Student`, `CourseOffering`, `AcademicRecord`.
- ❌ Không: `TblStudent`, `StudentEntity`.

### 3.3 Controller

Phân cấp theo Layer và Portal:

- Web (Stateful): `Http/Web/Admin/{Entity}Controller.php`
- Api (Stateless): `Http/Api/Student/{Entity}Controller.php`

### 3.4 Action (NƠI VIẾT NGHIỆP VỤ)

**Format:** `Verb + [Entity] + Action`

| Ví dụ đúng                   | Ghi chú                                |
| ---------------------------- | -------------------------------------- |
| `CreateEventAction`          | Nghiệp vụ tạo                          |
| `UpdateStudentProfileAction` | Dùng chung cho cả Student/Admin tự sửa |
| `CalculateGpaAction`         | Logic tính toán                        |
| `FinalizeGradeAction`        | Nghiệp vụ chốt điểm                    |

📌 **Rule vàng:** 1 Action = 1 Business Use-case.

### 3.5 Query (Read-only)

**Format:** `[Verb/Get] + Entity + [Context] + Query`

- `ListAcademicRecordsQuery`
- `GetStudentGpaQuery`
- `FindEventByCodeQuery`

### 3.6 Policy

```
AcademicRecordPolicy
EventPolicy
```

---

## 4. FUNCTION & METHOD NAMING

### Action public method

```php
public static function run(array $data): mixed
```

📌 TẤT CẢ Action đều dùng `run()` làm entry point.

### Query public method

```php
public function handle(...$args): mixed
```

### Controller method (Resource-standard)

`index`, `show`, `create`, `store`, `edit`, `update`, `destroy`.

---

## 5. ROUTE NAMING

### Web route

Format: `{module}.{resource}.{action}`  
Ví dụ: `identity.login.show`, `academic.records.index`.

### API route

Ví dụ: `/api/v1/student/auth/login`.

---

## 6. FRONTEND CONVENTIONS (VUE)

### 6.1 Page Naming

Mapping 1-1 với Route: `Academic/Records/Index.vue`.

### 6.2 Component Naming

- PascalCase: `RecordTable.vue`, `EventForm.vue`.
- ❌ Tránh tên chung chung: `Table.vue`, `Form.vue`.

### 6.3 Composable

- `useInertiaFilters.ts`, `usePermissions.ts`.
- **Không** viết nghiệp vụ phức tạp trong Composable.

---

## 7. VALIDATION & REQUEST

- Validation BẮT BUỘC nằm ở **Form Request**, không viết ở Action hay Controller.
- Namespace: `Http/Requests/{Domain}/...` (Ví dụ: `Http/Requests/Identity/LoginRequest.php`).
- Gom nhóm theo Domain Context, KHÔNG chia theo Actor (Student/Admin).

## 8. ERROR HANDLING

- Action chỉ throw **Domain Exception**.
- Controller có trách nhiệm catch và render về View hoặc trả về JSON ApiResponse.

---

## 12. RULE VÀNG CUỐI CÙNG

> **Nếu bạn phải hỏi “file này đặt ở đâu?”**
> → Cấu trúc chưa đủ rõ.

> **Nếu bạn phải đọc 5 file mới hiểu logic**
> → Boundary bị vỡ.
