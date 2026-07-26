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
        $userCampusIds = DB::table('campus_user_roles')
            ->where('role_id', $roleId)
            ->get(['user_id', 'campus_id'])
            ->groupBy('user_id')
            ->map(static fn ($roles): array => $roles
                ->pluck('campus_id')
                ->map(static fn (int|string $campusId): int => (int) $campusId)
                ->all());
        app(RoleAdministrationStore::class)->delete($data);
        foreach ($userCampusIds as $userId => $campusIds) {
            ClearUserPermissionCacheAction::run([
                'user_id' => (int) $userId,
                'campus_ids' => $campusIds,
            ]);
        }
    }
}
