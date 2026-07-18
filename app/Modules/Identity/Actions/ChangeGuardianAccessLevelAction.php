<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\GuardianAccessGrant;
use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant as GuardianAccessGrantDto;
use DomainException;

final class ChangeGuardianAccessLevelAction
{
    /** @param array{guardian_relationship_id: int, access_level: string} $data */
    public static function run(array $data): GuardianAccessGrantDto
    {
        $accessLevel = trim($data['access_level']);

        if ($accessLevel === '') {
            throw new DomainException('Guardian access level cannot be empty.');
        }

        $grant = GuardianAccessGrant::query()
            ->where('guardian_relationship_id', $data['guardian_relationship_id'])
            ->firstOrFail();
        $grant->update(['access_level' => $accessLevel]);

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
