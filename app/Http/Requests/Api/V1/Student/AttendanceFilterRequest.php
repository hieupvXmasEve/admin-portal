<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Student;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AttendanceFilterRequest extends FormRequest
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
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'course_code' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'in:present,absent,late,excused'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'day_of_week' => ['nullable', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'semester_id.exists' => 'The selected semester does not exist',
            'course_code.max' => 'Course code cannot exceed 20 characters',
            'status.in' => 'Status must be one of: present, absent, late, excused',
            'date_from.date' => 'From date must be a valid date',
            'date_to.date' => 'To date must be a valid date',
            'date_to.after_or_equal' => 'To date must be after or equal to from date',
            'day_of_week.in' => 'Day of week must be a valid day (monday-sunday)',
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
                'Invalid attendance filter parameters'
            )
        );
    }
}
