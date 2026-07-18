<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

use App\Shared\Contracts\Identity\DTO\GuardianAccessGrant;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;

interface GuardianAccessGrantWriter
{
    public function grant(
        GuardianRelationship $relationship,
        string $accessLevel = 'read_only',
        bool $isPrimaryPortalAccount = false,
        ?string $accountEmail = null,
        ?string $accountName = null,
    ): GuardianAccessGrant;

    public function changeAccessLevel(int $guardianRelationshipId, string $accessLevel): GuardianAccessGrant;

    public function revoke(int $guardianRelationshipId): GuardianAccessGrant;
}
