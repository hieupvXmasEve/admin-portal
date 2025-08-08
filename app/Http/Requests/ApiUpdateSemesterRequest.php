<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiUpdateSemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('semesters', 'code')->ignore($this->semester->id),
            ],
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'enrollment_start_date' => 'nullable|date|before_or_equal:end_date',
            'enrollment_end_date' => 'nullable|date|after_or_equal:enrollment_start_date|before_or_equal:end_date',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }
}
