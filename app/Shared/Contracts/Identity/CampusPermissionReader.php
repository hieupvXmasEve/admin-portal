<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

interface CampusPermissionReader
{
    /** @return list<string> */
    public function permissionCodesForUserId(int $userId, ?int $campusId = null): array;

    /**
     * @param  list<int>  $knownCampusIds
     */
    public function forgetPermissionCodesForUserId(int $userId, array $knownCampusIds = []): void;
}
