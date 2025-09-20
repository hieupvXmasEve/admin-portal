<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ScheduleFilterRequest extends FormRequest
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
            'start' => [
                'nullable',
                'date',
                'before_or_equal:end',
            ],
            'end' => [
                'nullable',
                'date',
                'after_or_equal:start',
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
            'start.date' => 'Start date must be a valid date',
            'end.date' => 'End date must be a valid date',
            'start.before_or_equal' => 'Start date must be before or equal to end date',
            'end.after_or_equal' => 'End date must be after or equal to start date',
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
                'Schedule filter validation failed'
            )
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default date range if not provided (current month)
        if (! $this->has('start') && ! $this->has('end')) {
            $this->merge([
                'start' => now()->startOfMonth()->format('Y-m-d'),
                'end' => now()->endOfMonth()->format('Y-m-d'),
            ]);
        } elseif (! $this->has('start') && $this->has('end')) {
            // If only end date provided, set start to beginning of that month
            $endDate = \Carbon\Carbon::parse($this->end);
            $this->merge([
                'start' => $endDate->copy()->startOfMonth()->format('Y-m-d'),
            ]);
        } elseif ($this->has('start') && ! $this->has('end')) {
            // If only start date provided, set end to end of that month
            $startDate = \Carbon\Carbon::parse($this->start);
            $this->merge([
                'end' => $startDate->copy()->endOfMonth()->format('Y-m-d'),
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