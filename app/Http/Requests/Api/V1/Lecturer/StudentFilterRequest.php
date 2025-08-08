<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StudentFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'attendance_status' => [
                'nullable',
                'string',
                'in:excellent,good,warning,at_risk',
            ],
            'sort_by' => [
                'nullable',
                'string',
                'in:name,student_number,attendance_percentage,last_attendance',
            ],
            'sort_direction' => [
                'nullable',
                'string',
                'in:asc,desc',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Search term must not exceed 255 characters',
            'attendance_status.in' => 'Attendance status must be one of: excellent, good, warning, at_risk',
            'sort_by.in' => 'Sort by must be one of: name, student_number, attendance_percentage, last_attendance',
            'sort_direction.in' => 'Sort direction must be either asc or desc',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Student filter validation failed'
            )
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim search term
        if ($this->has('search')) {
            $this->merge([
                'search' => trim($this->search),
            ]);
        }

        // Set default sort direction
        if ($this->has('sort_by') && ! $this->has('sort_direction')) {
            $this->merge([
                'sort_direction' => 'asc',
            ]);
        }
    }
}
