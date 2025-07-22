<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Student;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProfileUpdateRequest extends FormRequest
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
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'address_street' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_state' => ['nullable', 'string', 'max:100'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'address_country' => ['nullable', 'string', 'max:100'],
            'preferred_language' => ['nullable', 'string', 'in:en,es,fr,de,zh,ja'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'date_format' => ['nullable', 'string', 'in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD'],
            'time_format' => ['nullable', 'string', 'in:12h,24h'],
            'theme_preference' => ['nullable', 'string', 'in:light,dark,auto'],
            'email_notifications' => ['nullable', 'boolean'],
            'push_notifications' => ['nullable', 'boolean'],
            'sms_notifications' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'preferred_name.max' => 'Preferred name cannot exceed 100 characters',
            'phone.regex' => 'Phone number format is invalid',
            'phone.max' => 'Phone number cannot exceed 20 characters',
            'emergency_contact_name.max' => 'Emergency contact name cannot exceed 255 characters',
            'emergency_contact_phone.regex' => 'Emergency contact phone format is invalid',
            'emergency_contact_phone.max' => 'Emergency contact phone cannot exceed 20 characters',
            'emergency_contact_relationship.max' => 'Emergency contact relationship cannot exceed 100 characters',
            'address_street.max' => 'Street address cannot exceed 255 characters',
            'address_city.max' => 'City cannot exceed 100 characters',
            'address_state.max' => 'State cannot exceed 100 characters',
            'address_postal_code.max' => 'Postal code cannot exceed 20 characters',
            'address_country.max' => 'Country cannot exceed 100 characters',
            'preferred_language.in' => 'Preferred language must be one of: en, es, fr, de, zh, ja',
            'timezone.max' => 'Timezone cannot exceed 50 characters',
            'date_format.in' => 'Date format must be one of: DD/MM/YYYY, MM/DD/YYYY, YYYY-MM-DD',
            'time_format.in' => 'Time format must be either 12h or 24h',
            'theme_preference.in' => 'Theme preference must be one of: light, dark, auto',
            'email_notifications.boolean' => 'Email notifications must be true or false',
            'push_notifications.boolean' => 'Push notifications must be true or false',
            'sms_notifications.boolean' => 'SMS notifications must be true or false',
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
                'Invalid profile data'
            )
        );
    }
}
