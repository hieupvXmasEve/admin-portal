<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

final class SurveyResultIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'semester_id' => ['nullable', 'string'],
            'department_id' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,closed,all'],
            'sort' => ['nullable', 'string', 'in:created_at,responses_count,status,semester,form_title'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
