<?php

declare(strict_types=1);

namespace App\Http\Requests\Campus;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit_campus');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $campus = $this->route('campus');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:campuses,code,'.$campus->id],
            'dng_code' => ['nullable', 'string', 'max:255', 'unique:campuses,dng_code,'.$campus->id],
            'address' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Campus name is required',
            'name.max' => 'Campus name must not exceed 255 characters',
            'code.required' => 'Campus code is required',
            'code.max' => 'Campus code must not exceed 255 characters',
            'code.unique' => 'Campus code already exists',
            'dng_code.max' => 'DNG code must not exceed 255 characters',
            'dng_code.unique' => 'DNG code already exists',
            'address.required' => 'Campus address is required',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'campus name',
            'code' => 'campus code',
            'dng_code' => 'DNG code',
            'address' => 'campus address',
        ];
    }
}
