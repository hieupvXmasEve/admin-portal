<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Modules\Academic\Delivery\Support\LecturerAttendanceService;

final class BulkMarkLecturerAttendanceAction
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function run(array $data): array
    {
        $results = [];
        $totalProcessed = 0;
        $totalErrors = 0;
        $service = app(LecturerAttendanceService::class);

        foreach ($data['sessions'] as $sessionData) {
            try {
                $result = $service->markAttendance($data['lecturer_id'], $sessionData['session_id'], $sessionData['attendance_data']);
                $results[] = $result;
                $totalProcessed += $result['total_marked'];
                $totalErrors += $result['total_errors'];
            } catch (\Throwable $exception) {
                $results[] = ['session_id' => $sessionData['session_id'], 'error' => $exception->getMessage()];
                $totalErrors++;
            }
        }

        return [
            'total_sessions_processed' => count($data['sessions']),
            'total_attendance_marked' => $totalProcessed,
            'total_errors' => $totalErrors,
            'results' => $results,
        ];
    }
}
