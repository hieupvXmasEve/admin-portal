<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCurriculumVersionUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['curriculum_version_id' => ['required', 'exists:curriculum_versions,id']];
    }
}
