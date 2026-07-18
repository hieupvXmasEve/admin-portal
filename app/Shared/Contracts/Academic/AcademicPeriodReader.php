<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\Academic\DTO\CampusPeriodScheduleReference;

interface AcademicPeriodReader
{
    public function current(): ?AcademicPeriodReference;

    public function find(int $academicPeriodId): ?AcademicPeriodReference;

    /**
     * @return list<AcademicPeriodReference>
     */
    public function selectable(): array;

    public function scheduleForCampus(int $academicPeriodId, int $campusId): ?CampusPeriodScheduleReference;
}
