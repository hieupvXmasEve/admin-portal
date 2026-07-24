<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\Student;
use App\Modules\Academic\Delivery\Support\StudentAttendanceService;
use App\Shared\Contracts\Academic\AcademicPeriodReader;

final class GetStudentAttendanceQuery
{
    public function handle(string $operation, mixed ...$arguments): mixed
    {
        return $this->{$operation}(...$arguments);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function summary(Student $student, ?int $semesterId, array $filters): array
    {
        return app(StudentAttendanceService::class)->getAttendanceSummary($student, $semesterId, $filters);
    }

    /** @return array<string, mixed> */
    public function course(Student $student, int $courseOfferingId): array
    {
        return app(StudentAttendanceService::class)->getCourseAttendance($student, $courseOfferingId);
    }

    /** @return array<string, mixed> */
    public function statistics(Student $student, ?int $semesterId): array
    {
        return app(StudentAttendanceService::class)->getAttendanceStatistics($student, $semesterId);
    }

    /** @return array<string, mixed> */
    public function report(Student $student, ?int $semesterId): array
    {
        return app(StudentAttendanceService::class)->getAttendanceReport($student, $semesterId);
    }

    /** @return array{report: array<string, mixed>, semester_id: int|null} */
    public function reportForRequestedPeriod(Student $student, ?int $requestedSemesterId): array
    {
        $semesterId = $requestedSemesterId ?? app(AcademicPeriodReader::class)->current()?->id;

        return [
            'report' => $this->report($student, $semesterId),
            'semester_id' => $semesterId,
        ];
    }
}
