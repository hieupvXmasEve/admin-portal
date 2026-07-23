<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Actions\ChangeGuardianAccessLevelAction;
use App\Modules\Identity\Actions\GrantGuardianAccessAction;
use App\Modules\Identity\Actions\RevokeGuardianAccessAction;
use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use DomainException;

final class EloquentGuardianAccessGrantWriter implements GuardianAccessGrantWriter
{
    public function grant(
        GuardianRelationship $relationship,
        string $accessLevel = 'read_only',
        bool $isPrimaryPortalAccount = false,
        ?string $accountEmail = null,
        ?string $accountName = null,
    ): GuardianAccessGrant {
        $grantEmail = $accountEmail ?? $relationship->email;

        if ($grantEmail === null || trim($grantEmail) === '') {
            throw new DomainException('A Guardian without an email cannot receive a portal access grant.');
        }

        return GrantGuardianAccessAction::run([
            'guardian_relationship_id' => $relationship->id,
            'full_name' => $accountName ?? $relationship->fullName,
            'email' => $grantEmail,
            'phone' => $relationship->phone,
            'relationship_type' => $relationship->relationshipType,
            'access_level' => $accessLevel,
            'is_primary_portal_account' => $isPrimaryPortalAccount,
        ]);
    }

    public function changeAccessLevel(int $guardianRelationshipId, string $accessLevel): GuardianAccessGrant
    {
        return ChangeGuardianAccessLevelAction::run([
            'guardian_relationship_id' => $guardianRelationshipId,
            'access_level' => $accessLevel,
        ]);
    }

    public function revoke(int $guardianRelationshipId): GuardianAccessGrant
    {
        return RevokeGuardianAccessAction::run([
            'guardian_relationship_id' => $guardianRelationshipId,
        ]);
    }

    public function revokeLegacyPrimaryForStudent(int $studentId): void
    {
        RevokeGuardianAccessAction::revokeLegacyPrimaryForStudent($studentId);
    }
}
