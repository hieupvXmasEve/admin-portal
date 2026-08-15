<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Modules\Identity\Models\GuardianAccessGrant;
use App\Shared\Contracts\Identity\DTO\GuardianAccessAccount;
use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant as GuardianAccessGrantDto;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Support\Enums\UserType;

final class EloquentGuardianAccessGrantReader implements GuardianAccessGrantReader
{
    public function activeForUser(int $userId): array
    {
        return GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->where('parents.user_id', $userId)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->orderBy('guardian_access_grants.id')
            ->get('guardian_access_grants.*')
            ->map(fn (GuardianAccessGrant $grant): GuardianAccessGrantDto => $this->toDto($grant))
            ->all();
    }

    public function activeStudentIdsForUser(int $userId): array
    {
        return array_map(
            static fn (GuardianAccessGrantDto $grant): int => $grant->studentId,
            $this->activeForUser($userId),
        );
    }

    public function hasActiveGrant(int $userId, int $studentId): bool
    {
        return GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->where('parents.user_id', $userId)
            ->where('guardian_access_grants.student_id', $studentId)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->exists();
    }

    public function hasAnyActiveGrant(int $userId): bool
    {
        return GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->where('parents.user_id', $userId)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->exists();
    }

    public function activeAccountForRelationship(int $guardianRelationshipId): ?GuardianAccessAccount
    {
        $account = GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->where('guardian_access_grants.guardian_relationship_id', $guardianRelationshipId)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->orderBy('guardian_access_grants.id')
            ->first(['users.id', 'users.name', 'users.email']);

        return $account === null ? null : $this->toAccountDto($account);
    }

    public function primaryAccountForStudent(int $studentId): ?GuardianAccessAccount
    {
        $account = GuardianAccessGrant::query()
            ->join('student_guardian_relationships', 'student_guardian_relationships.id', '=', 'guardian_access_grants.guardian_relationship_id')
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->where('guardian_access_grants.student_id', $studentId)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->where('student_guardian_relationships.is_primary', true)
            ->orderBy('guardian_access_grants.id')
            ->first(['users.id', 'users.name', 'users.email']);

        return $account === null ? null : $this->toAccountDto($account);
    }

    public function accountsForStudent(int $studentId): array
    {
        return GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->where('guardian_access_grants.student_id', $studentId)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->where('users.type', UserType::PARENT->value)
            ->where('users.status', 'active')
            ->orderBy('guardian_access_grants.id')
            ->get(['users.id', 'users.name', 'users.email'])
            ->filter(static fn (object $account): bool => is_string($account->email) && trim($account->email) !== '')
            ->map(fn (object $account): GuardianAccessAccount => $this->toAccountDto($account))
            ->unique(static fn (GuardianAccessAccount $account): string => strtolower($account->email))
            ->values()
            ->all();
    }

    public function studentIdsWithAccounts(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->whereIn('guardian_access_grants.student_id', $studentIds)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->where('users.type', UserType::PARENT->value)
            ->where('users.status', 'active')
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '')
            ->pluck('guardian_access_grants.student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->unique()
            ->values()
            ->all();
    }

    public function hasRelationshipForOtherStudent(int $accountId, int $studentId): bool
    {
        return GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->where('parents.user_id', $accountId)
            ->where('guardian_access_grants.student_id', '!=', $studentId)
            ->exists();
    }

    public function accountExistsForEmail(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function activeRelationshipIds(array $guardianRelationshipIds): array
    {
        if ($guardianRelationshipIds === []) {
            return [];
        }

        return GuardianAccessGrant::query()
            ->whereIn('guardian_relationship_id', $guardianRelationshipIds)
            ->where('status', GuardianAccessGrant::STATUS_ACTIVE)
            ->orderBy('guardian_relationship_id')
            ->pluck('guardian_relationship_id')
            ->map(static fn (int|string $relationshipId): int => (int) $relationshipId)
            ->all();
    }

    private function toDto(GuardianAccessGrant $grant): GuardianAccessGrantDto
    {
        return new GuardianAccessGrantDto(
            id: (int) $grant->id,
            guardianRelationshipId: (int) $grant->guardian_relationship_id,
            parentId: (int) $grant->parent_id,
            studentId: (int) $grant->student_id,
            accessLevel: (string) $grant->access_level,
            status: (string) $grant->status,
        );
    }

    /** @param object{id: int|string, name: string|null, email: string|null} $account */
    private function toAccountDto(object $account): GuardianAccessAccount
    {
        return new GuardianAccessAccount(
            id: (int) $account->id,
            name: (string) ($account->name ?? ''),
            email: (string) ($account->email ?? ''),
        );
    }
}
