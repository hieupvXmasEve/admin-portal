<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Identity;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class AdminLecturerImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|in:support,testing,training,audit',
        ];
    }

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

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponse::validationError($validator->errors()->toArray()));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(ApiResponse::authorizationError(
            'You do not have permission to impersonate lecturers. Please contact your administrator.',
        ));
    }
}
