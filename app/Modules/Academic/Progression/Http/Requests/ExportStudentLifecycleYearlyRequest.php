<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportStudentLifecycleYearlyRequest extends FormRequest
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
            'selected_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'current_status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
