<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCurriculumUnitsRequest extends FormRequest
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
            'filter.curriculum_version_id' => ['nullable', 'exists:curriculum_versions,id'],
            'filter.unit_scope' => ['nullable', 'in:program,common,specialization_specific,cross_program'],
            'sort' => ['nullable', 'in:created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
