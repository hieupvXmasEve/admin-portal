# 🏗 PROJECT STRUCTURE & RULES

## 1. Architecture: Modular Monolith

Hệ thống được phát triển theo mô hình **Modular Monolith**. Ứng dụng được chia thành các **Modules** độc lập dựa trên nghiệp vụ (Identity, Academic, Finance), chia sẻ chung lớp dữ liệu (`app/Models`).

### Nguyên tắc cốt lõi:

1. **Tính độc lập**: Một module không được phụ thuộc trực tiếp vào logic nội bộ của module khác.
2. **Shared Models**: `app/Models` chứa các Eloquent models dùng chung (trừ khi module sở hữu dữ liệu riêng biệt).
3. **Actions over Services**: Logic nghiệp vụ tập trung vào **Actions** (Single Use-Case).
4. **Thin Controllers**: Controllers chỉ đóng vai trò Adapter: nhận input, gọi Action/Query, trả response.

---

## 2. Directory Structure (Backend)

Cấu trúc thư mục của một Module chuẩn:

```text
app/Modules/{Domain}/
 ├─ Actions/             # Business Logic (Write/State change)
 ├─ Queries/             # Read Logic (Complex joins/Reports)
 ├─ Http/                # Communication Layer
 │   ├─ Web/             # Stateful (Inertia/Blade)
 │   │   ├─ Admin/       # Controllers cho Admin Portal
 │   │   └─ Student/     # Controllers cho Student Portal
 │   ├─ Api/             # Stateless (JSON)
 │   │   ├─ Admin/       # APIs cho Admin FE
 │   │   ├─ Student/     # APIs cho Student Portal
 │   │   └─ Lecturer/    # APIs cho Lecturer Portal
 │   └─ Requests/        # Validation theo Domain/Nghiệp vụ
 │       └─ {Domain}/    # e.g., Identity, Enrollment, Grading
 ├─ Policies/            # Authorization rules (Gates/Policies)
 ├─ Providers/           # Module ServiceProvider (Route loading, Binding)
 └─ routes/              # routes/web.php, api.php
```

---

## 3. Directory Structure (Frontend)

Toàn bộ Page component được tổ chức theo Module tại `resources/js/Pages`:

```text
resources/js/
 ├─ Pages/                   # Tổ chức theo /{Module}/{Entity}/
 │   ├─ Academic/
 │   │   └─ CourseOffering/
 │   │       ├─ Index.vue
 │   │       └─ Show.vue
 ├─ Components/              # Reusable UI (Shadcn/Custom)
 ├─ Layouts/                 # Main wrappers
 ├─ Composables/             # Logic dùng chung (hooks)
 └─ types/                   # TypeScript definitions
```

---

## 4. Coding Standards & Conventions

Chi tiết về quy tắc đặt tên (Naming), các mẫu lập trình (Patterns) và cách tổ chức Code sạch:  
👉 **[CODING_RULES.md](CODING_RULES.md)**

---

## 5. Development Workflow

1. **Phân tích**: Xác định Module và Nghiệp vụ (Action).
2. **Database**: Cập nhật `app/Models` và Migrations (Shared Layer).
3. **Backend Implementation**:
    - Tạo `Action` trong `app/Modules/{Module}/Actions`.
    - Tạo `FormRequest` cho validation.
    - Tạo `Controller` làm Adapter.
    - Đăng ký `Route`.
4. **Frontend Implementation**:
    - Tạo Page component.
    - Mapping Route-to-Page 1-1.

---

## 6. Liên kết hữu ích

- [CODING_RULES.md](CODING_RULES.md): Quy tắc viết code và đặt tên.
- [RULES_api_interaction.md](../RULES_api_interaction.md): Quy chuẩn về API Response.
