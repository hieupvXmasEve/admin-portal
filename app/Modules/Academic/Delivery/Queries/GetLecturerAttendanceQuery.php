<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Modules\Academic\Delivery\Support\LecturerAttendanceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetLecturerAttendanceQuery
{
    public function handle(string $operation, mixed ...$arguments): mixed
    {
        return $this->{$operation}(...$arguments);
    }

    /** @param array<string, mixed> $filters */
    public function sessions(int $lecturerId, array $filters, int $perPage): LengthAwarePaginator
    {
        return app(LecturerAttendanceService::class)->getAttendanceSessions($lecturerId, $filters, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function session(int $lecturerId, int $sessionId): ?array
    {
        return app(LecturerAttendanceService::class)->getSessionAttendance($lecturerId, $sessionId);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function analytics(int $lecturerId, int $courseOfferingId, array $filters): array
    {
        return app(LecturerAttendanceService::class)->getCourseAttendanceAnalytics($lecturerId, $courseOfferingId, $filters);
    }

    /** @param array<string, mixed> $filters @return array<int, mixed> */
    public function alerts(int $lecturerId, array $filters): array
    {
        return app(LecturerAttendanceService::class)->getAttendanceAlerts($lecturerId, $filters);
    }

    /** @return array<string, mixed> */
    public function export(int $lecturerId, int $courseOfferingId, string $format, string $lecturerName): array
    {
        return app(LecturerAttendanceService::class)->exportAttendanceData($lecturerId, $courseOfferingId, $format, $lecturerName);
    }
}
