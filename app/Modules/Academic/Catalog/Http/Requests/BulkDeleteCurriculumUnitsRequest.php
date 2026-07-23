<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteCurriculumUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['curriculum_unit_ids' => ['required', 'array', 'min:1', 'max:100'], 'curriculum_unit_ids.*' => ['integer', 'exists:curriculum_units,id']];
    }
}
