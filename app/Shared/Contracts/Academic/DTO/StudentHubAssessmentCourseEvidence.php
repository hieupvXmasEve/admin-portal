<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentHubAssessmentCourseEvidence
{
    /**
     * @param  array<string, mixed>|null  $gradingScheme
     * @param  list<StudentHubAssessmentScoreEvidence>  $scores
     */
    public function __construct(
        public int $courseOfferingId,
        public string $courseName,
        public string $courseCode,
        public string $semesterName,
        public ?string $gradingType,
        public ?array $gradingScheme,
        public array $scores,
    ) {}
}
