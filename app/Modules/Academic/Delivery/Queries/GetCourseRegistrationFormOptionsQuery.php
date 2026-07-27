<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\CourseRegistrationPresenter;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;

final readonly class GetCourseRegistrationFormOptionsQuery
{
    public function __construct(
        private CourseOfferingCatalogReader $catalog,
        private CourseRegistrationPresenter $presenter,
    ) {}

    /**
     * @return array{semesters: mixed, selectedSemester: object|null, courseOfferings: mixed}
     */
    public function handle(?int $semesterId, int $campusId): array
    {
        $selectedSemester = $semesterId === null ? null : $this->catalog->offeringPeriod($semesterId);

        $courseOfferings = collect();
        if ($semesterId !== null) {
            $units = collect($this->catalog->offeringUnits())->keyBy('id');
            $courseOfferings = CourseOffering::query()
                ->where('course_offerings.semester_id', $semesterId)
                ->where('course_offerings.campus_id', $campusId)
                ->where('course_offerings.is_active', true)
                ->orderBy('course_offerings.section_code')
                ->get()
                ->sortBy(fn (CourseOffering $offering): string => $units->get($offering->unit_id)?->code ?? '')
                ->map(fn (CourseOffering $offering): array => $this->presenter->offering($offering))
                ->values();
        }

        return [
            'semesters' => collect($this->catalog->offeringPeriods())
                ->filter(static fn ($period): bool => $period->is_current)
                ->map(fn ($period): array => $this->presenter->academicPeriod($period))
                ->values(),
            'selectedSemester' => $selectedSemester === null ? null : $this->presenter->academicPeriod($selectedSemester),
            'courseOfferings' => $courseOfferings,
        ];
    }
}
