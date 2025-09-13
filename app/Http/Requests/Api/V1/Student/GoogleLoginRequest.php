<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Student;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GoogleLoginRequest extends FormRequest
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
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'remember_me' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_token.required' => 'Google ID token is required',
            'id_token.string' => 'Google ID token must be a valid string',
            'device_name.max' => 'Device name cannot exceed 255 characters',
            'remember_me.boolean' => 'Remember me must be true or false',
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
                'The provided Google login data is invalid'
            )
        );
    }
}
