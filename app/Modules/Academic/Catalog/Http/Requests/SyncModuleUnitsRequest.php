<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncModuleUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'units' => ['required', 'array'],
            'units.*.unit_id' => ['required', 'exists:units,id'],
            'units.*.grading_type' => ['required', 'in:grade,pass_fail'],
            'units.*.weight' => ['nullable', 'numeric', 'min:0'],
            'units.*.order' => ['required', 'integer', 'min:0'],
        ];
    }
}
