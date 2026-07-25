<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\StudentHubAssessmentCourseEvidence;

interface StudentHubAssessmentEvidenceReader
{
    /** @return list<StudentHubAssessmentCourseEvidence> */
    public function forStudent(int $studentId): array;
}
