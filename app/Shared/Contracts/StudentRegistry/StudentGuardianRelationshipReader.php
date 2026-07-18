<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;

interface StudentGuardianRelationshipReader
{
    /** @return list<GuardianRelationship> */
    public function forStudent(int $studentId): array;

    public function find(int $relationshipId): ?GuardianRelationship;
}
