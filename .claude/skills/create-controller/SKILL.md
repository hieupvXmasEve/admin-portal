---
name: create-controller
description: Generates a standard Controller class. Use when creating API or Web endpoints (e.g., "Create a controller for managing courses").
allowed-tools: Write, Read
---

# Create Controller

This skill generates a standardized Controller class that acts as a "Thin Adapter" in the Modular Monolith architecture.

## Instructions

1.  **Identify Parameters**:
    -   **Module**: (e.g., `Academic`, `Identity`).
    -   **Type**: `Web` (Inertia/Stateful) or `Api` (JSON/Stateless).
    -   **Portal**: `Admin`, `Student`, `Lecturer`.
    -   **Entity**: (e.g., `Course`, `Student`).
    -   **Path**: `app/Modules/{Module}/Http/Controllers/{Type}/{Portal}/{Entity}Controller.php`.

2.  **Generate Code**:
    -   Namespace: `App\Modules\{Module}\Http\Controllers\{Type}\{Portal}`
    -   **Strict Rule**: Controllers must NOT contain business logic.
    -   **Pattern**:
        -   Validate using `FormRequest`.
        -   Execute logic using `Action::run($data)` or `Query->handle()`.
        -   Return `Inertia::render()` (Web) or `response()->json()` (Api).

## Template (Web - Admin)

```php
<?php

namespace App\Modules\Academic\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\Course;
use App\Modules\Academic\Queries\ListCoursesQuery;
use App\Modules\Academic\Actions\CreateCourseAction;
use App\Modules\Academic\Http\Requests\Academic\CreateCourseRequest;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(ListCoursesQuery $query): Response
    {
        // 1. Authorize (Gate/Policy)
        // $this->authorize('viewAny', Course::class);

        // 2. Query Data
        $courses = $query->handle();

        // 3. Return View
        return Inertia::render('Academic/Course/Index', [
            'items' => $courses,
        ]);
    }

    public function store(CreateCourseRequest $request): mixed
    {
        // 1. Authorization handled in FormRequest or Policy

        // 2. Execute Action
        CreateCourseAction::run($request->validated());

        // 3. Return Response
        return to_route('academic.courses.index')
            ->with('success', 'Course created successfully.');
    }
}
```

## Best Practices
-   **Dependency Injection**: Inject Queries into methods or constructor.
-   **No DB Logic**: Never call `Course::create()` or `DB::transaction()` here.
-   **Authorization**: Use `$this->authorize()` for policies.
