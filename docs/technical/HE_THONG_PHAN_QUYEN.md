# Hệ Thống Phân Quyền Trong Swinburne Education Management System

## 1. Tổng Quan Hệ Thống

Swinburne Education Management System sử dụng một hệ thống phân quyền tùy chỉnh (custom) dựa trên **Laravel Gates** và **Session**, thay vì sử dụng các package bên thứ ba như `spatie/laravel-permission`. Hệ thống này được thiết kế để đơn giản, hiệu quả và tích hợp chặt chẽ với các tính năng có sẵn của Laravel.

### 1.1. Ưu điểm của hệ thống

- **Đơn giản**: Không phụ thuộc vào các package bên ngoài
- **Hiệu suất cao**: Sử dụng session để lưu trữ quyền, giảm thiểu truy vấn database
- **Linh hoạt**: Dễ dàng mở rộng và tùy chỉnh theo yêu cầu
- **Tích hợp tốt**: Hoạt động liền mạch với Laravel và Inertia.js

## 2. Cấu Trúc Hệ Thống

### 2.1. Thành phần chính

1. **File cấu hình**: `config/permission.php` - Định nghĩa tất cả các quyền trong hệ thống
2. **Service Provider**: `app/Providers/PermissionServiceProvider.php` - Đăng ký các quyền dưới dạng Laravel Gates
3. **Helper**: `app/Helpers/RoutePermissionHelper.php` - Cung cấp các hàm tiện ích để làm việc với quyền
4. **Middleware**: `app/Http/Middleware/CheckPermission.php` - Kiểm tra quyền truy cập cho các route

### 2.2. Cách hoạt động

1. **Định nghĩa quyền**: Tất cả quyền được định nghĩa trong `config/permission.php` theo cấu trúc module và hành động
2. **Đăng ký quyền**: Khi ứng dụng khởi động, `PermissionServiceProvider` đọc các quyền từ config và đăng ký chúng dưới dạng Laravel Gates
3. **Lưu trữ quyền**: Khi người dùng đăng nhập, hệ thống truy vấn quyền của họ từ database và lưu vào session
4. **Kiểm tra quyền**: Các controller, middleware và view sử dụng Laravel Gates hoặc các helper function để kiểm tra quyền

## 3. Quy Trình Thêm Quyền Mới

### 3.1. Bước 1: Định nghĩa quyền trong config

Mở file `config/permission.php` và thêm quyền mới vào mảng `access`:

```php
'access' => [
    // Module mới
    'your_module' => [
        'view_your_module' => 'view_your_module',
        'create_your_module' => 'create_your_module',
        'edit_your_module' => 'edit_your_module',
        'delete_your_module' => 'delete_your_module',
    ],
    // ...
],
```

Đồng thời, cập nhật mảng `modules` để phân nhóm module mới:

```php
'modules' => [
    'your_module_group' => ['your_module', /* các module khác */],
    // ...
],
```

### 3.2. Bước 2: Cập nhật database

Thêm các quyền mới vào bảng `permissions` trong database:

```sql
INSERT INTO permissions (name, guard_name, created_at, updated_at)
VALUES 
('view_your_module', 'web', NOW(), NOW()),
('create_your_module', 'web', NOW(), NOW()),
('edit_your_module', 'web', NOW(), NOW()),
('delete_your_module', 'web', NOW(), NOW());
```

Hoặc sử dụng command để tự động tạo quyền:

```bash
php artisan app:create-import-permissions
```

### 3.3. Bước 3: Gán quyền cho vai trò (Role)

Gán quyền mới cho các vai trò thích hợp thông qua giao diện quản lý vai trò hoặc sử dụng seeder:

```php
// Trong một seeder
$role = Role::where('name', 'admin')->first();
$permissions = Permission::whereIn('name', [
    'view_your_module',
    'create_your_module',
    'edit_your_module',
    'delete_your_module'
])->get();

$role->permissions()->attach($permissions->pluck('id')->toArray());
```

## 4. Cách Sử Dụng Quyền

### 4.1. Trong Controller

```php
public function index()
{
    // Kiểm tra quyền
    $this->authorize('view_your_module');
    
    // Hoặc sử dụng Gate facade
    if (Gate::denies('view_your_module')) {
        abort(403);
    }
    
    // Code tiếp theo...
}
```

### 4.2. Trong Form Request

```php
public function authorize()
{
    return Gate::allows('create_your_module');
}
```

### 4.3. Trong Route (sử dụng Middleware)

```php
Route::get('/your-module', [YourModuleController::class, 'index'])
    ->middleware('permission:view_your_module')
    ->name('your_module.index');
```

### 4.4. Trong Blade Template

