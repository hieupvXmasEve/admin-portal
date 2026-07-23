<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurriculumModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'year_level' => ['nullable', 'integer', 'min:1', 'max:9'],
            'semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'is_required' => ['boolean'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'order' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ];
    }
}
