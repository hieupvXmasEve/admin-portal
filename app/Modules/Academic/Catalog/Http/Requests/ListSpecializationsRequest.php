<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListSpecializationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'program_id' => 'nullable|exists:programs,id',
            'sort' => 'nullable|string|in:name,code,created_at,program_name',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }
}
