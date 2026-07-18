<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity\DTO;

final readonly class GuardianAccessGrant
{
    public function __construct(
        public int $id,
        public int $guardianRelationshipId,
        public int $parentId,
        public int $studentId,
        public string $accessLevel,
        public string $status,
    ) {}
}
