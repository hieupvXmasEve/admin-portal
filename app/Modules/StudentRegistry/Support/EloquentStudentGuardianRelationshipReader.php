<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Modules\StudentRegistry\Models\StudentGuardianRelationship;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipReader;

final class EloquentStudentGuardianRelationshipReader implements StudentGuardianRelationshipReader
{
    public function forStudent(int $studentId): array
    {
        return StudentGuardianRelationship::query()
            ->where('student_id', $studentId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get()
            ->map(fn (StudentGuardianRelationship $relationship): GuardianRelationship => $this->toDto($relationship))
            ->all();
    }

    public function find(int $relationshipId): ?GuardianRelationship
    {
        $relationship = StudentGuardianRelationship::query()->find($relationshipId);

        return $relationship !== null ? $this->toDto($relationship) : null;
    }

    private function toDto(StudentGuardianRelationship $relationship): GuardianRelationship
    {
        return new GuardianRelationship(
            id: (int) $relationship->id,
            studentId: (int) $relationship->student_id,
            sourceApplicationGuardianId: $relationship->source_application_guardian_id !== null
                ? (int) $relationship->source_application_guardian_id
                : null,
            fullName: (string) $relationship->full_name,
            relationshipType: $relationship->relationship_type,
            phone: $relationship->phone,
            email: $relationship->email,
            occupation: $relationship->occupation,
            address: $relationship->address,
            isPrimary: (bool) $relationship->is_primary,
        );
    }
}
