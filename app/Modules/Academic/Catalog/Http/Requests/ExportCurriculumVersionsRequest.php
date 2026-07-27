<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportCurriculumVersionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'specialization_id' => ['nullable', 'integer', 'exists:specializations,id'],
        ];
    }
}
