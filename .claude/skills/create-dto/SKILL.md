---
name: create-dto
description: Generates a Data Transfer Object (DTO) class. Use when defining structured data for Contracts, API responses, or complex Action inputs.
allowed-tools: Write, Read
---

# Create DTO

This skill generates a Data Transfer Object (DTO) to ensure type safety for data moving between Modules or Layers.

## Instructions

1.  **Identify Parameters**:
    -   **Module**: (e.g., `Identity`, `Academic`) or `Shared` if used across modules.
    -   **Purpose**: What data does it hold? (e.g., `StudentRegistrationData`, `CourseSummary`).
    -   **Path**: `app/Modules/{Module}/DTO/{ClassName}.php` or `app/Shared/DTO/{ClassName}.php`.

2.  **Generate Code**:
    -   Namespace: `App\Modules\{Module}\DTO` or `App\Shared\DTO`.
    -   Use `readonly` class (PHP 8.2+).
    -   Public typed properties.
    -   Optional: `fromRequest` or `fromArray` static factories.

## Template

```php
<?php

namespace App\Shared\DTO;

use Illuminate\Http\Request;

readonly class StudentGpaDTO
{
    public function __construct(
        public int $studentId,
        public string $studentName,
        public float $gpa,
        public int $totalCredits,
        public ?string $rank = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            studentId: $data['student_id'],
            studentName: $data['student_name'],
            gpa: (float) $data['gpa'],
            totalCredits: (int) $data['total_credits'],
            rank: $data['rank'] ?? null,
        );
    }
}
```

## Best Practices
-   **Immutability**: Always use `readonly` classes.
-   **Strict Typing**: All properties must have types.
-   **Shared vs Module**: If it's the return type of a `Shared Contract`, put it in `app/Shared/DTO`. If it's internal to a module (e.g. Action input), put it in `app/Modules/{Module}/DTO`.
