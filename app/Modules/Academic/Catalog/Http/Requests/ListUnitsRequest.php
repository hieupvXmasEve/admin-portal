<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListUnitsRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:code,name,credit_points,level,unit_type,base_fee,retake_fee,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'type' => ['nullable', 'string', 'in:general,egc,semi,ai,mkt,ba,cs,ee,me,fin'],
            'level' => ['nullable', 'integer', 'min:0', 'max:9'],
        ];
    }
}
