<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentLifecycleYearlyRequest extends FormRequest
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
            'selected_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'cohort_semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'current_status' => ['nullable', 'string', 'max:50'],
            'status_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
