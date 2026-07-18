<?php

declare(strict_types=1);

namespace App\Modules\Institution\Actions;

use App\Models\DepartmentMembership;

class UpdateDepartmentMemberAction
{
    /**
     * @param  array{department_id: int, membership_id: int, department_role: 'head'|'staff', is_active: bool}  $data
     */
    public static function run(array $data): DepartmentMembership
    {
        $membership = DepartmentMembership::query()
            ->where('department_id', $data['department_id'])
            ->findOrFail($data['membership_id']);
        $membership->update([
            'department_role' => $data['department_role'],
            'is_active' => $data['is_active'],
        ]);

        return $membership;
    }
}
