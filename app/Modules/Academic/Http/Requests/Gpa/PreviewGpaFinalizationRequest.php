<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Gpa;

use Illuminate\Foundation\Http\FormRequest;

class PreviewGpaFinalizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
        ];
    }
}
