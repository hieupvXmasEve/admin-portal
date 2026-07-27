<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportUnitsRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'in:general,egc,semi,ai,mkt,ba,cs,ee,me,fin'],
            'level' => ['nullable', 'integer', 'min:0', 'max:9'],
            'credit_points_from' => ['nullable', 'numeric', 'min:0'],
            'credit_points_to' => ['nullable', 'numeric', 'min:0', 'gte:credit_points_from'],
        ];
    }
}
