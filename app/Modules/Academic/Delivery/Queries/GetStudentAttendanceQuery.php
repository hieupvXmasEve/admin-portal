<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Modules\Academic\Delivery\Support\StudentAttendanceService;
use App\Shared\Contracts\Academic\AcademicPeriodReader;

final class GetStudentAttendanceQuery
{
    public function handle(string $operation, mixed ...$arguments): mixed
    {
        return $this->{$operation}(...$arguments);
    }

    /** @return array<string, mixed> */
    public function course(int $studentId, int $courseOfferingId): array
    {
        return app(StudentAttendanceService::class)->getCourseAttendance($studentId, $courseOfferingId);
    }

    /** @return array<string, mixed> */
    public function report(int $studentId, ?int $semesterId): array
    {
        return app(StudentAttendanceService::class)->getAttendanceReport($studentId, $semesterId);
    }

    /** @return array{report: array<string, mixed>, semester_id: int|null} */
    public function reportForRequestedPeriod(int $studentId, ?int $requestedSemesterId): array
    {
        $semesterId = $requestedSemesterId ?? app(AcademicPeriodReader::class)->current()?->id;

        return [
            'report' => $this->report($studentId, $semesterId),
            'semester_id' => $semesterId,
        ];
    }
}
