<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\Academic\DTO\CampusPeriodScheduleReference;
use Carbon\CarbonImmutable;

interface AcademicPeriodReader
{
    public function current(): ?AcademicPeriodReference;

    /**
     * Return the administrator-designated active period, even when legacy
     * records have not yet populated their schedule dates.
     */
    public function active(): ?AcademicPeriodReference;

    public function find(int $academicPeriodId): ?AcademicPeriodReference;

    /**
     * @param  list<int>  $academicPeriodIds
     * @return array<int, AcademicPeriodReference>
     */
    public function findMany(array $academicPeriodIds): array;

    public function countStartingBetween(CarbonImmutable $start, CarbonImmutable $end): int;

    /**
     * @return list<AcademicPeriodReference>
     */
    public function selectable(): array;

    public function scheduleForCampus(int $academicPeriodId, int $campusId): ?CampusPeriodScheduleReference;

    /**
     * Count of course offerings registered against this academic period.
     */
    public function courseOfferingCount(int $academicPeriodId): int;
}
