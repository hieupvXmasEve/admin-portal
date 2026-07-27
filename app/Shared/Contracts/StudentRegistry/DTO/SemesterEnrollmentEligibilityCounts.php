<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry\DTO;

readonly class SemesterEnrollmentEligibilityCounts
{
    public function __construct(
        public int $totalEligible,
        public int $enrolled,
        public int $notEnrolled,
    ) {}
}
