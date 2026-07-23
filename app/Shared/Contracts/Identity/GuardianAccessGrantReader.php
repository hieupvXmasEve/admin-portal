<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

use App\Shared\Contracts\Identity\DTO\GuardianAccessAccount;
use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant;

interface GuardianAccessGrantReader
{
    /** @return list<GuardianAccessGrant> */
    public function activeForUser(int $userId): array;

    /** @return list<int> */
    public function activeStudentIdsForUser(int $userId): array;

    public function hasActiveGrant(int $userId, int $studentId): bool;

    public function hasAnyActiveGrant(int $userId): bool;

    public function activeAccountForRelationship(int $guardianRelationshipId): ?GuardianAccessAccount;

    public function primaryAccountForStudent(int $studentId): ?GuardianAccessAccount;

    /** @return list<GuardianAccessAccount> */
    public function accountsForStudent(int $studentId): array;

    /** @param list<int> $studentIds @return list<int> */
    public function studentIdsWithAccounts(array $studentIds): array;

    /**
     * @param  list<int>  $guardianRelationshipIds
     * @return list<int>
     */
    public function activeRelationshipIds(array $guardianRelationshipIds): array;
}
