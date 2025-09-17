<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class AdminLecturerImpersonationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if the user has permission to impersonate lecturers
        // This should be configured in your permission system
//        return $this->user()?->can('impersonate-lecturers') ?? false;
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|in:support,testing,training,audit',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Lecturer email or employee ID is required',
            'email.string' => 'Email must be a valid string',
            'email.max' => 'Email must not exceed 255 characters',
            'device_name.string' => 'Device name must be a valid string',
            'device_name.max' => 'Device name must not exceed 255 characters',
            'purpose.in' => 'Purpose must be one of: support, testing, training, audit',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::authorizationError(
                'You do not have permission to impersonate lecturers. Please contact your administrator.'
            )
        );
    }
}
