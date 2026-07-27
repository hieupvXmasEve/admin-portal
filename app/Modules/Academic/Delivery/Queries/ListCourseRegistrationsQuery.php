<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Academic\Delivery\Support\CourseRegistrationPresenter;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use App\Shared\Contracts\StudentRegistry\StudentSerializedReferenceReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class ListCourseRegistrationsQuery
{
    public function __construct(
        private CourseOfferingCatalogReader $catalog,
        private CourseRegistrationPresenter $presenter,
        private StudentSerializedReferenceReader $students,
    ) {}

    /**
     * @param  array{search?: string, semester_id?: int|string|null, status?: string|null, course_offering_id?: int|string|null, per_page?: int}  $filters
     * @return array{registrations: mixed, statistics: array<string, int>, semesters: Collection<int, object>, courseOfferings: Collection<int, array{value: int, label: string}>, statusOptions: list<array{value: string, label: string}>}
     */
    public function handle(array $filters, int $campusId): array
    {
        $registrations = CourseRegistration::query()
            ->whereHas('courseOffering', fn (Builder $query) => $query->where('campus_id', $campusId))
            ->orderByDesc('created_at');
        $this->applyFilters($registrations, $filters);

        $statisticsQuery = CourseRegistration::query()
            ->whereHas('courseOffering', fn (Builder $query) => $query->where('campus_id', $campusId));
        $this->applyFilters($statisticsQuery, $filters);

        $semesterId = $filters['semester_id'] ?? null;
        $courseOfferings = collect();
        if ($semesterId !== null && $semesterId !== '' && $semesterId !== 'all') {
            $units = collect($this->catalog->offeringUnits())->keyBy('id');
            $courseOfferings = CourseOffering::query()
                ->where('course_offerings.semester_id', $semesterId)
                ->where('course_offerings.campus_id', $campusId)
                ->where('course_offerings.is_active', true)
                ->orderBy('course_offerings.section_code')
                ->get()
                ->sortBy(fn (CourseOffering $offering): string => $units->get($offering->unit_id)?->code ?? '')
                ->map(fn (CourseOffering $offering): array => [
                    'value' => $offering->id,
                    'label' => ($units->get($offering->unit_id)?->code ?? 'N/A').' - '.($units->get($offering->unit_id)?->name ?? 'N/A').($offering->section_code ? " (Section {$offering->section_code})" : ''),
                ]);
        }

        $registrations = $registrations->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
        $registrationItems = $registrations->getCollection();
        $students = $this->students
            ->findManySerialized($registrationItems->pluck('student_id')->map(static fn (int|string $id): int => (int) $id)->all());
        $offerings = CourseOffering::query()
            ->whereIn('id', $registrationItems->pluck('course_offering_id'))
            ->get()
            ->keyBy('id')
            ->all();
        $units = collect($this->catalog->offeringUnits())->keyBy('id')->all();
        $periods = collect($this->catalog->offeringPeriods())->keyBy('id')->all();
        $lecturers = $registrationItems->isEmpty() ? [] : $this->presenter->lecturersById(
            collect($offerings)
                ->pluck('lecture_id')
                ->filter()
                ->map(static fn (int|string $id): int => (int) $id)
                ->all(),
        );
        $registrations->setCollection($registrationItems->map(fn (CourseRegistration $registration): array => $this->presenter->registrationFromMaps(
            $registration,
            $students,
            $offerings,
            $units,
            $periods,
            $lecturers,
        )));

        return [
            'registrations' => $registrations,
            'statistics' => [
                'total_registrations' => $statisticsQuery->count(),
                'active_registrations' => (clone $statisticsQuery)->whereIn('registration_status', ['registered', 'confirmed'])->count(),
                'pending_registrations' => (clone $statisticsQuery)->where('registration_status', 'registered')->count(),
            ],
            'semesters' => collect($this->catalog->offeringPeriods())
                ->map(fn ($period): array => $this->presenter->academicPeriod($period))
                ->values(),
            'courseOfferings' => $courseOfferings,
            'statusOptions' => [
                ['value' => 'registered', 'label' => 'Registered'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'dropped', 'label' => 'Dropped'],
                ['value' => 'withdrawn', 'label' => 'Withdrawn'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
        ];
    }

    /** @param Builder<CourseRegistration> $query @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $searchQuery) use ($search): void {
                $studentIds = app(StudentReferenceReader::class)->idsMatchingSearch($search);
                $unitIds = collect($this->catalog->offeringUnits())
                    ->filter(fn ($unit): bool => str_contains(mb_strtolower($unit->code.' '.$unit->name), mb_strtolower($search)))
                    ->pluck('id')
                    ->all();
                $offeringIds = CourseOffering::query()->whereIn('unit_id', $unitIds)->pluck('id')->all();
                $searchQuery->whereIn('student_id', $studentIds)->orWhereIn('course_offering_id', $offeringIds);
            });
        }

        foreach (['semester_id', 'status' => 'registration_status', 'course_offering_id'] as $filter => $column) {
            if (is_int($filter)) {
                $filter = $column;
                $column = $filter;
            }

            $value = $filters[$filter] ?? null;
            if ($value !== null && $value !== '' && $value !== 'all') {
                $query->where($column, $value);
            }
        }
    }
}
