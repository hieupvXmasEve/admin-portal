<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Actions;

use App\Modules\StudentRegistry\Models\StudentGuardianRelationship;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use Illuminate\Support\Facades\DB;

final class PreserveStudentGuardianRelationshipsAction
{
    /**
     * @param  array{student_id: int, guardians: list<array{source_application_guardian_id?: int|null, full_name: string, relationship_type?: string|null, phone?: string|null, email?: string|null, occupation?: string|null, address?: string|null, is_primary?: bool}>}  $data
     * @return list<GuardianRelationship>
     */
    public static function run(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $studentId = $data['student_id'];
            $guardians = $data['guardians'];

            if (collect($guardians)->contains(fn (array $guardian): bool => (bool) ($guardian['is_primary'] ?? false))) {
                StudentGuardianRelationship::query()
                    ->where('student_id', $studentId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $relationships = [];

            foreach ($guardians as $guardian) {
                $sourceGuardianId = $guardian['source_application_guardian_id'] ?? null;
                $email = isset($guardian['email']) && trim((string) $guardian['email']) !== ''
                    ? strtolower(trim((string) $guardian['email']))
                    : null;
                $lookup = $sourceGuardianId !== null
                    ? ['source_application_guardian_id' => $sourceGuardianId]
                    : ($email === null
                        ? [
                            'student_id' => $studentId,
                            'full_name' => $guardian['full_name'],
                            'email' => null,
                        ]
                        : [
                            'student_id' => $studentId,
                            'email' => $email,
                        ]);

                $relationship = StudentGuardianRelationship::query()->updateOrCreate($lookup, [
                    'student_id' => $studentId,
                    'full_name' => $guardian['full_name'],
                    'relationship_type' => $guardian['relationship_type'] ?? null,
                    'phone' => $guardian['phone'] ?? null,
                    'email' => $email,
                    'occupation' => $guardian['occupation'] ?? null,
                    'address' => $guardian['address'] ?? null,
                    'is_primary' => (bool) ($guardian['is_primary'] ?? false),
                ]);

                $relationships[] = self::toDto($relationship);
            }

            return $relationships;
        });
    }

    private static function toDto(StudentGuardianRelationship $relationship): GuardianRelationship
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
