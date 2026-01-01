---
name: create-test
description: Generates a PHPUnit/Pest test file. Use when adding test coverage for Actions, Features, or Endpoints (e.g., "Create a test for RegisterStudentAction").
allowed-tools: Write, Read
---

# Create Test

This skill generates a Pest test file (preferred) or PHPUnit test class.

## Instructions

1.  **Identify Parameters**:
    -   **Target**: What are we testing? (e.g., `RegisterStudentAction`, `CourseController`).
    -   **Type**: `Unit` (for Actions/DTOs/Support) or `Feature` (for Controllers/Integration).
    -   **Module**: (e.g., `Academic`).
    -   **Path**: `app/Modules/{Module}/tests/{Type}/{Target}Test.php` (if module-based) OR `tests/{Type}/{Target}Test.php`.

2.  **Generate Code**:
    -   Use **Pest** syntax (it's succinct and readable).
    -   **Namespace**: `App\Modules\{Module}\Tests\{Type}`.
    -   **Setup**: Use `uses(TestCase::class)->in(__DIR__);` if needed, or rely on `Pest.php` configuration.
    -   **Content**:
        -   Happy path (Success scenario).
        -   Validation failure scenario.
        -   Authorization failure scenario.

## Template (Unit - Action)

```php
<?php

namespace App\Modules\Academic\Tests\Unit;

use App\Modules\Academic\Actions\CreateCourseAction;
use App\Modules\Academic\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it creates a course successfully', function () {
    $data = [
        'name' => 'Introduction to Computer Science',
        'code' => 'CS101',
        'credits' => 3,
    ];

    $course = CreateCourseAction::run($data);

    expect($course)
        ->toBeInstanceOf(Course::class)
        ->name->toBe('Introduction to Computer Science');

    $this->assertDatabaseHas('courses', ['code' => 'CS101']);
});
```

## Template (Feature - Controller)

```php
<?php

namespace App\Modules\Academic\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view course list', function () {
    $user = User::factory()->create();
    // specific permission setup if needed

    $response = $this->actingAs($user)
        ->get(route('academic.courses.index'));

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Academic/Course/Index')
            ->has('items')
        );
});
```

## Checklist
- [ ] Are you using `RefreshDatabase`?
- [ ] Are you asserting the side effects (DB changes, Events dispatched)?
- [ ] Are you testing the 'Unhappy Path' (errors)?
