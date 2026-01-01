# AUTHORIZATION RULES

## 1. Mục tiêu

Tài liệu này định nghĩa **cách sử dụng Gate và Policy** trong hệ thống nhằm:

- Phân tách rõ **permission-level** và **data-level**
- Tránh nhầm lẫn giữa route authorization và object authorization
- Đảm bảo code **dễ maintain – dễ mở rộng – đúng kiến trúc**

## 2. Khái niệm cốt lõi

### 2.1. Gate là gì?

**Gate dùng để kiểm tra quyền (permission) của user đã login**, nhằm trả lời câu hỏi:

> _User này có được phép truy cập / thực hiện hành động này không?_

Đặc điểm:

- Không làm việc với object cụ thể
- Không phụ thuộc dữ liệu record
- Thường map với:
    - route
    - feature
    - menu
    - action chung

➡️ **Gate = permission-level authorization**

### 2.2. Policy là gì?

**Policy dùng để kiểm tra user có được thực hiện hành động đó với một object cụ thể hay không**, nhằm trả lời câu hỏi:

> _User này có được làm việc này với dữ liệu này không?_

Đặc điểm:

- Luôn nhận model (object)
- Phụ thuộc:
    - ownership
    - campus
    - trạng thái
    - business rule

➡️ **Policy = data-level authorization**

## 3. Quy tắc phân biệt nhanh (QUAN TRỌNG)

### Rule 1 – Có object hay không?

| Câu hỏi           | Dùng   |
| ----------------- | ------ |
| Không cần object  | Gate   |
| Cần object cụ thể | Policy |

### Rule 2 – Cùng user, object này được, object khác không?

- Có → **Policy**
- Không → **Gate**

## 4. Gate – Quy tắc sử dụng

### 4.1. Gate dùng khi nào?

Gate **chỉ được dùng cho**:

- Route không có `{id}`
- Menu / sidebar
- Feature toggle
- Action chung (create, view list, export, access module)

Ví dụ:

```php
Gate::define('event.view', fn (User $user) =>
    app(PermissionService::class)->has('event.view')
);
```

### 4.2. Gate KHÔNG được làm

❌ Không query DB
❌ Không dùng `session()`
❌ Không nhận model (Event, Student, …)
❌ Không xử lý business rule

### 4.3. Gate dùng ở đâu?

✅ Route middleware:

```php
Route::middleware('can:event.view')->group(function () {
    Route::get('/admin/events', ...);
});
```

✅ Menu / Inertia permission:

```js
if (permissions.includes('event.view')) { ... }
```

❌ Controller logic phức tạp

## 5. Policy – Quy tắc sử dụng

### 5.1. Policy dùng khi nào?

Policy **bắt buộc dùng khi**:

- Route có `{model}`
- Cần kiểm tra ownership
- Cần kiểm tra campus / scope
- Có rule theo trạng thái dữ liệu

Ví dụ:

```php
class EventPolicy
{
    public function update(User $user, Event $event): bool
    {
        if (! Gate::allows('event.update')) {
            return false;
        }

        return $event->created_by === $user->id
            && $event->status === 'draft';
    }
}
```

### 5.2. Policy được phép làm

✅ Gọi Gate
✅ Check business rule
✅ Check data state
✅ Check relationship

### 5.3. Policy KHÔNG được làm

❌ Resolve permission context
❌ Check route permission
❌ Truy cập session

## 6. Quy tắc áp dụng cho Route & Controller

### 6.1. Route

| Loại route      | Authorization |
| --------------- | ------------- |
| Không có `{id}` | Gate          |
| Có `{id}`       | Policy        |

### 6.2. Controller

- Controller **KHÔNG dùng Gate**
- Controller **CHỈ dùng Policy**

Ví dụ:

```php
public function update(Event $event)
{
    $this->authorize('update', $event);

    // business logic
}
```

## 7. Flow chuẩn cho 1 request

```
User login
  ↓
Resolve permission context (campus, role)
  ↓
Gate → có được vào route không?
  ↓
Controller
  ↓
Policy → có được làm với object này không?
```

## 8. Mapping thực tế trong hệ thống

### Event Module

**Gate**

- `event.view`
- `event.create`
- `event.publish`

**Policy**

- `EventPolicy::view`
- `EventPolicy::update`
- `EventPolicy::publish`

### Academic Module

**Gate**

- `academic.view`
- `academic.export`

**Policy**

- `AcademicRecordPolicy::view`

## 9. Rule bắt buộc khi review code

> ❗ PR sẽ bị reject nếu:
>
> - Gate nhận model
> - Controller gọi Gate thay vì Policy
> - Policy chỉ check permission mà không check data
> - Route `{id}` không có Policy

## 10. Tóm tắt 3 dòng (ghi nhớ)

> 🔐 Gate bảo vệ **cửa**
> 📦 Policy bảo vệ **dữ liệu**
> 🚫 Không trộn vai trò

### ✅ Trạng thái: **READY TO USE**

Nếu bạn muốn, bước kế tiếp mình có thể:

