<?php

declare(strict_types=1);

namespace App\Modules\Institution\Actions;

use App\Models\DepartmentMembership;

class RemoveDepartmentMemberAction
{
    /**
     * @param  array{department_id: int, membership_id: int}  $data
     */
    public static function run(array $data): void
    {
        $membership = DepartmentMembership::query()
            ->where('department_id', $data['department_id'])
            ->findOrFail($data['membership_id']);
        $membership->delete();
    }
}
