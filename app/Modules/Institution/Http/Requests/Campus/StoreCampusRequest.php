<?php

declare(strict_types=1);

namespace App\Modules\Institution\Http\Requests\Campus;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create_campus') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:campuses,code'],
            'address' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Campus name is required',
            'name.max' => 'Campus name must not exceed 255 characters',
            'code.required' => 'Campus code is required',
            'code.max' => 'Campus code must not exceed 255 characters',
            'code.unique' => 'Campus code already exists',
            'address.required' => 'Campus address is required',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'campus name',
            'code' => 'campus code',
            'address' => 'campus address',
        ];
    }
}
