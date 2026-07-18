<?php

declare(strict_types=1);

namespace App\Modules\Institution\Http\Requests\Department;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_departments') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $department = $this->route('department');
        $departmentId = $department instanceof Department
            ? $department->id
            : (int) $department;

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('department_memberships')->where('department_id', $departmentId),
            ],
            'department_role' => ['required', 'in:head,staff'],
        ];
    }
}
