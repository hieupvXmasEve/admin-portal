<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class AdminStudentImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            'purpose' => ['sometimes', 'string', 'max:255', 'in:support,debugging,testing,demonstration,troubleshooting'],
        ];
    }

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

    public function attributes(): array
    {
        return [
            'email' => 'student identifier',
            'device_name' => 'device name',
            'purpose' => 'impersonation purpose',
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'You do not have permission to impersonate students. Please contact your administrator if you need access.');
    }
}
