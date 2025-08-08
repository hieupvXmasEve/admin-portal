<?php

namespace App\Http\Requests\Building;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuildingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit_building');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $building = $this->route('building');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:buildings,code,'.$building->id],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
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

    /**
     * Get custom attributes for validator errors.
     */
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
