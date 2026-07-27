<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Academic\Delivery\Support\CourseRegistrationPresenter;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GetAvailableCourseRegistrationsQuery
{
    public function __construct(
        private StudentReferenceReader $students,
        private CourseOfferingCatalogReader $catalog,
        private CourseRegistrationPresenter $presenter,
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
                $eligibility = $this->eligibility($student->id, $offering);

                return [
                    'offering' => $this->presenter->offering($offering),
                    'eligible' => $eligibility['eligible'],
                    'reasons' => $eligibility['reasons'],
                    'available_spots' => $offering->getAvailableSpots(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{eligible: bool, reasons: list<string>} */
    private function eligibility(int $studentId, CourseOffering $courseOffering): array
    {
        $reasons = [];
        if ($courseOffering->prerequisites) {
            $completedCourseCodes = CourseRegistration::query()
                ->where('student_id', $studentId)
                ->where('registration_status', 'completed')
                ->passing()
                ->get()
                ->pluck('course_offering_id')
                ->all();

            $completedCourseCodes = CourseOffering::query()
                ->whereIn('id', $completedCourseCodes)
                ->get()
                ->map(fn (CourseOffering $offering): ?string => $this->catalog->offeringUnit((int) $offering->unit_id)?->code)
                ->filter()
                ->values()
                ->all();

            foreach ($courseOffering->prerequisites as $prerequisite) {
                if (! in_array($prerequisite, $completedCourseCodes, true)) {
                    $reasons[] = "Missing prerequisite: {$prerequisite}";
                }
            }
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }
}
