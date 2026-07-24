<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class CurriculumGraduationRequirement
{
    public function __construct(
        public int $unitId,
        public string $unitCode,
        public float $creditPoints,
        public string $type,
    ) {}
}
