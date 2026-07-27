<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListClassSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'session_type' => ['nullable', 'string'],
            'delivery_mode' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
