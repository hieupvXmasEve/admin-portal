**DDL đầy đủ – production-ready**, bám sát **kiến trúc Identity bạn đang dùng (users → profiles)** và **dễ migrate từ trạng thái hiện tại**.

> Giả định:
>
> * MySQL / MariaDB
> * `users.id`, `students.id` là `bigint unsigned`
> * charset `utf8mb4`, engine `InnoDB`

---

## 1️⃣ Bảng `parents` (Parent profile)

```sql
CREATE TABLE parents (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id          BIGINT UNSIGNED NOT NULL COMMENT 'FK to users.id (identity)',
    full_name        VARCHAR(255) NOT NULL,
    phone            VARCHAR(50) NULL,
    email_snapshot   VARCHAR(255) NULL COMMENT 'Email at creation time, NOT for auth',
    relationship     ENUM('father', 'mother', 'guardian', 'other') DEFAULT 'guardian',

    status           ENUM('active', 'inactive') DEFAULT 'active',

    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,

    CONSTRAINT parents_user_id_unique UNIQUE (user_id),

    CONSTRAINT parents_user_id_fk
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Parent profile, identity via users table';
```

### 🔎 Giải thích nhanh

* `user_id UNIQUE` → **1 user = 1 parent profile**
* `email_snapshot` → chỉ để hiển thị / audit
* **KHÔNG có password / token**
* `ON DELETE CASCADE` → xoá user thì xoá parent profile (đúng logic)

---

## 2️⃣ Bảng `parent_student` (Liên kết parent ↔ student)

```sql
CREATE TABLE parent_student (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    parent_id        BIGINT UNSIGNED NOT NULL,
    student_id       BIGINT UNSIGNED NOT NULL,

    relationship     ENUM('father', 'mother', 'guardian', 'other') DEFAULT 'guardian',
    is_primary       TINYINT(1) DEFAULT 0 COMMENT 'Primary student for default context',
    access_level     ENUM('read_only') DEFAULT 'read_only',

    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,

    CONSTRAINT parent_student_unique UNIQUE (parent_id, student_id),

    CONSTRAINT parent_student_parent_fk
        FOREIGN KEY (parent_id)
        REFERENCES parents (id)
        ON DELETE CASCADE,

    CONSTRAINT parent_student_student_fk
        FOREIGN KEY (student_id)
        REFERENCES students (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Mapping between parents and students (read-only access)';
```

### 🔎 Giải thích nhanh

* `UNIQUE(parent_id, student_id)` → không link trùng
* `is_primary`:

  * dùng để auto-select student khi parent login
  * **chỉ 1 record nên = 1 (enforce bằng code)**
* `access_level`:

  * hiện tại chỉ `read_only`
  * future-proof (nếu sau này cho upload giấy tờ, ký form, …)

---

## 3️⃣ Index khuyến nghị (performance)

```sql
CREATE INDEX idx_parent_student_parent
    ON parent_student (parent_id);

CREATE INDEX idx_parent_student_student
    ON parent_student (student_id);
```

---

## 4️⃣ Quan hệ Eloquent (để dev dùng đúng)

### `User.php`

```php
public function parent()
{
    return $this->hasOne(ParentProfile::class, 'user_id');
}
```

### `ParentProfile.php`

```php
class ParentProfile extends Model
{
    protected $table = 'parents';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'parent_student')
            ->withPivot(['relationship', 'is_primary', 'access_level'])
            ->withTimestamps();
    }
}
```

### `Student.php`

```php
public function parents()
{
    return $this->belongsToMany(ParentProfile::class, 'parent_student')
        ->withPivot(['relationship', 'is_primary', 'access_level'])
        ->withTimestamps();
}
```

---

## 5️⃣ Rule nghiệp vụ (rất nên ghi vào spec)

```text
RULE:
- Parent login bằng users
- Parent KHÔNG có role RBAC
- Parent chỉ truy cập StudentContext (read-only)
- Mọi write action đều bị block ở policy/middleware
```

---

## 6️⃣ Migration từ trạng thái hiện tại (role = parent)

Flow an toàn:

1. Tạo bảng `parents`, `parent_student`
2. Map:

   * user(role=parent) → parents
   * gán student cũ → parent_student
3. Remove role `parent`
4. Refactor code → StudentContext
5. Block write actions

---

## ✅ Chốt

| Thành phần      | Trạng thái       |
| --------------- | ---------------- |
| Identity        | `users`          |
| Parent profile  | `parents`        |
| Student profile | `students`       |
| Quan hệ         | `parent_student` |
| Permission      | read-only        |
