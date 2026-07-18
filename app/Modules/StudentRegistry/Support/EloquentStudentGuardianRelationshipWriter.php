<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Modules\StudentRegistry\Actions\PreserveStudentGuardianRelationshipsAction;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;

final class EloquentStudentGuardianRelationshipWriter implements StudentGuardianRelationshipWriter
{
    public function preserveForStudent(int $studentId, array $guardians): array
    {
        return PreserveStudentGuardianRelationshipsAction::run([
            'student_id' => $studentId,
            'guardians' => $guardians,
        ]);
    }
}
