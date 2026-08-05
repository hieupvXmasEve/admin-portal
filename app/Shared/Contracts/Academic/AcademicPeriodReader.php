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
     * The nth period (1-based) starting on or after $start — the inverse of
     * countStartingBetween(), over the same set, so a term number derived from
     * one can be turned back into the period it belongs to.
     */
    public function nthStartingFrom(CarbonImmutable $start, int $nth): ?AcademicPeriodReference;

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
