<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class CourseResultProgressionContext
{
    public function __construct(
        public int $courseOfferingId,
        public int $semesterId,
        public string $unitType,
        public ?int $unitLevel,
        public string $unitCode,
        public string $unitName,
    ) {}
}
