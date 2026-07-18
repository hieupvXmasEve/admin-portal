<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;

interface StudentGuardianRelationshipWriter
{
    /**
     * @param  list<array{source_application_guardian_id?: int|null, full_name: string, relationship_type?: string|null, phone?: string|null, email?: string|null, occupation?: string|null, address?: string|null, is_primary?: bool}>  $guardians
     * @return list<GuardianRelationship>
     */
    public function preserveForStudent(int $studentId, array $guardians): array;
}
