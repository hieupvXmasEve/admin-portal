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
            'full_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'current_address_line' => ['nullable', 'string', 'max:255'],
            'current_ward' => ['nullable', 'string', 'max:100'],
            'current_province' => ['nullable', 'string', 'max:100'],
            'current_country' => ['nullable', 'string', 'max:30'],
            'cccd_address' => ['nullable', 'string', 'max:500'],
            'cccd_address_line' => ['nullable', 'string', 'max:255'],
            'cccd_ward' => ['nullable', 'string', 'max:100'],
            'cccd_province' => ['nullable', 'string', 'max:100'],
            'cccd_country' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'high_school_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female,other'],
            'emergency_contact_phone' => ['required', 'string', 'max:20', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'emergency_contact_name' => ['required', 'string', 'max:100'],
            'emergency_contact_relationship' => ['required', 'string', 'max:50'],
            'emergency_contact_email' => ['nullable', 'email', 'max:255'],
            'emergency_contact_name_1' => ['nullable', 'string', 'max:100'],
            'emergency_contact_email_1' => ['nullable', 'email', 'max:255'],
            'emergency_contact_phone_1' => ['nullable', 'string', 'max:20', 'regex:/^[\+]?[0-9\s\-\(\)]+$/'],
            'emergency_contact_relationship_1' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'full_name.max' => 'Full name cannot exceed 100 characters',
            'phone.regex' => 'Phone number format is invalid',
            'phone.max' => 'Phone number cannot exceed 20 characters',
            'date_of_birth.date' => 'Date of birth must be a valid date',
            'date_of_birth.before' => 'Date of birth must be before today',
            'address.max' => 'Address cannot exceed 500 characters',
            'current_address_line.max' => 'Current address line cannot exceed 255 characters',
            'current_ward.max' => 'Current ward cannot exceed 100 characters',
            'current_province.max' => 'Current province cannot exceed 100 characters',
            'current_country.max' => 'Current country cannot exceed 30 characters',
            'cccd_address.max' => 'CCCD address cannot exceed 500 characters',
            'cccd_address_line.max' => 'CCCD address line cannot exceed 255 characters',
            'cccd_ward.max' => 'CCCD ward cannot exceed 100 characters',
            'cccd_province.max' => 'CCCD province cannot exceed 100 characters',
            'cccd_country.max' => 'CCCD country cannot exceed 100 characters',
            'national_id.max' => 'National ID cannot exceed 20 characters',
            'ethnicity.max' => 'Ethnicity cannot exceed 100 characters',
            'high_school_name.max' => 'High school name cannot exceed 255 characters',
            'gender.in' => 'Gender must be one of: male, female, other',
            'emergency_contact_phone.regex' => 'Phone number format is invalid',
            'emergency_contact_name.max' => 'Name cannot exceed 100 characters',
            'emergency_contact_name.required' => 'Name cannot be empty',
            'emergency_contact_relationship.required' => 'Relationship cannot be empty',
            'emergency_contact_email.email' => 'Emergency contact email must be a valid email address',
            'emergency_contact_email.max' => 'Emergency contact email cannot exceed 255 characters',
            'emergency_contact_name_1.max' => 'Emergency contact name 1 cannot exceed 100 characters',
            'emergency_contact_email_2.email' => 'Emergency contact email 2 must be a valid email address',
            'emergency_contact_email_2.max' => 'Emergency contact email 2 cannot exceed 255 characters',
            'emergency_contact_phone_2.regex' => 'Emergency contact phone 2 format is invalid',
            'emergency_contact_phone_2.max' => 'Emergency contact phone 2 cannot exceed 20 characters',
            'emergency_contact_relationship_2.max' => 'Emergency contact relationship 2 cannot exceed 50 characters',
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
