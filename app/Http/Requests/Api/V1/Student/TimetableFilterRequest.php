<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Student;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class TimetableFilterRequest extends FormRequest
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
            'day_of_week' => ['nullable', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'session_type' => ['nullable', 'string', 'max:50'],
            'lecturer_name' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:100'],
            'room_code' => ['nullable', 'string', 'max:20'],
            'course_code' => ['nullable', 'string', 'max:20'],
            'start_time_after' => ['nullable', 'date_format:H:i'],
            'end_time_before' => ['nullable', 'date_format:H:i'],
            'time_range' => ['nullable', 'array'],
            'time_range.start' => ['required_with:time_range', 'date_format:H:i'],
            'time_range.end' => ['required_with:time_range', 'date_format:H:i', 'after:time_range.start'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'semester_id.exists' => 'The selected semester does not exist',
            'day_of_week.in' => 'Day of week must be a valid day (monday-sunday)',
            'session_type.max' => 'Session type cannot exceed 50 characters',
            'lecturer_name.max' => 'Lecturer name cannot exceed 255 characters',
            'building.max' => 'Building name cannot exceed 100 characters',
            'room_code.max' => 'Room code cannot exceed 20 characters',
            'course_code.max' => 'Course code cannot exceed 20 characters',
            'start_time_after.date_format' => 'Start time must be in HH:MM format',
            'end_time_before.date_format' => 'End time must be in HH:MM format',
            'time_range.start.required_with' => 'Start time is required when time range is specified',
            'time_range.end.required_with' => 'End time is required when time range is specified',
            'time_range.start.date_format' => 'Start time must be in HH:MM format',
            'time_range.end.date_format' => 'End time must be in HH:MM format',
            'time_range.end.after' => 'End time must be after start time',
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
                'Invalid timetable filter parameters'
            )
        );
    }
}
