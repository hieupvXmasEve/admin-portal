<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class EloquentCampusPermissionReader implements CampusPermissionReader
{
    public function permissionCodesForUserId(int $userId, ?int $campusId = null): array
    {
        $cacheKey = $this->cacheKey($userId, $campusId);

        /** @var list<string> $permissions */
        $permissions = Cache::remember($cacheKey, now()->addDay(), function () use ($userId, $campusId): array {
            $query = DB::table('permissions')
                ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->join('campus_user_roles', 'role_permissions.role_id', '=', 'campus_user_roles.role_id')
                ->where('campus_user_roles.user_id', $userId);

            if ($campusId !== null) {
                $query->where('campus_user_roles.campus_id', $campusId);
            }

            return $query->select('permissions.code')
                ->distinct()
                ->pluck('code')
                ->map(static fn (mixed $code): string => (string) $code)
                ->all();
        });

        return $permissions;
    }

    public function userIdsWithPermissionAtCampus(string $permissionCode, int $campusId): array
    {
        return DB::table('campus_user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'campus_user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('campus_user_roles.campus_id', $campusId)
            ->where('permissions.code', $permissionCode)
            ->distinct()
            ->pluck('campus_user_roles.user_id')
            ->map(static fn (int|string $userId): int => (int) $userId)
            ->all();
    }

    public function campusIdsWithPermissionForUser(int $userId, string $permissionCode): array
    {
        return DB::table('campus_user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'campus_user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('campus_user_roles.user_id', $userId)
            ->where('permissions.code', $permissionCode)
            ->distinct()
            ->pluck('campus_user_roles.campus_id')
            ->map(static fn (int|string $campusId): int => (int) $campusId)
            ->all();
    }

    public function forgetPermissionCodesForUserId(int $userId, array $knownCampusIds = []): void
    {
        Cache::forget($this->cacheKey($userId, null));

        $currentCampusIds = DB::table('campus_user_roles')
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('campus_id')
            ->map(static fn (int|string $campusId): int => (int) $campusId)
            ->all();

        foreach (array_unique([...$knownCampusIds, ...$currentCampusIds]) as $campusId) {
            Cache::forget($this->cacheKey($userId, $campusId));
        }
    }

    private function cacheKey(int $userId, ?int $campusId): string
    {
        return "user_permissions_{$userId}_campus_".($campusId ?? 'all');
    }
}
