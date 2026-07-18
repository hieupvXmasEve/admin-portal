<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuildingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create_building');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:buildings,code'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Building name is required',
            'name.max' => 'Building name must not exceed 100 characters',
            'code.required' => 'Building code is required',
            'code.max' => 'Building code must not exceed 20 characters',
            'code.unique' => 'Building code already exists',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'building name',
            'code' => 'building code',
            'description' => 'description',
            'address' => 'address',
        ];
    }
}
