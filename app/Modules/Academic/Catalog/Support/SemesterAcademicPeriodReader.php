<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Support;

use App\Models\Semester;
use App\Modules\Academic\Catalog\Models\CampusPeriodSchedule;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\Academic\DTO\CampusPeriodScheduleReference;
use Carbon\CarbonImmutable;

class SemesterAcademicPeriodReader implements AcademicPeriodReader
{
    public function current(): ?AcademicPeriodReference
    {
        return $this->mapPeriod(Semester::query()
            ->where('is_active', true)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->orderByDesc('id')
            ->first());
    }

    public function active(): ?AcademicPeriodReference
    {
        return $this->mapPeriod(Semester::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first());
    }

    public function find(int $academicPeriodId): ?AcademicPeriodReference
    {
        return $this->mapPeriod(Semester::query()->find($academicPeriodId));
    }

    public function findMany(array $academicPeriodIds): array
    {
        return Semester::query()
            ->whereKey(array_values(array_unique($academicPeriodIds)))
            ->get()
            ->mapWithKeys(function (Semester $semester): array {
                $period = $this->mapPeriod($semester);

                return $period === null ? [] : [$period->id => $period];
            })
            ->all();
    }

    public function countStartingBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return Semester::query()
            ->where('start_date', '>=', $start)
            ->where('start_date', '<=', $end)
            ->count();
    }

    /**
     * @return list<AcademicPeriodReference>
     */
    public function selectable(): array
    {
        return Semester::query()
            ->where('is_archived', false)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Semester $semester): AcademicPeriodReference => $this->mapPeriod($semester))
            ->all();
    }

    public function scheduleForCampus(int $academicPeriodId, int $campusId): ?CampusPeriodScheduleReference
    {
        $period = Semester::query()->find($academicPeriodId);
        if ($period === null) {
            return null;
        }

        $schedule = CampusPeriodSchedule::query()
            ->where('campus_id', $campusId)
            ->where('semester_id', $period->id)
            ->first();

        return new CampusPeriodScheduleReference(
            academic_period_id: (int) $period->id,
            campus_id: $campusId,
            operating_start_date: $schedule?->operating_start_date ?? $this->nullableDate($period->start_date),
            operating_end_date: $schedule?->operating_end_date ?? $this->nullableDate($period->end_date),
            registration_start_date: $schedule?->registration_start_date ?? $this->nullableDate($period->enrollment_start_date),
            registration_end_date: $schedule?->registration_end_date ?? $this->nullableDate($period->enrollment_end_date),
        );
    }

    public function courseOfferingCount(int $academicPeriodId): int
    {
        $semester = Semester::query()->find($academicPeriodId);

        return $semester?->courseOfferings()->count() ?? 0;
    }

    private function mapPeriod(?Semester $semester): ?AcademicPeriodReference
    {
        if ($semester === null) {
            return null;
        }

        return new AcademicPeriodReference(
            id: (int) $semester->id,
            code: $semester->code,
            name: $semester->name,
            start_date: $this->nullableDate($semester->start_date),
            end_date: $this->nullableDate($semester->end_date),
            registration_start_date: $this->nullableDate($semester->enrollment_start_date),
            registration_end_date: $this->nullableDate($semester->enrollment_end_date),
            is_current: (bool) $semester->is_active,
        );
    }

    private function date(mixed $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    private function nullableDate(mixed $date): ?CarbonImmutable
    {
        return $date === null ? null : $this->date($date);
    }
}
