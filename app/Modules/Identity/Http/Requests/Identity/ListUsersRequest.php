<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

final class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'role_id' => 'nullable|integer|exists:roles,id',
            'type' => 'nullable|string|in:staff,student,lecturer,parent',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
