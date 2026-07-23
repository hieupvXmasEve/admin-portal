<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'unit_ids' => ['required', 'array', 'min:1', 'max:100'],
            'unit_ids.*' => ['integer', 'exists:units,id'],
        ];
    }
}
