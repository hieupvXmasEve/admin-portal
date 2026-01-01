---
name: create-contract
description: Generates a Shared Contract (Interface) and its implementation outline. Use when a module needs to expose data or behavior to other modules (e.g., "Create a contract for getting student GPA").
allowed-tools: Write, Read
---

# Create Contract

This skill generates a Shared Contract (Interface) and guides the implementation, enforcing the project's strict boundary rules.

## Instructions

1.  **Analyze Request**:
    -   **Domain**: The domain requiring the contract (e.g., `Academic`, `Finance`).
    -   **Purpose**: What is being exposed? (e.g., Reading GPA, Checking balance).
    -   **Name**: Follow `{Noun}{Verb}{Purpose}` (e.g., `StudentAcademicReader`, `WalletBalanceProvider`).

2.  **Verify Rules**:
    -   ❌ NEVER return Eloquent Models.
    -   ❌ NEVER accept Eloquent Models as parameters.
    -   ✅ DO return primitives (int, float, string, bool) or DTOs.
    -   ✅ DO use strict typing.

3.  **Generate Files**:
    -   **Contract**: `app/Shared/Contracts/{Domain}/{Name}.php`
    -   **Implementation**: `app/Modules/{Domain}/Services/{Name}Implementation.php` (Suggest this path).

4.  **Register**:
    -   Remind user to bind the interface to the implementation in `{Domain}ServiceProvider`.

## Template (Contract)

```php
<?php

namespace App\Shared\Contracts\Academic;

use App\Shared\DTO\StudentGpaDTO; // Example DTO

interface StudentAcademicReader
{
    /**
     * Get the GPA for a specific student.
     *
     * @param int $studentId
     * @return float
     */
    public function getStudentGpa(int $studentId): float;

    /**
     * Get detailed academic summary.
     *
     * @param int $studentId
     * @return StudentGpaDTO
     */
    public function getStudentSummary(int $studentId): StudentGpaDTO;
}
```

## Template (Implementation Helper)

```php
// In app/Modules/Academic/Services/StudentAcademicReaderImplementation.php

namespace App\Modules\Academic\Services;

use App\Shared\Contracts\Academic\StudentAcademicReader;
use App\Modules\Academic\Models\AcademicRecord;
use App\Shared\DTO\StudentGpaDTO;

class StudentAcademicReaderImplementation implements StudentAcademicReader
{
    public function getStudentGpa(int $studentId): float
    {
        // Implementation logic here
        return AcademicRecord::where('student_id', $studentId)->avg('gpa') ?? 0.0;
    }
    // ...
}
```

## Service Provider Binding

```php
// In app/Modules/Academic/Providers/AcademicServiceProvider.php

public function register(): void
{
    $this->app->bind(
        \App\Shared\Contracts\Academic\StudentAcademicReader::class,
        \App\Modules\Academic\Services\StudentAcademicReaderImplementation::class
    );
}
```

## Checklist
- [ ] Is the interface in `app/Shared/Contracts`?
- [ ] Does it avoid Eloquent Models in method signatures?
- [ ] Is the name descriptive (Noun+Verb+Purpose)?
