<?php

declare(strict_types=1);

namespace App\Modules\Institution\Queries;

use App\Models\Department;
use App\Models\DepartmentMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetDepartmentMembershipAdministrationQuery
{
    /**
     * @return array{department: Department, members: Collection<int, DepartmentMembership>, availableUsers: Collection<int, User>}
     */
    public function handle(int|string $departmentId): array
    {
        $department = Department::query()->findOrFail($departmentId);
        $members = $department->memberships()
            ->with('user')
            ->get();

        return [
            'department' => $department,
            'members' => $members,
            'availableUsers' => User::query()
                ->whereNotIn('id', $members->pluck('user_id'))
                ->get(['id', 'name', 'email']),
        ];
    }
}
