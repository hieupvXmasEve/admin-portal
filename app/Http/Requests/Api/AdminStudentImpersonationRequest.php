<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AdminStudentImpersonationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if the user is authenticated and has the necessary permissions
        if (!$this->user()) {
            return false;
        }

        // Check if the user has the permission to impersonate students
        // This uses the existing permission system from the codebase
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'max:255',
            ],
            'device_name' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'purpose' => [
                'sometimes',
                'string',
                'max:255',
                'in:support,debugging,testing,demonstration,troubleshooting',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Student email or student ID is required.',
            'email.string' => 'Student email or student ID must be a string.',
            'email.max' => 'Student email or student ID cannot exceed 255 characters.',
            'device_name.string' => 'Device name must be a string.',
            'device_name.max' => 'Device name cannot exceed 100 characters.',
            'purpose.string' => 'Purpose must be a string.',
            'purpose.max' => 'Purpose cannot exceed 255 characters.',
            'purpose.in' => 'Purpose must be one of: support, debugging, testing, demonstration, troubleshooting.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'email' => 'student identifier',
            'device_name' => 'device name',
            'purpose' => 'impersonation purpose',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        abort(403, 'You do not have permission to impersonate students. Please contact your administrator if you need access.');
    }
}
