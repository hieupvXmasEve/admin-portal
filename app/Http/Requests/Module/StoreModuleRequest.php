<?php

declare(strict_types=1);

namespace App\Http\Requests\Module;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'exists:campuses,id'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('modules')->where(function ($query) {
                    return $query->where('campus_id', $this->campus_id);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'grading_type' => ['required', 'in:grade,pass_fail'],
            'prerequisite_module_id' => ['nullable', 'exists:modules,id'],
            'units' => ['nullable', 'array'],
            'units.*.unit_id' => ['required', 'exists:units,id'],
            'units.*.weight' => ['nullable', 'numeric', 'min:0'],
            'units.*.order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'campus_id.required' => 'Campus is required',
            'campus_id.exists' => 'Selected campus does not exist',
            'code.required' => 'Module code is required',
            'code.unique' => 'Module code already exists for this campus',
            'name.required' => 'Module name is required',
            'grading_type.required' => 'Grading type is required',
            'grading_type.in' => 'Grading type must be either grade or pass_fail',
            'prerequisite_module_id.exists' => 'Selected prerequisite module does not exist',
            'units.*.unit_id.required' => 'Unit ID is required',
            'units.*.unit_id.exists' => 'Selected unit does not exist',
            'units.*.weight.numeric' => 'Weight must be a number',
            'units.*.order.integer' => 'Order must be an integer',
        ];
    }
}
