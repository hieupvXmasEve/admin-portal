<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use App\Models\Campus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchLecturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_lecturer') ?? false;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'min:2'],
            'campus_id' => ['nullable', Rule::exists(Campus::class, 'id')],
            'available_only' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
