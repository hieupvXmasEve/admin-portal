<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:programs,name'],
            'code' => ['required', 'string', 'max:255', 'unique:programs,code'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Program name is required.',
            'name.unique' => 'A program with this name already exists.',
            'code.required' => 'Program code is required.',
            'code.unique' => 'A program with this code already exists.',
            'description.max' => 'Description must be less than 255 characters.',
        ];
    }
}
