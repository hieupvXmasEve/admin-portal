<?php

declare(strict_types=1);

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'min:2',
                'regex:/^[A-Z0-9]{2,}$/',
                Rule::unique('units', 'code')
                    ->ignore($unit->id),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                'min:3',
            ],
            'credit_points' => [
                'required',
                'numeric',
                'min:0',
                'max:999.99',
                'decimal:0,2',
            ],

            // Prerequisite groups validation
            'prerequisite_groups' => ['nullable', 'array'],
            'prerequisite_groups.*.logic_operator' => ['required', 'string', 'in:AND,OR'],
            'prerequisite_groups.*.description' => ['nullable', 'string', 'max:500'],
            'prerequisite_groups.*.conditions' => ['nullable', 'array'],
            'prerequisite_groups.*.conditions.*.type' => ['required', 'string', 'in:prerequisite,co_requisite,concurrent_prerequisite,anti_requisite,assumed_knowledge,credit_requirement'],
            'prerequisite_groups.*.conditions.*.required_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'prerequisite_groups.*.conditions.*.required_credits' => ['nullable', 'integer', 'min:1'],
            'prerequisite_groups.*.conditions.*.free_text' => ['nullable', 'string', 'max:500'],

            // Prerequisite expression validation (for backwards compatibility)
            'prerequisite_expression' => ['nullable', 'string', 'max:1000'],
            'prerequisite_description' => ['nullable', 'string', 'max:500'],

            // Equivalent units validation
            'equivalent_units' => ['nullable', 'array'],
            'equivalent_units.*.equivalent_unit_id' => ['required', 'integer', 'exists:units,id'],
            'equivalent_units.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Unit code is required.',
            'code.unique' => 'This unit code already exists.',
            'code.regex' => 'Unit code must contain only uppercase letters and numbers.',
            'name.required' => 'Unit name is required.',
            'name.min' => 'Unit name must be at least 3 characters.',
            'credit_points.required' => 'Credit points are required.',
            'credit_points.min' => 'Credit points must be at least 0.25.',
            'credit_points.max' => 'Credit points cannot exceed 999.99.',

            'prerequisite_expression.max' => 'Prerequisite expression cannot exceed 1000 characters.',
            'prerequisite_description.max' => 'Prerequisite description cannot exceed 500 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'unit code',
            'name' => 'unit name',
            'credit_points' => 'credit points',
            'prerequisite_expression' => 'prerequisite expression',
            'prerequisite_description' => 'prerequisite description',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim($this->code ?? '')),
            'name' => trim($this->name ?? ''),
            'credit_points' => is_numeric($this->credit_points) ?
                round((float) $this->credit_points, 2) : $this->credit_points,
            'prerequisite_expression' => trim($this->prerequisite_expression ?? ''),
            'prerequisite_description' => trim($this->prerequisite_description ?? ''),
        ]);
    }
}
