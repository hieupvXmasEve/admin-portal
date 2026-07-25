<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class CurriculumModuleComposition
{
    /**
     * @param  list<array{id: int, code: string, name: string, credit_points: float, grading_type: string|null, weight: float|null, order: int|null}>  $units
     */
    public function __construct(
        public int $moduleId,
        public string $moduleCode,
        public string $moduleName,
        public ?string $gradingType,
        public float $totalCredits,
        public ?int $yearLevel,
        public ?int $semesterNumber,
        public bool $isRequired,
        public ?string $groupName,
        public array $units,
    ) {}
}
