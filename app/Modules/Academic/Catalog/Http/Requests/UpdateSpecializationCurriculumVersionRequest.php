<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpecializationCurriculumVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version_code' => 'required|string|max:50',
            'semester_id' => 'nullable|exists:semesters,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
