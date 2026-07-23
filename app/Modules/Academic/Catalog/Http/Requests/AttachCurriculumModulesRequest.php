<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachCurriculumModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.*.module_id' => ['required', 'exists:modules,id'],
            'modules.*.year_level' => ['nullable', 'integer', 'min:1', 'max:9'],
            'modules.*.semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'modules.*.is_required' => ['boolean'],
            'modules.*.group_name' => ['nullable', 'string', 'max:255'],
            'modules.*.order' => ['required', 'integer', 'min:0'],
            'modules.*.note' => ['nullable', 'string'],
        ];
    }
}
