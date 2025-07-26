<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StudentNoteRequest extends FormRequest
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
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        
        return [
            'course_offering_id' => [
                $isUpdate ? 'sometimes' : 'nullable',
                'integer',
                'exists:course_offerings,id'
            ],
            'note_type' => [
                $isUpdate ? 'sometimes' : 'nullable',
                'string',
                'in:general,academic,behavioral,attendance,performance,personal,alert'
            ],
            'title' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:255'
            ],
            'content' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:2000'
            ],
            'is_private' => [
                'sometimes',
                'boolean'
            ],
            'is_alert' => [
                'sometimes',
                'boolean'
            ],
            'priority' => [
                'sometimes',
                'string',
                'in:low,medium,high,urgent'
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
            'note_type.in' => 'Note type must be one of: general, academic, behavioral, attendance, performance, personal, alert',
            'title.required' => 'Note title is required',
            'title.max' => 'Note title must not exceed 255 characters',
            'content.required' => 'Note content is required',
            'content.max' => 'Note content must not exceed 2000 characters',
            'is_private.boolean' => 'Is private must be true or false',
            'is_alert.boolean' => 'Is alert must be true or false',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent'
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
                'Student note validation failed'
            )
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $defaults = [
            'note_type' => 'general',
            'is_private' => true,
            'is_alert' => false,
            'priority' => 'medium',
        ];

        foreach ($defaults as $key => $value) {
            if (!$this->has($key)) {
                $this->merge([$key => $value]);
            }
        }

        // Convert string 'true'/'false' to boolean
        foreach (['is_private', 'is_alert'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->$field, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                ]);
            }
        }
    }
}
