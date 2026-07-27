<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseRegistration;
use App\Modules\Academic\Delivery\Support\CourseRegistrationPresenter;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ListStudentCourseRegistrationsQuery
{
    public function __construct(
        private StudentReferenceReader $students,
        private CourseRegistrationPresenter $presenter,
    ) {}

    public function handle(int $studentId, ?int $semesterId, int $campusId): mixed
    {
        $student = $this->students->find($studentId);
        if ($student === null || $student->campusId !== $campusId) {
            throw new NotFoundHttpException;
        }

        $registrations = CourseRegistration::query()
            ->where('student_id', $studentId)
            ->whereHas('courseOffering', fn ($query) => $query->where('campus_id', $campusId))
            ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
            ->get();

        return $registrations
            ->map(fn (CourseRegistration $registration): array => $this->presenter->registration(
                $registration,
                includeStudent: false,
                includeRegistrationSemester: false,
                includeOfferingSemester: false,
                includeLecture: false,
            ))
            ->values()
            ->all();
    }
}
