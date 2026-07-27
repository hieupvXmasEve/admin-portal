<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteSpecializationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'specialization_ids' => 'required|array|min:1|max:100',
            'specialization_ids.*' => 'integer|exists:specializations,id',
        ];
    }
}
