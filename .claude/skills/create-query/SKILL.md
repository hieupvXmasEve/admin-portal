---
name: create-query
description: Generates a Query class for read-only operations. Use when implementing complex data retrieval, reports, or optimized fetching (e.g., "Create a query to list students with GPA").
allowed-tools: Write, Read
---

# Create Query

This skill generates a Query class for handling read logic, keeping Controllers thin and separating "Writes" (Actions) from "Reads" (Queries).

## Instructions

1.  **Identify Parameters**:
    -   **Module**: (e.g., `Academic`, `Identity`).
    -   **Entity/Purpose**: What data is being retrieved? (e.g., `Student`, `MonthlyRevenue`).
    -   **Type**: `List`, `Get`, `Find`, `Report`.
    -   **Class Name**: `{Verb}{Entity}[Context]Query` (e.g., `ListStudentsQuery`, `GetStudentGpaQuery`).

2.  **Determine Path**:
    -   `app/Modules/{Module}/Queries/{ClassName}.php`

3.  **Generate Code**:
    -   Namespace: `App\Modules\{Module}\Queries`
    -   Method: `public function handle(...)`
    -   **Strictly Read-Only**: No `create`, `update`, `delete`, or `transaction` calls.
    -   **Return Type**: Typed (e.g., `Collection`, `Paginator`, `DTO`).

## Template

```php
<?php ș

namespace App\Modules\Academic\Queries;

use App\Modules\Academic\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ListStudentsQuery
{
    /**
     * Get a paginated list of students with filters.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function handle(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Student::class)
            ->allowedFilters([
                AllowedFilter::partial('name'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('campus_id'),
            ])
            ->allowedSorts(['created_at', 'name'])
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
```

## Best Practices
-   **Use QueryBuilder**: For filtering and sorting API/Web lists.
-   **No Side Effects**: A query should never modify the database.
-   **Optimization**: Use `select()`, `with()` (eager loading) to prevent N+1 issues.
-   **Authorization**: Ensure the query respects scopes (e.g., `currentCampus()`).
