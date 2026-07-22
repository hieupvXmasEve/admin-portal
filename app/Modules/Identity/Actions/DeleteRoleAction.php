<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Shared\Contracts\Identity\RoleAdministrationStore;
use Illuminate\Support\Facades\DB;

final class DeleteRoleAction
{
    /** @param array{role_id:int} $data */
    public static function run(array $data): void
    {
        $roleId = $data['role_id'];
        $userIds = DB::table('campus_user_roles')->where('role_id', $roleId)->pluck('user_id')->all();
        app(RoleAdministrationStore::class)->delete($data);
        foreach ($userIds as $userId) {
            ClearUserPermissionCacheAction::run(['user_id' => (int) $userId]);
        }
    }
}
