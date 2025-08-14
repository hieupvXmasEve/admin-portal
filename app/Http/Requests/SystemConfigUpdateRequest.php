<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SystemConfigUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // You can add authorization logic here if needed
        // For now, assume authorized if authenticated
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['sometimes', 'string', 'max:255'],
            'logo_full' => ['sometimes', 'string', 'max:500'],
            'logo_text' => ['sometimes', 'string', 'max:500'],
            'copyright_text' => ['sometimes', 'string', 'max:500'],
            'country' => ['sometimes', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'app_name.string' => 'App name must be a string.',
            'app_name.max' => 'App name cannot exceed 255 characters.',
            'logo_full.string' => 'Logo full path must be a string.',
            'logo_full.max' => 'Logo full path cannot exceed 500 characters.',
            'logo_text.string' => 'Logo text path must be a string.',
            'logo_text.max' => 'Logo text path cannot exceed 500 characters.',
            'copyright_text.string' => 'Copyright text must be a string.',
            'copyright_text.max' => 'Copyright text cannot exceed 500 characters.',
            'country.string' => 'Country must be a string.',
            'country.max' => 'Country cannot exceed 100 characters.',
        ];
    }
}
