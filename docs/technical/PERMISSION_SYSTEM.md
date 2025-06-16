# Optimized Permission System

This document describes the optimized permission system that leverages the shared `config/permission.php` configuration for streamlined authorization management.

## Overview

The optimized system provides:
- **Automatic Gate Registration**: Gates are automatically registered from the permission configuration
- **Simplified Route Definition**: Routes can be defined with automatic permission middleware
- **Centralized Configuration**: All permissions are managed in `config/permission.php`
- **Code Generation**: Artisan commands to generate route files automatically
- **Consistent Patterns**: Standardized approach across all modules

## Components

### 1. Permission Configuration (`config/permission.php`)

The central configuration file that defines all permissions:

```php
return [
    'access' => [
        'users' => [
            'view_user' => 'view_user',
            'add_user' => 'add_user',
            'edit_user' => 'edit_user',
            'delete_user' => 'delete_user',
        ],
        'roles' => [
            'view_role' => 'view_role',
            'add_role' => 'add_role',
            'edit_role' => 'edit_role',
            'delete_role' => 'delete_role',
        ],
        // ... other modules
    ],
];
```

### 2. RoutePermissionHelper (`app/Helpers/RoutePermissionHelper.php`)

Provides methods to automatically apply permissions to routes:

```php
// Automatic CRUD routes with permissions
RoutePermissionHelper::resourceWithPermissions(
    prefix: 'users',
    controller: UserController::class,
    module: 'users'
);

// Get permission middleware for specific actions
$middleware = RoutePermissionHelper::withPermission('users', 'view');
```

### 3. PermissionServiceProvider (`app/Providers/PermissionServiceProvider.php`)

Automatically registers all permission gates and provides custom Blade directives:

- Registers gates from configuration
- Provides `@hasPermission` and `@canPermission` Blade directives

### 4. CheckPermission Middleware (`app/Http/Middleware/CheckPermission.php`)

Optional middleware for automatic permission checking based on route patterns.

## Usage

### Creating Routes for New Modules

#### Option 1: Using RoutePermissionHelper (Recommended)

```php
<?php
// routes/products.php

use App\Http\Controllers\ProductController;
use App\Helpers\RoutePermissionHelper;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    RoutePermissionHelper::resourceWithPermissions(
        prefix: 'products',
        controller: ProductController::class,
        module: 'products'
    );
});
```

#### Option 2: Using Artisan Command

```bash
php artisan make:module-routes products ProductController
```

This will:
1. Check if the module exists in `config/permission.php`
2. Generate the route file with proper permissions
3. Remind you to include it in `routes/web.php`

### Adding Custom Routes

```php
RoutePermissionHelper::resourceWithPermissions(
    prefix: 'products',
    controller: ProductController::class,
    module: 'products',
    options: [
        'additional' => [
            [
                'method' => 'post',
                'uri' => '/import',
                'action' => 'import',
                'permission' => 'import_products',
                'name' => 'products.import'
            ]
        ]
    ]
);
```

### Frontend Permission Checking

#### Vue.js (using composable)

```typescript
import { usePermission } from '@/composables/usePermission';

const { can } = usePermission();

// Check permission
if (can('view_user')) {
    // Show content
}
```

#### Blade Templates

```blade
@hasPermission('view_user')
    <a href="{{ route('users.index') }}">View Users</a>
@endhasPermission

@canPermission('users', 'add')
    <a href="{{ route('users.create') }}">Add User</a>
@endcanPermission
```

## Migration from Old System

### Before (Manual approach)

```php
// routes/user.php
Route::get('/', [UserController::class, 'index'])
    ->middleware('can:view_user')
    ->name('users.index');

Route::get('/add', [UserController::class, 'create'])
    ->middleware('can:add_user')
    ->name('users.create');

// AuthServiceProvider.php
Gate::define('view_user', [UserPolicy::class, 'view']);
Gate::define('add_user', [UserPolicy::class, 'create']);
```

### After (Optimized approach)

```php
// routes/user.php
RoutePermissionHelper::resourceWithPermissions(
    prefix: 'users',
    controller: UserController::class,
    module: 'users'
);

// Gates are automatically registered from config/permission.php
```

## Benefits

1. **Reduced Code Duplication**: No need to manually define gates or repeat middleware
2. **Consistency**: All modules follow the same pattern
3. **Maintainability**: Changes to permissions only require config updates
4. **Scalability**: Easy to add new modules and permissions
5. **Developer Experience**: Artisan commands for quick scaffolding
6. **Type Safety**: Centralized configuration reduces typos and errors

## Best Practices

1. **Always define permissions in config first** before creating routes
2. **Use the helper methods** instead of manual middleware application
3. **Follow naming conventions**: `{action}_{module_singular}` (e.g., `view_user`, `add_role`)
4. **Group related permissions** under the same module key
5. **Use the Artisan command** for new modules to ensure consistency

## Troubleshooting

### Permission Not Working

1. Check if permission exists in `config/permission.php`
2. Verify the permission is in the user's session (`session('permissions')`)
3. Ensure the gate is registered (check `PermissionServiceProvider`)
4. Clear config cache: `php artisan config:clear`

### Route Not Found

1. Ensure the route file is included in `routes/web.php`
2. Check route naming conventions match the helper expectations
3. Verify controller methods exist

### Gates Not Registered

1. Ensure `PermissionServiceProvider` is registered in `bootstrap/providers.php`
2. Check the permission configuration structure
3. Clear application cache: `php artisan optimize:clear` 
