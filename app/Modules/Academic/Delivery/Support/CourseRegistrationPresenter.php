<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\Academic\DTO\CourseOfferingUnitReference;
use App\Shared\Contracts\Identity\LecturerReferenceReader;
use App\Shared\Contracts\StudentRegistry\StudentSerializedReferenceReader;

/**
 * Shapes Delivery-owned registrations for staff routes without leaking owner models.
 */
final readonly class CourseRegistrationPresenter
{
    public function __construct(
        private CourseOfferingCatalogReader $catalog,
        private StudentSerializedReferenceReader $students,
        private LecturerReferenceReader $lecturers,
    ) {}

    /** @return array<string, mixed> */
    public function registration(
        CourseRegistration $registration,
        bool $includeStudent = true,
        bool $includeRegistrationSemester = false,
        bool $includeOfferingSemester = true,
        bool $includeLecture = true,
    ): array {
        $offering = CourseOffering::query()->find($registration->course_offering_id);

        return $this->registrationFromReferences(
            $registration,
            $includeStudent ? $this->students->findSerialized((int) $registration->student_id) : null,
            $offering,
            $offering === null ? null : $this->catalog->offeringUnit((int) $offering->unit_id),
            $includeRegistrationSemester || $includeOfferingSemester ? $this->catalog->offeringPeriod((int) $registration->semester_id) : null,
            $includeLecture && $offering !== null && $offering->lecture_id !== null ? $this->lecturers->find((int) $offering->lecture_id) : null,
            $includeRegistrationSemester,
            $includeOfferingSemester,
            $includeLecture,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $students
     * @param  array<int, CourseOffering>  $offerings
     * @param  array<int, CourseOfferingUnitReference>  $units
     * @param  array<int, AcademicPeriodReference>  $periods
     * @param  array<int, array<string, mixed>>  $lecturers
     * @return array<string, mixed>
     */
    public function registrationFromMaps(
        CourseRegistration $registration,
        array $students,
        array $offerings,
        array $units,
        array $periods,
        array $lecturers,
    ): array {
        $offering = $offerings[$registration->course_offering_id] ?? null;

        return $this->registrationFromReferences(
            $registration,
            $students[$registration->student_id] ?? null,
            $offering,
            $offering === null ? null : ($units[$offering->unit_id] ?? null),
            $periods[$registration->semester_id] ?? null,
            $offering === null || $offering->lecture_id === null ? null : ($lecturers[$offering->lecture_id] ?? null),
            true,
            true,
            false,
        );
    }

    /** @return array<string, mixed> */
    public function offering(CourseOffering $offering, bool $includeSemester = false, bool $includeLecture = true): array
    {
        return $this->offeringFromReferences(
            $offering,
            $this->catalog->offeringUnit((int) $offering->unit_id),
            $includeSemester ? $this->catalog->offeringPeriod((int) $offering->semester_id) : null,
            $includeLecture && $offering->lecture_id !== null ? $this->lecturers->find((int) $offering->lecture_id) : null,
            $includeSemester,
            $includeLecture,
        );
    }

    /** @return array<string, mixed> */
    public function academicPeriod(AcademicPeriodReference $period): array
    {
        return $this->period($period);
    }

    /** @return array<string, mixed> */
    private function registrationFromReferences(
        CourseRegistration $registration,
        ?array $student,
        ?CourseOffering $offering,
        ?CourseOfferingUnitReference $unit,
        ?AcademicPeriodReference $period,
        ?array $lecturer,
        bool $includeRegistrationSemester,
        bool $includeOfferingSemester,
        bool $includeLecture,
    ): array {
        $payload = (clone $registration)->toArray();
        if ($student !== null) {
            $payload['student'] = $student;
        }
        $payload['course_offering'] = $offering === null ? null : $this->offeringFromReferences($offering, $unit, $period, $lecturer, $includeOfferingSemester, $includeLecture);
        if ($includeRegistrationSemester) {
            $payload['semester'] = $period === null ? null : $this->period($period);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function offeringFromReferences(
        CourseOffering $offering,
        ?CourseOfferingUnitReference $unit,
        ?AcademicPeriodReference $period,
        ?array $lecturer,
        bool $includeSemester,
        bool $includeLecture,
    ): array {
        $payload = (clone $offering)->setAppends([])->toArray();
        $payload['course_code'] = $unit?->code;
        $payload['course_title'] = $unit?->name;
        $payload['credit_points'] = $unit === null ? null : (int) $unit->credit_points;
        $payload['status'] = $offering->enrollment_status ?? 'open';
        $payload['max_enrollment'] = (int) ($offering->max_capacity ?? 0);
        $payload['unit'] = $unit === null ? null : $this->unit($unit);
        if ($includeSemester) {
            $payload['semester'] = $period === null ? null : $this->period($period);
        }
        if ($includeLecture) {
            $payload['lecture'] = $lecturer;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function unit(CourseOfferingUnitReference $unit): array
    {
        if ($unit->payload !== []) {
            return $unit->payload;
        }

        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'credit_points' => $unit->credit_points,
            'level' => $unit->level,
            'unit_type' => $unit->unit_type,
        ];
    }

    /** @return array<string, mixed> */
    private function period(AcademicPeriodReference $period): array
    {
        if ($period->payload !== []) {
            return $period->payload;
        }

        return [
            'id' => $period->id,
            'code' => $period->code,
            'name' => $period->name,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'enrollment_start_date' => $period->registration_start_date?->toDateString(),
            'enrollment_end_date' => $period->registration_end_date?->toDateString(),
            'is_active' => $period->is_current,
        ];
    }

    /** @param list<int> $lecturerIds @return array<int, array<string, mixed>> */
    public function lecturersById(array $lecturerIds): array
    {
        return $this->lecturers->findMany($lecturerIds);
    }
}