- 🔧 Viết **Module Skeleton template** (Gate + Policy + Route sẵn)
- 🔍 Viết **PHPStan rule** để enforce các rule trên
- 🔁 Refactor 1 module thật của bạn (Event / Form / Academic)

Chỉ cần nói: **“chọn 2”** hoặc **“chọn 3”** là mình làm tiếp.
👍 Ok, mình làm **(2) – Module Skeleton template (Gate + Policy + Route sẵn)**
Đây là **khung chuẩn để bạn copy cho MỌI module** trong hệ thống (Event, Academic, Form, Research…), dùng được cho **modular monolith + Inertia + API**.

# 🧱 Module Skeleton – Authorization-ready

## 1. Cấu trúc thư mục CHUẨN cho 1 module

Ví dụ module **Event** (module khác copy y chang):

```
Modules/Event/
├─ Domain/
│  ├─ Models/
│  │  └─ Event.php
│  └─ Policies/
│     └─ EventPolicy.php
│
├─ Application/
│  └─ Services/
│     └─ EventService.php
│
├─ Infrastructure/
│  └─ Providers/
│     └─ EventServiceProvider.php
│
├─ Http/
│  ├─ Controllers/
│  │  └─ Admin/
│  │     └─ EventController.php
│  └─ Routes/
│     └─ admin.php
│
└─ module.json   (optional)
```

> 🔑 **Gate không nằm trong module**, chỉ có **Policy nằm trong module**

## 2. Bước 1 – Khai báo permission cho module (GLOBAL)

### `config/permission.php`

```php
return [
    'event' => [
        'event.view',
        'event.create',
        'event.update',
        'event.publish',
        'event.delete',
    ],
];
```

👉 Đây là **permission-level**, dùng cho:

- route
- menu
- feature toggle

## 3. Bước 2 – Đăng ký Gate (GLOBAL, dùng chung)

> Chỉ làm **1 lần cho toàn hệ thống**

```php
foreach (config('permission.access') as $module => $permissions) {
    foreach ($permissions as $permission) {
        Gate::define($permission, fn (User $user) =>
            app(PermissionService::class)->has($permission)
        );
    }
}
```

⚠️ Rule:

- Gate **chỉ gọi service**
- Không model
- Không session
- Không DB

## 4. Bước 3 – Route của module (chỉ dùng Gate)

### `Modules/Event/Http/Routes/admin.php`

```php
Route::middleware(['auth', 'can:event.view'])->group(function () {

    Route::get('/events', [EventController::class, 'index']);

    Route::middleware('can:event.create')->group(function () {
        Route::post('/events', [EventController::class, 'store']);
    });

});
```

👉 Route **KHÔNG gọi Policy**

## 5. Bước 4 – Policy của module (DATA-LEVEL)

### `Modules/Event/Domain/Policies/EventPolicy.php`

```php
class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return Gate::allows('event.view')
            && $event->campus_id === $user->current_campus_id;
    }

    public function update(User $user, Event $event): bool
    {
        if (! Gate::allows('event.update')) {
            return false;
        }

        if ($event->status !== 'draft') {
            return false;
        }

        return $event->created_by === $user->id;
    }

    public function publish(User $user, Event $event): bool
    {
        return Gate::allows('event.publish')
            && $event->status === 'draft';
    }
}
```

👉 **Policy = Gate + business rule + data rule**

## 6. Bước 5 – Controller (CHỈ dùng Policy)

### `EventController.php`

```php
class EventController extends Controller
{
    public function index()
    {
        // Gate đã check ở route
        return Inertia::render('Event/Index');
    }

    public function show(Event $event)
    {
        $this->authorize('view', $event);

        return Inertia::render('Event/Show', [
            'event' => $event,
        ]);
    }

    public function update(Event $event)
    {
        $this->authorize('update', $event);

        // update logic
    }

    public function publish(Event $event)
    {
        $this->authorize('publish', $event);

        // publish logic
    }
}
```

🚫 Controller **KHÔNG gọi Gate**

## 7. Áp dụng cho module KHÁC (copy tư duy)

### Academic Module

**Gate**

- `academic.view`
- `academic.export`

**Policy**

```php
AcademicRecordPolicy::view(User, AcademicRecord)
```

Check:

- campus
- staff role
- student scope

### Form / Survey Module

**Gate**

- `form.manage`
- `form.view_results`

**Policy**

```php
FormResponsePolicy::view(User, FormResponse)
```

Check:

- department
- scope
- ownership

## 8. Checklist khi tạo module mới (rất quan trọng)

Khi tạo module mới, **bắt buộc làm đủ 6 bước**:

1. Khai báo permission trong `config/permission.php`
2. Route không `{id}` → dùng Gate
3. Route có `{id}` → **KHÔNG dùng Gate**
4. Tạo Policy cho model chính
5. Controller **chỉ gọi `$this->authorize()`**
6. Policy **được gọi Gate**

## 9. Anti-pattern (thấy là sai ngay)

❌ Gate nhận model
❌ Route `{id}` dùng `can:` middleware
❌ Controller gọi `Gate::allows()`
❌ Policy chỉ check permission, không check data

## 10. Câu chốt

> **Gate bảo vệ route – Policy bảo vệ record – Module chỉ chứa Policy**
