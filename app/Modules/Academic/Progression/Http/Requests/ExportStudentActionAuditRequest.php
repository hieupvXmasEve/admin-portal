<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Export filters are intentionally looser than the audit listing's: the ids are
 * not existence-checked here, so an export of an already-rendered filter state
 * cannot fail on a semester or actor removed since the page loaded.
 */
class ExportStudentActionAuditRequest extends FormRequest
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
            'semester_id' => ['nullable', 'integer'],
            'from_semester_id' => ['nullable', 'integer'],
            'egc_defer_from_block_number' => ['nullable', 'integer', 'in:1,2'],
            'return_semester_id' => ['nullable', 'integer'],
            'dropout_semester_id' => ['nullable', 'integer'],
            'effective_semester_id' => ['nullable', 'integer'],
            'actor_id' => ['nullable', 'integer'],
            'missing_documents' => ['nullable', 'string'],
            'search' => ['nullable', 'string'],
        ];
    }
}
