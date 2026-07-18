<?php

declare(strict_types=1);

namespace App\Modules\Institution\Actions;

use App\Models\DepartmentMembership;

class AddDepartmentMemberAction
{
    /**
     * @param  array{department_id: int, user_id: int, department_role: 'head'|'staff'}  $data
     */
    public static function run(array $data): DepartmentMembership
    {
        return DepartmentMembership::query()->create($data);
    }
}
