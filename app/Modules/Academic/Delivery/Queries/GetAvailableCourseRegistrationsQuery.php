<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Academic\Delivery\Support\CourseRegistrationPresenter;
use App\Services\V1\Student\PrerequisiteValidationService;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GetAvailableCourseRegistrationsQuery
{
    public function __construct(
        private StudentReferenceReader $students,
        private CourseOfferingCatalogReader $catalog,
        private CourseRegistrationPresenter $presenter,
        private PrerequisiteValidationService $prerequisites,
    ) {}

    /**
     * @return list<array{offering: array<string, mixed>, eligible: bool, reasons: list<string>, available_spots: int}>
     */
    public function handle(int $studentId, int $semesterId, int $campusId): array
    {
        if ($this->catalog->offeringPeriod($semesterId) === null) {
            throw new \RuntimeException('Semester not found.');
        }

        $student = $this->students->find($studentId);
        if ($student === null) {
            throw new \RuntimeException('Student not found.');
        }
        if ($student->campusId !== $campusId) {
            throw new NotFoundHttpException;
        }

        $registeredCourseIds = CourseRegistration::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->pluck('course_offering_id')
            ->all();

        return CourseOffering::query()
            ->where('semester_id', $semesterId)
            ->where('campus_id', $campusId)
            ->availableForRegistration()
            ->whereNotIn('id', $registeredCourseIds)
            ->get()
            ->map(function (CourseOffering $offering) use ($student): array {
                $eligibility = $this->prerequisites->getPrerequisiteValidation($student->id, $offering);
                $reasons = collect($eligibility['missing_groups'])
                    ->flatMap(fn (array $group): array => collect($group['conditions'])
                        ->where('met', false)
                        ->map(fn (array $c): string => $c['unit']['code'] ?? $c['type'])
                        ->all())
                    ->map(fn (string $code): string => "Missing prerequisite: {$code}")
                    ->values()
                    ->all();

                return [
                    'offering' => $this->presenter->offering($offering),
                    'eligible' => $eligibility['all_met'],
                    'reasons' => $reasons,
                    'available_spots' => $offering->getAvailableSpots(),
                ];
            })
            ->values()
            ->all();
    }
}
