---
name: create-action
description: Generates a new business logic Action class following the Modular Monolith pattern. Use when implementing a new feature or moving logic out of a controller (e.g., "Create an action to register students").
allowed-tools: Write, Read
---

# Create Business Action

This skill generates a standardized Action class for business logic, ensuring consistent naming and structure.

## Instructions

1.  **Identify Parameters**:
    -   **Module**: The domain module (e.g., `Identity`, `Academic`).
    -   **Entity**: The object being acted upon (e.g., `Student`, `Course`).
    -   **Verb**: The operation (e.g., `Register`, `Update`, `Cancel`).
    -   **Class Name**: `{Verb}{Entity}Action` (e.g., `RegisterStudentAction`).

2.  **Determine Path**:
    -   `app/Modules/{Module}/Actions/{ClassName}.php`

3.  **Generate Code**:
    -   Namespace: `App\Modules\{Module}\Actions`
    -   Method: `public static function run(...)`
    -   Strict typing.
    -   Transaction handling if writing to DB.

## Template

```php
<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Modules\Identity\DTO\StudentRegistrationData; // Example DTO usage if applicable

class RegisterStudentAction
{
    /**
     * Execute the action.
     *
     * @param array $data Validated data from Request
     * @return User
     */
    public static function run(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Business logic here
            // 1. Create User
            // 2. Assign Role
            // 3. Dispatch Events

            return $user;
        });
    }
}
```

## Best Practices Reminders
-   **No Validation**: Validation happens in FormRequests before calling this Action.
-   **No HTTP**: Do not return `response()` or `redirect()` from here. Return data or throw exceptions.
-   **Single Responsibility**: One action per business use case.
