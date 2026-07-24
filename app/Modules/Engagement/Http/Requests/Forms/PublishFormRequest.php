<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class PublishFormRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'effective_to.after' => 'Effective end date must be after start date',
        ];
    }
}
