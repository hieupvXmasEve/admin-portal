---
name: scaffold-module
description: Scaffolds a new Domain Module structure in the Modular Monolith architecture. Use when creating a new module (e.g., "Create a new Inventory module").
allowed-tools: Bash, Write, Read
---

# Scaffold New Module

This skill creates the directory structure and core files for a new Module in `app/Modules/{Module}`.

## Instructions

1.  **Analyze the Request**: Identify the module name (PascalCase, Singular). Example: `Inventory`.
2.  **Create Directories**:
    Run `mkdir -p` for:
    - `app/Modules/{Module}/Actions`
    - `app/Modules/{Module}/Queries`
    - `app/Modules/{Module}/Http/Controllers/Web/Admin`
    - `app/Modules/{Module}/Http/Controllers/Api`
    - `app/Modules/{Module}/Http/Requests/{Module}`
    - `app/Modules/{Module}/Models`
    - `app/Modules/{Module}/Policies`
    - `app/Modules/{Module}/Providers`
    - `app/Modules/{Module}/routes`
    - `app/Modules/{Module}/tests`

3.  **Create Service Provider**:
    Create `app/Modules/{Module}/Providers/{Module}ServiceProvider.php`.
    - It must extend `Illuminate\Support\ServiceProvider`.
    - It should load routes from `routes/web.php` and `routes/api.php` (if they exist).
    - It should load migrations if needed (though we use shared migrations usually, unless module specific).
    - It should register policies if needed (though usually done in AuthServiceProvider or automatically discovered).

4.  **Create Route Files**:
    Create empty route files:
    - `app/Modules/{Module}/routes/web.php`
    - `app/Modules/{Module}/routes/api.php`

5.  **Register Module**:
    Remind the user to register the `{Module}ServiceProvider` in `bootstrap/providers.php` or `config/app.php`.

## Template: Service Provider

```php
<?php

namespace App\Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutes();
    }

    protected function loadRoutes(): void
    {
        if (file_exists(__DIR__ . '/../routes/web.php')) {
            Route::middleware('web')
                ->group(__DIR__ . '/../routes/web.php');
        }

        if (file_exists(__DIR__ . '/../routes/api.php')) {
            Route::prefix('api')
                ->middleware('api')
                ->group(__DIR__ . '/../routes/api.php');
        }
    }
}
```
