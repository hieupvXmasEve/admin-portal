---
name: scaffold-crud
description: Scaffolds a complete CRUD feature for an entity (Actions, Controller, Requests, Pages, Routes). Use when building a new feature from scratch (e.g., "Scaffold CRUD for Events in Academic module").
allowed-tools: Bash, Write, Read
---

# Scaffold CRUD Feature

This skill helps generate the full stack of files needed for a standard CRUD feature in the Modular Monolith architecture.

## Instructions

1.  **Analyze Request**:
    -   **Module**: (e.g., `Academic`)
    -   **Entity**: (e.g., `Course`)
    -   **Portal**: `Admin` (default) or `Student`.

2.  **Plan the Files**:
    Create a checklist of files to generate using the specific single-purpose skills (or manually if skills can't be called directly).

    -   **Backend**:
        1.  `Model`: `app/Modules/{Module}/Models/{Entity}.php`
        2.  `Migration`: `database/migrations/xxxx_xx_xx_create_{table}_table.php`
        3.  `Actions`: `Create{Entity}Action`, `Update{Entity}Action`, `Delete{Entity}Action`.
        4.  `Query`: `List{Entity}Query` (for Index), `Get{Entity}Query` (for Show/Edit).
        5.  `Requests`: `Create{Entity}Request`, `Update{Entity}Request`.
        6.  `Controller`: `Http/Controllers/Web/{Portal}/{Entity}Controller.php`.
        7.  `Policy`: `Policies/{Entity}Policy.php`.
        8.  `Routes`: Add to `routes/web.php` in the module.

    -   **Frontend** (Inertia):
        1.  `Index.vue`: List with DataTable.
        2.  `Create.vue`: Form for creating.
        3.  `Edit.vue`: Form for editing.

3.  **Execute (Step-by-Step)**:
    -   Don't try to write all files in one turn.
    -   Start with **Model & Migration**.
    -   Then **Actions & Queries**.
    -   Then **Requests & Policy**.
    -   Then **Controller & Routes**.
    -   Finally **Vue Pages**.

4.  **Wiring**:
    -   Ensure the Controller calls the Actions and Queries.
    -   Ensure the Vue pages use the correct Routes.

## Template: Route Registration

```php
// app/Modules/{Module}/routes/web.php

use App\Modules\Academic\Http\Controllers\Web\Admin\CourseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('academic/courses', CourseController::class)
        ->names('academic.courses');
});
```

## Best Practices
-   **Consistency**: Ensure the `route()` names in Vue match the names defined in `routes/web.php`.
-   **Authorization**: Remind the user to register the Policy and Permissions.
-   **Validation**: Ensure Requests are strictly typed.
