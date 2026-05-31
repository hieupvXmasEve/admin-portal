<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use App\Models\Semester;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLecturerGpaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('view_lecturer')
            && $user->can('view_survey_results_aggregate');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'semester_id' => ['nullable', $this->semesterIdRule()],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort' => ['nullable', Rule::in([
                'lecturer_name',
                'employee_id',
                'type',
                'gpa',
                'evaluated_classes_count',
                'responses_count',
            ])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('semester_id') === '') {
            $this->merge(['semester_id' => null]);
        }
    }

    private function semesterIdRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! ctype_digit((string) $value) || ! Semester::query()->whereKey((int) $value)->exists()) {
                $fail("The selected {$attribute} is invalid.");
            }
        };
    }
}
