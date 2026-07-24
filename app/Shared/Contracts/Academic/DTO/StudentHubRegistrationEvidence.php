<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentHubRegistrationEvidence
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public int $id,
        public int $courseOfferingId,
        public ?int $semesterId,
        public ?int $unitId,
        public string $courseName,
        public string $courseCode,
        public string $sectionCode,
        public float $unitCreditPoints,
        public string $semesterName,
        public string $semesterCode,
        public ?string $semesterStartDate,
        public ?string $semesterEndDate,
        public string $registrationStatus,
        public ?string $registrationDate,
        public ?string $registrationMethod,
        public ?string $completionDate,
        public ?string $dropDate,
        public ?string $withdrawalDate,
        public float $retakeFee,
        public string $isRetakePaid,
        public ?string $notes,
        public ?int $attemptNumber,
        public bool $isRetake,
        public float $creditPoints,
        public ?string $finalGrade,
        public ?float $gradePoints,
    ) {}
}
