<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewUnitImportRequest extends FormRequest
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
            'preview_rows' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