```blade
@canPermission('your_module', 'view')
    <!-- Hiển thị nội dung khi có quyền -->
@endcanPermission

<!-- Hoặc -->

@hasPermission('view_your_module')
    <!-- Hiển thị nội dung khi có quyền -->
@endhasPermission
```

### 4.5. Trong Vue.js / Inertia.js

```vue
<script setup>
import { usePage } from '@inertiajs/vue3';

// Kiểm tra quyền
const hasPermission = (permission) => {
  return usePage().props.auth.permissions.includes(permission);
};
</script>

<template>
  <div v-if="hasPermission('view_your_module')">
    <!-- Hiển thị nội dung khi có quyền -->
  </div>
</template>
```

## 5. Ví Dụ Thực Tế: Thêm Module "Tuyển Sinh"

### 5.1. Định nghĩa quyền trong config

```php
// Trong config/permission.php
'access' => [
    // ...
    'admissions' => [
        'view_admission' => 'view_admission',
        'create_admission' => 'create_admission',
        'edit_admission' => 'edit_admission',
        'delete_admission' => 'delete_admission',
        'approve_admission' => 'approve_admission',
    ],
],

'modules' => [
    // ...
    'student_management' => ['students', 'admissions'],
],
```

### 5.2. Tạo migration để thêm quyền mới

```php
// Trong một migration file
public function up()
{
    $permissions = [
        'view_admission',
        'create_admission',
        'edit_admission',
        'delete_admission',
        'approve_admission',
    ];
    
    foreach ($permissions as $permission) {
        DB::table('permissions')->insert([
            'name' => $permission,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

### 5.3. Sử dụng trong Controller

```php
namespace App\Http\Controllers;

use App\Models\Admission;
use Illuminate\Support\Facades\Gate;

class AdmissionController extends Controller
{
    public function index()
    {
        $this->authorize('view_admission');
        
        $admissions = Admission::paginate(15);
        
        return inertia('Admissions/Index', [
            'admissions' => $admissions,
        ]);
    }
    
    public function approve($id)
    {
        $this->authorize('approve_admission');
        
        $admission = Admission::findOrFail($id);
        $admission->status = 'approved';
        $admission->save();
        
        return redirect()->route('admissions.index')
            ->with('success', 'Đơn tuyển sinh đã được phê duyệt');
    }
}
```

### 5.4. Sử dụng trong Vue Component

```vue
<script setup>
import { usePage } from '@inertiajs/vue3';

const hasPermission = (permission) => {
  return usePage().props.auth.permissions.includes(permission);
};
</script>

<template>
  <div>
    <h1>Quản Lý Tuyển Sinh</h1>
    
    <Button v-if="hasPermission('create_admission')" :href="route('admissions.create')">
      Thêm Đơn Tuyển Sinh
    </Button>
    
    <DataTable :data="admissions.data" :columns="columns">
      <template #cell-actions="{ row }">
        <div class="flex items-center gap-2">
          <Button v-if="hasPermission('edit_admission')" variant="ghost" size="sm" 
                  :href="route('admissions.edit', row.original.id)">
            <Edit class="h-4 w-4" />
          </Button>
          
          <Button v-if="hasPermission('approve_admission')" variant="ghost" size="sm" 
                  @click="approveAdmission(row.original.id)">
            <CheckCircle class="h-4 w-4" />
          </Button>
          
          <Button v-if="hasPermission('delete_admission')" variant="ghost" size="sm" 
                  @click="deleteAdmission(row.original.id)">
            <Trash2 class="h-4 w-4" />
          </Button>
        </div>
      </template>
    </DataTable>
  </div>
</template>
```

## 6. Lưu Ý Quan Trọng

1. **Làm mới Session**: Khi thay đổi quyền của người dùng, cần làm mới session để cập nhật danh sách quyền
2. **Kiểm tra nhiều quyền**: Sử dụng `Gate::any()` hoặc `Gate::all()` để kiểm tra nhiều quyền cùng lúc
3. **Quyền mặc định**: Xem xét việc cung cấp một số quyền mặc định cho tất cả người dùng đã xác thực
4. **Cache quyền**: Với hệ thống lớn, cân nhắc việc cache danh sách quyền để tăng hiệu suất

## 7. Kết Luận

Hệ thống phân quyền tùy chỉnh của Swinburne Education Management System cung cấp một giải pháp đơn giản nhưng mạnh mẽ để quản lý quyền truy cập. Bằng cách sử dụng các tính năng có sẵn của Laravel và lưu trữ quyền trong session, hệ thống đạt được hiệu suất cao mà không cần phụ thuộc vào các package bên ngoài.

Khi thêm các module mới vào hệ thống, việc tuân theo quy trình thêm quyền như đã mô tả sẽ đảm bảo tính nhất quán và bảo mật cho ứng dụng. 
