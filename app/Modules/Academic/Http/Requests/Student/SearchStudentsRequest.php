<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SearchStudentsRequest extends FormRequest
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
            'query' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,suspended,graduated',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ];
    }
}
