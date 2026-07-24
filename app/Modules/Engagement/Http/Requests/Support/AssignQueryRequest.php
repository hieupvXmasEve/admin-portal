<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class AssignQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to_user_id' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
