<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Student;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NotificationPreferenceRequest extends FormRequest
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
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.email' => ['nullable', 'array'],
            'preferences.*.email.enabled' => ['boolean'],
            'preferences.*.email.settings' => ['nullable', 'array'],
            'preferences.*.push' => ['nullable', 'array'],
            'preferences.*.push.enabled' => ['boolean'],
            'preferences.*.push.settings' => ['nullable', 'array'],
            'preferences.*.sms' => ['nullable', 'array'],
            'preferences.*.sms.enabled' => ['boolean'],
            'preferences.*.sms.settings' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'preferences.required' => 'Preferences are required',
            'preferences.array' => 'Preferences must be an array',
            'preferences.*.array' => 'Each preference category must be an array',
            'preferences.*.email.array' => 'Email preferences must be an array',
            'preferences.*.email.enabled.boolean' => 'Email enabled must be true or false',
            'preferences.*.email.settings.array' => 'Email settings must be an array',
            'preferences.*.push.array' => 'Push preferences must be an array',
            'preferences.*.push.enabled.boolean' => 'Push enabled must be true or false',
            'preferences.*.push.settings.array' => 'Push settings must be an array',
            'preferences.*.sms.array' => 'SMS preferences must be an array',
            'preferences.*.sms.enabled.boolean' => 'SMS enabled must be true or false',
            'preferences.*.sms.settings.array' => 'SMS settings must be an array',
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
                'Invalid notification preferences'
            )
        );
    }
}
