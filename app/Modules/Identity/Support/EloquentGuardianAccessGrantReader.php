<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\GuardianAccessGrant;
use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant as GuardianAccessGrantDto;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;

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
}
