<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCurriculumVersionSummaryUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'unit_scope' => ['nullable', 'in:program,common,specialization_specific,cross_program'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
