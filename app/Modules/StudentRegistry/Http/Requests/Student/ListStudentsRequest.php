<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentsRequest extends FormRequest
{
    use ChecksStudentDirectoryFilters;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->studentFilterRules(),
            'sort' => 'nullable|string|in:student_id,full_name,email,admission_date,created_at,gc_starting_level,gc_current_level,gc_total_levels',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
