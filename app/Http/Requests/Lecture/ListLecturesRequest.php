<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLecturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_lecturer') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'campus_id' => ['nullable', $this->idOrAllRule(Campus::class)],
            'semester_id' => ['nullable', $this->idOrAllRule(Semester::class)],
            'unit_type' => ['nullable', $this->unitTypeOrAllRule()],
            'employment_status' => ['nullable', Rule::in([
                'all',
                'active',
                'on_leave',
                'sabbatical',
                'retired',
                'terminated',
                'suspended',
            ])],
            'employment_type' => ['nullable', Rule::in([
                'all',
                'full_time',
                'part_time',
                'contract',
                'visiting',
                'emeritus',
            ])],
            'available_for_assignment' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort' => ['nullable', Rule::in([
                'employee_id',
                'full_name',
                'academic_rank',
                'employment_status',
                'employment_type',
                'is_available_for_assignment',
            ])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('available_for_assignment')) {
            $this->merge([
                'available_for_assignment' => $this->boolean('available_for_assignment'),
            ]);
        }
    }

    private function idOrAllRule(string $modelClass): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($modelClass): void {
            if ($value === null || $value === '' || $value === 'all') {
                return;
            }

            if (! ctype_digit((string) $value) || ! $modelClass::query()->whereKey((int) $value)->exists()) {
                $fail("The selected {$attribute} is invalid.");
            }
        };
    }

    private function unitTypeOrAllRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '' || $value === 'all') {
                return;
            }

            if (! is_string($value) || ! Unit::query()->where('unit_type', $value)->exists()) {
                $fail("The selected {$attribute} is invalid.");
            }
        };
    }
}
