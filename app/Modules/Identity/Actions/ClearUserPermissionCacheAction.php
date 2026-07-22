<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ClearUserPermissionCacheAction
{
    /** @param array{user_id:int} $data */
    public static function run(array $data): void
    {
        $userId = $data['user_id'];
        Cache::forget("user_permissions_{$userId}_campus_all");
        foreach (DB::table('campus_user_roles')->where('user_id', $userId)->distinct()->pluck('campus_id') as $campusId) {
            Cache::forget("user_permissions_{$userId}_campus_{$campusId}");
        }
    }
}
