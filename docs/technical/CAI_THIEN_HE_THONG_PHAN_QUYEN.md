# Kế Hoạch Cải Thiện Hệ Thống Phân Quyền Theo Campus

Dựa trên cấu trúc database hiện tại và hệ thống phân quyền đã triển khai, tài liệu này mô tả chi tiết các bước cần thực hiện để tối ưu hóa hệ thống phân quyền của Swinburne Education Management System.

## Phân Tích Cấu Trúc Hiện Tại

Hiện tại, hệ thống đang sử dụng:
- Bảng `users`: Lưu thông tin người dùng
- Bảng `roles`: Định nghĩa các vai trò trong hệ thống
- Bảng `permissions`: Lưu danh sách các quyền
- Bảng `role_permissions`: Liên kết vai trò với quyền (many-to-many)
- Bảng `campus_user_roles`: Liên kết người dùng với vai trò tại một campus cụ thể

Điểm đặc biệt: Hệ thống phân quyền theo campus, nghĩa là một người dùng có thể có các vai trò khác nhau ở các campus khác nhau.

## Kế Hoạch Cải Thiện

### 1. Caching Quyền Người Dùng

#### Bước 1: Tạo Service Quản Lý Quyền

```php
<?php
// app/Services/PermissionService.php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Lấy danh sách quyền của người dùng tại một campus cụ thể
     */
    public function getUserPermissions(User $user, int $campusId = null)
    {
        $cacheKey = "user_permissions_{$user->id}_campus_" . ($campusId ?? 'all');
        
        return Cache::remember($cacheKey, now()->addDay(), function () use ($user, $campusId) {
            $query = DB::table('permissions')
                ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->join('campus_user_roles', 'role_permissions.role_id', '=', 'campus_user_roles.role_id')
                ->where('campus_user_roles.user_id', $user->id);
            
            if ($campusId) {
                $query->where('campus_user_roles.campus_id', $campusId);
            }
            
            return $query->select('permissions.name')
                ->distinct()
                ->pluck('name')
                ->toArray();
        });
    }
    
    /**
     * Xóa cache quyền của người dùng
     */
    public function clearUserPermissionsCache(User $user)
    {
        Cache::forget("user_permissions_{$user->id}_campus_all");
        
        // Xóa cache cho từng campus
        $campusIds = DB::table('campus_user_roles')
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('campus_id');
            
        foreach ($campusIds as $campusId) {
            Cache::forget("user_permissions_{$user->id}_campus_{$campusId}");
        }
    }
}
```

#### Bước 2: Cập Nhật PermissionServiceProvider

```php
<?php
// app/Providers/PermissionServiceProvider.php

namespace App\Providers;

use App\Services\PermissionService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PermissionService::class, function ($app) {
            return new PermissionService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPermissionGates();
        $this->registerBladeDirectives();
    }

    /**
     * Register all permission gates from configuration
     */
    private function registerPermissionGates(): void
    {
        $permissions = config('permission.access', []);

        foreach ($permissions as $module => $modulePermissions) {
            foreach ($modulePermissions as $permission) {
                Gate::define($permission, function ($user) use ($permission) {
                    // Lấy campus_id hiện tại từ session
                    $currentCampusId = session('current_campus_id');
                    
                    // Sử dụng service để lấy quyền từ cache
                    $permissionService = app(PermissionService::class);
                    $permissions = $permissionService->getUserPermissions($user, $currentCampusId);
                    
                    return in_array($permission, $permissions);
                });
            }
        }
    }

    // ... Các phương thức khác giữ nguyên
}
```

#### Bước 3: Cập Nhật Middleware Xác Thực

```php
<?php
// app/Http/Middleware/HandleInertiaRequests.php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    // ... Các phương thức khác giữ nguyên
    
    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $currentCampusId = session('current_campus_id');
        
        return [
            // ... Các props khác
            'auth' => function () use ($request, $user, $currentCampusId) {
                if (!$user) {
                    return null;
                }

                $permissionService = app(PermissionService::class);
                
                return [
                    'user' => $user->only('id', 'name', 'email'),
                    'permissions' => $permissionService->getUserPermissions($user, $currentCampusId),
                    'current_campus_id' => $currentCampusId,
                ];
            },
        ];
    }
}
```

