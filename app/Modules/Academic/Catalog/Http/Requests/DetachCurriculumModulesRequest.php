<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DetachCurriculumModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'module_ids' => ['required', 'array'],
            'module_ids.*' => ['required', 'exists:modules,id'],
        ];
    }
}
