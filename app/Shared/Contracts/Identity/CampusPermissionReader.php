<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

interface CampusPermissionReader
{
    /** @return list<string> */
    public function permissionCodesForUserId(int $userId, ?int $campusId = null): array;

    /**
     * The reverse lookup: everyone holding a permission AT one campus.
     *
     * Exists so a module that needs to notify "whoever can act on this" does not
     * reach into campus_user_roles itself. Deliberately not cached — the caller
     * is usually a notification fan-out where a stale list means a staff member
     * silently stops being told about work assigned to them.
     *
     * @return list<int>
     */
    public function userIdsWithPermissionAtCampus(string $permissionCode, int $campusId): array;

    /**
     * @param  list<int>  $knownCampusIds
     */
    public function forgetPermissionCodesForUserId(int $userId, array $knownCampusIds = []): void;
}
