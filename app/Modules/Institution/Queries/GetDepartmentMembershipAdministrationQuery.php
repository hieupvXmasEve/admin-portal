<?php

declare(strict_types=1);

namespace App\Modules\Institution\Queries;

use App\Models\Department;
use App\Models\DepartmentMembership;
use App\Shared\Contracts\Identity\UserDirectoryReader;
use Illuminate\Database\Eloquent\Collection;

class GetDepartmentMembershipAdministrationQuery
{
    public function __construct(private readonly UserDirectoryReader $users) {}

    /**
     * @return array{department: Department, members: Collection<int, DepartmentMembership>, availableUsers: list<array{id:int, name:string, email:string}>}
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
            'availableUsers' => array_map(
                static fn ($user): array => $user->toArray(),
                $this->users->excludingIds(
                    $members->pluck('user_id')
                        ->map(static fn (int|string $userId): int => (int) $userId)
                        ->all(),
                ),
            ),
        ];
    }
}
