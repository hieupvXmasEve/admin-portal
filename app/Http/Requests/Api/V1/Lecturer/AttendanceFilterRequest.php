<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

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
            'course_offering_id' => [
                'nullable',
                'integer',
                'exists:course_offerings,id'
            ],
            'semester_id' => [
                'nullable',
                'integer',
                'exists:semesters,id'
            ],
            'status' => [
                'nullable',
                'string',
                'in:scheduled,completed,cancelled,in_progress'
            ],
            'attendance_status' => [
                'nullable',
                'string',
                'in:marked,unmarked,all'
            ],
            'date_from' => [
                'nullable',
                'date',
                'before_or_equal:date_to'
            ],
            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from'
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:5',
                'max:50'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'course_offering_id.exists' => 'The selected course offering does not exist',
            'semester_id.exists' => 'The selected semester does not exist',
            'status.in' => 'Status must be one of: scheduled, completed, cancelled, in_progress',
            'attendance_status.in' => 'Attendance status must be one of: marked, unmarked, all',
            'date_from.date' => 'From date must be a valid date',
            'date_to.date' => 'To date must be a valid date',
            'date_from.before_or_equal' => 'From date must be before or equal to the to date',
            'date_to.after_or_equal' => 'To date must be after or equal to the from date',
            'per_page.min' => 'Per page must be at least 5',
            'per_page.max' => 'Per page must not exceed 50'
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
                'Attendance filter validation failed'
            )
        );
    }
}
