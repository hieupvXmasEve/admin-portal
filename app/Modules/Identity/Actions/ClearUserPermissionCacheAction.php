<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Support\Facades\DB;

final class ClearUserPermissionCacheAction
{
    /** @param array{user_id:int, campus_ids?:list<int>} $data */
    public static function run(array $data): void
    {
        $userId = $data['user_id'];
        $campusIds = $data['campus_ids'] ?? [];

        DB::afterCommit(static function () use ($userId, $campusIds): void {
            app(CampusPermissionReader::class)->forgetPermissionCodesForUserId($userId, $campusIds);
        });
    }
}
