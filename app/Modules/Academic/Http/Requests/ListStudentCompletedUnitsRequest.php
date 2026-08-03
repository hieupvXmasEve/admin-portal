<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListStudentCompletedUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * campus_id is deliberately absent: the report's campus always comes from
     * the session (see StudentCompletedUnitsController::resolveFilters), never
     * from client input.
     */
    public function rules(): array
    {
        return [
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            // Restricted to the same non-archived set the filter dropdown
            // offers: an archived id would still scope the report while the
            // <Select> silently fell back to showing "All semesters".
            'semester_id' => ['nullable', 'integer', Rule::exists('semesters', 'id')->where('is_archived', false)],
            'keyword' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:student_id,full_name,units_count,credits_earned'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
