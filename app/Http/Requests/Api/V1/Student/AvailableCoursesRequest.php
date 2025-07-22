<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Student;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AvailableCoursesRequest extends FormRequest
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
            'unit_code' => ['nullable', 'string', 'max:20'],
            'unit_name' => ['nullable', 'string', 'max:255'],
            'lecturer' => ['nullable', 'string', 'max:255'],
            'day_of_week' => ['nullable', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'time_slot' => ['nullable', 'array'],
            // 'time_slot.start' => ['required_with:time_slot', 'date_format:H:i'],
            // 'time_slot.end' => ['required_with:time_slot', 'date_format:H:i', 'after:time_slot.start'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'unit_code.max' => 'Unit code cannot exceed 20 characters',
            'unit_name.max' => 'Unit name cannot exceed 255 characters',
            'lecturer.max' => 'Lecturer name cannot exceed 255 characters',
            'day_of_week.in' => 'Day of week must be a valid day (monday-sunday)',
            'time_slot.start.required_with' => 'Start time is required when time slot is specified',
            'time_slot.end.required_with' => 'End time is required when time slot is specified',
            'time_slot.start.date_format' => 'Start time must be in HH:MM format',
            'time_slot.end.date_format' => 'End time must be in HH:MM format',
            'time_slot.end.after' => 'End time must be after start time',
            'page.min' => 'Page number must be at least 1',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 100',
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
                'Invalid course search parameters'
            )
        );
    }
}
