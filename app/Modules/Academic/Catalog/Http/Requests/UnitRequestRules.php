<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UnitRequestRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?int $ignoringUnitId = null): array
    {
        $codeRule = Rule::unique('units', 'code');

        if ($ignoringUnitId !== null) {
            $codeRule->ignore($ignoringUnitId);
        }

        return [
            'code' => ['required', 'string', 'max:20', 'min:2', 'regex:/^[A-Z0-9]{2,}$/', $codeRule],
            'name' => ['required', 'string', 'max:255', 'min:3'],
            'credit_points' => ['required', 'numeric', 'min:0', 'max:999.99', 'decimal:0,2'],
            'level' => ['required', 'integer', 'min:0', 'max:10'],
            'base_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2'],
            'retake_fee' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2'],
            'unit_type' => ['required', 'string', 'in:general,egc,semi,ai,mkt,ba,cs,ee,me,fin'],
            'prerequisite_groups' => ['nullable', 'array'],
            'prerequisite_groups.*.logic_operator' => ['required', 'string', 'in:AND,OR'],
            'prerequisite_groups.*.description' => ['nullable', 'string', 'max:500'],
            'prerequisite_groups.*.conditions' => ['nullable', 'array'],
            'prerequisite_groups.*.conditions.*.type' => ['required', 'string', 'in:prerequisite,co_requisite,concurrent_prerequisite,anti_requisite,assumed_knowledge,credit_requirement'],
            'prerequisite_groups.*.conditions.*.required_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'prerequisite_groups.*.conditions.*.required_credits' => ['nullable', 'integer', 'min:1'],
            'prerequisite_groups.*.conditions.*.free_text' => ['nullable', 'string', 'max:500'],
            'prerequisite_expression' => ['nullable', 'string', 'max:1000'],
            'prerequisite_description' => ['nullable', 'string', 'max:500'],
            'equivalent_units' => ['nullable', 'array'],
            'equivalent_units.*.equivalent_unit_id' => ['required', 'integer', 'exists:units,id'],
            'equivalent_units.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'code.required' => 'Unit code is required.',
            'code.unique' => 'This unit code already exists.',
            'code.regex' => 'Unit code must contain only uppercase letters and numbers.',
            'name.required' => 'Unit name is required.',
            'credit_points.required' => 'Credit points are required.',
            'level.required' => 'Level is required.',
            'unit_type.required' => 'Unit type is required.',
            'unit_type.in' => 'Invalid unit type selected.',
        ];
    }

    public static function normalize(FormRequest $request): void
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'name' => trim((string) $request->input('name', '')),
            'credit_points' => is_numeric($request->input('credit_points'))
                ? round((float) $request->input('credit_points'), 2)
                : $request->input('credit_points'),
            'prerequisite_expression' => trim((string) $request->input('prerequisite_expression', '')),
            'prerequisite_description' => trim((string) $request->input('prerequisite_description', '')),
        ]);
    }
}
