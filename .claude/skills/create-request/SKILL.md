---
name: create-request
description: Generates a Laravel FormRequest class for validation. Use when creating or updating data (e.g., "Add validation for student registration").
allowed-tools: Write, Read
---

# Create Form Request

This skill generates a FormRequest class, which is the **mandatory** location for all validation logic in this project.

## Instructions

1.  **Identify Parameters**:
    -   **Module**: (e.g., `Identity`, `Academic`).
    -   **Context**: What action is being validated? (e.g., `RegisterStudent`, `UpdateProfile`).
    -   **Path**: `app/Modules/{Module}/Http/Requests/{Module}/{ClassName}.php`.

2.  **Generate Code**:
    -   Namespace: `App\Modules\{Module}\Http\Requests\{Module}`
    -   Method `authorize()`: Return `true` (authorization is handled by Policies/Middleware, not here, unless simple ownership check).
    -   Method `rules()`: Return array of validation rules.
    -   **Strict Typing**: Add `string`, `int`, `array` type hints to the returned rules.

3.  **Naming Convention**:
    -   Do **not** prefix with Actor (e.g., `StudentLoginRequest` ❌).
    -   Use Domain Context (e.g., `LoginRequest` ✅ inside `Identity` module).

## Template

```php
<?php

namespace App\Modules\Identity\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Auth handled by Middleware/Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'password' => ['required', 'confirmed', 'min:8'],
        ];
    }
}
```

## Best Practices
-   **Complex Rules**: Use `Rule::` builder (e.g., `Rule::unique()`, `Rule::in()`).
-   **Custom Messages**: Implement `messages()` only if standard messages are insufficient.
-   **Attributes**: Implement `attributes()` to provide friendly names for fields.
