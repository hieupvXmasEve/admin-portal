<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentActionAuditRequest extends FormRequest
{
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
            'action_type' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'signed_date_from' => ['nullable', 'date'],
            'signed_date_to' => ['nullable', 'date'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'from_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'egc_defer_from_block_number' => ['nullable', 'integer', 'in:1,2'],
            'return_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'dropout_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'effective_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'missing_documents' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