### 2. Lazy Loading Quyền

#### Bước 1: Tạo Trait LazyPermissions

```php
<?php
// app/Traits/LazyPermissions.php

namespace App\Traits;

use App\Services\PermissionService;
use Illuminate\Support\Facades\Cache;

trait LazyPermissions
{
    protected $loadedPermissions = [];
    protected $permissionService = null;

    /**
     * Kiểm tra quyền theo cách lazy loading
     */
    public function hasPermission(string $permission, int $campusId = null)
    {
        $campusId = $campusId ?? session('current_campus_id');
        $cacheKey = "campus_{$campusId}";
        
        if (!isset($this->loadedPermissions[$cacheKey])) {
            if (!$this->permissionService) {
                $this->permissionService = app(PermissionService::class);
            }
            
            $this->loadedPermissions[$cacheKey] = $this->permissionService->getUserPermissions($this, $campusId);
        }
        
        return in_array($permission, $this->loadedPermissions[$cacheKey]);
    }
    
    /**
     * Kiểm tra nhiều quyền cùng lúc (OR logic)
     */
    public function hasAnyPermission(array $permissions, int $campusId = null)
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $campusId)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Kiểm tra nhiều quyền cùng lúc (AND logic)
     */
    public function hasAllPermissions(array $permissions, int $campusId = null)
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission, $campusId)) {
                return false;
            }
        }
        
        return true;
    }
}
```

#### Bước 2: Cập Nhật Model User

```php
<?php
// app/Models/User.php

namespace App\Models;

use App\Traits\LazyPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, LazyPermissions;
    
    // ... Các thuộc tính và phương thức khác
    
    /**
     * Lấy danh sách vai trò của người dùng tại một campus
     */
    public function rolesAtCampus(int $campusId)
    {
        return $this->belongsToMany(Role::class, 'campus_user_roles')
            ->where('campus_user_roles.campus_id', $campusId);
    }
}
```

### 3. Tối Ưu Hóa Kiểm Tra Quyền

#### Bước 1: Tạo Helper Function

```php
<?php
// app/Helpers/PermissionHelper.php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PermissionHelper
{
    /**
     * Kiểm tra quyền với campus hiện tại
     */
    public static function can($permission)
    {
        return Gate::allows($permission);
    }
    
    /**
     * Kiểm tra quyền với campus cụ thể
     */
    public static function canAtCampus($permission, $campusId)
    {
        $user = Auth::user();
        if (!$user) return false;
        
        return $user->hasPermission($permission, $campusId);
    }
    
    /**
     * Kiểm tra nhiều quyền (OR logic)
     */
    public static function canAny(array $permissions)
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Kiểm tra nhiều quyền (AND logic)
     */
    public static function canAll(array $permissions)
    {
        foreach ($permissions as $permission) {
            if (!self::can($permission)) {
                return false;
            }
        }
        
        return true;
    }
}
```

#### Bước 2: Đăng Ký Helper Function Toàn Cục

```php
<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Helpers\PermissionHelper;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Đăng ký helper function toàn cục
        if (!function_exists('can_permission')) {
            function can_permission($permission) {
                return PermissionHelper::can($permission);
            }
        }
        
        if (!function_exists('can_any_permission')) {
            function can_any_permission(array $permissions) {
                return PermissionHelper::canAny($permissions);
            }
        }
    }
}
```

### 4. Middleware Kiểm Tra Quyền Tùy Chỉnh

