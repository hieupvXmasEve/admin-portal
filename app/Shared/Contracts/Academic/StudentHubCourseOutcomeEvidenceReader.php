<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentHubCourseOutcomeEvidence;

interface StudentHubCourseOutcomeEvidenceReader
{
    public const SCHEMA = 'student-hub-course-outcome-evidence.v1';

    /** @return list<StudentHubCourseOutcomeEvidence> */
    public function forStudent(int $studentId, array $courseOfferingIds = []): array;
}
