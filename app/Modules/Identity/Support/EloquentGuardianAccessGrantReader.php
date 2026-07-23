<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\GuardianAccessGrant;
use App\Shared\Contracts\Identity\DTO\GuardianAccessAccount;
use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant as GuardianAccessGrantDto;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Support\Enums\UserType;
use Illuminate\Support\Facades\DB;

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
        $account = DB::table('parent_student')
            ->join('parents', 'parents.id', '=', 'parent_student.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->where('parent_student.student_id', $studentId)
            ->where('parent_student.is_primary', true)
            ->orderBy('parent_student.id')
            ->first(['users.id', 'users.name', 'users.email']);

        return $account === null ? null : $this->toAccountDto($account);
    }

    public function accountsForStudent(int $studentId): array
    {
        $accounts = GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->where('guardian_access_grants.student_id', $studentId)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->where('users.type', UserType::PARENT->value)
            ->where('users.status', 'active')
            ->orderBy('guardian_access_grants.id')
            ->get(['users.id', 'users.name', 'users.email']);

        $legacyAccounts = DB::table('parent_student')
            ->join('parents', 'parents.id', '=', 'parent_student.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->where('parent_student.student_id', $studentId)
            ->where('parents.status', 'active')
            ->where('users.type', UserType::PARENT->value)
            ->where('users.status', 'active')
            ->orderBy('parent_student.id')
            ->get(['users.id', 'users.name', 'users.email']);

        return $accounts
            ->concat($legacyAccounts)
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

        $grantStudentIds = GuardianAccessGrant::query()
            ->join('parents', 'parents.id', '=', 'guardian_access_grants.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->whereIn('guardian_access_grants.student_id', $studentIds)
            ->where('guardian_access_grants.status', GuardianAccessGrant::STATUS_ACTIVE)
            ->where('users.type', UserType::PARENT->value)
            ->where('users.status', 'active')
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '')
            ->pluck('guardian_access_grants.student_id');
        $legacyStudentIds = DB::table('parent_student')
            ->join('parents', 'parents.id', '=', 'parent_student.parent_id')
            ->join('users', 'users.id', '=', 'parents.user_id')
            ->whereIn('parent_student.student_id', $studentIds)
            ->where('parents.status', 'active')
            ->where('users.type', UserType::PARENT->value)
            ->where('users.status', 'active')
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '')
            ->pluck('parent_student.student_id');

        return $grantStudentIds
            ->merge($legacyStudentIds)
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->unique()
            ->values()
            ->all();
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
