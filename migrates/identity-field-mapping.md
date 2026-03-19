# Identity Module — Field Mapping Guide

> Tài liệu liệt kê toàn bộ field liên quan đến **Authentication** & **Authorization** trong project hiện tại.
> Dùng để mapping data từ code cũ sang hệ thống mới.
>
> **Module:** `app/Modules/Identity` > **Ngày tạo:** 2026-03-19

---

## Mục lục

1. [Tổng quan kiến trúc](#1-tổng-quan-kiến-trúc)
2. [Bảng `users` — Tài khoản đăng nhập chính](#2-bảng-users)
3. [Bảng `roles` — Vai trò](#3-bảng-roles)
4. [Bảng `permissions` — Quyền hạn](#4-bảng-permissions)
5. [Bảng `campus_user_roles` — Gán vai trò theo campus](#5-bảng-campus_user_roles)
6. [Bảng `role_permissions` — Gán quyền cho vai trò](#6-bảng-role_permissions)
7. [Bảng `personal_access_tokens` — API Token (Sanctum)](#7-bảng-personal_access_tokens)
8. [Token Abilities — Phân quyền API](#8-token-abilities)
9. [User Type — Loại người dùng](#9-user-type)
10. [User Status — Trạng thái tài khoản](#10-user-status)
11. [OAuth / Social Login — Đăng nhập Google](#11-oauth--social-login)
12. [Luồng xác thực theo vai trò](#12-luồng-xác-thực-theo-vai-trò)
13. [IdentityContext — Context phiên đăng nhập](#13-identitycontext)
14. [Bảng `students` — Student Profile](#14-bảng-students--student-profile)
15. [Bảng `lectures` — Lecturer Profile](#15-bảng-lectures--lecturer-profile)
16. [Bảng `parents` — Parent Profile](#16-bảng-parents--parent-profile)
17. [Bảng `parent_student` — Quan hệ phụ huynh–sinh viên](#17-bảng-parent_student)
18. [Checklist mapping](#18-checklist-mapping)

---

## 1. Tổng quan kiến trúc

```
User (users table)            ← Single Source of Truth cho authentication
 ├── type (enum)              ← Xác định loại: staff | student | lecturer | parent
 ├── status                   ← Xác định trạng thái tài khoản
 ├── OAuth fields             ← Cho Google login
 │
 ├── → Student (students)     ← Profile riêng, HasApiTokens cho API token
 ├── → Lecturer (lectures)    ← Profile riêng, HasApiTokens cho API token
 ├── → ParentProfile          ← Profile riêng, token issue trên User model
 │
 ├── → CampusUserRole         ← Gán role theo campus (Staff/Admin web)
 │     ├── → Role             ← Vai trò (admin, manager, ...)
 │     └── → Campus           ← Campus áp dụng
 │
 └── → Role → Permission     ← Phân quyền chi tiết theo module
```

**Auth method:**

- **Admin (Staff):** Session-based (Laravel web guard) + Google OAuth
- **Student API:** Token-based (Sanctum) với ability `['student']`
- **Lecturer API:** Token-based (Sanctum) với ability `['lecturer:access']`
- **Parent API:** Token-based (Sanctum) với ability `['parent']`

---

## 2. Bảng `users`

> Model: `App\Models\User`
> Đây là bảng chính cho authentication. Mọi loại user đều có 1 record ở đây.

| Field               | Type                  | Ý nghĩa                  | Giá trị mẫu             | Ghi chú mapping                          |
| ------------------- | --------------------- | ------------------------ | ----------------------- | ---------------------------------------- |
| `id`                | bigint (PK)           | ID tự tăng               | `1`                     | Primary key, tham chiếu từ các bảng khác |
| `name`              | string                | Họ tên đầy đủ            | `"Nguyen Van A"`        | Tên hiển thị chung                       |
| `email`             | string (unique)       | Email đăng nhập          | `"a@swinburne.edu.vn"`  | **Unique**, dùng làm username đăng nhập  |
| `password`          | string (hashed)       | Mật khẩu (bcrypt)        | `"$2y$12$..."`          | Tự động hash qua cast `hashed`           |
| `phone`             | string (nullable)     | Số điện thoại            | `"0901234567"`          | Tùy chọn                                 |
| `address`           | string (nullable)     | Địa chỉ                  | `"123 ABC"`             | Tùy chọn                                 |
| `status`            | string                | Trạng thái tài khoản     | `"active"`              | Xem [mục 10](#10-user-status)            |
| `type`              | enum (UserType)       | Loại người dùng          | `"staff"`               | Xem [mục 9](#9-user-type)                |
| `department_id`     | bigint (nullable, FK) | Phòng ban (cho staff)    | `5`                     | FK → `departments.id`                    |
| `oauth_provider`    | string (nullable)     | Nhà cung cấp OAuth       | `"google"`              | Hiện chỉ hỗ trợ `google`                 |
| `oauth_provider_id` | string (nullable)     | ID từ OAuth provider     | `"1234567890"`          | Google `sub` claim                       |
| `email_verified_at` | datetime (nullable)   | Thời điểm xác minh email | `"2026-01-01 00:00:00"` | Tự cập nhật khi login Google             |
| `last_login_at`     | datetime (nullable)   | Lần đăng nhập cuối       | `"2026-03-19 10:00:00"` | Cập nhật mỗi lần login thành công        |
| `remember_token`    | string (nullable)     | Token "Remember Me"      | —                       | Laravel mặc định, cho web session        |
| `created_at`        | datetime              | Ngày tạo                 | —                       | Audit                                    |
| `updated_at`        | datetime              | Ngày cập nhật            | —                       | Audit                                    |

---

## 3. Bảng `roles`

> Model: `App\Models\Role`
> Định nghĩa các vai trò trong hệ thống (áp dụng cho Staff/Admin portal).

| Field        | Type            | Ý nghĩa                 | Giá trị mẫu | Ghi chú mapping                                         |
| ------------ | --------------- | ----------------------- | ----------- | ------------------------------------------------------- |
| `id`         | bigint (PK)     | ID vai trò              | `1`         |                                                         |
| `name`       | string          | Tên vai trò hiển thị    | `"Admin"`   | Tên đọc được                                            |
| `code`       | string (unique) | Mã vai trò (snake_case) | `"admin"`   | **Unique**, tự sinh từ `name`, dùng để check trong code |
| `created_at` | datetime        | Ngày tạo                | —           |                                                         |
| `updated_at` | datetime        | Ngày cập nhật           | —           |                                                         |

**Lưu ý:** `code` tự động được generate từ `name` khi tạo mới (nếu để trống). Dùng `code` để check role trong business logic.

---

## 4. Bảng `permissions`

> Model: `App\Models\Permission`
> Định nghĩa quyền hạn chi tiết, nhóm theo module, hỗ trợ phân cấp (parent-child).

| Field          | Type                  | Ý nghĩa              | Giá trị mẫu               | Ghi chú mapping                     |
| -------------- | --------------------- | -------------------- | ------------------------- | ----------------------------------- |
| `id`           | bigint (PK)           | ID quyền             | `1`                       |                                     |
| `name`         | string                | Tên quyền (internal) | `"manage_users"`          | Dùng trong code để check            |
| `code`         | string                | Mã quyền             | `"users.manage"`          | Format `module.action`              |
| `display_name` | string (nullable)     | Tên hiển thị         | `"Quản lý người dùng"`    | Hiển thị trên UI                    |
| `description`  | string (nullable)     | Mô tả chi tiết       | `"Toàn quyền quản lý..."` |                                     |
| `module`       | string (nullable)     | Tên module/nhóm      | `"users"`                 | Nhóm quyền theo chức năng           |
| `parent_id`    | bigint (nullable, FK) | Quyền cha            | `null`                    | FK → `permissions.id`, cho phân cấp |
| `created_at`   | datetime              | Ngày tạo             | —                         |                                     |
| `updated_at`   | datetime              | Ngày cập nhật        | —                         |                                     |

---

## 5. Bảng `campus_user_roles`

> Model: `App\Models\CampusUserRole`
> Bảng pivot gán vai trò cho user **theo từng campus**. Mỗi user có thể có nhiều vai trò ở nhiều campus khác nhau.

| Field        | Type        | Ý nghĩa          | Giá trị mẫu | Ghi chú mapping    |
| ------------ | ----------- | ---------------- | ----------- | ------------------ |
| `id`         | bigint (PK) | ID bản ghi       | `1`         |                    |
| `user_id`    | bigint (FK) | User được gán    | `10`        | FK → `users.id`    |
| `campus_id`  | bigint (FK) | Campus áp dụng   | `2`         | FK → `campuses.id` |
| `role_id`    | bigint (FK) | Vai trò được gán | `1`         | FK → `roles.id`    |
| `created_at` | datetime    | Ngày gán         | —           |                    |
| `updated_at` | datetime    | Ngày cập nhật    | —           |                    |

**Logic:** Một user type `staff` có thể là "Admin" ở campus HCM và "Manager" ở campus HN cùng lúc.

---

## 6. Bảng `role_permissions`

> Model: `App\Models\RolePermission`
> Bảng pivot gán permission cho role.

| Field           | Type        | Ý nghĩa       | Giá trị mẫu | Ghi chú mapping       |
| --------------- | ----------- | ------------- | ----------- | --------------------- |
| `id`            | bigint (PK) | ID bản ghi    | `1`         |                       |
| `role_id`       | bigint (FK) | Vai trò       | `1`         | FK → `roles.id`       |
| `permission_id` | bigint (FK) | Quyền hạn     | `5`         | FK → `permissions.id` |
| `created_at`    | datetime    | Ngày gán      | —           |                       |
| `updated_at`    | datetime    | Ngày cập nhật | —           |                       |

---

## 7. Bảng `personal_access_tokens`

> Laravel Sanctum mặc định.
> Dùng cho API authentication (Student, Lecturer, Parent).

| Field            | Type                | Ý nghĩa                | Giá trị mẫu             | Ghi chú mapping                                       |
| ---------------- | ------------------- | ---------------------- | ----------------------- | ----------------------------------------------------- |
| `id`             | bigint (PK)         | ID token               | `1`                     |                                                       |
| `tokenable_type` | string              | Model sở hữu token     | `"App\Models\Student"`  | Polymorphic: Student, Lecturer (`Lecture`), hoặc User |
| `tokenable_id`   | bigint              | ID của model sở hữu    | `42`                    |                                                       |
| `name`           | string              | Tên thiết bị           | `"Student Portal"`      | Phân biệt thiết bị                                    |
| `token`          | string (hash)       | SHA-256 hash của token | —                       | Chỉ lưu hash, plain text trả 1 lần                    |
| `abilities`      | json                | Danh sách ability      | `["student"]`           | Xem [mục 8](#8-token-abilities)                       |
| `expires_at`     | datetime (nullable) | Hết hạn                | `"2026-03-20 02:00:00"` | Mặc định: **+8 giờ**                                  |
| `last_used_at`   | datetime (nullable) | Lần sử dụng cuối       | —                       |                                                       |
| `created_at`     | datetime            | Ngày tạo               | —                       |                                                       |
| `updated_at`     | datetime            | Ngày cập nhật          | —                       |                                                       |

---

## 8. Token Abilities — Phân quyền API

Token abilities xác định scope truy cập API cho mỗi loại user:

| Ability           | Dành cho | Mô tả                              | Token owner model |
| ----------------- | -------- | ---------------------------------- | ----------------- |
| `student`         | Student  | Truy cập toàn bộ Student API       | `Student` model   |
| `lecturer:access` | Lecturer | Truy cập toàn bộ Lecturer API      | `Lecture` model   |
| `parent`          | Parent   | Truy cập Parent API (xem data con) | `User` model      |

**Middleware bảo vệ route:**

- `auth:sanctum` — Xác thực token
- `api.actor:student_or_parent` — Kiểm tra actor type
- `api.actor:lecturer` — Kiểm tra actor type
- `api.actor:parent` — Kiểm tra actor type
- `lecturer.api.auth` — Kiểm tra quyền lecturer
- `either:parent.student.access,student.api.auth` — Cho phép parent hoặc student

---

## 9. User Type — Loại người dùng

> Enum: `App\Shared\Support\Enums\UserType`
> Field: `users.type`

| Giá trị    | Label    | Mô tả              | Auth method                |
| ---------- | -------- | ------------------ | -------------------------- |
| `staff`    | Staff    | Nhân viên quản trị | Web session + Google OAuth |
| `student`  | Student  | Sinh viên          | API token (Sanctum)        |
| `lecturer` | Lecturer | Giảng viên         | API token (Sanctum)        |
| `parent`   | Parent   | Phụ huynh          | API token (Sanctum)        |

**Logic kiểm tra trong code:**

```php
$user->isStaff()     // type === 'staff'
$user->isStudent()   // type === 'student'
$user->isLecturer()  // type === 'lecturer'
$user->isParent()    // type === 'parent'
```

---

## 10. User Status — Trạng thái tài khoản

> Field: `users.status`
> Mặc định: `active`

| Giá trị      | Label      | Ý nghĩa                                | Được phép đăng nhập? |
| ------------ | ---------- | -------------------------------------- | -------------------- |
| `active`     | Active     | Tài khoản đang hoạt động               | ✅ Có                |
| `inactive`   | Inactive   | Ngừng hoạt động                        | ❌ Không             |
| `pending`    | Pending    | Chờ duyệt                              | ❌ Không             |
| `suspended`  | Suspended  | Bị đình chỉ tạm thời                   | ❌ Không             |
| `banned`     | Banned     | Bị cấm vĩnh viễn                       | ❌ Không             |
| `locked`     | Locked     | Bị khóa (do login sai nhiều lần, v.v.) | ❌ Không             |
| `verified`   | Verified   | Đã xác minh (Staff: cho phép login)    | ✅ Có (Staff only)   |
| `unverified` | Unverified | Chưa xác minh                          | ❌ Không             |

**Lưu ý:** Staff login chấp nhận cả `active` và `verified`. Các loại user khác chỉ chấp nhận `active`.

---

## 11. OAuth / Social Login — Đăng nhập Google

Dùng cho cả Admin (web), Student, Lecturer, và Parent API.

| Field trong `users` | Ý nghĩa                      | Cập nhật khi nào                    |
| ------------------- | ---------------------------- | ----------------------------------- |
| `oauth_provider`    | Provider name (`"google"`)   | Lần đầu login Google                |
| `oauth_provider_id` | Google user ID (`sub` claim) | Lần đầu login Google                |
| `email_verified_at` | Timestamp xác minh email     | Lần đầu login Google (nếu chưa set) |

**Admin (Staff):** Sử dụng Laravel Socialite → redirect flow → `AuthenticateSocialUserAction`
**Student/Lecturer/Parent API:** Client gửi `id_token` → server verify trực tiếp qua `Google\Client`

---

## 12. Luồng xác thực theo vai trò

### 12.1 Staff (Admin Web)

| Bước | Field/Logic kiểm tra                       | Action                               |
| ---- | ------------------------------------------ | ------------------------------------ |
| 1    | `email` + `password`                       | `LoginAction`                        |
| 2    | `user.type === 'staff'`                    | Chỉ staff mới được login admin       |
| 3    | `user.status === 'active' \|\| 'verified'` | Kiểm tra trạng thái                  |
| 4    | Session + `current_campus_id`              | `SetCurrentCampusAction` chọn campus |
| 5    | `campus_user_roles`                        | Load roles theo campus hiện tại      |

### 12.2 Student (API)

| Bước | Field/Logic kiểm tra                                | Action                             |
| ---- | --------------------------------------------------- | ---------------------------------- |
| 1    | `users.email` + `password`                          | `StudentLoginAction`               |
| 2    | `users.status === 'active'`                         | User phải active                   |
| 3    | `user→student` relationship                         | Phải tồn tại student profile       |
| 4    | `students.status` → `isActive()`                    | Student profile phải active        |
| 5    | `academic_holds` (status=active, hold_category=all) | Không bị giữ học                   |
| 6    | Token ability: `['student']`                        | Issue token trên **Student** model |

### 12.3 Lecturer (API)

| Bước | Field/Logic kiểm tra                                                     | Action                             |
| ---- | ------------------------------------------------------------------------ | ---------------------------------- |
| 1    | `users.email` + `password`                                               | `LecturerLoginAction`              |
| 2    | `users.status === 'active'`                                              | User phải active                   |
| 3    | `user→lecturer` relationship                                             | Phải tồn tại lecturer profile      |
| 4    | `lectures.is_active === true`                                            | Lecturer profile phải active       |
| 5    | `lectures.employment_status ∈ ['active', 'employed', 'contract_active']` | Trạng thái việc làm hợp lệ         |
| 6    | Token ability: `['lecturer:access']`                                     | Issue token trên **Lecture** model |

### 12.4 Parent (API)

| Bước | Field/Logic kiểm tra                  | Action                          |
| ---- | ------------------------------------- | ------------------------------- |
| 1    | `users.email` + `password`            | `ParentLoginAction`             |
| 2    | `users.status === 'active'`           | User phải active                |
| 3    | `users.type === 'parent'`             | Phải là parent                  |
| 4    | `user→parentProfile` relationship     | Phải tồn tại parent profile     |
| 5    | `parent_profiles.status === 'active'` | Profile phải active             |
| 6    | Token ability: `['parent']`           | Issue token trên **User** model |

---

## 13. IdentityContext — Context phiên đăng nhập

> Class: `App\Modules\Identity\IdentityContext`
> Dùng cho **Admin (Staff) web session**, cung cấp context xuyên suốt request.

| Property / Method | Ý nghĩa                               | Source                                   |
| ----------------- | ------------------------------------- | ---------------------------------------- |
| `user()`          | User đang đăng nhập                   | `Auth::user()`                           |
| `campusId()`      | Campus hiện tại                       | `Session::get('current_campus_id')`      |
| `roles()`         | Danh sách vai trò tại campus hiện tại | `campus_user_roles` (filtered by campus) |
| `hasRole($code)`  | Kiểm tra có vai trò cụ thể            | So sánh `roles.code`                     |
| `isStaff()`       | Có phải staff?                        | `user.type === 'staff'`                  |
| `isStudent()`     | Có phải student?                      | `user.type === 'student'`                |
| `isLecturer()`    | Có phải lecturer?                     | `user.type === 'lecturer'`               |
| `isParent()`      | Có phải parent?                       | `user.type === 'parent'`                 |
| `check()`         | Đã đăng nhập chưa?                    | `user !== null`                          |

**LazyPermissions trait** (trên User model):

```php
$user->hasPermission('users.manage', $campusId)      // Kiểm tra 1 quyền
$user->hasAnyPermission(['a', 'b'], $campusId)        // OR logic
$user->hasAllPermissions(['a', 'b'], $campusId)       // AND logic
```

---

## 14. Bảng `students` — Student Profile

> Model: `App\Models\Student`
> Bảng: `students`
> Trait: `HasApiTokens` — Token Sanctum issue trực tiếp trên Student model.
> Liên kết: `students.user_id` → `users.id` (1-1)

### 14.1 Thông tin cơ bản & Định danh

| Field           | Type                      | Ý nghĩa             | Giá trị mẫu                    | Ghi chú mapping                    |
| --------------- | ------------------------- | ------------------- | ------------------------------ | ---------------------------------- |
| `id`            | bigint (PK)               | ID nội bộ           | `1`                            | Primary key                        |
| `student_id`    | string (unique)           | Mã sinh viên        | `"HCM2026001"`                 | Format: `{campus_code}{year}{seq}` |
| `user_id`       | bigint (FK)               | Tài khoản đăng nhập | `10`                           | FK → `users.id` — **bắt buộc**     |
| `full_name`     | string                    | Họ tên đầy đủ       | `"Nguyen Van A"`               |                                    |
| `email`         | string (unique)           | Email sinh viên     | `"a@student.swinburne.edu.vn"` | Có thể khác `users.email`          |
| `phone`         | string (nullable)         | Số điện thoại       | `"0901234567"`                 |                                    |
| `avatar_url`    | string (nullable)         | URL ảnh đại diện    | `"https://..."`                | Tự cập nhật từ Google login        |
| `date_of_birth` | date (nullable)           | Ngày sinh           | `"2005-01-15"`                 | Cast: `date:Y-m-d`                 |
| `gender`        | string (nullable)         | Giới tính           | `"male"`                       | Enum: `male`, `female`, `other`    |
| `nationality`   | string (nullable)         | Quốc tịch           | `"Vietnamese"`                 |                                    |
| `ethnicity`     | string (nullable)         | Dân tộc             | `"Kinh"`                       |                                    |
| `national_id`   | string (nullable, unique) | CCCD/CMND           | `"079123456789"`               |                                    |

### 14.2 Địa chỉ hiện tại

| Field                  | Type              | Ý nghĩa                | Giá trị mẫu        | Ghi chú mapping |
| ---------------------- | ----------------- | ---------------------- | ------------------ | --------------- |
| `address`              | string (nullable) | Địa chỉ chung (legacy) | `"123 ABC"`        |                 |
| `current_address_line` | string (nullable) | Số nhà, đường          | `"123 Nguyen Hue"` |                 |
| `current_ward`         | string (nullable) | Phường/xã              | `"Ben Nghe"`       |                 |
| `current_province`     | string (nullable) | Tỉnh/TP                | `"Ho Chi Minh"`    |                 |
| `current_country`      | string (nullable) | Quốc gia               | `"Vietnam"`        |                 |

### 14.3 Địa chỉ CCCD (hộ khẩu)

| Field               | Type              | Ý nghĩa               | Giá trị mẫu    | Ghi chú mapping |
| ------------------- | ----------------- | --------------------- | -------------- | --------------- |
| `cccd_address`      | string (nullable) | Địa chỉ CCCD (legacy) | `"456 XYZ"`    |                 |
| `cccd_address_line` | string (nullable) | Số nhà, đường         | `"456 Le Loi"` |                 |
| `cccd_ward`         | string (nullable) | Phường/xã             | `"Phu My"`     |                 |
| `cccd_province`     | string (nullable) | Tỉnh/TP               | `"Ba Ria"`     |                 |
| `cccd_country`      | string (nullable) | Quốc gia              | `"Vietnam"`    |                 |

### 14.4 Thông tin học vụ

| Field                              | Type                  | Ý nghĩa                 | Giá trị mẫu    | Ghi chú mapping               |
| ---------------------------------- | --------------------- | ----------------------- | -------------- | ----------------------------- |
| `campus_id`                        | bigint (FK)           | Campus                  | `1`            | FK → `campuses.id`            |
| `program_id`                       | bigint (FK)           | Chương trình học        | `3`            | FK → `programs.id`            |
| `specialization_id`                | bigint (nullable, FK) | Chuyên ngành            | `5`            | FK → `specializations.id`     |
| `curriculum_version_id`            | bigint (FK)           | Phiên bản chương trình  | `2`            | FK → `curriculum_versions.id` |
| `intake_semester_id`               | bigint (nullable, FK) | Kỳ nhập học             | `10`           | FK → `semesters.id`           |
| `intake`                           | integer (nullable)    | Khóa nhập học           | `2026`         |                               |
| `intake_mode`                      | string (nullable)     | Chế độ nhập học         | `"sequential"` | `sequential` hoặc `parallel`  |
| `intake_gc`                        | integer (nullable)    | Kỳ bắt đầu EGC          | `15`           | FK → `semesters.id`           |
| `intake_course`                    | string (nullable)     | Kỳ chuyển sang Course   | —              |                               |
| `intake_major`                     | integer (nullable)    | Kỳ chuyển sang Major    | `18`           |                               |
| `gc_to_course_transition_semester` | string (nullable)     | Kỳ chuyển GC→Course     | —              |                               |
| `admission_date`                   | date                  | Ngày nhập học           | `"2026-01-15"` | Bắt buộc                      |
| `expected_graduation_date`         | date (nullable)       | Ngày dự kiến tốt nghiệp | `"2030-01-15"` |                               |
| `gc_starting_level`                | string (nullable)     | Level EGC bắt đầu       | —              |                               |
| `gc_current_level`                 | string (nullable)     | Level EGC hiện tại      | —              |                               |
| `gc_total_levels`                  | string (nullable)     | Tổng số level EGC       | —              |                               |

### 14.5 Trạng thái (quan trọng cho auth)

| Field                | Type                  | Ý nghĩa                  | Giá trị mẫu           | Ghi chú mapping                     |
| -------------------- | --------------------- | ------------------------ | --------------------- | ----------------------------------- |
| `status`             | string                | Trạng thái sinh viên     | `"intake_course"`     | **Ảnh hưởng login** — xem bảng dưới |
| `academic_status`    | string (nullable)     | Trạng thái học vụ        | `"active"`            | Độc lập với `status`                |
| `status_change_date` | date (nullable)       | Ngày thay đổi trạng thái | `"2026-03-01"`        |                                     |
| `status_reason`      | string (nullable)     | Lý do thay đổi           | `"Transfer to major"` |                                     |
| `status_changed_by`  | bigint (nullable, FK) | Người thay đổi           | `5`                   | FK → `users.id`                     |

**Student Status Enum & ảnh hưởng login:**

| Giá trị              | Label              | Cho phép login? | Ghi chú                          |
| -------------------- | ------------------ | --------------- | -------------------------------- |
| `intake_pre_uni_gc`  | Intake Pre-Uni GC  | ✅ Có           | Giai đoạn EGC                    |
| `intake_course`      | Intake Course      | ✅ Có           | Đã chuyển sang Course            |
| `intake_major`       | Intake Major       | ✅ Có           | Đã chuyển sang Major             |
| `suspended`          | Suspended          | ✅ Có           | Bị đình chỉ nhưng vẫn login được |
| `deferred`           | Deferred           | ✅ Có           | Bảo lưu                          |
| `admission_deferred` | Admission Deferred | ✅ Có           | Bảo lưu nhập học                 |
| `inactive`           | Inactive           | ❌ Không        | **BLOCKED**                      |
| `dropout`            | Dropout            | ❌ Không        | **BLOCKED** — thôi học           |
| `dropout_transfer`   | Dropout Transfer   | ❌ Không        | **BLOCKED** — chuyển trường      |
| `graduated`          | Graduated          | ❌ Không        | **BLOCKED** — đã tốt nghiệp      |
| `pending`            | Pending            | ❌ Không        | **BLOCKED** — chờ duyệt          |

**Academic Status Enum:**

| Giá trị     | Label     |
| ----------- | --------- |
| `active`    | Active    |
| `inactive`  | Inactive  |
| `graduated` | Graduated |
| `suspended` | Suspended |
| `withdrawn` | Withdrawn |

### 14.6 Thông tin trước nhập học

| Field                         | Type                  | Ý nghĩa             | Giá trị mẫu         | Ghi chú mapping |
| ----------------------------- | --------------------- | ------------------- | ------------------- | --------------- |
| `high_school_name`            | string (nullable)     | Tên trường THPT     | `"THPT Nguyen Hue"` |                 |
| `high_school_graduation_year` | integer (nullable)    | Năm tốt nghiệp THPT | `2025`              |                 |
| `entrance_exam_score`         | decimal(2) (nullable) | Điểm thi đầu vào    | `7.50`              |                 |
| `admission_notes`             | string (nullable)     | Ghi chú tuyển sinh  | `"Scholarship"`     |                 |

### 14.7 Timestamps

| Field        | Type     | Ý nghĩa       |
| ------------ | -------- | ------------- |
| `created_at` | datetime | Ngày tạo      |
| `updated_at` | datetime | Ngày cập nhật |

---

## 15. Bảng `lectures` — Lecturer Profile

> Model: `App\Models\Lecture`
> Bảng: `lectures`
> Extends: `Authenticatable` — Lecturer là Authenticatable model độc lập.
> Trait: `HasApiTokens`, `SoftDeletes`
> Liên kết: `lectures.user_id` → `users.id` (1-1)

### 15.1 Thông tin cơ bản & Định danh

| Field          | Type              | Ý nghĩa             | Giá trị mẫu            | Ghi chú mapping             |
| -------------- | ----------------- | ------------------- | ---------------------- | --------------------------- |
| `id`           | bigint (PK)       | ID nội bộ           | `1`                    | Primary key                 |
| `employee_id`  | string (unique)   | Mã nhân viên        | `"EMP001"`             | Bắt buộc, max 20 ký tự      |
| `user_id`      | bigint (FK)       | Tài khoản đăng nhập | `10`                   | FK → `users.id`             |
| `title`        | string (nullable) | Danh xưng           | `"Dr."`                | Mr., Mrs., Dr., Prof., v.v. |
| `first_name`   | string            | Tên                 | `"Van A"`              | Bắt buộc                    |
| `last_name`    | string            | Họ                  | `"Nguyen"`             | Bắt buộc                    |
| `email`        | string (unique)   | Email giảng viên    | `"a@swinburne.edu.vn"` |                             |
| `phone`        | string (nullable) | Số ĐT cố định       | `"02812345678"`        |                             |
| `mobile_phone` | string (nullable) | Số ĐT di động       | `"0901234567"`         |                             |
| `avatar_url`   | string (nullable) | URL ảnh đại diện    | `"https://..."`        | Tự cập nhật từ Google login |
| `campus_id`    | bigint (FK)       | Campus chính        | `1`                    | FK → `campuses.id`          |

### 15.2 Thông tin học thuật

| Field             | Type               | Ý nghĩa             | Giá trị mẫu                 | Ghi chú mapping              |
| ----------------- | ------------------ | ------------------- | --------------------------- | ---------------------------- |
| `department`      | string (nullable)  | Khoa/Bộ môn         | `"Computer Science"`        | Text, max 100                |
| `faculty`         | string (nullable)  | Phân khoa           | `"Engineering"`             | Text, max 100                |
| `specialization`  | string (nullable)  | Chuyên môn          | `"AI & Machine Learning"`   |                              |
| `expertise_areas` | json (nullable)    | Lĩnh vực chuyên gia | `["AI", "NLP"]`             | Cast: `array`                |
| `academic_rank`   | string             | Học hàm             | `"senior_lecturer"`         | **Bắt buộc** — xem bảng dưới |
| `highest_degree`  | string (nullable)  | Bằng cấp cao nhất   | `"PhD"`                     |                              |
| `degree_field`    | string (nullable)  | Ngành bằng cấp      | `"Computer Science"`        |                              |
| `alma_mater`      | string (nullable)  | Trường đào tạo      | `"MIT"`                     |                              |
| `graduation_year` | integer (nullable) | Năm tốt nghiệp      | `2018`                      |                              |
| `biography`       | text (nullable)    | Tiểu sử             | —                           |                              |
| `certifications`  | json (nullable)    | Chứng chỉ           | `["AWS", "PMP"]`            | Cast: `array`                |
| `languages`       | json (nullable)    | Ngôn ngữ            | `["Vietnamese", "English"]` | Cast: `array`                |

**Academic Rank Enum:**

| Giá trị               | Label               |
| --------------------- | ------------------- |
| `lecturer`            | Lecturer            |
| `senior_lecturer`     | Senior Lecturer     |
| `associate_professor` | Associate Professor |
| `professor`           | Professor           |
| `emeritus_professor`  | Emeritus Professor  |
| `visiting_lecturer`   | Visiting Lecturer   |
| `adjunct_professor`   | Adjunct Professor   |

### 15.3 Thông tin hợp đồng & Trạng thái (quan trọng cho auth)

| Field                 | Type                  | Ý nghĩa              | Giá trị mẫu    | Ghi chú mapping                    |
| --------------------- | --------------------- | -------------------- | -------------- | ---------------------------------- |
| `hire_date`           | date                  | Ngày tuyển dụng      | `"2020-09-01"` | Bắt buộc                           |
| `contract_start_date` | date (nullable)       | Bắt đầu hợp đồng     | `"2024-01-01"` |                                    |
| `contract_end_date`   | date (nullable)       | Kết thúc hợp đồng    | `"2026-12-31"` |                                    |
| `employment_type`     | string                | Loại hình tuyển dụng | `"full_time"`  | **Bắt buộc** — xem bảng dưới       |
| `employment_status`   | string                | Trạng thái việc làm  | `"active"`     | **Bắt buộc** — **ảnh hưởng login** |
| `is_active`           | boolean               | Trạng thái hoạt động | `true`         | **Ảnh hưởng login** — phải `true`  |
| `hourly_rate`         | decimal(2) (nullable) | Lương theo giờ       | `50.00`        |                                    |
| `salary`              | decimal(2) (nullable) | Lương cố định        | `15000000.00`  |                                    |

**Employment Type Enum:**

| Giá trị     | Label     |
| ----------- | --------- |
| `full_time` | Full Time |
| `part_time` | Part Time |
| `contract`  | Contract  |
| `visiting`  | Visiting  |
| `emeritus`  | Emeritus  |

**Employment Status Enum & ảnh hưởng login:**

| Giá trị           | Label           | Cho phép login? | Ghi chú            |
| ----------------- | --------------- | --------------- | ------------------ |
| `active`          | Active          | ✅ Có           |                    |
| `employed`        | Employed        | ✅ Có           | Alias login hợp lệ |
| `contract_active` | Contract Active | ✅ Có           | Alias login hợp lệ |
| `on_leave`        | On Leave        | ❌ Không        | Nghỉ phép          |
| `sabbatical`      | Sabbatical      | ❌ Không        | Nghỉ nghiên cứu    |
| `retired`         | Retired         | ❌ Không        | Nghỉ hưu           |
| `terminated`      | Terminated      | ❌ Không        | Bị sa thải         |
| `suspended`       | Suspended       | ❌ Không        | Bị đình chỉ        |

> **Điều kiện login Lecturer:** `is_active === true` **VÀ** `employment_status ∈ ['active', 'employed', 'contract_active']`

### 15.4 Lịch giảng dạy

| Field                         | Type               | Ý nghĩa                | Giá trị mẫu               | Ghi chú mapping                 |
| ----------------------------- | ------------------ | ---------------------- | ------------------------- | ------------------------------- |
| `preferred_teaching_days`     | json (nullable)    | Ngày dạy ưu tiên       | `["Monday", "Wednesday"]` | Cast: `array`                   |
| `preferred_start_time`        | time (nullable)    | Giờ bắt đầu ưu tiên    | `"08:00"`                 |                                 |
| `preferred_end_time`          | time (nullable)    | Giờ kết thúc ưu tiên   | `"17:00"`                 |                                 |
| `max_teaching_hours_per_week` | integer (nullable) | Số giờ dạy tối đa/tuần | `20`                      | Min 1, Max 80                   |
| `teaching_modalities`         | json (nullable)    | Hình thức giảng dạy    | `["in_person", "online"]` | `in_person`, `online`, `hybrid` |
| `can_teach_online`            | boolean            | Có thể dạy online?     | `true`                    |                                 |
| `is_available_for_assignment` | boolean            | Sẵn sàng phân công?    | `true`                    |                                 |

### 15.5 Liên hệ

| Field                            | Type              | Ý nghĩa           | Giá trị mẫu           | Ghi chú mapping |
| -------------------------------- | ----------------- | ----------------- | --------------------- | --------------- |
| `office_address`                 | string (nullable) | Địa chỉ văn phòng | `"Room 301, Block A"` |                 |
| `office_phone`                   | string (nullable) | SĐT văn phòng     | `"02812345"`          |                 |
| `emergency_contact_name`         | string (nullable) | Tên liên hệ KC    | `"Tran Thi B"`        |                 |
| `emergency_contact_phone`        | string (nullable) | SĐT liên hệ KC    | `"0908765432"`        |                 |
| `emergency_contact_relationship` | string (nullable) | Mối quan hệ       | `"Spouse"`            |                 |
| `notes`                          | text (nullable)   | Ghi chú chung     | —                     |                 |

### 15.6 Timestamps & Soft Delete

| Field            | Type                | Ý nghĩa            |
| ---------------- | ------------------- | ------------------ |
| `created_at`     | datetime            | Ngày tạo           |
| `updated_at`     | datetime            | Ngày cập nhật      |
| `deleted_at`     | datetime (nullable) | Soft delete        |
| `remember_token` | string (nullable)   | Laravel auth token |

### 15.7 Computed Attributes (không lưu DB)

| Attribute            | Ý nghĩa                   | Logic                           |
| -------------------- | ------------------------- | ------------------------------- |
| `full_name`          | Họ tên đầy đủ             | `first_name + ' ' + last_name`  |
| `display_name`       | Tên hiển thị có danh xưng | `title + ' ' + full_name`       |
| `years_of_service`   | Số năm công tác           | `hire_date → now()`             |
| `is_contract_active` | Hợp đồng còn hiệu lực?    | `start_date <= now <= end_date` |

---

## 16. Bảng `parents` — Parent Profile

> Model: `App\Models\ParentProfile`
> Bảng: `parents`
> Trait: `SoftDeletes`
> Liên kết: `parents.user_id` → `users.id` (1-1)
> **Lưu ý:** Token Sanctum được issue trên **User** model, KHÔNG phải trên ParentProfile.

| Field            | Type                | Ý nghĩa             | Giá trị mẫu      | Ghi chú mapping                                    |
| ---------------- | ------------------- | ------------------- | ---------------- | -------------------------------------------------- |
| `id`             | bigint (PK)         | ID nội bộ           | `1`              | Primary key                                        |
| `user_id`        | bigint (FK)         | Tài khoản đăng nhập | `20`             | FK → `users.id` — **bắt buộc**                     |
| `full_name`      | string              | Họ tên phụ huynh    | `"Nguyen Thi B"` |                                                    |
| `phone`          | string (nullable)   | Số điện thoại       | `"0909123456"`   |                                                    |
| `email_snapshot` | string (nullable)   | Snapshot email      | `"b@gmail.com"`  | Email tại thời điểm tạo, có thể khác `users.email` |
| `status`         | string              | Trạng thái          | `"active"`       | **Phải `active` để login**                         |
| `created_at`     | datetime            | Ngày tạo            | —                |                                                    |
| `updated_at`     | datetime            | Ngày cập nhật       | —                |                                                    |
| `deleted_at`     | datetime (nullable) | Soft delete         | —                |                                                    |

---

## 17. Bảng `parent_student` — Quan hệ phụ huynh–sinh viên

> Bảng pivot liên kết ParentProfile ↔ Student.
> Một phụ huynh có thể có nhiều con, một sinh viên có thể có nhiều phụ huynh.

| Field          | Type               | Ý nghĩa          | Giá trị mẫu | Ghi chú mapping                       |
| -------------- | ------------------ | ---------------- | ----------- | ------------------------------------- |
| `parent_id`    | bigint (FK)        | ID phụ huynh     | `1`         | FK → `parents.id`                     |
| `student_id`   | bigint (FK)        | ID sinh viên     | `42`        | FK → `students.id`                    |
| `relationship` | string (nullable)  | Mối quan hệ      | `"mother"`  | Mô tả: mother, father, guardian, v.v. |
| `is_primary`   | boolean (nullable) | Phụ huynh chính? | `true`      | Dùng để xác định liên hệ ưu tiên      |
| `access_level` | string (nullable)  | Mức độ truy cập  | —           | Phân quyền xem data của student       |
| `created_at`   | datetime           | Ngày tạo         | —           |                                       |
| `updated_at`   | datetime           | Ngày cập nhật    | —           |                                       |

---

## 18. Checklist mapping

Dùng bảng này để tracking tiến độ mapping data từ hệ thống cũ:

| #   | Bảng/Entity                  | Trạng thái | Ghi chú                                      |
| --- | ---------------------------- | ---------- | -------------------------------------------- |
| 1   | `users` (core fields)        | ⬜ Chưa    | name, email, password, phone, address        |
| 2   | `users.type`                 | ⬜ Chưa    | Map sang enum: staff/student/lecturer/parent |
| 3   | `users.status`               | ⬜ Chưa    | Map sang 8 trạng thái                        |
| 4   | `users` (OAuth fields)       | ⬜ Chưa    | oauth_provider, oauth_provider_id            |
| 5   | `roles`                      | ⬜ Chưa    | name, code                                   |
| 6   | `permissions`                | ⬜ Chưa    | name, code, module, parent_id                |
| 7   | `campus_user_roles`          | ⬜ Chưa    | user_id, campus_id, role_id                  |
| 8   | `role_permissions`           | ⬜ Chưa    | role_id, permission_id                       |
| 9   | `students` (full profile)    | ⬜ Chưa    | 55+ fields — xem mục 14                      |
| 10  | `students.status`            | ⬜ Chưa    | Map sang 11 trạng thái                       |
| 11  | `lectures` (full profile)    | ⬜ Chưa    | 45+ fields — xem mục 15                      |
| 12  | `lectures.employment_status` | ⬜ Chưa    | Map sang 8 trạng thái                        |
| 13  | `parents` (profile)          | ⬜ Chưa    | user_id, full_name, phone, status            |
| 14  | `parent_student` (pivot)     | ⬜ Chưa    | relationship, is_primary, access_level       |

---

## Bảng phụ trợ liên quan

Không nằm trong Identity module nhưng liên quan trực tiếp đến auth flow:

| Bảng                     | Ý nghĩa              | Liên kết Identity                               |
| ------------------------ | -------------------- | ----------------------------------------------- |
| `students`               | Profile sinh viên    | `students.user_id` → `users.id`                 |
| `lectures`               | Profile giảng viên   | `lectures.user_id` → `users.id`                 |
| `parent_profiles`        | Profile phụ huynh    | `parent_profiles.user_id` → `users.id`          |
| `campuses`               | Danh sách campus     | Tham chiếu từ `campus_user_roles.campus_id`     |
| `departments`            | Phòng ban            | `users.department_id` → `departments.id`        |
| `department_memberships` | Thành viên phòng ban | `department_memberships.user_id` → `users.id`   |
| `academic_holds`         | Giữ học sinh viên    | Ảnh hưởng login Student nếu `hold_category=all` |

---

## Rate Limiting

| Endpoint       | Key format         | Max attempts           | Lockout            | Áp dụng cho                |
| -------------- | ------------------ | ---------------------- | ------------------ | -------------------------- |
| Student login  | `login:{ip}`       | 5 lần                  | 900 giây (15 phút) | Email/password + Google    |
| Parent login   | `login:{ip}`       | 5 lần                  | 900 giây (15 phút) | Email/password + Google    |
| Lecturer login | —                  | Không rate limit riêng | —                  | Dùng exception handling    |
| Staff login    | Via `LoginRequest` | Mặc định Laravel       | —                  | `ensureIsNotRateLimited()` |
