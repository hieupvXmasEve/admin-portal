<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Progression;

use Illuminate\Foundation\Http\FormRequest;

final class StudentGpaTrendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'semester_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function semesterCount(): int
    {
        return (int) $this->validated('semester_count', 8);
    }
}