```php
<?php
// app/Http/Middleware/CheckPermissions.php

namespace App\Http\Middleware;

use App\Helpers\PermissionHelper;
use Closure;
use Illuminate\Http\Request;

class CheckPermissions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // Kiểm tra logic OR (mặc định)
        $logicType = 'any';
        
        // Nếu tham số đầu tiên là 'all', sử dụng logic AND
        if (isset($permissions[0]) && $permissions[0] === 'all') {
            $logicType = 'all';
            array_shift($permissions);
        }
        
        $hasPermission = $logicType === 'all' 
            ? PermissionHelper::canAll($permissions)
            : PermissionHelper::canAny($permissions);
            
        if (!$hasPermission) {
            abort(403, 'Unauthorized action.');
        }
        
        return $next($request);
    }
}
```

#### Đăng Ký Middleware

```php
<?php
// app/Http/Kernel.php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // ...
    
    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        // ...
        'permissions' => \App\Http\Middleware\CheckPermissions::class,
    ];
}
```

#### Sử Dụng Middleware

```php
// routes/web.php

// Kiểm tra một quyền
Route::get('/students', [StudentController::class, 'index'])
    ->middleware('permissions:view_student')
    ->name('students.index');
    
// Kiểm tra nhiều quyền (OR logic)
Route::post('/students', [StudentController::class, 'store'])
    ->middleware('permissions:create_student,edit_student')
    ->name('students.store');
    
// Kiểm tra nhiều quyền (AND logic)
Route::delete('/students/{id}', [StudentController::class, 'destroy'])
    ->middleware('permissions:all,view_student,delete_student')
    ->name('students.destroy');
```

### 5. Tích Hợp Frontend Tốt Hơn

#### Bước 1: Tạo Composable Vue.js

```typescript
// resources/js/composables/usePermissions.ts

import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
  const page = usePage();
  
  const permissions = computed(() => page.props.auth?.permissions || []);
  const currentCampusId = computed(() => page.props.auth?.current_campus_id);
  
  // Kiểm tra một quyền
  const can = (permission: string) => {
    return permissions.value.includes(permission);
  };
  
  // Kiểm tra nhiều quyền (OR logic)
  const canAny = (permissionList: string[]) => {
    return permissionList.some(permission => can(permission));
  };
  
  // Kiểm tra nhiều quyền (AND logic)
  const canAll = (permissionList: string[]) => {
    return permissionList.every(permission => can(permission));
  };
  
  return {
    permissions,
    currentCampusId,
    can,
    canAny,
    canAll
  };
}
```

#### Bước 2: Tạo Directive Vue.js

```typescript
// resources/js/directives/permission.ts

import { DirectiveBinding } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

export const vCan = {
  mounted(el: HTMLElement, binding: DirectiveBinding) {
    const { can } = usePermissions();
    
    if (!can(binding.value)) {
      el.parentNode?.removeChild(el);
    }
  }
};

export const vCanAny = {
  mounted(el: HTMLElement, binding: DirectiveBinding) {
    const { canAny } = usePermissions();
    const permissions = Array.isArray(binding.value) ? binding.value : [binding.value];
    
    if (!canAny(permissions)) {
      el.parentNode?.removeChild(el);
    }
  }
};
```

#### Bước 3: Đăng Ký Directive

```typescript
// resources/js/app.ts

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { vCan, vCanAny } from './directives/permission';

createInertiaApp({
  // ...
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });
    
    app.use(plugin);
    
    // Đăng ký directive
    app.directive('can', vCan);
    app.directive('can-any', vCanAny);
    
    app.mount(el);
  },
});
```

#### Bước 4: Sử Dụng Trong Component

```vue
<template>
  <div>
    <!-- Sử dụng composable -->
    <button v-if="can('create_student')" @click="createStudent">
      Thêm Sinh Viên
    </button>
    
    <!-- Sử dụng directive -->
    <div v-can="'view_student'">
      Danh sách sinh viên
    </div>
    
    <div v-can-any="['edit_student', 'delete_student']">
      Quản lý sinh viên
    </div>
  </div>
</template>

<script setup>
import { usePermissions } from '@/composables/usePermissions';

const { can, canAny, canAll } = usePermissions();

// Các logic khác...
</script>
```

### 6. Quản Lý Quyền Động

#### Bước 1: Cập Nhật Model Permission

