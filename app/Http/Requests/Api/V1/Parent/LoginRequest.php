<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Parent;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'], // email or phone
            'password' => ['required', 'string'],
            'remember_me' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
