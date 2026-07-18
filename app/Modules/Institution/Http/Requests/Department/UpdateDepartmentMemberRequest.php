<?php

declare(strict_types=1);

namespace App\Modules\Institution\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_departments') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'department_role' => ['required', 'in:head,staff'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