```php
<?php
// app/Models/Permission.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name', 'display_name', 'description', 'module'];
    
    /**
     * Lấy danh sách vai trò có quyền này
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
    
    /**
     * Nhóm quyền theo module
     */
    public static function getGroupedPermissions()
    {
        $permissions = self::all();
        $grouped = [];
        
        foreach ($permissions as $permission) {
            if (!isset($grouped[$permission->module])) {
                $grouped[$permission->module] = [];
            }
            
            $grouped[$permission->module][] = $permission;
        }
        
        return $grouped;
    }
}
```

#### Bước 2: Cập Nhật Migration Bảng Permissions

```php
<?php
// Tạo migration mới
// php artisan make:migration update_permissions_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('display_name')->after('name')->nullable();
            $table->string('description')->after('display_name')->nullable();
            $table->string('module')->after('description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'module']);
        });
    }
};
```

#### Bước 3: Tạo Command Đồng Bộ Quyền

```php
<?php
// app/Console/Commands/SyncPermissions.php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';
    protected $description = 'Synchronize permissions from config to database';

    public function handle()
    {
        $configPermissions = config('permission.access', []);
        $existingPermissions = Permission::pluck('name')->toArray();
        $count = 0;
        
        foreach ($configPermissions as $module => $permissions) {
            foreach ($permissions as $key => $permission) {
                if (!in_array($permission, $existingPermissions)) {
                    Permission::create([
                        'name' => $permission,
                        'display_name' => ucwords(str_replace('_', ' ', $permission)),
                        'module' => $module,
                    ]);
                    
                    $this->info("Added permission: {$permission}");
                    $count++;
                }
            }
        }
        
        $this->info("Synchronized {$count} new permissions.");
    }
}
```

### 7. Tự Động Xóa Cache (Automatic Cache Clearing)

Để đảm bảo tính nhất quán của dữ liệu, cache quyền cần được xóa tự động mỗi khi có sự thay đổi liên quan đến quyền của người dùng. Việc này được thực hiện thông qua một Service Layer trung gian, đảm bảo logic được đóng gói và tái sử dụng.

#### Bước 1: Tạo Service Layer Quản Lý Vai Trò và Quyền

Tạo `RoleAssignmentService` để quản lý việc gán/gỡ vai trò và quyền. Service này sẽ thực hiện các thay đổi trên database và sau đó gọi `PermissionService` để xóa cache tương ứng.

```php
<?php
// app/Services/RoleAssignmentService.php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleAssignmentService
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Gán vai trò cho người dùng tại một campus.
     */
    public function assignRoleToUser(User $user, Role $role, int $campusId): void
    {
        DB::table('campus_user_roles')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $role->id, 'campus_id' => $campusId]
        );
        
        $this->permissionService->clearUserPermissionsCache($user);
    }

    /**
     * Gỡ vai trò của người dùng tại một campus.
     */
    public function removeRoleFromUser(User $user, Role $role, int $campusId): void
    {
        DB::table('campus_user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->where('campus_id', $campusId)
            ->delete();
        
        $this->permissionService->clearUserPermissionsCache($user);
    }
    
    /**
     * Thêm quyền cho một vai trò.
     */
    public function addPermissionToRole(Role $role, Permission $permission): void
    {
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        
        $this->clearCacheForRole($role);
    }
    
    /**
     * Gỡ quyền khỏi một vai trò.
     */
    public function removePermissionFromRole(Role $role, Permission $permission): void
    {
        $role->permissions()->detach($permission->id);

        $this->clearCacheForRole($role);
    }

    /**
     * Xóa cache cho tất cả người dùng có một vai trò cụ thể.
     */
    protected function clearCacheForRole(Role $role): void
    {
        $userIds = DB::table('campus_user_roles')
            ->where('role_id', $role->id)
            ->distinct()
            ->pluck('user_id');
        
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $this->permissionService->clearUserPermissionsCache($user);
        }
    }
}
```

#### Bước 2: Sử Dụng Service Layer

