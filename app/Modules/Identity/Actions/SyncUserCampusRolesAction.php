<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;

final class SyncUserCampusRolesAction
{
    /** @param array{user_id:int, role_ids:list<int>, campus_id:int} $data */
    public static function run(array $data): void
    {
        $userId = $data['user_id'];
        $roleIds = $data['role_ids'];
        $campusId = $data['campus_id'];
        $roleIds = DB::table('roles')->whereIn('id', $roleIds)->pluck('id')->map(static fn (int|string $id): int => (int) $id)->all();
        DB::transaction(function () use ($userId, $campusId, $roleIds): void {
            DB::table('campus_user_roles')->where('user_id', $userId)->where('campus_id', $campusId)->delete();
            if ($roleIds !== []) {
                DB::table('campus_user_roles')->insert(array_map(fn (int $roleId): array => ['user_id' => $userId, 'role_id' => $roleId, 'campus_id' => $campusId, 'created_at' => now(), 'updated_at' => now()], $roleIds));
            }
        });
        ClearUserPermissionCacheAction::run([
            'user_id' => $userId,
            'campus_ids' => [$campusId],
        ]);
    }
}
