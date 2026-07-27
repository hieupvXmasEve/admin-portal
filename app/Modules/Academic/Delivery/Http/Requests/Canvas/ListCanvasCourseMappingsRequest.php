<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Canvas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCanvasCourseMappingsRequest extends FormRequest
{
    private const SORTABLE_COLUMNS = [
        'canvas_course_code',
        'canvas_course_name',
        'canvas_course_id',
        'sync_status',
        'last_synced_at',
        'created_at',
        'updated_at',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sync_status' => ['nullable', Rule::in(['pending', 'mapped', 'ignored'])],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'sort' => ['nullable', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sort = $this->input('sort');

        if ($sort !== null && (! is_string($sort) || ! in_array($sort, self::SORTABLE_COLUMNS, true))) {
            $this->merge(['sort' => 'created_at']);
        }
    }
}
