<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

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
            'start_date' => [
                'nullable',
                'date',
                'before_or_equal:end_date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'view' => [
                'nullable',
                'string',
                'in:day,week,month',
            ],
            'course_offering_id' => [
                'nullable',
                'integer',
                'exists:course_offerings,id',
            ],
            'status' => [
                'nullable',
                'string',
                'in:scheduled,completed,cancelled,in_progress',
            ],
            'include_cancelled' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'start_date.date' => 'Start date must be a valid date',
            'end_date.date' => 'End date must be a valid date',
            'start_date.before_or_equal' => 'Start date must be before or equal to end date',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'view.in' => 'View must be one of: day, week, month',
            'course_offering_id.exists' => 'The selected course offering does not exist',
            'status.in' => 'Status must be one of: scheduled, completed, cancelled, in_progress',
            'include_cancelled.boolean' => 'Include cancelled must be true or false',
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
                'Timetable filter validation failed'
            )
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default view if not provided
        if (! $this->has('view')) {
            $this->merge(['view' => 'week']);
        }

        // Set default date range if not provided (current week)
        if (! $this->has('start_date') && ! $this->has('end_date')) {
            $this->merge([
                'start_date' => now()->startOfWeek()->format('Y-m-d'),
                'end_date' => now()->endOfWeek()->format('Y-m-d'),
            ]);
        }

        // Convert string 'true'/'false' to boolean for include_cancelled
        if ($this->has('include_cancelled')) {
            $this->merge([
                'include_cancelled' => filter_var($this->include_cancelled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
