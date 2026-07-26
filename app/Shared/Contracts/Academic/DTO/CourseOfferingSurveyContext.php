<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class CourseOfferingSurveyContext
{
    public function __construct(
        public int $courseOfferingId,
        public int $campusId,
        public ?int $semesterId,
        public ?string $unitType,
        public ?string $semesterEndDate,
    ) {}
}
