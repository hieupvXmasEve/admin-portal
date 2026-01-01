---
name: generate-policy
description: Generates a Laravel Policy class for authorization logic. Use when adding permission checks for a model or resource (e.g., "Create a policy for the Course model").
allowed-tools: Write, Read
---

# Generate Policy

This skill generates a standard Laravel Policy class to handle authorization, typically placed within a Module.

## Instructions

1.  **Identify Parameters**:
    -   **Module**: (e.g., `Academic`, `Identity`). If unsure, ask or infer from the Model location.
    -   **Model**: The model being protected (e.g., `Course`).
    -   **Permissions**: Identify standard permission names (e.g., `course.view`, `course.create`, `course.update`, `course.delete`).

2.  **Determine Path**:
    -   `app/Modules/{Module}/Policies/{Model}Policy.php`

3.  **Generate Code**:
    -   Namespace: `App\Modules\{Module}\Policies`
    -   Methods: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`.
    -   Logic: Use `$user->can('permission_name')`.
    -   Strict typing: `User $user`, `Model $model`.

## Template

```php
<?php

namespace App\Modules\Academic\Policies;

use App\Models\User;
use App\Modules\Academic\Models\Course;
use Illuminate\Auth\Access\HandlesAuthorization;

class CoursePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('course.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Course $course): bool
    {
        return $user->can('course.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('course.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Course $course): bool
    {
        return $user->can('course.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Course $course): bool
    {
        return $user->can('course.delete');
    }
}
```

## Best Practices
-   **Granular Permissions**: Avoid generic `admin` checks if possible; use specific permissions like `course.view`.
-   **Model Ownership**: If a user can only edit their own records, add `$user->id === $model->user_id`.
-   **Registration**: Remind the user to register the policy in the `AuthServiceProvider` or the Module's `ServiceProvider` if auto-discovery doesn't work.