Mọi hành động gán quyền trong Controller, Command, hoặc các service khác phải được thực hiện thông qua `RoleAssignmentService` để đảm bảo cache được xử lý đúng cách.

**Ví dụ trong Controller:**
```php
<?php
// app/Http/Controllers/Admin/UserRoleController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentService;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    protected $assignmentService;

    public function __construct(RoleAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    public function store(Request $request, User $user, int $campusId)
    {
        $role = Role::findOrFail($request->input('role_id'));
        $this->assignmentService->assignRoleToUser($user, $role, $campusId);
        
        return back()->with('success', 'Role assigned successfully.');
    }
}
```

### 8. Kế Hoạch Kiểm Thử (Testing Plan)

Do tính chất phức tạp của hệ thống phân quyền, việc viết unit test và feature test là cực kỳ quan trọng để đảm bảo hệ thống hoạt động chính xác và dễ dàng bảo trì.

#### 1. Unit Test cho `PermissionService`
- `test_get_user_permissions_from_cache`: Kiểm tra service trả về quyền từ cache nếu có.
- `test_get_user_permissions_from_database_and_cache`: Kiểm tra service lấy quyền từ database, lưu vào cache, và trả về kết quả đúng.
- `test_get_user_permissions_for_specific_campus`: Kiểm tra service trả về quyền chính xác cho một campus cụ thể.
- `test_get_user_permissions_for_all_campuses_is_correct`: Kiểm tra service trả về quyền tổng hợp chính xác khi không có `campus_id`.
- `test_clear_user_permissions_cache`: Kiểm tra cache của người dùng được xóa thành công.

#### 2. Unit Test cho `LazyPermissions` Trait
- `test_has_permission_loads_permissions_lazily`: Kiểm tra các quyền chỉ được load một lần cho mỗi campus.
- `test_has_permission_returns_correct_value`: Kiểm tra `hasPermission` trả về `true` khi người dùng có quyền và `false` khi không có.
- `test_has_any_permission_logic`: Kiểm tra logic `OR` của `hasAnyPermission`.
- `test_has_all_permissions_logic`: Kiểm tra logic `AND` của `hasAllPermissions`.

#### 3. Feature Test
- **Middleware `permissions`**:
    - `test_middleware_allows_access_for_authorized_user`: Kiểm tra người dùng có quyền có thể truy cập route.
    - `test_middleware_blocks_access_for_unauthorized_user`: Kiểm tra người dùng không có quyền bị trả về lỗi 403.
    - `test_middleware_with_or_logic`: Kiểm tra logic 'any' (mặc định) của middleware.
    - `test_middleware_with_and_logic`: Kiểm tra logic 'all' của middleware.
- **`RoleAssignmentService`**:
    - `test_cache_is_cleared_when_assigning_role_to_user`: Kiểm tra cache được xóa sau khi gán vai trò.
    - `test_cache_is_cleared_when_removing_role_from_user`: Kiểm tra cache được xóa sau khi gỡ vai trò.
    - `test_cache_is_cleared_for_all_affected_users_when_permission_is_added_to_role`: Kiểm tra cache được xóa cho tất cả người dùng liên quan khi một quyền được thêm vào vai trò.

Các cải tiến chính:
1. **Caching**: Giảm truy vấn database, tăng hiệu suất.
2. **Lazy Loading**: Tối ưu bộ nhớ và thời gian xử lý khi kiểm tra quyền.
3. **Helper Function**: Giảm lặp code, tăng khả năng bảo trì.
4. **Middleware Tùy Chỉnh**: Đơn giản hóa việc kiểm tra quyền trong route.
5. **Tích Hợp Frontend**: Cải thiện trải nghiệm người dùng với `usePermissions` và `v-can`.
6. **Quản Lý Quyền Động**: Đồng bộ quyền từ config vào database.
7. **Quản Lý Cache Tự Động**: Đảm bảo dữ liệu quyền luôn nhất quán thông qua Service Layer.
8. **Kiểm Thử Toàn Diện**: Tăng độ tin cậy và giảm thiểu lỗi với Unit và Feature test.
