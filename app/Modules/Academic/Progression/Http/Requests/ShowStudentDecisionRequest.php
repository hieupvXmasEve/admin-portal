<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowStudentDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'linked_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'linked_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
