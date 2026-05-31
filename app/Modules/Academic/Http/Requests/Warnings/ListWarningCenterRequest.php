<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Warnings;

use Illuminate\Foundation\Http\FormRequest;

class ListWarningCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('view_grade') || $user->can('view_attendance'));
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:cumulative_gpa,credits_earned,credits_attempted,updated_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
