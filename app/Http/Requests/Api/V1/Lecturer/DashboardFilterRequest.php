<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DashboardFilterRequest extends FormRequest
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
            'semester_id' => [
                'nullable',
                'integer',
                'exists:semesters,id',
            ],
            'include_inactive' => [
                'nullable',
                'boolean',
            ],
            'date_from' => [
                'nullable',
                'date',
                'before_or_equal:date_to',
            ],
            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'semester_id.exists' => 'The selected semester does not exist',
            'semester_id.integer' => 'Semester ID must be a valid number',
            'include_inactive.boolean' => 'Include inactive must be true or false',
            'date_from.date' => 'From date must be a valid date',
            'date_to.date' => 'To date must be a valid date',
            'date_from.before_or_equal' => 'From date must be before or equal to the to date',
            'date_to.after_or_equal' => 'To date must be after or equal to the from date',
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
                'Dashboard filter validation failed'
            )
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string 'true'/'false' to boolean for include_inactive
        if ($this->has('include_inactive')) {
            $this->merge([
                'include_inactive' => filter_var($this->include_inactive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }
}
