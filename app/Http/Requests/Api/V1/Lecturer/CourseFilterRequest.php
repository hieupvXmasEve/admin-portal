<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CourseFilterRequest extends FormRequest
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
            'delivery_mode' => [
                'nullable',
                'string',
                'in:in_person,online,hybrid,blended',
            ],
            'enrollment_status' => [
                'nullable',
                'string',
                'in:open,closed,full',
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:5',
                'max:50',
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
            'delivery_mode.in' => 'Delivery mode must be one of: in_person, online, hybrid, blended',
            'enrollment_status.in' => 'Enrollment status must be one of: open, closed, full',
            'search.max' => 'Search term must not exceed 255 characters',
            'per_page.integer' => 'Per page must be a valid number',
            'per_page.min' => 'Per page must be at least 5',
            'per_page.max' => 'Per page must not exceed 50',
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
                'Course filter validation failed'
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
    }
}
