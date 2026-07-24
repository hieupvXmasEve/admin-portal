<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentHubRegistrationEvidence;

interface StudentHubRegistrationEvidenceReader
{
    public const SCHEMA = 'student-hub-registration-evidence.v1';

    /** @return list<StudentHubRegistrationEvidence> */
    public function forStudent(int $studentId): array;
}
