<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProcessUnitImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'file_path' => ['required', 'string'],
            'duplicate_handling' => ['nullable', 'in:skip,update,error'],
            'create_prerequisites' => ['nullable', 'boolean'],
            'create_equivalents' => ['nullable', 'boolean'],
        ];
    }
}
